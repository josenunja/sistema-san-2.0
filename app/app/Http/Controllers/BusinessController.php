<?php

namespace App\Http\Controllers;

use App\Services\Commerce;
use App\Services\Invitations;
use App\Services\Purchasing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    private const SECTIONS = ['resumen', 'inventario', 'compras', 'pedidos', 'pos', 'caja', 'clientes', 'promociones', 'configuracion', 'auditoria'];

    private function allowed(Request $request, string $action): void
    {
        $role = $request->attributes->get('membership')->role;
        $allowed = match ($role) {
            'owner' => true,
            'cashier' => in_array($action, ['pedidos', 'pos', 'caja', 'clientes', 'open_cash', 'close_cash', 'pos_sale', 'payment', 'customer'], true),
            'warehouse' => in_array($action, ['inventario', 'receipt', 'transfer', 'receive_transfer'], true),
            default => false,
        };
        abort_unless($allowed, 403);
    }

    public function index(Request $request, string $slug, string $section = 'resumen'): Response
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);
        $this->allowed($request, $section);
        $tenant = $request->attributes->get('tenant');
        $id = (int) $tenant->id;
        $data = [];
        if (in_array($section, ['inventario', 'compras', 'pos'], true)) {
            $data['products'] = DB::table('products')->where('tenant_id', $id)->orderBy('name')->limit(500)->get(['id', 'name', 'sku', 'price_minor']);
            $data['stock'] = DB::table('stock_positions')->where('tenant_id', $id)->get();
        }
        if ($section === 'inventario') {
            $data['movements'] = DB::table('stock_movements')->where('tenant_id', $id)->orderByDesc('id')->limit(100)->get();
            $data['transfers'] = DB::table('transfers')->where('tenant_id', $id)->whereRaw('received + damaged < quantity')->orderBy('id')->get();
        }
        if ($section === 'compras') {
            $data['suppliers'] = DB::table('suppliers')->where('tenant_id', $id)->orderBy('name')->get();
            $data['purchases'] = DB::table('purchases')->where('tenant_id', $id)->orderByDesc('id')->limit(200)->get();
            $data['purchaseReceipts'] = DB::table('purchase_receipts')->where('tenant_id', $id)->orderByDesc('id')->limit(200)->get();
        }
        if ($section === 'pedidos') {
            $data['orders'] = DB::table('orders')->where('tenant_id', $id)->orderByDesc('created_at')->paginate(25)->withQueryString();
        }
        if (in_array($section, ['pos', 'caja'], true)) {
            $data['cashSessions'] = DB::table('cash_sessions')->where('tenant_id', $id)->where('user_id', $request->user()->id)->orderByDesc('id')->limit(30)->get()->map(function ($cash) use ($id) {
                $cash->expected_minor = $cash->opening_minor + (int) DB::table('cash_entries')->where('tenant_id', $id)->where('cash_session_id', $cash->id)->sum('amount_minor');

                return $cash;
            });
            $data['cashEntries'] = DB::table('cash_entries')->join('cash_sessions', 'cash_sessions.id', '=', 'cash_entries.cash_session_id')->where('cash_entries.tenant_id', $id)->where('cash_sessions.user_id', $request->user()->id)->orderByDesc('cash_entries.id')->limit(100)->get(['cash_entries.*']);
        }
        if (in_array($section, ['pos', 'clientes'], true)) {
            $data['customers'] = DB::table('customers')->where('tenant_id', $id)->orderBy('name')->limit(500)->get();
        }
        if ($section === 'clientes') {
            $paid = DB::table('payments')->where('tenant_id', $id)->where('status', 'verified')->groupBy('order_id')->selectRaw('order_id, sum(amount_minor) as paid_minor');
            $history = DB::table('orders')->leftJoinSub($paid, 'paid', 'paid.order_id', '=', 'orders.id')->where('orders.tenant_id', $id)->where('orders.status', 'confirmed')->whereNotNull('customer_id')->groupBy('customer_id')->selectRaw('customer_id, count(*) as purchases_count, max(orders.created_at) as last_purchase_at, sum(total_minor-returned_minor) as spent_minor, sum(greatest(0,total_minor-returned_minor-coalesce(paid.paid_minor,0))) as debt_minor');
            $data['customers'] = DB::table('customers')->leftJoinSub($history, 'history', 'history.customer_id', '=', 'customers.id')->where('customers.tenant_id', $id)->orderBy('customers.name')->limit(500)->get(['customers.*', 'history.purchases_count', 'history.last_purchase_at', 'history.spent_minor', 'history.debt_minor']);
        }
        if ($section === 'promociones') {
            $data['coupons'] = DB::table('coupons')->where('tenant_id', $id)->orderByDesc('id')->get();
        }
        if ($section === 'auditoria') {
            $data['audit'] = DB::table('audit_entries')->where('tenant_id', $id)->orderByDesc('id')->paginate(50);
        }
        if ($section === 'configuracion') {
            $data['settings'] = json_decode($tenant->settings, true) ?: [];
            $data['versions'] = DB::table('storefront_versions')->where('tenant_id', $id)->orderByDesc('id')->limit(30)->get(['id', 'published_at', 'created_at']);
            $data['members'] = DB::table('memberships')->join('users', 'users.id', '=', 'memberships.user_id')->where('memberships.tenant_id', $id)->get(['users.id', 'users.name', 'users.email', 'memberships.role']);
            $data['invitations'] = app(Invitations::class)->listing($id);
        }
        if ($section === 'resumen') {
            $data['report'] = $this->report($id, $request);
        }

        return Inertia::render('Business', ['tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $slug], 'section' => $section, 'role' => $request->attributes->get('membership')->role, 'warehouses' => DB::table('warehouses')->where('tenant_id', $id)->orderBy('name')->get(), 'data' => $data]);
    }

    /**
     * @return array<string, mixed>
     */
    public function report(int $tenant, Request $request): array
    {
        $range = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $from = Carbon::parse($range['from'] ?? now('America/Lima')->startOfMonth()->toDateString(), 'America/Lima')->startOfDay()->utc();
        $to = Carbon::parse($range['to'] ?? now('America/Lima')->toDateString(), 'America/Lima')->endOfDay()->utc();
        $orders = DB::table('orders')->join('sales', 'sales.order_id', '=', 'orders.id')->where('orders.tenant_id', $tenant)->whereBetween('sales.confirmed_at', [$from, $to]);
        $ids = (clone $orders)->pluck('orders.id');
        $gross = (int) (clone $orders)->sum('subtotal_minor');
        $discounts = (int) (clone $orders)->sum('discount_minor');
        // Returns are recorded by their own event date rather than rewriting old periods.
        $returns = (int) DB::table('returns')->where('tenant_id', $tenant)->whereBetween('created_at', [$from, $to])->sum('amount_minor');
        $cost = (int) DB::table('order_lines')->where('tenant_id', $tenant)->whereIn('order_id', $ids)->sum('cost_minor');
        $returnedCost = (int) DB::table('returns')->where('tenant_id', $tenant)->whereBetween('created_at', [$from, $to])->sum('cost_minor');
        $unknown = DB::table('order_lines')->where('tenant_id', $tenant)->whereIn('order_id', $ids)->whereNull('cost_minor')->count();
        $unknown += DB::table('returns')->where('tenant_id', $tenant)->whereBetween('created_at', [$from, $to])->whereNull('cost_minor')->count();
        $collections = (int) DB::table('payments')->where('tenant_id', $tenant)->where('status', 'verified')->whereBetween('verified_at', [$from, $to])->sum('amount_minor');
        $refunds = (int) DB::table('refunds')->where('tenant_id', $tenant)->whereBetween('created_at', [$from, $to])->sum('amount_minor');
        $debt = DB::table('orders')->where('tenant_id', $tenant)->where('status', 'confirmed')->get()->sum(fn ($o) => max(0, $o->total_minor - $o->returned_minor - app(Commerce::class)->paid($tenant, $o->id)));
        $delivery = (int) (clone $orders)->sum('delivery_minor');
        $expenses = (int) DB::table('expenses')->where('tenant_id', $tenant)->whereBetween('created_at', [$from, $to])->sum('amount_minor');
        $loss = -(int) DB::table('stock_movements')->where('tenant_id', $tenant)->where('kind', 'inventory_loss')->whereBetween('created_at', [$from, $to])->sum('pool_value_delta_minor');
        $unknown += DB::table('stock_movements')->where('tenant_id', $tenant)->where('kind', 'inventory_loss')->whereBetween('created_at', [$from, $to])->whereNull('pool_value_delta_minor')->count();

        return ['from' => $from->timezone('America/Lima')->toDateString(), 'to' => $to->timezone('America/Lima')->toDateString(), 'gross_minor' => $gross, 'discount_minor' => $discounts, 'returns_minor' => $returns, 'delivery_minor' => $delivery, 'net_minor' => $gross - $discounts + $delivery - $returns, 'collections_minor' => $collections, 'refunds_minor' => $refunds, 'debt_minor' => $debt, 'cost_minor' => $cost - $returnedCost, 'gross_profit_minor' => $unknown ? null : $gross - $discounts + $delivery - $returns - $cost + $returnedCost, 'expenses_minor' => $expenses, 'inventory_loss_minor' => $loss, 'management_result_minor' => $unknown ? null : $gross - $discounts + $delivery - $returns - $cost + $returnedCost - $expenses - $loss, 'missing_cost_lines' => $unknown];
    }

    public function mutate(Request $request, Commerce $commerce): RedirectResponse
    {
        $base = $request->validate(['action' => 'required|string', 'key' => 'required|uuid']);
        $action = $base['action'];
        $this->allowed($request, $action);
        $tenant = (int) $request->attributes->get('tenant')->id;
        $rules = match ($action) {
            'warehouse' => ['name' => 'required|string|max:120', 'branch' => 'required|string|max:120'],
            'receipt' => ['warehouse_id' => 'required|integer', 'product_id' => 'required|integer', 'quantity' => 'required|integer|min:1|max:1000000', 'unit_cost_minor' => 'required|integer|min:0|max:999999999', 'supplier' => 'required|string|max:160', 'reference' => 'required|string|max:120'],
            'transfer' => ['source_id' => 'required|integer', 'destination_id' => 'required|integer', 'product_id' => 'required|integer', 'quantity' => 'required|integer|min:1|max:1000000'],
            'receive_transfer' => ['transfer_id' => 'required|integer', 'quantity' => 'required|integer|min:1|max:1000000', 'damaged' => 'nullable|integer|min:0|max:1000000'],
            'supplier' => ['name' => 'required|string|max:160', 'reference' => 'nullable|string|max:40', 'contact' => 'nullable|string|max:160'],
            'purchase' => ['supplier_id' => 'required|integer', 'warehouse_id' => 'required|integer', 'product_id' => 'required|integer', 'reference' => 'required|string|max:120', 'quantity' => 'required|integer|min:1|max:1000000', 'unit_cost_minor' => 'required|integer|min:0|max:999999999'],
            'purchase_confirm', 'purchase_close' => ['purchase_id' => 'required|integer'],
            'purchase_receive' => ['purchase_id' => 'required|integer', 'quantity' => 'required|integer|min:1|max:1000000'],
            'purchase_reverse' => ['purchase_id' => 'required|integer', 'receipt_id' => 'required|integer', 'reason' => 'required|string|min:5|max:300'],
            'stock_adjust' => ['warehouse_id' => 'required|integer', 'product_id' => 'required|integer', 'quantity' => 'required|integer|min:1|max:1000000', 'mode' => ['required', Rule::in(['block', 'unblock', 'damage'])], 'reason' => 'required|string|min:5|max:300'],
            'stock_count' => ['warehouse_id' => 'required|integer', 'product_id' => 'required|integer', 'expected_physical' => 'required|integer|min:0|max:1000000000', 'counted_physical' => 'required|integer|min:0|max:1000000000', 'reason' => 'required|string|min:5|max:300'],
            'expense' => ['amount_minor' => 'required|integer|min:1|max:999999999', 'category' => 'required|string|max:80', 'reason' => 'required|string|min:5|max:300', 'cash_session_id' => 'nullable|integer'],
            'customer' => ['name' => 'required|string|max:160', 'phone' => 'nullable|string|max:30', 'email' => 'nullable|email|max:254', 'address' => 'nullable|string|max:1000', 'marketing_consent' => 'required|boolean', 'credit_limit_minor' => 'nullable|integer|min:0|max:999999999', 'credit_days' => 'nullable|integer|min:0|max:365'],
            'customer_credit' => ['customer_id' => 'required|integer', 'credit_limit_minor' => 'required|integer|min:0|max:999999999', 'credit_days' => 'required|integer|min:0|max:365'],
            'coupon' => ['code' => 'required|regex:/^[A-Za-z0-9_-]{3,40}$/', 'percent' => 'required|integer|min:1|max:100', 'amount_minor' => 'nullable|integer|min:1|max:999999999', 'category' => 'nullable|string|max:100', 'product_id' => 'nullable|integer', 'channel' => ['nullable', Rule::in(['web', 'pos'])], 'per_customer' => 'nullable|integer|min:1|max:1000000', 'minimum_minor' => 'required|integer|min:0|max:999999999', 'usage_limit' => 'required|integer|min:1|max:1000000', 'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at'],
            'open_cash' => ['warehouse_id' => 'required|integer', 'opening_minor' => 'required|integer|min:0|max:999999999'],
            'close_cash' => ['cash_session_id' => 'required|integer', 'counted_minor' => 'required|integer|min:0|max:999999999'],
            'cash_movement' => ['cash_session_id' => 'required|integer', 'amount_minor' => 'required|integer|not_in:0|min:-999999999|max:999999999', 'reason' => 'required|string|min:5|max:300'],
            'pos_sale' => self::orderRules(),
            'payment' => ['order_id' => 'required|uuid', 'amount_minor' => 'required|integer|min:1|max:999999999', 'method' => ['required', Rule::in(['cash', 'yape', 'bcp'])], 'cash_session_id' => 'nullable|integer', 'reference' => 'nullable|string|max:120'],
            'verify_payment' => ['payment_id' => 'required|integer', 'accept' => 'required|boolean'],
            'confirm' => ['order_id' => 'required|uuid'],
            'cancel' => ['order_id' => 'required|uuid', 'reason' => 'required|string|min:5|max:300'],
            'delivery' => ['order_id' => 'required|uuid', 'status' => ['required', Rule::in(['preparing', 'ready', 'delivered'])]],
            'return' => ['line_id' => 'required|integer', 'quantity' => 'required|integer|min:1|max:1000000', 'condition' => ['required', Rule::in(['available', 'damaged'])], 'reason' => 'required|string|min:5|max:300'],
            'refund' => ['order_id' => 'required|uuid', 'amount_minor' => 'required|integer|min:1|max:999999999', 'method' => ['required', Rule::in(['cash', 'yape', 'bcp'])], 'cash_session_id' => 'nullable|integer', 'reference' => 'required|string|min:5|max:120'],
            'settings' => ['whatsapp' => ['nullable', 'regex:/^[1-9][0-9]{7,14}$/'], 'tagline' => 'nullable|string|max:300', 'yape_instructions' => 'nullable|string|max:500', 'bcp_instructions' => 'nullable|string|max:500', 'delivery_minor' => 'nullable|integer|min:0|max:999999999', 'theme' => ['required', Rule::in(['verde', 'azul', 'violeta'])], 'publication' => ['required', Rule::in(['draft', 'publish'])]],
            'restore_settings' => ['version_id' => 'required|integer'],
            default => abort(404),
        };
        $data = $request->validate($rules);
        if (in_array($action, ['settings', 'restore_settings', 'refund'], true)) {
            abort_unless($request->session()->has('auth.password_confirmed_at') && time() - $request->session()->get('auth.password_confirmed_at') < 10800, 403, 'Confirma nuevamente tu contraseña en configuración de seguridad.');
        }
        $result = $commerce->run($tenant, $base['key'], $action, ['actor' => $request->user()->id, ...$data], function () use ($tenant, $action, $data, $commerce, $request) {
            return match ($action) {
                'supplier', 'purchase', 'purchase_confirm', 'purchase_close', 'purchase_receive', 'purchase_reverse' => app(Purchasing::class)->execute($tenant, $action, $data),
                'warehouse' => $this->warehouse($tenant, $data, $commerce),
                'receipt' => $commerce->receive($tenant, $data),
                'transfer' => $commerce->transfer($tenant, $data),
                'receive_transfer' => $commerce->receiveTransfer($tenant, $data),
                'stock_adjust' => $this->adjust($tenant, $data, $commerce),
                'stock_count' => $commerce->countStock($tenant, $data),
                'expense' => $this->expense($tenant, $data, $commerce),
                'customer' => $this->customer($tenant, $data, $request, $commerce),
                'customer_credit' => $this->credit($tenant, $data, $commerce),
                'coupon' => $this->coupon($tenant, $data),
                'open_cash' => $this->openCash($tenant, $data, $request, $commerce),
                'close_cash' => $this->closeCash($tenant, $data, $commerce),
                'cash_movement' => $this->cashMovement($tenant, $data, $commerce),
                'pos_sale' => $commerce->createOrder($tenant, $data, true),
                'payment' => $commerce->pay($tenant, $data, $data['method'] === 'cash'),
                'verify_payment' => $commerce->verifyPayment($tenant, $data['payment_id'], $data['accept']),
                'confirm' => $commerce->confirm($tenant, $data['order_id']),
                'cancel' => $commerce->cancel($tenant, $data['order_id'], $data['reason']),
                'delivery' => $this->deliver($tenant, $data, $commerce),
                'return' => $commerce->returnItems($tenant, $data),
                'refund' => $commerce->refund($tenant, $data),
                'settings' => $this->settings($tenant, $data),
                'restore_settings' => $this->restoreSettings($tenant, $data['version_id'], $commerce),
            };
        });

        return back()->with('operation_result', $result)->with('success', 'Operación guardada correctamente.');
    }

    /** @return array<string, mixed> */
    public static function orderRules(): array
    {
        return ['warehouse_id' => 'required|integer', 'customer_id' => 'nullable|integer', 'customer_name' => 'required|string|max:160', 'phone' => 'required|string|max:30', 'email' => 'nullable|email|max:254', 'address' => 'nullable|string|max:1000', 'delivery_method' => ['required', Rule::in(['pickup', 'delivery', 'courier', 'shipping'])], 'coupon' => 'nullable|string|max:40', 'cash_session_id' => 'nullable|integer', 'cash_minor' => 'nullable|integer|min:0|max:999999999', 'items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|integer', 'items.*.quantity' => 'required|integer|min:1|max:10000'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function adjust(int $tenant, array $data, Commerce $commerce): array
    {
        $p = $commerce->position($tenant, $data['warehouse_id'], $data['product_id']);
        if ($data['mode'] === 'unblock' && $p->blocked < $data['quantity']) {
            $commerce->fail('No hay suficientes unidades bloqueadas.');
        }
        $commerce->move($tenant, $data['warehouse_id'], $data['product_id'], 0, 0, $data['mode'] === 'unblock' ? -$data['quantity'] : $data['quantity'], 0, $data['mode'], $data['reason']);

        return ['id' => $data['product_id']];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function warehouse(int $tenant, array $data, Commerce $commerce): array
    {
        $plan = DB::table('tenants')->where('id', $tenant)->value('plan');
        if (DB::table('warehouses')->where('tenant_id', $tenant)->count() >= DB::table('plans')->where('slug', $plan)->value('warehouse_limit')) {
            $commerce->fail('Se alcanzó el límite de almacenes del plan.');
        }
        if (DB::table('warehouses')->where('tenant_id', $tenant)->where('name', $data['name'])->exists()) {
            $commerce->fail('El almacén ya existe.');
        }

        return ['id' => DB::table('warehouses')->insertGetId(['tenant_id' => $tenant, ...$data])];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function customer(int $tenant, array $data, Request $request, Commerce $commerce): array
    {
        $data['credit_limit_minor'] ??= 0;
        $data['credit_days'] ??= 30;
        if ($request->attributes->get('membership')->role !== 'owner' && $data['credit_limit_minor'] > 0) {
            abort(403);
        }
        $id = DB::table('customers')->insertGetId(['tenant_id' => $tenant, ...array_map(fn ($v) => $v ?? '', $data)]);

        return ['id' => $id];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function credit(int $tenant, array $data, Commerce $commerce): array
    {
        $customer = DB::table('customers')->where('tenant_id', $tenant)->where('id', $data['customer_id'])->first();
        abort_unless($customer !== null, 404);
        DB::table('customers')->where('tenant_id', $tenant)->where('id', $customer->id)->update(['credit_limit_minor' => $data['credit_limit_minor'], 'credit_days' => $data['credit_days']]);
        $commerce->audit($tenant, 'credit_policy_changed', $customer->id.':'.$customer->credit_limit_minor.'=>'.$data['credit_limit_minor'].':days='.$data['credit_days']);

        return ['id' => $customer->id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function coupon(int $tenant, array $data): array
    {
        if (! empty($data['product_id'])) {
            abort_unless(DB::table('products')->where('tenant_id', $tenant)->where('id', $data['product_id'])->exists(), 404);
        }
        $data['code'] = strtoupper($data['code']);
        if (DB::table('coupons')->where('tenant_id', $tenant)->where('code', $data['code'])->exists()) {
            app(Commerce::class)->fail('El código del cupón ya existe.');
        }
        $data['starts_at'] = Carbon::parse($data['starts_at'], 'America/Lima')->utc();
        $data['ends_at'] = Carbon::parse($data['ends_at'], 'America/Lima')->utc();

        return ['id' => DB::table('coupons')->insertGetId(['tenant_id' => $tenant, ...$data])];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function openCash(int $tenant, array $data, Request $request, Commerce $commerce): array
    {
        abort_unless(DB::table('warehouses')->where('tenant_id', $tenant)->where('id', $data['warehouse_id'])->exists(), 404);
        if (DB::table('cash_sessions')->where('tenant_id', $tenant)->where('user_id', $request->user()->id)->whereNull('closed_at')->exists()) {
            $commerce->fail('Ya tienes una caja abierta.');
        }

        return ['id' => DB::table('cash_sessions')->insertGetId(['tenant_id' => $tenant, 'user_id' => $request->user()->id, ...$data])];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function closeCash(int $tenant, array $data, Commerce $commerce): array
    {
        $cash = $commerce->cashSession($tenant, $data['cash_session_id']);
        if (DB::table('offline_devices')->where('tenant_id', $tenant)->where('cash_session_id', $cash->id)->whereNull('reconciled_at')->exists()) {
            $commerce->fail('Concilia los dispositivos offline de esta caja antes de cerrarla.');
        }
        DB::table('cash_sessions')->where('tenant_id', $tenant)->where('id', $cash->id)->update(['counted_minor' => $data['counted_minor'], 'closed_at' => now()]);

        return ['id' => $cash->id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function cashMovement(int $tenant, array $data, Commerce $commerce): array
    {
        $commerce->cashSession($tenant, $data['cash_session_id']);

        return ['id' => DB::table('cash_entries')->insertGetId(['tenant_id' => $tenant, ...$data])];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function expense(int $tenant, array $data, Commerce $commerce): array
    {
        if (! empty($data['cash_session_id'])) {
            $commerce->cashSession($tenant, $data['cash_session_id']);
            DB::table('cash_entries')->insert(['tenant_id' => $tenant, 'cash_session_id' => $data['cash_session_id'], 'amount_minor' => -$commerce->integer($data['amount_minor']), 'reason' => 'Gasto: '.$data['reason']]);
        } else {
            $data['cash_session_id'] = null;
        }

        return ['id' => DB::table('expenses')->insertGetId(['tenant_id' => $tenant, 'actor_id' => auth()->id(), ...$data])];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function deliver(int $tenant, array $data, Commerce $commerce): array
    {
        $order = $commerce->order($tenant, $data['order_id']);
        if ($order->status !== 'confirmed') {
            $commerce->fail('Primero confirma la venta.');
        }
        $transitions = ['pending' => 'preparing', 'preparing' => 'ready', 'ready' => 'delivered'];
        if (($transitions[$order->delivery_status] ?? null) !== $data['status']) {
            $commerce->fail('La transición de entrega no es válida.');
        }
        $status = $data['status'] ?? null;
        $copy = match ($status) {
            'preparing' => ['Pedido en preparación', 'La tienda está preparando los productos de tu pedido.'],
            'ready' => ['Pedido listo', $order->delivery_method === 'pickup' ? 'Tu pedido ya está listo para recoger.' : 'Tu pedido está listo para iniciar la entrega.'],
            'delivered' => ['Pedido entregado', 'La tienda marcó el pedido como entregado.'],
            default => $commerce->fail('El estado de entrega no es válido.'),
        };
        DB::table('orders')->where('tenant_id', $tenant)->where('id', $order->id)->update(['delivery_status' => $status]);
        $commerce->recordOrderEvent($tenant, $order->id, 'delivery_'.$status, $status, $copy[0], $copy[1]);

        return ['id' => $order->id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function settings(int $tenant, array $data): array
    {
        $publish = $data['publication'] === 'publish';
        unset($data['publication']);
        $current = json_decode((string) DB::table('tenants')->where('id', $tenant)->value('settings'), true) ?: [];
        $json = json_encode([...$current, ...$data], JSON_THROW_ON_ERROR);
        $version = DB::table('storefront_versions')->insertGetId(['tenant_id' => $tenant, 'actor_id' => auth()->id(), 'settings' => $json, 'published_at' => $publish ? now() : null]);
        if ($publish) {
            DB::table('tenants')->where('id', $tenant)->update(['settings' => $json]);
        }

        return ['id' => $version];
    }

    /** @return array<string, mixed> */
    private function restoreSettings(int $tenant, int $versionId, Commerce $commerce): array
    {
        $version = DB::table('storefront_versions')->where('tenant_id', $tenant)->where('id', $versionId)->first();
        abort_unless($version !== null, 404);

        return $this->settings($tenant, [...json_decode($version->settings, true), 'publication' => 'publish']);
    }

    public function detail(Request $request, string $slug, string $orderId, Commerce $commerce): Response
    {
        $this->allowed($request, 'pedidos');
        $tenant = $request->attributes->get('tenant');
        $order = $commerce->order($tenant->id, $orderId);
        unset($order->tracking_hash);

        return Inertia::render('Order', ['tenant' => ['name' => $tenant->name, 'slug' => $slug], 'order' => $order, 'lines' => DB::table('order_lines')->where('tenant_id', $tenant->id)->where('order_id', $orderId)->get(), 'payments' => DB::table('payments')->where('tenant_id', $tenant->id)->where('order_id', $orderId)->get(['id', 'amount_minor', 'method', 'status', 'reference', 'created_at']), 'events' => DB::table('order_events')->where('tenant_id', $tenant->id)->where('order_id', $orderId)->orderBy('created_at')->orderBy('id')->get(['id', 'kind', 'status', 'title', 'message', 'created_at']), 'paid' => $commerce->paid($tenant->id, $orderId), 'refunds' => DB::table('refunds')->where('tenant_id', $tenant->id)->where('order_id', $orderId)->get(), 'managing' => true, 'role' => $request->attributes->get('membership')->role, 'cashSessions' => DB::table('cash_sessions')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->whereNull('closed_at')->get(), 'whatsapp' => null]);
    }
}
