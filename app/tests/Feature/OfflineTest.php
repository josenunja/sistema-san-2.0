<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Commerce;
use App\Services\OfflineSales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OfflineTest extends TestCase
{
    private int $tenant;

    private int $product;

    private int $cash;

    private Commerce $commerce;

    private OfflineSales $offline;

    private array $grant;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('Isolated testing database required.');
        }
        DB::beginTransaction();
        $this->tenant = DB::table('tenants')->insertGetId(['name' => 'Offline test', 'slug' => 'offline-test']);
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenant]);
        $warehouse = DB::table('warehouses')->insertGetId(['tenant_id' => $this->tenant, 'name' => 'POS', 'branch' => 'Local']);
        $this->product = DB::table('products')->insertGetId(['tenant_id' => $this->tenant, 'sku' => 'OFF', 'name' => 'Offline product', 'price_minor' => 15000, 'published' => true]);
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->cash = DB::table('cash_sessions')->insertGetId(['tenant_id' => $this->tenant, 'warehouse_id' => $warehouse, 'user_id' => $user->id, 'opening_minor' => 0]);
        $this->commerce = app(Commerce::class);
        $this->offline = app(OfflineSales::class);
        $this->commerce->run($this->tenant, (string) Str::uuid(), 'receipt', [], fn () => $this->commerce->receive($this->tenant, ['warehouse_id' => $warehouse, 'product_id' => $this->product, 'quantity' => 2, 'unit_cost_minor' => 6000, 'supplier' => 'Synthetic', 'reference' => 'OPEN']));
        $this->grant = $this->commerce->run($this->tenant, (string) Str::uuid(), 'grant', [], fn () => $this->offline->grant($this->tenant, ['name' => 'Test device', 'cash_session_id' => $this->cash, 'items' => [['product_id' => $this->product, 'quantity' => 2]]]));
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function payload(): array
    {
        return ['device_id' => $this->grant['id'], 'operation_id' => (string) Str::uuid(), 'sequence' => 1, 'captured_at' => now()->toIso8601String(), 'total_minor' => 15000, 'items' => [['product_id' => $this->product, 'quantity' => 1]]];
    }

    private function sync(array $payload): array
    {
        return $this->commerce->run($this->tenant, $payload['operation_id'], 'offline_sale', $payload, fn () => $this->offline->sync($this->tenant, $payload));
    }

    public function test_allocation_removes_central_availability_and_replay_has_one_effect(): void
    {
        $this->assertSame(2, (int) DB::table('stock_positions')->value('offline'));
        $payload = $this->payload();
        $first = $this->sync($payload);
        $again = $this->sync($payload);
        $this->assertSame($first, $again);
        $this->assertSame(1, DB::table('sales')->count());
        $this->assertSame(1, (int) DB::table('stock_positions')->value('physical'));
        $this->assertSame(1, (int) DB::table('stock_positions')->value('offline'));
        $this->assertSame(15000, (int) DB::table('cash_entries')->sum('amount_minor'));
    }

    public function test_price_snapshot_survives_later_catalog_changes(): void
    {
        DB::table('products')->where('id', $this->product)->update(['price_minor' => 99999]);
        $this->sync($this->payload());
        $this->assertSame(15000, (int) DB::table('orders')->value('total_minor'));
        $this->assertSame(15000, (int) DB::table('order_lines')->value('unit_minor'));
    }

    public function test_expired_device_keeps_units_until_explicit_reconciliation(): void
    {
        DB::table('offline_devices')->where('id', $this->grant['id'])->update(['expires_at' => now()->subMinute()]);
        try {
            $this->sync($this->payload());
            $this->fail('Expired permission must be rejected');
        } catch (ValidationException) {
        }
        $this->assertSame(2, (int) DB::table('stock_positions')->value('offline'));
        $this->assertSame(0, DB::table('sales')->count());
        $this->commerce->run($this->tenant, (string) Str::uuid(), 'reconcile', [], fn () => $this->offline->reconcile($this->tenant, $this->grant['id'], 0));
        $this->assertSame(0, (int) DB::table('stock_positions')->value('offline'));
        $this->assertSame(2, (int) DB::table('stock_positions')->value('physical'));
    }

    public function test_wrong_amount_and_duplicate_sequence_cannot_consume_more_stock(): void
    {
        $bad = $this->payload();
        $bad['total_minor'] = 1;
        try {
            $this->sync($bad);
            $this->fail('Wrong total must be rejected');
        } catch (ValidationException) {
        }
        $this->assertSame(2, (int) DB::table('stock_positions')->value('offline'));
        $this->sync($this->payload());
        $this->expectException(ValidationException::class);
        $this->sync($this->payload());
    }

    public function test_another_user_cannot_sync_the_device(): void
    {
        $this->actingAs(User::factory()->create());
        $this->expectException(HttpException::class);
        $this->sync($this->payload());
    }

    public function test_expired_sale_can_only_sync_after_matching_individual_review(): void
    {
        $payload = $this->payload();
        DB::table('offline_devices')->where('id', $this->grant['id'])->update(['expires_at' => now()->subMinute()]);
        DB::table('offline_reviews')->insert(['tenant_id' => $this->tenant, 'device_id' => $this->grant['id'], 'operation_id' => $payload['operation_id'], 'payload_hash' => $this->offline->operationHash($payload), 'actor_id' => auth()->id(), 'reason' => 'Venta física revisada y conciliada']);
        $this->sync($payload);
        $this->assertSame(1, DB::table('sales')->count());
        $this->assertSame(1, (int) DB::table('stock_positions')->value('offline'));
    }

    public function test_reconciliation_rejects_an_unsynchronized_declared_sequence(): void
    {
        try {
            $this->commerce->run($this->tenant, (string) Str::uuid(), 'reconcile', [], fn () => $this->offline->reconcile($this->tenant, $this->grant['id'], 1));
            $this->fail('Missing sale must keep allocation committed');
        } catch (ValidationException) {
        }
        $this->assertSame(2, (int) DB::table('stock_positions')->value('offline'));
        $this->assertNull(DB::table('offline_devices')->value('reconciled_at'));
    }
}
