<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('Isolated database required.');
        }
        $this->withoutVite();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_public_marketplace_never_redirects_to_initial_setup(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('mode', 'marketplace')
                ->has('products.data'));
    }

    public function test_platform_role_does_not_grant_access_to_business_data(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['platform_owner' => true, 'two_factor_secret' => encrypt('TEST'), 'two_factor_confirmed_at' => now()])->save();
        $tenant = DB::table('tenants')->insertGetId(['name' => 'Private', 'slug' => 'private-test']);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get('/plataforma')->assertOk();
        $this->get('/gestion/private-test/operacion/resumen')->assertForbidden();
        $other = User::factory()->create();
        DB::table('memberships')->insert(['tenant_id' => $tenant, 'user_id' => $other->id, 'role' => 'owner']);
        $this->withSession(['auth.password_confirmed_at' => time()])->post('/invitaciones', ['tenant_id' => $tenant, 'email' => $user->email, 'role' => 'owner'])->assertForbidden();
    }

    public function test_platform_owner_creates_company_with_secure_owner_invitation(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->forceFill(['platform_owner' => true, 'two_factor_secret' => encrypt('TEST'), 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/plataforma', [
                'name' => 'Distrito Norte',
                'slug' => 'distrito-norte',
                'plan' => 'inicial',
                'owner_email' => 'OWNER@EXAMPLE.TEST',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $tenant = DB::table('tenants')->where('slug', 'distrito-norte')->first();
        $this->assertNotNull($tenant);
        $invite = DB::table('invitations')->where('tenant_id', $tenant->id)->first();
        $this->assertSame('owner@example.test', $invite->email);
        $this->assertSame('owner', $invite->role);
        $this->assertSame(64, strlen($invite->token_hash));
        $this->assertSame(0, DB::table('memberships')->where('tenant_id', $tenant->id)->count());
    }

    public function test_invitation_role_is_server_controlled_and_single_use(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $tenant = DB::table('tenants')->insertGetId(['name' => 'Invites', 'slug' => 'invites-test']);
        $token = Str::random(64);
        $id = (string) Str::uuid();
        DB::table('invitations')->insert(['id' => $id, 'tenant_id' => $tenant, 'email' => 'invite@example.test', 'role' => 'cashier', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour()]);
        $this->post('/invitacion', ['token' => $token, 'name' => 'Test member', 'password' => 'long-test-password', 'role' => 'owner', 'tenant_id' => 999999])->assertRedirect('/login');
        $this->assertSame('cashier', DB::table('memberships')->where('tenant_id', $tenant)->value('role'));
        $this->post('/invitacion', ['token' => $token, 'name' => 'Test member', 'password' => 'long-test-password'])->assertForbidden();
        $this->assertSame(1, DB::table('memberships')->where('tenant_id', $tenant)->count());
    }

    public function test_product_plan_limit_is_enforced_in_server(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_secret' => encrypt('TEST'), 'two_factor_confirmed_at' => now()])->save();
        $tenant = DB::table('tenants')->insertGetId(['name' => 'Limit', 'slug' => 'limit-test']);
        DB::table('memberships')->insert(['tenant_id' => $tenant, 'user_id' => $user->id, 'role' => 'owner']);
        DB::table('plans')->where('slug', 'inicial')->update(['product_limit' => 1]);
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant]);
        DB::table('products')->insert(['tenant_id' => $tenant, 'sku' => 'EXISTING', 'name' => 'Existing', 'price_minor' => 100]);
        $this->actingAs($user)->post('/gestion/limit-test/productos', ['sku' => 'NEW', 'name' => 'New', 'price_minor' => 200, 'published' => true])->assertSessionHasErrors('operation');
        $this->assertSame(1, DB::table('products')->where('tenant_id', $tenant)->count());
    }

    public function test_customer_account_is_not_a_staff_membership(): void
    {
        $tenant = DB::table('tenants')->insertGetId(['name' => 'Customer', 'slug' => 'account-test']);
        $this->post('/tienda/account-test/cuenta/registro', ['name' => 'Comprador', 'email' => 'buyer@example.test', 'phone' => '000000000', 'password' => 'long-test-password', 'password_confirmation' => 'long-test-password', 'consent' => true, 'platform_owner' => true, 'role' => 'owner'])->assertRedirect();
        $user = User::where('email', 'buyer@example.test')->firstOrFail();
        $this->assertFalse($user->platform_owner);
        $this->assertSame(0, DB::table('memberships')->where('user_id', $user->id)->count());
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user)->get('/gestion/account-test/operacion/resumen')->assertForbidden();
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant]);
        $this->assertSame($user->id, (int) DB::table('customers')->where('tenant_id', $tenant)->value('user_id'));
    }
}
