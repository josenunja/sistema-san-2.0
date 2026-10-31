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
}
