<?php

namespace Tests\Feature;

use App\Http\Responses\LoginResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreAccessTest extends TestCase
{
    private User $owner;

    private int $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('StoreAccess requires sas_san_test.');
        }
        DB::beginTransaction();
        $this->withoutVite();
        $this->tenant = DB::table('tenants')->insertGetId(['name' => 'Tienda recién creada', 'slug' => 'new-owner-store']);
        $this->owner = User::factory()->create();
        DB::table('memberships')->insert(['tenant_id' => $this->tenant, 'user_id' => $this->owner->id, 'role' => 'owner']);
        config(['business.single' => true, 'business.slug' => 'new-owner-store']);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_profile_explains_how_to_enter_the_store_without_bypassing_mfa(): void
    {
        $this->actingAs($this->owner)->get('/settings/profile')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('businessAccess.name', 'Tienda recién creada')->where('businessAccess.requiresMfa', true)
            ->where('businessAccess.storeUrl', '/tienda/new-owner-store')->where('businessAccess.manageUrl', '/gestion/new-owner-store/operacion/resumen'));
        $this->get('/tienda/new-owner-store')->assertOk();
        $this->get('/gestion/new-owner-store')->assertRedirect('/settings/security');
    }

    public function test_first_login_guides_staff_to_security_instead_of_an_old_profile_destination(): void
    {
        $this->withSession(['url.intended' => url('/settings/profile')])->post('/login', [
            'email' => $this->owner->email, 'password' => 'password',
        ])->assertRedirect('/settings/security');
        $this->assertAuthenticatedAs($this->owner);
    }

    public function test_confirmed_mfa_enables_the_management_link(): void
    {
        $this->owner->forceFill(['two_factor_secret' => encrypt('SYNTHETIC'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($this->owner)->get('/settings/profile')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('businessAccess.requiresMfa', false));
        $this->get('/gestion/new-owner-store')->assertOk();
    }

    public function test_catalog_staff_enters_catalog_instead_of_a_forbidden_summary(): void
    {
        DB::table('memberships')->where('tenant_id', $this->tenant)->where('user_id', $this->owner->id)->update(['role' => 'catalog']);
        $this->owner->forceFill(['two_factor_secret' => encrypt('SYNTHETIC'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($this->owner)->get('/settings/profile')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('businessAccess.manageUrl', '/gestion/new-owner-store'));
        $request = Request::create('/login');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $this->owner);
        $response = app(LoginResponse::class)->toResponse($request);
        $this->assertSame(url('/gestion/new-owner-store'), $response->getTargetUrl());
    }

    public function test_buyer_and_guest_do_not_receive_staff_navigation(): void
    {
        $this->get('/tienda/new-owner-store')->assertOk()->assertInertia(fn (Assert $page) => $page->where('businessAccess', null));
        $buyer = User::factory()->create();
        $this->actingAs($buyer)->get('/settings/profile')->assertOk()->assertInertia(fn (Assert $page) => $page->where('businessAccess', null));
        $this->get('/gestion/new-owner-store')->assertForbidden();
    }

    public function test_public_store_and_buyer_account_expose_the_owners_existing_access(): void
    {
        $this->actingAs($this->owner);
        foreach (['/tienda/new-owner-store', '/tienda/new-owner-store/cuenta'] as $url) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('businessAccess.slug', 'new-owner-store')
                ->where('businessAccess.role', 'owner')
                ->where('businessAccess.requiresMfa', true));
        }
        $this->get('/gestion/new-owner-store/configurar')->assertRedirect('/settings/security');
        $this->owner->forceFill(['two_factor_secret' => encrypt('SYNTHETIC'), 'two_factor_confirmed_at' => now()])->save();
        $this->get('/gestion/new-owner-store/configurar')->assertOk();
    }

    public function test_store_access_matches_the_current_store_for_users_in_multiple_businesses(): void
    {
        config(['business.single' => false]);
        $other = DB::table('tenants')->insertGetId(['name' => 'A first store', 'slug' => 'first-store']);
        DB::table('memberships')->insert(['tenant_id' => $other, 'user_id' => $this->owner->id, 'role' => 'catalog']);
        $this->actingAs($this->owner)->get('/tienda/new-owner-store')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('businessAccess.slug', 'new-owner-store')->where('businessAccess.role', 'owner'));
        $this->get('/tienda/first-store')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('businessAccess.slug', 'first-store')->where('businessAccess.role', 'catalog'));
        $unrelated = User::factory()->create();
        DB::table('memberships')->insert(['tenant_id' => $other, 'user_id' => $unrelated->id, 'role' => 'owner']);
        $this->actingAs($unrelated)->get('/tienda/new-owner-store')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('businessAccess', null));
        $this->get('/gestion/new-owner-store/configurar')->assertForbidden();
    }

    public function test_catalog_permission_does_not_allow_configuring_the_store_design(): void
    {
        DB::table('memberships')->where('tenant_id', $this->tenant)->where('user_id', $this->owner->id)->update(['role' => 'catalog']);
        $this->owner->forceFill(['two_factor_secret' => encrypt('SYNTHETIC'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($this->owner)->get('/tienda/new-owner-store')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('businessAccess.role', 'catalog')->where('businessAccess.manageUrl', '/gestion/new-owner-store'));
        $this->get('/gestion/new-owner-store')->assertOk();
        $this->get('/gestion/new-owner-store/configurar')->assertForbidden();
    }

    public function test_general_admin_without_mfa_receives_platform_setup_instead_of_only_store_access(): void
    {
        config(['business.single' => false]);
        $this->owner->forceFill(['platform_owner' => true])->save();
        $this->withSession(['url.intended' => url('/tienda/new-owner-store/cuenta'), 'login_store' => 'new-owner-store'])
            ->post('/login', ['email' => $this->owner->email, 'password' => 'password'])
            ->assertRedirect('/settings/security');
        $this->withSession(['auth.password_confirmed_at' => time()])->get('/settings/security')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('platformAccess.manageUrl', '/plataforma')->where('platformAccess.requiresMfa', true));
        $this->get('/dashboard')->assertRedirect('/settings/security');
    }

    public function test_general_admin_enters_platform_even_when_also_a_store_owner(): void
    {
        config(['business.single' => false]);
        $this->owner->forceFill(['platform_owner' => true, 'two_factor_secret' => encrypt('SYNTHETIC'), 'two_factor_confirmed_at' => now()])->save();
        $request = Request::create('/login');
        $request->setLaravelSession(app('session.store'));
        $request->session()->put('url.intended', url('/tienda/new-owner-store/cuenta'));
        $request->setUserResolver(fn () => $this->owner);
        $this->assertSame(url('/plataforma'), app(LoginResponse::class)->toResponse($request)->getTargetUrl());
        $this->actingAs($this->owner)->get('/dashboard')->assertRedirect('/plataforma');
        $this->withSession(['auth.password_confirmed_at' => time()])->get('/plataforma')->assertOk();
        $this->get('/settings/profile')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('platformAccess.requiresMfa', false));
    }

    public function test_store_owner_and_single_install_do_not_receive_general_admin_navigation(): void
    {
        $this->actingAs($this->owner)->get('/settings/profile')->assertOk()->assertInertia(fn (Assert $page) => $page->where('platformAccess', null));
        $this->owner->forceFill(['platform_owner' => true])->save();
        $this->get('/settings/profile')->assertOk()->assertInertia(fn (Assert $page) => $page->where('platformAccess', null));
        $this->get('/dashboard')->assertRedirect('/gestion/new-owner-store/operacion/resumen');
        $this->get('/plataforma')->assertNotFound();
    }
}
