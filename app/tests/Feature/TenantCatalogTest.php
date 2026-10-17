<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantCatalogTest extends TestCase
{
    private int $tenantA;

    private int $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('These tests require the isolated sas_san_test database.');
        }
        $this->withoutVite();
        DB::beginTransaction();
        $this->tenantA = DB::table('tenants')->insertGetId(['slug' => 'alpha-test', 'name' => 'Alpha']);
        $this->tenantB = DB::table('tenants')->insertGetId(['slug' => 'beta-test', 'name' => 'Beta']);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_runtime_role_has_no_bypass_and_rejects_missing_context(): void
    {
        $role = DB::selectOne('SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user');
        $this->assertFalse($role->rolsuper);
        $this->assertFalse($role->rolbypassrls);
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenantA]);
        DB::table('products')->insert(['tenant_id' => $this->tenantA, 'sku' => 'A', 'name' => 'Solo A', 'price_minor' => 1050, 'published' => true]);
        DB::select("select set_config('app.tenant_id', '', true)");
        $this->assertSame(0, DB::table('products')->count());
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenantB]);
        $this->assertSame(0, DB::table('products')->count());
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenantA]);
        $this->assertSame(1050, (int) DB::table('products')->value('price_minor'));
    }

    public function test_membership_blocks_foreign_management_and_ignores_injected_tenant(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_secret' => encrypt('TESTSECRET'), 'two_factor_confirmed_at' => now()])->save();
        DB::table('memberships')->insert(['tenant_id' => $this->tenantA, 'user_id' => $user->id, 'role' => 'owner']);
        $this->actingAs($user)->get('/gestion/beta-test')->assertForbidden();
        $this->post('/gestion/alpha-test/productos', ['tenant_id' => $this->tenantB, 'sku' => 'VALID', 'name' => 'Producto', 'price_minor' => 1050, 'published' => true])->assertRedirect();
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenantA]);
        $this->assertSame($this->tenantA, (int) DB::table('products')->where('sku', 'VALID')->value('tenant_id'));
    }

    public function test_public_catalog_only_exposes_published_products(): void
    {
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenantA]);
        foreach ([true, false] as $published) {
            DB::table('products')->insert(['tenant_id' => $this->tenantA, 'sku' => $published ? 'PUBLIC' : 'PRIVATE', 'name' => $published ? 'Visible' : 'Oculto', 'price_minor' => 100, 'published' => $published]);
        }
        $this->get('/tienda/alpha-test')->assertOk()->assertSee('Visible')->assertDontSee('Oculto');
    }

    public function test_cross_tenant_sql_write_is_rejected(): void
    {
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenantA]);
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('products')->insert(['tenant_id' => $this->tenantB, 'sku' => 'BAD', 'name' => 'Forbidden', 'price_minor' => 10]));
    }

    public function test_public_staff_registration_is_closed(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'Attacker', 'email' => 'nobody@example.test', 'password' => 'not-a-real-password'])->assertNotFound();
    }

    public function test_business_owner_must_confirm_mfa_before_managing(): void
    {
        $user = User::factory()->create();
        DB::table('memberships')->insert(['tenant_id' => $this->tenantA, 'user_id' => $user->id, 'role' => 'owner']);
        $this->actingAs($user)->get('/gestion/alpha-test')->assertRedirect('/settings/security');
        $this->post('/gestion/alpha-test/productos', ['sku' => 'NO-MFA', 'name' => 'Blocked', 'price_minor' => 100, 'published' => true])->assertRedirect('/settings/security');
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenantA]);
        $this->assertSame(0, DB::table('products')->count());
    }

    public function test_business_member_cannot_delete_their_account_and_orphan_access(): void
    {
        $user = User::factory()->create();
        DB::table('memberships')->insert(['tenant_id' => $this->tenantA, 'user_id' => $user->id, 'role' => 'owner']);
        $this->actingAs($user)->delete('/settings/profile', ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertNotNull($user->fresh());
        $this->assertAuthenticatedAs($user);
    }
}
