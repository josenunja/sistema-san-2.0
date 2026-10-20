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

    public function test_guest_can_apply_store_coupon_before_creating_an_account(): void
    {
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenantA]);
        DB::table('tenants')->where('id', $this->tenantA)->update([
            'settings' => json_encode(['delivery_methods' => ['pickup']], JSON_THROW_ON_ERROR),
        ]);
        $product = DB::table('products')->insertGetId([
            'tenant_id' => $this->tenantA,
            'sku' => 'COUPON-PUBLIC',
            'name' => 'Producto con cupón',
            'price_minor' => 10000,
            'published' => true,
        ]);
        DB::table('coupons')->insert([
            'tenant_id' => $this->tenantA,
            'code' => 'SAVE10',
            'percent' => 10,
            'usage_limit' => 5,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'channel' => 'web',
        ]);

        $this->postJson('/tienda/alpha-test/cotizar', [
            'items' => [['product_id' => $product, 'quantity' => 2]],
            'coupon' => 'save10',
            'delivery_method' => 'pickup',
        ])->assertOk()->assertJson([
            'subtotal_minor' => 20000,
            'discount_minor' => 2000,
            'delivery_minor' => 0,
            'total_minor' => 18000,
        ]);

        $this->assertGuest();
    }

    public function test_home_marketplace_combines_only_published_products_from_active_stores(): void
    {
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenantA]);
        DB::table('products')->insert([
            ['tenant_id' => $this->tenantA, 'sku' => 'PUBLIC-A', 'name' => 'Producto Alpha', 'price_minor' => 2500, 'published' => true],
            ['tenant_id' => $this->tenantA, 'sku' => 'PRIVATE-A', 'name' => 'Producto privado', 'price_minor' => 3000, 'published' => false],
        ]);
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenantB]);
        DB::table('products')->insert(['tenant_id' => $this->tenantB, 'sku' => 'PUBLIC-B', 'name' => 'Producto Beta', 'price_minor' => 4500, 'published' => true]);
        $inactive = DB::table('tenants')->insertGetId(['slug' => 'inactive-test', 'name' => 'Inactive', 'active' => false]);
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $inactive]);
        DB::table('products')->insert(['tenant_id' => $inactive, 'sku' => 'INACTIVE', 'name' => 'Producto inactivo', 'price_minor' => 5000, 'published' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Producto Alpha')
            ->assertSee('Producto Beta')
            ->assertDontSee('Producto privado')
            ->assertDontSee('Producto inactivo');
    }

    public function test_marketplace_navigation_filters_audience_trends_and_promotions(): void
    {
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenantA]);
        DB::table('products')->insert([
            ['tenant_id' => $this->tenantA, 'sku' => 'WOMEN', 'name' => 'Vestido urbano', 'price_minor' => 8900, 'compare_at_minor' => null, 'published' => true, 'audience' => 'women', 'trending' => false],
            ['tenant_id' => $this->tenantA, 'sku' => 'MEN', 'name' => 'Casaca tendencia', 'price_minor' => 12900, 'compare_at_minor' => null, 'published' => true, 'audience' => 'men', 'trending' => true],
            ['tenant_id' => $this->tenantA, 'sku' => 'KIDS', 'name' => 'Polo infantil en oferta', 'price_minor' => 3500, 'compare_at_minor' => 4900, 'published' => true, 'audience' => 'kids', 'trending' => false],
        ]);

        $this->get('/?section=women')->assertOk()->assertSee('Vestido urbano')->assertDontSee('Casaca tendencia');
        $this->get('/?section=men')->assertOk()->assertSee('Casaca tendencia')->assertDontSee('Vestido urbano');
        $this->get('/?section=kids')->assertOk()->assertSee('Polo infantil en oferta')->assertDontSee('Vestido urbano');
        $this->get('/?section=trends')->assertOk()->assertSee('Casaca tendencia')->assertDontSee('Polo infantil en oferta');
        $this->get('/?section=promotions')->assertOk()->assertSee('Polo infantil en oferta')->assertDontSee('Casaca tendencia');
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
