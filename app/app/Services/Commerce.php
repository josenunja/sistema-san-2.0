<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Commerce
{
    public function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_INT);
            if ($parsed !== false) {
                return $parsed;
            }
        }
        $this->fail('La operación requiere una cantidad entera dentro de los límites admitidos.');
    }

    public function fail(string $message): never
    {
        throw ValidationException::withMessages(['operation' => $message]);
    }

    /** Serialize mutations per company; prioritize correctness over throughput initially. */
    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(): array<string, mixed>  $action
     * @return array<string, mixed>
     */
    public function run(int $tenant, string $key, string $kind, array $payload, callable $action): array
    {
        return DB::transaction(function () use ($tenant, $key, $kind, $payload, $action) {
            DB::select('SELECT pg_advisory_xact_lock(?)', [$tenant]);
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $previous = DB::table('operations')->where('tenant_id', $tenant)->where('key', $key)->first();
            if ($previous) {
                if ($previous->kind !== $kind || ! hash_equals($previous->payload_hash, $hash)) {
                    $this->fail('La clave de esta operación ya se utilizó con otros datos.');
                }

                return json_decode(Crypt::decryptString($previous->result), true, 512, JSON_THROW_ON_ERROR);
            }
            $result = $action();
            DB::table('operations')->insert(['tenant_id' => $tenant, 'key' => $key, 'kind' => $kind, 'payload_hash' => $hash, 'result' => Crypt::encryptString(json_encode($result, JSON_THROW_ON_ERROR))]);
            $this->audit($tenant, $kind, (string) ($result['id'] ?? ''));

            return $result;
        });
    }

    public function audit(int $tenant, string $action, string $reference = ''): void
    {
        DB::table('audit_entries')->insert(['tenant_id' => $tenant, 'actor_id' => auth()->id(), 'action' => $action, 'reference' => $reference]);
    }

    public function position(int $tenant, int $warehouse, int $product): \stdClass
    {
        app(WarehouseAccess::class)->check($tenant, $warehouse);
        abort_unless(DB::table('warehouses')->where('tenant_id', $tenant)->where('id', $warehouse)->exists(), 404);
        abort_unless(DB::table('products')->where('tenant_id', $tenant)->where('id', $product)->exists(), 404);
        DB::table('stock_positions')->insertOrIgnore(['tenant_id' => $tenant, 'warehouse_id' => $warehouse, 'product_id' => $product]);

        return DB::table('stock_positions')->where('tenant_id', $tenant)->where('warehouse_id', $warehouse)->where('product_id', $product)->lockForUpdate()->first();
    }

    public function move(int $tenant, int $warehouse, int $product, int $physical, int $reserved, int $blocked, int $offline, string $kind, string $reason, string $reference = '', ?int $costDelta = null): void
    {
        $p = $this->position($tenant, $warehouse, $product);
        $values = ['physical' => $p->physical + $physical, 'reserved' => $p->reserved + $reserved, 'blocked' => $p->blocked + $blocked, 'offline' => $p->offline + $offline];
        if (max($values) > 1000000000) {
            $this->fail('Se superó el límite de unidades de una posición.');
        }
        if (min($values) < 0 || $values['physical'] < $values['reserved'] + $values['blocked'] + $values['offline']) {
            $this->fail('Existencias insuficientes o comprometidas en otra operación.');
        }
        DB::table('stock_positions')->where('tenant_id', $tenant)->where('warehouse_id', $warehouse)->where('product_id', $product)->update($values);
        DB::table('stock_movements')->insert(['tenant_id' => $tenant, 'warehouse_id' => $warehouse, 'product_id' => $product, 'quantity' => $physical, 'reserved_delta' => $reserved, 'blocked_delta' => $blocked, 'offline_delta' => $offline, 'kind' => $kind, 'reason' => $reason, 'reference' => $reference, 'actor_id' => auth()->id(), 'pool_value_delta_minor' => $costDelta]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function receive(int $tenant, array $data): array
    {
        $id = DB::table('receipts')->insertGetId(['tenant_id' => $tenant, 'warehouse_id' => $data['warehouse_id'], 'product_id' => $data['product_id'], 'quantity' => $data['quantity'], 'unit_cost_minor' => $data['unit_cost_minor'], 'supplier' => $data['supplier'], 'reference' => $data['reference'], 'actor_id' => auth()->id()]);
        $this->move($tenant, $data['warehouse_id'], $data['product_id'], $data['quantity'], 0, 0, 0, 'receipt', $data['supplier'], (string) $id);
        DB::table('products')->where('tenant_id', $tenant)->where('id', $data['product_id'])->incrementEach(['cost_units' => $data['quantity'], 'cost_value_minor' => $data['quantity'] * $data['unit_cost_minor']]);

        return ['id' => $id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transfer(int $tenant, array $data): array
    {
        if ($data['source_id'] === $data['destination_id']) {
            $this->fail('El almacén de destino debe ser diferente.');
        }
        abort_unless(DB::table('warehouses')->where('tenant_id', $tenant)->where('id', $data['destination_id'])->exists(), 404);
        $id = DB::table('transfers')->insertGetId(['tenant_id' => $tenant, ...$data]);
        $this->move($tenant, $data['source_id'], $data['product_id'], -$this->integer($data['quantity']), 0, 0, 0, 'transfer_out', 'Despacho a tránsito', (string) $id);

        return ['id' => $id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function receiveTransfer(int $tenant, array $data): array
    {
        $transfer = DB::table('transfers')->where('tenant_id', $tenant)->where('id', $data['transfer_id'])->lockForUpdate()->first();
        abort_unless($transfer !== null, 404);
        $damaged = $this->integer($data['damaged'] ?? 0);
        if ($damaged < 0 || $damaged > $data['quantity'] || $transfer->received + $transfer->damaged + $data['quantity'] > $transfer->quantity) {
            $this->fail('La recepción supera las unidades en tránsito.');
        }
        $this->move($tenant, $transfer->destination_id, $transfer->product_id, $data['quantity'], 0, $damaged, 0, 'transfer_in', $damaged ? 'Recepción de traslado con unidades dañadas bloqueadas' : 'Recepción de traslado', (string) $transfer->id);
        DB::table('transfers')->where('tenant_id', $tenant)->where('id', $transfer->id)->update(['received' => $transfer->received + $data['quantity'] - $damaged, 'damaged' => $transfer->damaged + $damaged]);

        return ['id' => $transfer->id];
    }

    /**
     * @param  array<array<string, mixed>>  $items
     * @param  array<int, int>  $authorizedPrices
     * @return array<string, mixed>
     */
    public function quote(int $tenant, array $items, ?string $couponCode, bool $public, array $authorizedPrices = []): array
    {
        $coupon = null;
        if ($couponCode) {
            $coupon = DB::table('coupons')->where('tenant_id', $tenant)->where('code', strtoupper($couponCode))->where('starts_at', '<=', now())->where('ends_at', '>', now())->lockForUpdate()->first();
            if (! $coupon || $coupon->used >= $coupon->usage_limit) {
                $this->fail('El cupón no está vigente o agotó sus usos.');
            }
        }
        $scheduledRules = $coupon ? collect() : app(ScheduledPrices::class)->rules($tenant, $public ? 'web' : 'pos');
        $lines = [];
        $subtotal = 0;
        $discount = 0;
        $seen = [];
        $eligible = [];
        if ($coupon && $coupon->channel && $coupon->channel !== ($public ? 'web' : 'pos')) {
            $this->fail('El cupón no corresponde a este canal.');
        }
        foreach ($items as $item) {
            if (isset($seen[$item['product_id']])) {
                $this->fail('Agrupa las unidades de un mismo producto en una línea.');
            }
            $seen[$item['product_id']] = true;
            $product = DB::table('products')->where('tenant_id', $tenant)->where('id', $item['product_id'])->when(! array_key_exists($item['product_id'], $authorizedPrices), fn ($q) => $q->where('archived', false))->when($public, fn ($q) => $q->where('published', true))->lockForUpdate()->first();
            if (! $product) {
                $this->fail('Un producto ya no está disponible en esta tienda.');
            }
            if (array_key_exists($product->id, $authorizedPrices)) {
                $product->price_minor = $authorizedPrices[$product->id];
            } else {
                $product->price_minor = app(ScheduledPrices::class)->price($product, $scheduledRules);
            }
            $base = $product->price_minor * $item['quantity'];
            $applies = $coupon && (! $coupon->category || $coupon->category === $product->category) && (! $coupon->product_id || $coupon->product_id === $product->id);
            $eligible[] = $applies ? $base : 0;
            $off = $applies && ! $coupon->amount_minor ? intdiv($base * $coupon->percent + 50, 100) : 0;
            $subtotal += $base;
            if ($subtotal > 999999999) {
                $this->fail('El subtotal supera el límite operativo de S/ 9,999,999.99 por documento.');
            }
            $discount += $off;
            $lines[] = ['product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name, 'quantity' => $item['quantity'], 'unit_minor' => $product->price_minor, 'discount_minor' => $off, 'total_minor' => $base - $off];
        }
        if ($coupon && $subtotal < $coupon->minimum_minor) {
            $this->fail('No se alcanza la compra mínima del cupón.');
        }
        if ($coupon && ! array_sum($eligible)) {
            $this->fail('El carrito no contiene productos elegibles para este cupón.');
        }
        if ($coupon?->amount_minor) {
            $eligibleTotal = array_sum($eligible);
            $discount = min($coupon->amount_minor, $eligibleTotal);
            $running = 0;
            $allocated = 0;
            foreach ($lines as $index => &$line) {
                $running += $eligible[$index];
                $cumulative = intdiv($discount * $running, $eligibleTotal);
                $line['discount_minor'] = $cumulative - $allocated;
                $line['total_minor'] = $line['unit_minor'] * $line['quantity'] - $line['discount_minor'];
                $allocated = $cumulative;
            }
            unset($line);
        }

        return ['lines' => $lines, 'subtotal_minor' => $subtotal, 'discount_minor' => $discount, 'total_minor' => $subtotal - $discount, 'coupon_id' => $coupon?->id];
    }

    /**
     * @return array{document_type: string, document_series: string, document_number: int, formatted_correlative: string}
     */
    public function allocateFiscalNumber(int $tenant, string $documentType, ?string $preferredSeries = null): array
    {
        $documentType = match ($documentType) {
            'factura' => 'factura',
            'boleta' => 'boleta',
            default => 'ticket',
        };

        $defaultSeries = match ($documentType) {
            'factura' => 'F001',
            'boleta' => 'B001',
            default => 'T001',
        };

        $series = $preferredSeries ? strtoupper(trim($preferredSeries)) : $defaultSeries;
        if (! preg_match('/^[A-Z0-9]{3,5}$/', $series)) {
            $series = $defaultSeries;
        }

        DB::table('fiscal_series_sequences')->insertOrIgnore([
            'tenant_id' => $tenant,
            'document_type' => $documentType,
            'series' => $series,
            'current_number' => 0,
            'created_at' => now(),
        ]);

        $seq = DB::table('fiscal_series_sequences')
            ->where('tenant_id', $tenant)
            ->where('document_type', $documentType)
            ->where('series', $series)
            ->lockForUpdate()
            ->first();

        $nextNumber = ($seq ? (int) $seq->current_number : 0) + 1;

        DB::table('fiscal_series_sequences')
            ->where('tenant_id', $tenant)
            ->where('document_type', $documentType)
            ->where('series', $series)
            ->update(['current_number' => $nextNumber]);

        return [
            'document_type' => $documentType,
            'document_series' => $series,
            'document_number' => $nextNumber,
            'formatted_correlative' => sprintf('%s-%08d', $series, $nextNumber),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createOrder(int $tenant, array $data, bool $pos): array
    {
        $quote = $this->quote($tenant, $data['items'], $data['coupon'] ?? null, ! $pos, $data['offline_prices'] ?? []);
        if ($quote['coupon_id']) {
            $limit = DB::table('coupons')->where('tenant_id', $tenant)->where('id', $quote['coupon_id'])->value('per_customer');
            if ($limit) {
                $customerId = $data['customer_id'] ?? null;
                if (! $customerId || ! DB::table('customers')->where('tenant_id', $tenant)->where('id', $customerId)->exists()) {
                    $this->fail('Este cupón requiere un cliente identificado en esta empresa.');
                }
                $used = DB::table('orders')->where('tenant_id', $tenant)->where('customer_id', $customerId)->where('coupon_id', $quote['coupon_id'])->whereNotIn('status', ['cancelled', 'expired'])->count();
                if ($used >= $limit) {
                    $this->fail('El cliente ya alcanzó el límite de este cupón.');
                }
            }
        }
        $shipping = $pos && $data['delivery_method'] === 'pickup'
            ? ['fee_minor' => 0, 'zone_id' => null, 'slot_id' => null, 'label' => 'Venta presencial']
            : app(DeliveryOptions::class)->quote($tenant, $data);
        $delivery = $shipping['fee_minor'];
        if ($data['delivery_method'] !== 'pickup' && empty($data['address'])) {
            $this->fail('La entrega requiere dirección.');
        }
        $customerId = $data['customer_id'] ?? null;
        $customer = null;
        if ($customerId) {
            $customer = DB::table('customers')->where('tenant_id', $tenant)->where('id', $customerId)->lockForUpdate()->first();
            abort_unless($customer !== null, 404);
        }
        $cashApplied = $pos ? (int) ($data['cash_minor'] ?? 0) : 0;
        $digital = $pos ? ($data['payments'] ?? []) : [];
        $digitalTotal = 0;
        foreach ($digital as $payment) {
            if (! in_array($payment['method'], ['yape', 'bcp'], true) || $payment['amount_minor'] <= 0 || empty($payment['reference'])) {
                $this->fail('Cada pago digital requiere medio, importe y referencia.');
            }
            $digitalTotal += $payment['amount_minor'];
        }
        $paid = $cashApplied;
        if ($digital && DB::table('memberships')->where('tenant_id', $tenant)->where('user_id', auth()->id())->value('role') !== 'owner') {
            $this->fail('El propietario debe verificar los pagos digitales antes de registrar esta venta mixta.');
        }
        $paid += $digitalTotal;
        $total = $quote['total_minor'] + $delivery;
        if (isset($data['expected_total_minor']) && (int) $data['expected_total_minor'] !== $total) {
            $this->fail('Los precios o descuentos cambiaron. Vuelve a revisar el total antes de confirmar.');
        }
        $received = (int) ($data['cash_received_minor'] ?? $cashApplied);
        if ($pos && ($received < $cashApplied || $received > 999999999)) {
            $this->fail('El efectivo recibido debe cubrir el efectivo aplicado.');
        }
        if ($paid > $total) {
            $this->fail('El cobro supera el total. Registra solo el efectivo aplicado, descontando el vuelto.');
        }
        if ($pos && $paid < $total && ! $customerId) {
            $this->fail('Para vender a crédito debes identificar al cliente.');
        }
        if ($pos && $paid < $total && $customer) {
            $debt = DB::table('orders')->where('tenant_id', $tenant)->where('customer_id', $customerId)->where('status', 'confirmed')->get()->sum(fn ($o) => max(0, $o->total_minor - $o->returned_minor - $this->paid($tenant, $o->id)));
            if ($debt + $total - $paid > $customer->credit_limit_minor) {
                $this->fail('La venta supera el límite de crédito autorizado para este cliente.');
            }
        }
        $documentType = match ($data['document_type'] ?? 'ticket') {
            'factura' => 'factura',
            'boleta' => 'boleta',
            default => 'ticket',
        };
        $customerDocType = (string) ($data['customer_document_type'] ?? ($documentType === 'factura' ? 'ruc' : 'dni'));
        $customerDocNumber = trim((string) ($data['customer_document_number'] ?? ''));
        $customerLegalName = trim((string) ($data['customer_legal_name'] ?? ''));

        if ($documentType === 'factura') {
            $customerDocType = 'ruc';
            if (! preg_match('/^(10|15|17|20)\d{9}$/', $customerDocNumber)) {
                $this->fail('Para emitir Factura se requiere un RUC válido de 11 dígitos que comience con 10, 15, 17 o 20.');
            }
            if ($customerLegalName === '') {
                $customerLegalName = trim((string) ($data['customer_name'] ?? ''));
            }
            if (mb_strlen($customerLegalName) < 3) {
                $this->fail('Para emitir Factura es obligatoria la Razón Social o denominación legal.');
            }
        } elseif ($documentType === 'boleta') {
            if (! in_array($customerDocType, ['dni', 'ce', 'pasaporte', 'otro'], true)) {
                $customerDocType = 'dni';
            }
            if ($customerDocType === 'dni' && $customerDocNumber !== '') {
                if (! preg_match('/^\d{8}$/', $customerDocNumber)) {
                    $this->fail('El DNI ingresado debe tener exactamente 8 dígitos numéricos.');
                }
            }
            if ($total >= 70000 && $customerDocNumber === '') {
                $this->fail('Para Boletas de venta de S/ 700.00 o más es obligatorio ingresar el DNI o documento del comprador.');
            }
        } else {
            if (! in_array($customerDocType, ['dni', 'ruc', 'ce', 'pasaporte', 'otro'], true)) {
                $customerDocType = 'dni';
            }
        }

        $taxBaseMinor = (int) round($total / 1.18);
        $taxIgvMinor = $total - $taxBaseMinor;
        $fiscal = $this->allocateFiscalNumber($tenant, $documentType, $data['document_series'] ?? null);

        $id = (string) Str::uuid();
        $secret = Str::random(64);
        DB::table('orders')->insert([
            'id' => $id,
            'tenant_id' => $tenant,
            'warehouse_id' => $data['warehouse_id'],
            'customer_id' => $customerId,
            'channel' => $pos ? 'pos' : 'web',
            'status' => 'reserved',
            'delivery_method' => $data['delivery_method'],
            'customer_name' => $documentType === 'factura' ? $customerLegalName : $data['customer_name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? '',
            'address' => $data['address'] ?? '',
            'subtotal_minor' => $quote['subtotal_minor'],
            'discount_minor' => $quote['discount_minor'],
            'delivery_minor' => $delivery,
            'total_minor' => $total,
            'coupon_id' => $quote['coupon_id'],
            'tracking_hash' => hash('sha256', $secret),
            'expires_at' => now()->addMinutes(30),
            'due_at' => ($pos && $paid < $total && $customer) ? now()->addDays($customer->credit_days) : null,
            'document_type' => $fiscal['document_type'],
            'document_series' => $fiscal['document_series'],
            'document_number' => $fiscal['document_number'],
            'customer_document_type' => $customerDocType,
            'customer_document_number' => $customerDocNumber,
            'customer_legal_name' => $customerLegalName,
            'tax_base_minor' => $taxBaseMinor,
            'tax_igv_minor' => $taxIgvMinor,
        ]);
        DB::table('orders')->where('tenant_id', $tenant)->where('id', $id)->update(['delivery_zone_id' => $shipping['zone_id'], 'delivery_slot_id' => $shipping['slot_id'], 'delivery_label' => $shipping['label']]);
        if ($pos) {
            DB::table('orders')->where('tenant_id', $tenant)->where('id', $id)->update(['cash_received_minor' => $received, 'change_minor' => $received - $cashApplied]);
        }
        foreach ($quote['lines'] as $line) {
            DB::table('order_lines')->insert(['tenant_id' => $tenant, 'order_id' => $id, ...$line]);
            $this->move($tenant, $data['warehouse_id'], $line['product_id'], 0, $line['quantity'], 0, 0, 'reserve', 'Reserva de pedido', $id);
        }
        if ($quote['coupon_id']) {
            DB::table('coupons')->where('tenant_id', $tenant)->where('id', $quote['coupon_id'])->increment('used');
        }
        $this->recordOrderEvent(
            $tenant,
            $id,
            'order_created',
            'reserved',
            'Pedido recibido',
            'La tienda recibió tu pedido y reservó los productos durante 30 minutos.'
        );
        if ($pos) {
            $cash = $this->cashSession($tenant, $data['cash_session_id']);
            if ((int) $cash->warehouse_id !== (int) $data['warehouse_id']) {
                $this->fail('La caja pertenece a otro almacén.');
            }
            $this->confirm($tenant, $id, true);
            if ($cashApplied > 0) {
                $this->pay($tenant, ['order_id' => $id, 'amount_minor' => $cashApplied, 'method' => 'cash', 'cash_session_id' => $cash->id, 'reference' => null], true);
            }
            foreach ($digital as $payment) {
                $this->pay($tenant, ['order_id' => $id, ...$payment], true);
            }
        }

        return [
            'id' => $id,
            'token' => $secret,
            'total_minor' => $total,
            'document_type' => $fiscal['document_type'],
            'document_series' => $fiscal['document_series'],
            'document_number' => $fiscal['document_number'],
            'correlative' => $fiscal['formatted_correlative'],
        ];
    }

    public function order(int $tenant, string $id): \stdClass
    {
        $order = DB::table('orders')->where('tenant_id', $tenant)->where('id', $id)->lockForUpdate()->first();
        abort_unless($order !== null, 404);
        app(WarehouseAccess::class)->check($tenant, (int) $order->warehouse_id);

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    public function confirm(int $tenant, string $id, bool $allowCredit = false): array
    {
        $order = $this->order($tenant, $id);
        if ($order->status === 'confirmed') {
            return ['id' => $id];
        }
        if ($order->status !== 'reserved' || now()->greaterThan($order->expires_at)) {
            $this->fail('La reserva ya no está vigente. Revisa el pedido antes de cobrar o confirmar.');
        }
        $paid = $this->paid($tenant, $id);
        if (! $allowCredit && $paid < $order->total_minor) {
            $this->fail('El pedido debe estar pagado antes de confirmar la venta.');
        }
        foreach (DB::table('order_lines')->where('tenant_id', $tenant)->where('order_id', $id)->orderBy('product_id')->get() as $line) {
            $product = DB::table('products')->where('tenant_id', $tenant)->where('id', $line->product_id)->lockForUpdate()->first();
            if ($product->cost_units < $line->quantity) {
                $this->fail('El costo y las existencias no concilian. Revisa el inventario.');
            }
            $cost = intdiv($product->cost_value_minor, $product->cost_units) * $line->quantity + intdiv(($product->cost_value_minor % $product->cost_units) * $line->quantity, $product->cost_units);
            $this->move($tenant, $order->warehouse_id, $line->product_id, -$this->integer($line->quantity), -$this->integer($line->quantity), 0, 0, 'sale', 'Venta confirmada', $id);
            DB::table('products')->where('tenant_id', $tenant)->where('id', $line->product_id)->decrementEach(['cost_units' => $line->quantity, 'cost_value_minor' => $cost]);
            DB::table('order_lines')->where('tenant_id', $tenant)->where('id', $line->id)->update(['cost_minor' => $product->cost_complete ? $cost : null]);
        }
        DB::table('orders')->where('tenant_id', $tenant)->where('id', $id)->update(['status' => 'confirmed']);
        DB::table('sales')->insert(['tenant_id' => $tenant, 'order_id' => $id]);
        $providerRef = sprintf('%s-%08d', $order->document_series, $order->document_number);
        DB::table('fiscal_documents')->insert([
            'tenant_id' => $tenant,
            'order_id' => $id,
            'status' => 'unconfigured',
            'provider_reference' => $providerRef,
        ]);
        $this->recordOrderEvent(
            $tenant,
            $id,
            'order_confirmed',
            'confirmed',
            'Pedido confirmado',
            'El pago fue validado y la tienda confirmó la venta.'
        );

        return ['id' => $id];
    }

    public function paid(int $tenant, string $id): int
    {
        return (int) DB::table('payments')->where('tenant_id', $tenant)->where('order_id', $id)->where('status', 'verified')->sum('amount_minor');
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function countStock(int $tenant, array $data): array
    {
        $position = $this->position($tenant, $data['warehouse_id'], $data['product_id']);
        if ($this->integer($position->physical) !== $this->integer($data['expected_physical'])) {
            $this->fail('Las existencias cambiaron desde el corte. Actualiza el conteo antes de aprobarlo.');
        }
        $delta = $this->integer($this->integer($data['counted_physical']) - $this->integer($position->physical));
        $product = DB::table('products')->where('tenant_id', $tenant)->where('id', $data['product_id'])->lockForUpdate()->first();
        if (! $product) {
            abort(404);
        }
        $value = 0;
        if ($delta < 0) {
            $units = $this->integer(-$delta);
            if ($product->cost_units < $units) {
                $this->fail('El pool de costos no concilia con la pérdida registrada.');
            }
            $value = intdiv($product->cost_value_minor, $product->cost_units) * $units + intdiv(($product->cost_value_minor % $product->cost_units) * $units, $product->cost_units);
            DB::table('products')->where('tenant_id', $tenant)->where('id', $product->id)->decrementEach(['cost_units' => $units, 'cost_value_minor' => $value]);
        } elseif ($delta > 0) {
            DB::table('products')->where('tenant_id', $tenant)->where('id', $product->id)->increment('cost_units', $delta);
            DB::table('products')->where('tenant_id', $tenant)->where('id', $product->id)->update(['cost_complete' => false]);
        }
        $this->move($tenant, $data['warehouse_id'], $data['product_id'], $delta, 0, 0, 0, $delta < 0 ? 'inventory_loss' : 'inventory_count', $data['reason'], '', $delta < 0 && $product->cost_complete ? $this->integer(-$value) : null);

        return ['id' => $product->id, 'difference' => $delta];
    }

    public function cashSession(int $tenant, int $id): \stdClass
    {
        $cash = DB::table('cash_sessions')->where('tenant_id', $tenant)->where('id', $id)->whereNull('closed_at')->where('user_id', auth()->id())->lockForUpdate()->first();
        if (! $cash) {
            $this->fail('No tienes una caja abierta autorizada para esta operación.');
        }
        app(WarehouseAccess::class)->check($tenant, (int) $cash->warehouse_id);

        return $cash;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function pay(int $tenant, array $data, bool $verified): array
    {
        $order = $this->order($tenant, $data['order_id']);
        if (! in_array($order->status, ['reserved', 'confirmed'], true)) {
            $this->fail('No se puede cobrar un pedido cancelado o vencido.');
        }
        if ($order->status === 'reserved' && now()->greaterThan($order->expires_at)) {
            $this->fail('Reserva vencida: revisa existencias antes de aceptar el pago.');
        }
        if ($verified && $this->paid($tenant, $order->id) + $data['amount_minor'] > $order->total_minor - $order->returned_minor) {
            $this->fail('El cobro supera el saldo pendiente.');
        }
        $cashId = null;
        if ($data['method'] === 'cash') {
            if (! $verified) {
                $this->fail('El efectivo debe registrarse por personal autorizado.');
            }
            $cashId = $this->cashSession($tenant, $data['cash_session_id'])->id;
        } else {
            $settings = json_decode(DB::table('tenants')->where('id', $tenant)->value('settings'), true) ?: [];
            if (empty($settings[$data['method'].'_instructions'])) {
                $this->fail('Este medio de pago no está configurado.');
            }
            if (empty($data['reference'])) {
                $this->fail('Ingresa una referencia de pago.');
            }
        }
        if (! empty($data['reference']) && DB::table('payments')->where('tenant_id', $tenant)->where('method', $data['method'])->where('reference', $data['reference'])->where('status', '!=', 'rejected')->exists()) {
            $this->fail('La referencia ya fue registrada; revisa si corresponde a otro pago.');
        }
        $id = DB::table('payments')->insertGetId(['tenant_id' => $tenant, 'order_id' => $order->id, 'method' => $data['method'], 'status' => $verified ? 'verified' : 'pending', 'amount_minor' => $data['amount_minor'], 'reference' => $data['reference'] ?? null, 'cash_session_id' => $cashId, 'verified_at' => $verified ? now() : null]);
        if ($cashId) {
            DB::table('cash_entries')->insert(['tenant_id' => $tenant, 'cash_session_id' => $cashId, 'amount_minor' => $data['amount_minor'], 'reason' => 'Cobro '.$order->id]);
        }
        $this->recordOrderEvent(
            $tenant,
            $order->id,
            $verified ? 'payment_verified' : 'payment_submitted',
            $verified ? 'verified' : 'pending',
            $verified ? 'Pago registrado' : 'Constancia recibida',
            $verified
                ? 'La tienda registró un pago verificado para este pedido.'
                : 'La constancia de pago fue enviada y está pendiente de revisión.'
        );

        return ['id' => $id];
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyPayment(int $tenant, int $paymentId, bool $accept): array
    {
        $payment = DB::table('payments')->where('tenant_id', $tenant)->where('id', $paymentId)->lockForUpdate()->first();
        abort_unless($payment !== null, 404);
        if ($payment->status !== 'pending') {
            $this->fail('El pago ya fue revisado.');
        }
        $order = $this->order($tenant, $payment->order_id);
        if ($accept && (! in_array($order->status, ['reserved', 'confirmed'], true) || ($order->status === 'reserved' && now()->greaterThan($order->expires_at)))) {
            $this->fail('Pago fuera de reserva: requiere conciliación antes de aceptar.');
        }
        if ($accept && $this->paid($tenant, $order->id) + $payment->amount_minor > $order->total_minor - $order->returned_minor) {
            $this->fail('El pago excede el saldo.');
        }
        DB::table('payments')->where('tenant_id', $tenant)->where('id', $paymentId)->update(['status' => $accept ? 'verified' : 'rejected', 'verified_at' => $accept ? now() : null]);
        $this->recordOrderEvent(
            $tenant,
            $order->id,
            $accept ? 'payment_verified' : 'payment_rejected',
            $accept ? 'verified' : 'rejected',
            $accept ? 'Pago verificado' : 'Pago rechazado',
            $accept
                ? 'La tienda verificó la constancia de pago.'
                : 'La tienda rechazó la constancia. Revisa los datos y vuelve a intentarlo.'
        );

        return ['id' => $paymentId];
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(int $tenant, string $id, string $reason, bool $expire = false): array
    {
        $order = $this->order($tenant, $id);
        if (in_array($order->status, ['cancelled', 'expired'], true)) {
            return ['id' => $id];
        }
        if ($order->status !== 'reserved') {
            $this->fail('Una venta confirmada requiere devolución, no cancelación.');
        }
        if ($expire && $this->paid($tenant, $id) > 0) {
            $this->fail('La reserva pagada requiere revisión manual.');
        }
        foreach (DB::table('order_lines')->where('tenant_id', $tenant)->where('order_id', $id)->orderBy('product_id')->get() as $line) {
            $this->move($tenant, $order->warehouse_id, $line->product_id, 0, -$this->integer($line->quantity), 0, 0, 'release', $reason, $id);
        }
        if ($order->coupon_id) {
            DB::table('coupons')->where('tenant_id', $tenant)->where('id', $order->coupon_id)->decrement('used');
        }
        DB::table('orders')->where('tenant_id', $tenant)->where('id', $id)->update(['status' => $expire ? 'expired' : 'cancelled']);
        $this->recordOrderEvent(
            $tenant,
            $id,
            $expire ? 'order_expired' : 'order_cancelled',
            $expire ? 'expired' : 'cancelled',
            $expire ? 'Reserva vencida' : 'Pedido cancelado',
            $expire
                ? 'La reserva venció y los productos volvieron al inventario.'
                : 'La tienda canceló el pedido. Motivo: '.$reason
        );

        return ['id' => $id];
    }

    public function recordOrderEvent(
        int $tenant,
        string $orderId,
        string $kind,
        ?string $status,
        string $title,
        string $message,
    ): void {
        DB::table('order_events')->insert([
            'tenant_id' => $tenant,
            'order_id' => $orderId,
            'actor_id' => auth()->id(),
            'kind' => $kind,
            'status' => $status,
            'title' => $title,
            'message' => $message,
        ]);

        $userId = DB::table('orders')
            ->join('customers', function ($join): void {
                $join->on('customers.id', '=', 'orders.customer_id')
                    ->on('customers.tenant_id', '=', 'orders.tenant_id');
            })
            ->where('orders.tenant_id', $tenant)
            ->where('orders.id', $orderId)
            ->value('customers.user_id');

        if ($userId === null) {
            return;
        }

        DB::table('customer_notifications')->insert([
            'tenant_id' => $tenant,
            'user_id' => $userId,
            'order_id' => $orderId,
            'kind' => $kind,
            'title' => $title,
            'message' => $message,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function returnItems(int $tenant, array $data): array
    {
        $line = DB::table('order_lines')->where('tenant_id', $tenant)->where('id', $data['line_id'])->lockForUpdate()->first();
        abort_unless($line !== null, 404);
        $order = $this->order($tenant, $line->order_id);
        if ($order->status !== 'confirmed' || $line->returned + $data['quantity'] > $line->quantity) {
            $this->fail('La devolución supera las unidades vendidas disponibles.');
        }
        // Cumulative allocation keeps the last partial return exact.
        $amount = intdiv($line->total_minor * ($line->returned + $data['quantity']), $line->quantity) - intdiv($line->total_minor * $line->returned, $line->quantity);
        $cost = $line->cost_minor === null ? 0 : intdiv($line->cost_minor * ($line->returned + $data['quantity']), $line->quantity) - intdiv($line->cost_minor * $line->returned, $line->quantity);
        $id = DB::table('returns')->insertGetId(['tenant_id' => $tenant, 'order_line_id' => $line->id, 'quantity' => $data['quantity'], 'condition' => $data['condition'], 'amount_minor' => $amount, 'cost_minor' => $line->cost_minor === null ? null : $cost, 'reason' => $data['reason']]);
        $this->move($tenant, $order->warehouse_id, $line->product_id, $data['quantity'], 0, $data['condition'] === 'damaged' ? $data['quantity'] : 0, 0, 'return', $data['reason'], (string) $id);
        DB::table('products')->where('tenant_id', $tenant)->where('id', $line->product_id)->incrementEach(['cost_units' => $data['quantity'], 'cost_value_minor' => $cost]);
        if ($line->cost_minor === null) {
            DB::table('products')->where('tenant_id', $tenant)->where('id', $line->product_id)->update(['cost_complete' => false]);
        }
        DB::table('order_lines')->where('tenant_id', $tenant)->where('id', $line->id)->increment('returned', $data['quantity']);
        DB::table('orders')->where('tenant_id', $tenant)->where('id', $order->id)->increment('returned_minor', $amount);

        return ['id' => $id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function refund(int $tenant, array $data): array
    {
        $order = $this->order($tenant, $data['order_id']);
        $refunded = (int) DB::table('refunds')->where('tenant_id', $tenant)->where('order_id', $order->id)->sum('amount_minor');
        $due = $this->paid($tenant, $order->id) - $refunded - (in_array($order->status, ['cancelled', 'expired'], true) ? 0 : $order->total_minor - $order->returned_minor);
        if ($data['amount_minor'] > $due) {
            $this->fail('El reembolso supera el importe a favor del comprador.');
        }
        $cashId = $data['method'] === 'cash' ? $this->cashSession($tenant, $data['cash_session_id'])->id : null;
        $id = DB::table('refunds')->insertGetId(['tenant_id' => $tenant, 'order_id' => $order->id, 'amount_minor' => $data['amount_minor'], 'reference' => $data['reference'], 'method' => $data['method'], 'cash_session_id' => $cashId]);
        if ($cashId) {
            DB::table('cash_entries')->insert(['tenant_id' => $tenant, 'cash_session_id' => $cashId, 'amount_minor' => -$data['amount_minor'], 'reason' => 'Reembolso '.$order->id]);
        }

        return ['id' => $id];
    }
}
