<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class Purchasing
{
    public function __construct(private Commerce $commerce) {}

    /** @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function execute(int $tenant, string $action, array $data): array
    {
        if ($action === 'supplier') {
            return ['id' => DB::table('suppliers')->insertGetId(['tenant_id' => $tenant, ...$data])];
        }
        if ($action === 'purchase') {
            foreach (['supplier_id' => 'suppliers', 'warehouse_id' => 'warehouses', 'product_id' => 'products'] as $key => $table) {
                abort_unless(DB::table($table)->where('tenant_id', $tenant)->where('id', $data[$key])->exists(), 404);
            }

            return ['id' => DB::table('purchases')->insertGetId(['tenant_id' => $tenant, ...$data])];
        }
        $purchase = DB::table('purchases')->where('tenant_id', $tenant)->where('id', $data['purchase_id'])->lockForUpdate()->first();
        abort_unless($purchase !== null, 404);
        if ($action === 'purchase_confirm') {
            if ($purchase->status !== 'draft') {
                $this->commerce->fail('Solo se confirma un borrador.');
            }
            DB::table('purchases')->where('tenant_id', $tenant)->where('id', $purchase->id)->update(['status' => 'confirmed']);
        } elseif ($action === 'purchase_close') {
            if (! in_array($purchase->status, ['draft', 'confirmed'], true)) {
                $this->commerce->fail('La compra ya está cerrada.');
            }
            DB::table('purchases')->where('tenant_id', $tenant)->where('id', $purchase->id)->update(['status' => $purchase->received ? 'closed' : 'cancelled']);
        } elseif ($action === 'purchase_receive') {
            if ($purchase->status !== 'confirmed' || $purchase->received + $data['quantity'] > $purchase->quantity) {
                $this->commerce->fail('La compra no admite esa recepción.');
            }
            $supplier = DB::table('suppliers')->where('tenant_id', $tenant)->where('id', $purchase->supplier_id)->value('name');
            $receipt = $this->commerce->receive($tenant, ['warehouse_id' => $purchase->warehouse_id, 'product_id' => $purchase->product_id, 'quantity' => $data['quantity'], 'unit_cost_minor' => $purchase->unit_cost_minor, 'supplier' => $supplier, 'reference' => $purchase->reference]);
            DB::table('purchase_receipts')->insert(['tenant_id' => $tenant, 'purchase_id' => $purchase->id, 'receipt_id' => $receipt['id'], 'quantity' => $data['quantity']]);
            DB::table('purchases')->where('tenant_id', $tenant)->where('id', $purchase->id)->increment('received', $data['quantity']);
        } elseif ($action === 'purchase_reverse') {
            $receipt = DB::table('purchase_receipts')->where('tenant_id', $tenant)->where('purchase_id', $purchase->id)->where('id', $data['receipt_id'])->lockForUpdate()->first();
            abort_unless($receipt !== null, 404);
            if ($receipt->reversed) {
                $this->commerce->fail('La recepción ya fue revertida.');
            }
            $product = DB::table('products')->where('tenant_id', $tenant)->where('id', $purchase->product_id)->lockForUpdate()->first();
            $quantity = $this->commerce->integer($receipt->quantity);
            $value = $this->commerce->integer($purchase->unit_cost_minor) * $quantity;
            if ($product->cost_units < $receipt->quantity || $product->cost_value_minor < $value || ($product->cost_units === $receipt->quantity && $product->cost_value_minor !== $value)) {
                $this->commerce->fail('El costo ya fue consumido. Registra una devolución al proveedor con revisión de costos.');
            }
            $this->commerce->move($tenant, $purchase->warehouse_id, $purchase->product_id, -$quantity, 0, 0, 0, 'purchase_reversal', $data['reason'], (string) $receipt->id, -$value);
            DB::table('products')->where('tenant_id', $tenant)->where('id', $purchase->product_id)->update(['cost_units' => $product->cost_units - $receipt->quantity, 'cost_value_minor' => $product->cost_value_minor - $value]);
            DB::table('purchase_reversals')->insert(['tenant_id' => $tenant, 'purchase_receipt_id' => $receipt->id, 'reason' => $data['reason'], 'actor_id' => auth()->id()]);
            DB::table('purchase_receipts')->where('tenant_id', $tenant)->where('id', $receipt->id)->update(['reversed' => true]);
            DB::table('purchases')->where('tenant_id', $tenant)->where('id', $purchase->id)->decrement('received', $receipt->quantity);
        }

        return ['id' => $purchase->id];
    }
}
