<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Commerce;
use App\Services\Purchasing;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CommerceTest extends TestCase
{
    private int $tenant;

    private int $warehouse;

    private int $product;

    private int $cash;

    private Commerce $commerce;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('Isolated testing database required.');
        }
        $this->withoutVite();
        DB::beginTransaction();
        $this->tenant = DB::table('tenants')->insertGetId(['name' => 'Commerce test', 'slug' => 'commerce-test']);
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenant]);
        $this->warehouse = DB::table('warehouses')->insertGetId(['tenant_id' => $this->tenant, 'name' => 'Principal', 'branch' => 'Local']);
        $this->product = DB::table('products')->insertGetId(['tenant_id' => $this->tenant, 'name' => 'Producto', 'sku' => 'SKU', 'price_minor' => 15000, 'published' => true]);
        $this->user = User::factory()->create();
        $this->user->forceFill(['two_factor_secret' => encrypt('TESTSECRET'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($this->user);
        DB::table('memberships')->insert(['tenant_id' => $this->tenant, 'user_id' => $this->user->id, 'role' => 'owner']);
        DB::table('customers')->insert(['tenant_id' => $this->tenant, 'user_id' => $this->user->id, 'name' => 'Comprador web', 'phone' => '000000000', 'email' => $this->user->email]);
        $this->cash = DB::table('cash_sessions')->insertGetId(['tenant_id' => $this->tenant, 'user_id' => $this->user->id, 'warehouse_id' => $this->warehouse, 'opening_minor' => 10000]);
        $this->commerce = app(Commerce::class);
        $this->operation('receive', fn () => $this->commerce->receive($this->tenant, ['warehouse_id' => $this->warehouse, 'product_id' => $this->product, 'quantity' => 2, 'unit_cost_minor' => 6000, 'supplier' => 'Supplier', 'reference' => 'OPEN']));
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function operation(string $kind, callable $callback): array
    {
        return $this->commerce->run($this->tenant, (string) Str::uuid(), $kind, [], $callback);
    }

    private function orderData(int $cash = 0): array
    {
        return ['warehouse_id' => $this->warehouse, 'customer_name' => 'Cliente', 'phone' => '000000000', 'delivery_method' => 'pickup', 'items' => [['product_id' => $this->product, 'quantity' => 1]], 'cash_session_id' => $this->cash, 'cash_minor' => $cash];
    }

    public function test_purchase_partial_receipt_retry_and_reversal_preserve_costs(): void
    {
        $purchasing = app(Purchasing::class);
        $supplier = $this->operation('supplier', fn () => $purchasing->execute($this->tenant, 'supplier', ['name' => 'Proveedor de prueba']));
        $purchase = $this->operation('purchase', fn () => $purchasing->execute($this->tenant, 'purchase', ['supplier_id' => $supplier['id'], 'warehouse_id' => $this->warehouse, 'product_id' => $this->product, 'quantity' => 3, 'unit_cost_minor' => 9000, 'reference' => 'OC-001']));
        $this->assertSame(2, (int) DB::table('stock_positions')->sum('physical'));
        $this->operation('purchase_confirm', fn () => $purchasing->execute($this->tenant, 'purchase_confirm', ['purchase_id' => $purchase['id']]));
        $key = (string) Str::uuid();
        $data = ['purchase_id' => $purchase['id'], 'quantity' => 2];
        for ($i = 0; $i < 2; $i++) {
            $this->commerce->run($this->tenant, $key, 'purchase_receive', $data, fn () => $purchasing->execute($this->tenant, 'purchase_receive', $data));
        }
        $this->assertSame(4, (int) DB::table('stock_positions')->sum('physical'));
        $this->assertSame(30000, (int) DB::table('products')->value('cost_value_minor'));
        $this->assertSame(1, DB::table('purchase_receipts')->count());
        $receipt = DB::table('purchase_receipts')->first();
        $this->operation('purchase_reverse', fn () => $purchasing->execute($this->tenant, 'purchase_reverse', ['purchase_id' => $purchase['id'], 'receipt_id' => $receipt->id, 'reason' => 'Recepción registrada por error']));
        $this->assertSame(2, (int) DB::table('stock_positions')->sum('physical'));
        $this->assertSame(12000, (int) DB::table('products')->value('cost_value_minor'));
        $this->assertSame(1, DB::table('purchase_reversals')->count());
        $this->expectException(ValidationException::class);
        $this->operation('purchase_reverse_again', fn () => $purchasing->execute($this->tenant, 'purchase_reverse', ['purchase_id' => $purchase['id'], 'receipt_id' => $receipt->id, 'reason' => 'Intento de doble reverso']));
    }

    public function test_fixed_coupon_rounding_scopes_and_customer_limit(): void
    {
        $second = DB::table('products')->insertGetId(['tenant_id' => $this->tenant, 'name' => 'Segundo', 'sku' => 'SECOND', 'price_minor' => 333, 'published' => true]);
        DB::table('coupons')->insert(['tenant_id' => $this->tenant, 'code' => 'FIXED', 'percent' => 1, 'amount_minor' => 101, 'usage_limit' => 5, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'channel' => 'web', 'per_customer' => 1]);
        $quote = $this->commerce->quote($this->tenant, [['product_id' => $this->product, 'quantity' => 1], ['product_id' => $second, 'quantity' => 1]], 'FIXED', true);
        $this->assertSame(101, $quote['discount_minor']);
        $this->assertSame(15232, $quote['total_minor']);
        $this->assertSame(101, array_sum(array_column($quote['lines'], 'discount_minor')));
        $customer = DB::table('customers')->insertGetId(['tenant_id' => $this->tenant, 'name' => 'Identificado']);
        $data = [...$this->orderData(), 'customer_id' => $customer, 'coupon' => 'FIXED'];
        $this->operation('web_coupon', fn () => $this->commerce->createOrder($this->tenant, $data, false));
        $this->expectException(ValidationException::class);
        $this->operation('web_coupon_again', fn () => $this->commerce->createOrder($this->tenant, $data, false));
    }

    public function test_store_delivery_methods_keep_their_own_configured_fee(): void
    {
        DB::table('tenants')->where('id', $this->tenant)->update(['settings' => json_encode([
            'delivery_methods' => ['pickup', 'delivery', 'courier', 'shipping'],
            'delivery_minor' => 800,
            'courier_minor' => 1250,
            'shipping_minor' => 1890,
        ])]);
        $data = [...$this->orderData(), 'delivery_method' => 'courier', 'address' => 'Av. Prueba 123'];

        $order = $this->operation('courier_order', fn () => $this->commerce->createOrder($this->tenant, $data, false));

        $this->assertSame(16250, $order['total_minor']);
        $this->assertSame(1250, (int) DB::table('orders')->where('id', $order['id'])->value('delivery_minor'));
        $this->assertSame('courier', DB::table('orders')->where('id', $order['id'])->value('delivery_method'));
    }

    public function test_customer_receives_a_complete_order_timeline_and_notifications(): void
    {
        DB::table('tenants')->where('id', $this->tenant)->update(['settings' => json_encode([
            'delivery_methods' => ['pickup'],
            'yape_instructions' => 'Cuenta de pruebas',
        ])]);
        $customer = DB::table('customers')
            ->where('tenant_id', $this->tenant)
            ->where('user_id', $this->user->id)
            ->value('id');
        $data = [...$this->orderData(), 'customer_id' => $customer];
        $order = $this->operation('timeline_order', fn () => $this->commerce->createOrder($this->tenant, $data, false));
        $payment = $this->operation('timeline_payment', fn () => $this->commerce->pay($this->tenant, [
            'order_id' => $order['id'],
            'amount_minor' => 15000,
            'method' => 'yape',
            'reference' => 'TIMELINE-001',
        ], false));
        $this->operation('timeline_verify', fn () => $this->commerce->verifyPayment($this->tenant, $payment['id'], true));
        $this->operation('timeline_confirm', fn () => $this->commerce->confirm($this->tenant, $order['id']));

        foreach (['preparing', 'ready', 'delivered'] as $status) {
            $this->post('/gestion/commerce-test/operacion', [
                'key' => (string) Str::uuid(),
                'action' => 'delivery',
                'order_id' => $order['id'],
                'status' => $status,
            ])->assertRedirect();
        }

        $this->assertSame([
            'order_created',
            'payment_submitted',
            'payment_verified',
            'order_confirmed',
            'delivery_preparing',
            'delivery_ready',
            'delivery_delivered',
        ], DB::table('order_events')->where('order_id', $order['id'])->orderBy('id')->pluck('kind')->all());
        $this->assertSame(7, DB::table('customer_notifications')->where('order_id', $order['id'])->count());

        $this->get('/tienda/commerce-test/cuenta/pedido/'.$order['id'])
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Order')
                ->has('events', 7)
                ->where('events.6.title', 'Pedido entregado'));
    }

    public function test_credit_150_abono_50_then_payment_100_is_exact_and_idempotent(): void
    {
        $customer = DB::table('customers')->insertGetId(['tenant_id' => $this->tenant, 'name' => 'Cliente', 'credit_limit_minor' => 20000]);
        $data = [...$this->orderData(5000), 'customer_id' => $customer];
        $key = (string) Str::uuid();
        $first = $this->commerce->run($this->tenant, $key, 'pos', $data, fn () => $this->commerce->createOrder($this->tenant, $data, true));
        $again = $this->commerce->run($this->tenant, $key, 'pos', $data, fn () => throw new \RuntimeException('Must not execute replay'));
        $this->assertSame($first, $again);
        $this->assertSame(1, DB::table('sales')->count());
        $this->assertSame(15000, $first['total_minor']);
        $this->assertSame(5000, $this->commerce->paid($this->tenant, $first['id']));
        $this->assertSame(1, (int) DB::table('stock_positions')->value('physical'));
        $this->assertSame(6000, (int) DB::table('order_lines')->value('cost_minor'));
        $this->operation('abono', fn () => $this->commerce->pay($this->tenant, ['order_id' => $first['id'], 'amount_minor' => 10000, 'method' => 'cash', 'cash_session_id' => $this->cash], true));
        $this->assertSame(15000, $this->commerce->paid($this->tenant, $first['id']));
        $this->assertSame(15000, (int) DB::table('cash_entries')->sum('amount_minor'));
    }

    public function test_customer_metrics_distinguish_sales_collections_and_debt(): void
    {
        $customer = DB::table('customers')->insertGetId(['tenant_id' => $this->tenant, 'name' => 'Cliente con historial', 'credit_limit_minor' => 20000]);
        $data = [...$this->orderData(5000), 'customer_id' => $customer];
        $this->operation('crm_sale', fn () => $this->commerce->createOrder($this->tenant, $data, true));
        $this->get('/gestion/commerce-test/operacion/clientes')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Business')->where('data.customers.0.name', 'Cliente con historial')->where('data.customers.0.purchases_count', fn ($value) => (int) $value === 1)->where('data.customers.0.spent_minor', fn ($value) => (int) $value === 15000)->where('data.customers.0.debt_minor', fn ($value) => (int) $value === 10000));
    }

    public function test_insufficient_stock_rolls_back_the_whole_order(): void
    {
        $data = $this->orderData();
        $data['items'][0]['quantity'] = 3;
        try {
            $this->operation('checkout', fn () => $this->commerce->createOrder($this->tenant, $data, false));
            $this->fail('Expected rejection');
        } catch (ValidationException) {
        }
        $this->assertSame(0, DB::table('orders')->count());
        $this->assertSame(0, (int) DB::table('stock_positions')->value('reserved'));
        $this->assertSame(2, (int) DB::table('stock_positions')->value('physical'));
    }

    public function test_transfer_and_partial_receipt_conserve_units(): void
    {
        $destination = DB::table('warehouses')->insertGetId(['tenant_id' => $this->tenant, 'name' => 'Secundario', 'branch' => 'Local']);
        $transfer = $this->operation('transfer', fn () => $this->commerce->transfer($this->tenant, ['source_id' => $this->warehouse, 'destination_id' => $destination, 'product_id' => $this->product, 'quantity' => 2]));
        $this->assertSame(0, (int) DB::table('stock_positions')->sum('physical'));
        $this->operation('receive_transfer', fn () => $this->commerce->receiveTransfer($this->tenant, ['transfer_id' => $transfer['id'], 'quantity' => 1]));
        $this->assertSame(1, (int) DB::table('stock_positions')->sum('physical'));
        $this->assertSame(2, (int) DB::table('products')->value('cost_units'));
        $this->assertSame(12000, (int) DB::table('products')->value('cost_value_minor'));
        $this->operation('receive_damaged_transfer', fn () => $this->commerce->receiveTransfer($this->tenant, ['transfer_id' => $transfer['id'], 'quantity' => 1, 'damaged' => 1]));
        $this->assertSame(2, (int) DB::table('stock_positions')->sum('physical'));
        $this->assertSame(1, (int) DB::table('stock_positions')->sum('blocked'));
        $this->assertSame(1, (int) DB::table('transfers')->value('received'));
        $this->assertSame(1, (int) DB::table('transfers')->value('damaged'));
        $this->expectException(ValidationException::class);
        $this->operation('over_receive', fn () => $this->commerce->receiveTransfer($this->tenant, ['transfer_id' => $transfer['id'], 'quantity' => 1]));
    }

    public function test_return_is_separate_from_refund_and_damaged_units_are_blocked(): void
    {
        $order = $this->operation('pos', fn () => $this->commerce->createOrder($this->tenant, $this->orderData(15000), true));
        $line = DB::table('order_lines')->first();
        $this->operation('return', fn () => $this->commerce->returnItems($this->tenant, ['line_id' => $line->id, 'quantity' => 1, 'condition' => 'damaged', 'reason' => 'Producto dañado']));
        $this->assertSame(2, (int) DB::table('stock_positions')->value('physical'));
        $this->assertSame(1, (int) DB::table('stock_positions')->value('blocked'));
        $this->assertSame(0, DB::table('refunds')->count());
        $this->operation('refund', fn () => $this->commerce->refund($this->tenant, ['order_id' => $order['id'], 'amount_minor' => 15000, 'method' => 'cash', 'cash_session_id' => $this->cash, 'reference' => 'REFUND-001']));
        $this->assertSame(0, (int) DB::table('cash_entries')->sum('amount_minor'));
        $this->expectException(ValidationException::class);
        $this->operation('return_again', fn () => $this->commerce->returnItems($this->tenant, ['line_id' => $line->id, 'quantity' => 1, 'condition' => 'available', 'reason' => 'Duplicate return']));
    }

    public function test_guest_price_and_tenant_manipulation_cannot_override_checkout(): void
    {
        $data = [...$this->orderData(), 'key' => (string) Str::uuid(), 'tenant_id' => 999999, 'expected_total_minor' => 1, 'price_minor' => 1];
        auth()->logout();
        $this->post('/tienda/commerce-test/pedido', $data)->assertRedirect(route('login'));
        $this->assertSame(0, DB::table('orders')->count());
        $this->actingAs(User::factory()->create());
        $data['expected_total_minor'] = 15000;
        $this->post('/tienda/commerce-test/pedido', $data)->assertSessionHasErrors('operation');
        $this->assertSame(0, DB::table('orders')->count());
        $this->actingAs($this->user);
        $data['expected_total_minor'] = 1;
        $this->post('/tienda/commerce-test/pedido', $data)->assertSessionHasErrors('operation');
        $this->assertSame(0, DB::table('orders')->count());
        $data['expected_total_minor'] = 15000;
        $this->post('/tienda/commerce-test/pedido', $data)->assertRedirect('/tienda/commerce-test/seguimiento');
        $this->assertSame(15000, (int) DB::table('orders')->value('total_minor'));
        $this->get('/tienda/commerce-test/seguimiento')->assertOk();
    }

    public function test_cashier_cannot_verify_digital_payment_or_change_settings(): void
    {
        DB::table('memberships')->where('tenant_id', $this->tenant)->update(['role' => 'cashier']);
        $this->post('/gestion/commerce-test/operacion', ['action' => 'settings', 'key' => (string) Str::uuid()])->assertForbidden();
        $this->post('/gestion/commerce-test/operacion', ['action' => 'verify_payment', 'key' => (string) Str::uuid()])->assertForbidden();
        $this->get('/gestion/commerce-test/operacion/resumen')->assertForbidden();
    }

    public function test_unpaid_reservation_expiry_releases_stock_once(): void
    {
        $order = $this->operation('checkout', fn () => $this->commerce->createOrder($this->tenant, $this->orderData(), false));
        $this->operation('expire', fn () => $this->commerce->cancel($this->tenant, $order['id'], 'Reserva vencida', true));
        $this->operation('expire_again', fn () => $this->commerce->cancel($this->tenant, $order['id'], 'Reserva vencida', true));
        $this->assertSame(0, (int) DB::table('stock_positions')->value('reserved'));
        $this->assertSame(1, DB::table('stock_movements')->where('kind', 'release')->count());
    }

    public function test_history_cannot_be_rewritten_even_with_runtime_sql(): void
    {
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('stock_movements')->update(['quantity' => 999]));
    }
}
