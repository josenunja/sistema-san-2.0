<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BusinessLimits;
use App\Services\Commerce;
use App\Services\Purchasing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommerceReadinessTest extends TestCase
{
    private int $tenant;

    private int $warehouse;

    private int $product;

    private int $cash;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('Isolated testing database required.');
        }
        DB::beginTransaction();
        $this->withoutVite();
        Storage::fake('local');
        $this->tenant = DB::table('tenants')->insertGetId(['name' => 'Readiness', 'slug' => 'readiness', 'settings' => json_encode(['yape_instructions' => 'Solo pruebas'])]);
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenant]);
        $this->owner = User::factory()->create();
        $this->owner->forceFill(['two_factor_secret' => encrypt('TEST'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($this->owner)->withSession(['auth.password_confirmed_at' => time()]);
        DB::table('memberships')->insert(['tenant_id' => $this->tenant, 'user_id' => $this->owner->id, 'role' => 'owner']);
        $this->warehouse = DB::table('warehouses')->insertGetId(['tenant_id' => $this->tenant, 'name' => 'Principal', 'branch' => 'Local']);
        $this->product = DB::table('products')->insertGetId(['tenant_id' => $this->tenant, 'name' => 'Producto', 'sku' => 'RDY', 'price_minor' => 15000, 'published' => true]);
        $this->cash = DB::table('cash_sessions')->insertGetId(['tenant_id' => $this->tenant, 'user_id' => $this->owner->id, 'warehouse_id' => $this->warehouse, 'opening_minor' => 0]);
        app(Commerce::class)->receive($this->tenant, ['warehouse_id' => $this->warehouse, 'product_id' => $this->product, 'quantity' => 10, 'unit_cost_minor' => 100, 'supplier' => 'Demo', 'reference' => 'OPEN']);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    /** @return array<string,mixed> */
    private function mixed(): array
    {
        return ['warehouse_id' => $this->warehouse, 'customer_name' => 'Demo', 'phone' => '000000000', 'delivery_method' => 'pickup', 'cash_session_id' => $this->cash, 'cash_minor' => 5000, 'cash_received_minor' => 10000, 'payments' => [['method' => 'yape', 'amount_minor' => 10000, 'reference' => 'TEST-REF']], 'items' => [['product_id' => $this->product, 'quantity' => 1]]];
    }

    public function test_mixed_payment_change_stock_and_cash_are_atomic_and_repeatable(): void
    {
        $data = ['action' => 'pos_sale', 'key' => (string) Str::uuid(), ...$this->mixed()];
        for ($i = 0; $i < 2; $i++) {
            $this->post('/gestion/readiness/operacion', $data)->assertSessionHasNoErrors();
        }
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertSame(2, DB::table('payments')->count());
        $this->assertSame(15000, (int) DB::table('payments')->sum('amount_minor'));
        $this->assertSame(5000, (int) DB::table('cash_entries')->sum('amount_minor'));
        $this->assertSame(5000, (int) DB::table('orders')->value('change_minor'));
        $this->assertSame(9, (int) DB::table('stock_positions')->value('physical'));
        $this->post('/gestion/readiness/operacion', ['action' => 'pos_sale', 'key' => (string) Str::uuid(), ...$this->mixed()])->assertSessionHasErrors('operation');
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertSame(9, (int) DB::table('stock_positions')->value('physical'));
    }

    public function test_cashier_cannot_confirm_a_digital_component(): void
    {
        DB::table('memberships')->where('tenant_id', $this->tenant)->update(['role' => 'cashier']);
        $this->post('/gestion/readiness/operacion', ['action' => 'pos_sale', 'key' => (string) Str::uuid(), ...$this->mixed()])->assertSessionHasErrors('operation');
        $this->assertSame(0, DB::table('orders')->count());
        $this->assertSame(10, (int) DB::table('stock_positions')->value('physical'));
    }

    public function test_purchase_document_distributes_every_cent_and_receives_each_lot_once(): void
    {
        $supplier = DB::table('suppliers')->insertGetId(['tenant_id' => $this->tenant, 'name' => 'Proveedor']);
        $other = DB::table('products')->insertGetId(['tenant_id' => $this->tenant, 'sku' => 'OTHER', 'name' => 'Segundo', 'price_minor' => 300]);
        $data = ['supplier_id' => $supplier, 'warehouse_id' => $this->warehouse, 'reference' => 'DOC', 'additional_minor' => 7, 'discount_minor' => 3, 'lines' => [['product_id' => $this->product, 'quantity' => 3, 'unit_cost_minor' => 101], ['product_id' => $other, 'quantity' => 2, 'unit_cost_minor' => 203]]];
        $service = app(Purchasing::class);
        $document = app(Commerce::class)->run($this->tenant, (string) Str::uuid(), 'purchase_document', $data, fn () => $service->execute($this->tenant, 'purchase_document', $data));
        $this->assertSame(713, $document['total_minor']);
        $lots = DB::table('purchases')->where('document_id', $document['id'])->get();
        $this->assertSame(713, $lots->sum(fn ($lot) => $lot->quantity * $lot->unit_cost_minor));
        foreach ($lots as $lot) {
            $service->execute($this->tenant, 'purchase_confirm', ['purchase_id' => $lot->id]);
            $payload = ['purchase_id' => $lot->id, 'quantity' => $lot->quantity];
            $key = (string) Str::uuid();
            for ($i = 0; $i < 2; $i++) {
                app(Commerce::class)->run($this->tenant, $key, 'purchase_receive', $payload, fn () => $service->execute($this->tenant, 'purchase_receive', $payload));
            }
        }
        $this->assertSame(1713, (int) DB::table('products')->sum('cost_value_minor'));
        $this->assertSame(15, (int) DB::table('stock_positions')->sum('physical'));
    }

    public function test_cash_reopening_requires_owner_confirmation_and_preserves_previous_close(): void
    {
        DB::table('cash_sessions')->where('id', $this->cash)->update(['closed_at' => now(), 'counted_minor' => 500]);
        $data = ['action' => 'reopen_cash', 'key' => (string) Str::uuid(), 'cash_session_id' => $this->cash, 'reason' => 'Corregir arqueo con autorización'];
        $this->post('/gestion/readiness/operacion', $data)->assertSessionHasNoErrors();
        $this->post('/gestion/readiness/operacion', $data)->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('cash_reopenings')->count());
        $this->assertSame(500, (int) DB::table('cash_reopenings')->value('previous_counted_minor'));
        $this->assertNull(DB::table('cash_sessions')->value('closed_at'));
        DB::table('memberships')->where('tenant_id', $this->tenant)->update(['role' => 'cashier']);
        $this->post('/gestion/readiness/operacion', [...$data, 'key' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_warehouse_scope_filters_inventory_and_rejects_direct_writes(): void
    {
        $other = DB::table('warehouses')->insertGetId(['tenant_id' => $this->tenant, 'name' => 'Restringido', 'branch' => 'Otra']);
        DB::table('memberships')->where('tenant_id', $this->tenant)->update(['role' => 'warehouse', 'warehouse_ids' => json_encode([$other])]);
        $this->get('/gestion/readiness/operacion/inventario')->assertInertia(fn (Assert $page) => $page->has('warehouses', 1)->where('warehouses.0.id', $other)->has('data.stock', 0));
        $this->post('/gestion/readiness/operacion', ['action' => 'receipt', 'key' => (string) Str::uuid(), 'warehouse_id' => $this->warehouse, 'product_id' => $this->product, 'quantity' => 1, 'unit_cost_minor' => 1, 'supplier' => 'Demo', 'reference' => 'DENIED'])->assertForbidden();
        $this->assertSame(10, (int) DB::table('stock_positions')->value('physical'));
    }

    public function test_product_photos_can_be_reordered_replaced_and_removed_without_cross_product_access(): void
    {
        $this->post('/gestion/readiness/productos/'.$this->product.'/foto', ['image' => UploadedFile::fake()->image('demo.png', 30, 30)])->assertSessionHasNoErrors();
        $image = DB::table('product_images')->first();
        $url = '/gestion/readiness/productos/'.$this->product.'/fotos/'.$image->id;
        $this->patch($url, ['alt_text' => 'Frente del producto', 'sort_order' => 3, 'cover' => true])->assertSessionHasNoErrors();
        $this->assertSame('Frente del producto', DB::table('product_images')->value('alt_text'));
        $this->delete($url)->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('product_images')->count());
        $this->assertNull(DB::table('products')->where('id', $this->product)->value('image_path'));
        $this->assertSame(1, DB::table('products')->count());
    }

    public function test_archived_product_is_not_sellable_and_independent_limits_ignore_saas_plan(): void
    {
        config(['business.single' => true, 'business.limits.product' => 700]);
        $this->assertSame(700, app(BusinessLimits::class)->limit($this->tenant, 'product'));
        DB::table('products')->where('id', $this->product)->update(['archived' => true]);
        $this->expectException(ValidationException::class);
        app(Commerce::class)->quote($this->tenant, [['product_id' => $this->product, 'quantity' => 1]], null, true);
    }

    public function test_scheduled_offer_is_shared_by_store_pos_and_quotes_and_does_not_stack_with_coupon(): void
    {
        $rule = DB::table('price_rules')->insertGetId(['tenant_id' => $this->tenant, 'name' => 'Oferta', 'product_id' => $this->product, 'percent' => 30, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $items = [['product_id' => $this->product, 'quantity' => 1]];
        $this->assertSame(10500, app(Commerce::class)->quote($this->tenant, $items, null, true)['total_minor']);
        $this->assertSame(10500, app(Commerce::class)->quote($this->tenant, $items, null, false)['total_minor']);
        $this->get('/tienda/readiness?section=promotions')->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.price_minor', 10500)->where('products.data.0.compare_at_minor', 15000));
        DB::table('coupons')->insert(['tenant_id' => $this->tenant, 'code' => 'COUPON', 'percent' => 10, 'usage_limit' => 10, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $this->assertSame(13500, app(Commerce::class)->quote($this->tenant, $items, 'COUPON', true)['total_minor']);
        DB::table('price_rules')->where('id', $rule)->update(['ends_at' => now()->subMinute(), 'starts_at' => now()->subHour()]);
        $this->assertSame(15000, app(Commerce::class)->quote($this->tenant, $items, null, true)['total_minor']);
    }

    public function test_delivery_zone_price_and_slot_capacity_are_checked_again_at_confirmation(): void
    {
        DB::table('tenants')->where('id', $this->tenant)->update(['settings' => json_encode(['delivery_methods' => ['pickup', 'delivery']])]);
        $zone = DB::table('delivery_zones')->insertGetId(['tenant_id' => $this->tenant, 'name' => 'Zona demo', 'method' => 'delivery', 'fee_minor' => 700]);
        $slot = DB::table('delivery_slots')->insertGetId(['tenant_id' => $this->tenant, 'method' => 'delivery', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'capacity' => 1]);
        $data = ['warehouse_id' => $this->warehouse, 'customer_name' => 'Demo', 'phone' => '000000000', 'address' => 'Dirección ficticia', 'delivery_method' => 'delivery', 'delivery_zone_id' => $zone, 'delivery_slot_id' => $slot, 'items' => [['product_id' => $this->product, 'quantity' => 1]]];
        $this->postJson('/tienda/readiness/cotizar', $data)->assertOk()->assertJsonPath('total_minor', 15700);
        $result = app(Commerce::class)->run($this->tenant, (string) Str::uuid(), 'checkout', $data, fn () => app(Commerce::class)->createOrder($this->tenant, $data, false));
        $this->assertSame(15700, $result['total_minor']);
        $this->assertSame($zone, (int) DB::table('orders')->value('delivery_zone_id'));
        $this->assertStringContainsString('Zona demo', DB::table('orders')->value('delivery_label'));
        $this->postJson('/tienda/readiness/cotizar', $data)->assertUnprocessable();
        $this->assertSame(1, DB::table('orders')->count());
    }

    public function test_session_management_uses_opaque_keys_and_cannot_revoke_another_account(): void
    {
        config(['session.driver' => 'database']);
        $this->withSession(['auth.password_confirmed_at' => time()]);
        $other = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'private-session-demo', 'user_id' => $this->owner->id, 'payload' => '', 'last_activity' => time(), 'user_agent' => 'Navegador demo']);
        DB::table('sessions')->insert(['id' => 'another-user-session', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()]);
        $key = hash_hmac('sha256', 'private-session-demo', config('app.key'));
        $this->get('/settings/security')->assertOk()->assertInertia(fn (Assert $page) => $page->has('sessions', 1)->where('sessions.0.key', $key)->missing('sessions.0.id'));
        $foreignKey = hash_hmac('sha256', 'another-user-session', config('app.key'));
        $this->delete('/settings/sessions/'.$foreignKey)->assertNotFound();
        $this->delete('/settings/sessions/'.$key)->assertSessionHasNoErrors();
        $this->assertFalse(DB::table('sessions')->where('id', 'private-session-demo')->exists());
        $this->assertTrue(DB::table('sessions')->where('id', 'another-user-session')->exists());
    }
}
