<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OfflineSales
{
    public function __construct(private Commerce $commerce) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function grant(int $tenant, array $data): array
    {
        $cash = $this->commerce->cashSession($tenant, $data['cash_session_id']);
        $id = (string) Str::uuid();
        $expires = now()->addHours(12);
        DB::table('offline_devices')->insert(['id' => $id, 'tenant_id' => $tenant, 'user_id' => auth()->id(), 'warehouse_id' => $cash->warehouse_id, 'cash_session_id' => $cash->id, 'name' => $data['name'], 'expires_at' => $expires]);
        $items = [];
        $seen = [];
        foreach ($data['items'] as $line) {
            if (isset($seen[$line['product_id']])) {
                $this->commerce->fail('No repitas productos en la asignación.');
            }
            $seen[$line['product_id']] = true;
            $product = DB::table('products')->where('tenant_id', $tenant)->where('id', $line['product_id'])->where('archived', false)->first();
            abort_unless($product !== null, 404);
            $product->price_minor = app(ScheduledPrices::class)->price($product, app(ScheduledPrices::class)->rules($tenant, 'pos'));
            $this->commerce->move($tenant, $cash->warehouse_id, $product->id, 0, 0, 0, $line['quantity'], 'offline_assign', 'Asignación exclusiva a dispositivo', $id);
            $item = ['product_id' => $product->id, 'quantity' => $line['quantity'], 'unit_minor' => $product->price_minor, 'name' => $product->name, 'sku' => $product->sku];
            DB::table('offline_allocations')->insert(['tenant_id' => $tenant, 'device_id' => $id, ...$item]);
            $items[] = $item;
        }

        return ['id' => $id, 'tenant_id' => $tenant, 'slug' => DB::table('tenants')->where('id', $tenant)->value('slug'), 'name' => $data['name'], 'expires_at' => $expires->toIso8601String(), 'server_time' => now()->toIso8601String(), 'items' => $items];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sync(int $tenant, array $data): array
    {
        $device = DB::table('offline_devices')->where('tenant_id', $tenant)->where('id', $data['device_id'])->where('user_id', auth()->id())->lockForUpdate()->first();
        abort_unless($device !== null, 404);
        $approval = DB::table('offline_reviews')->where('tenant_id', $tenant)->where('device_id', $device->id)->where('operation_id', $data['operation_id'])->first();
        $approved = $approval && hash_equals($approval->payload_hash, $this->operationHash($data));
        if ($device->reconciled_at || (($device->revoked_at || now()->greaterThan($device->expires_at)) && ! $approved)) {
            $this->commerce->fail('El permiso venció o fue revocado. Conserva esta venta y su exportación para revisión del responsable.');
        }
        if (DB::table('offline_sales')->where('tenant_id', $tenant)->where('device_id', $device->id)->where('sequence', $data['sequence'])->exists()) {
            $this->commerce->fail('La secuencia ya fue usada por otra operación.');
        }
        $prices = [];
        $total = 0;
        foreach ($data['items'] as $line) {
            $allocation = DB::table('offline_allocations')->where('tenant_id', $tenant)->where('device_id', $device->id)->where('product_id', $line['product_id'])->lockForUpdate()->first();
            if (! $allocation || $allocation->consumed + $line['quantity'] > $allocation->quantity) {
                $this->commerce->fail('La venta excede las unidades asignadas al dispositivo.');
            }
            $prices[$line['product_id']] = $allocation->unit_minor;
            $total += $allocation->unit_minor * $line['quantity'];
            $this->commerce->move($tenant, $device->warehouse_id, $line['product_id'], 0, 0, 0, -$this->commerce->integer($line['quantity']), 'offline_consume', 'Venta offline sincronizada', $data['operation_id']);
            DB::table('offline_allocations')->where('tenant_id', $tenant)->where('device_id', $device->id)->where('product_id', $line['product_id'])->increment('consumed', $line['quantity']);
        }
        if ($total !== (int) $data['total_minor']) {
            $this->commerce->fail('El importe no coincide con los precios autorizados. La venta queda para revisión.');
        }
        $order = $this->commerce->createOrder($tenant, ['warehouse_id' => $device->warehouse_id, 'cash_session_id' => $device->cash_session_id, 'cash_minor' => $total, 'expected_total_minor' => $total, 'offline_prices' => $prices, 'customer_name' => 'Venta offline', 'phone' => 'Sin datos personales', 'delivery_method' => 'pickup', 'items' => $data['items']], true);
        DB::table('orders')->where('tenant_id', $tenant)->where('id', $order['id'])->update(['channel' => 'offline']);
        DB::table('offline_sales')->insert(['tenant_id' => $tenant, 'device_id' => $device->id, 'operation_id' => $data['operation_id'], 'order_id' => $order['id'], 'sequence' => $data['sequence'], 'captured_at' => $data['captured_at']]);

        return ['id' => $order['id'], 'operation_id' => $data['operation_id']];
    }

    /**
     * @return array<string, mixed>
     */
    public function reconcile(int $tenant, string $id, int $lastSequence): array
    {
        $device = DB::table('offline_devices')->where('tenant_id', $tenant)->where('id', $id)->lockForUpdate()->first();
        abort_unless($device !== null, 404);
        if ($device->reconciled_at) {
            return ['id' => $id];
        }
        $max = (int) DB::table('offline_sales')->where('tenant_id', $tenant)->where('device_id', $id)->max('sequence');
        $count = DB::table('offline_sales')->where('tenant_id', $tenant)->where('device_id', $id)->count();
        if ($max !== $lastSequence || $count !== $max) {
            $this->commerce->fail('La secuencia del dispositivo no coincide con las ventas sincronizadas. Revisa pendientes y conflictos antes de liberar unidades.');
        }
        foreach (DB::table('offline_allocations')->where('tenant_id', $tenant)->where('device_id', $id)->get() as $line) {
            $unused = $line->quantity - $line->consumed;
            if ($unused > 0) {
                $this->commerce->move($tenant, $device->warehouse_id, $line->product_id, 0, 0, 0, -$unused, 'offline_release', 'Cierre y conciliación explícita', $id);
            }
        }
        DB::table('offline_devices')->where('tenant_id', $tenant)->where('id', $id)->update(['reconciled_at' => now(), 'revoked_at' => now()]);

        return ['id' => $id];
    }

    /** @param array<string, mixed> $data */
    public function operationHash(array $data): string
    {
        $normalized = ['device_id' => $data['device_id'], 'operation_id' => $data['operation_id'], 'sequence' => $this->commerce->integer($data['sequence']), 'captured_at' => $data['captured_at'], 'total_minor' => $this->commerce->integer($data['total_minor']), 'items' => array_map(fn ($line) => ['product_id' => $this->commerce->integer($line['product_id']), 'quantity' => $this->commerce->integer($line['quantity'])], $data['items'])];

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }
}
