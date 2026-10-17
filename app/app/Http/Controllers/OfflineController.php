<?php

namespace App\Http\Controllers;

use App\Services\Commerce;
use App\Services\OfflineSales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OfflineController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(in_array($request->attributes->get('membership')->role, ['owner', 'cashier'], true), 403);
        $tenant = $request->attributes->get('tenant');

        return Inertia::render('Offline', ['tenant' => ['name' => $tenant->name, 'slug' => $tenant->slug], 'role' => $request->attributes->get('membership')->role, 'products' => DB::table('products')->where('tenant_id', $tenant->id)->orderBy('name')->limit(500)->get(['id', 'name', 'sku', 'price_minor']), 'cashSessions' => DB::table('cash_sessions')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->whereNull('closed_at')->get(['id']), 'devices' => DB::table('offline_devices')->where('tenant_id', $tenant->id)->when($request->attributes->get('membership')->role !== 'owner', fn ($q) => $q->where('user_id', $request->user()->id))->orderByDesc('created_at')->limit(100)->get()]);
    }

    public function grant(Request $request, Commerce $commerce, OfflineSales $offline): JsonResponse
    {
        abort_unless(in_array($request->attributes->get('membership')->role, ['owner', 'cashier'], true), 403);
        $data = $request->validate(['key' => 'required|uuid', 'name' => 'required|string|max:100', 'cash_session_id' => 'required|integer', 'items' => 'required|array|min:1|max:500', 'items.*.product_id' => 'required|integer', 'items.*.quantity' => 'required|integer|min:1|max:10000']);
        $tenant = $request->attributes->get('tenant');

        return response()->json($commerce->run($tenant->id, $data['key'], 'offline_grant', ['actor' => $request->user()->id, ...$data], fn () => $offline->grant($tenant->id, $data)));
    }

    public function sync(Request $request, Commerce $commerce, OfflineSales $offline): JsonResponse
    {
        abort_unless(in_array($request->attributes->get('membership')->role, ['owner', 'cashier'], true), 403);
        $data = $request->validate(['device_id' => 'required|uuid', 'operation_id' => 'required|uuid', 'sequence' => 'required|integer|min:1|max:1000000', 'captured_at' => 'required|string|max:50', 'total_minor' => 'required|integer|min:0|max:9999999999999', 'items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|integer', 'items.*.quantity' => 'required|integer|min:1|max:10000']);
        $tenant = $request->attributes->get('tenant');

        return response()->json($commerce->run($tenant->id, $data['operation_id'], 'offline_sale', ['actor' => $request->user()->id, ...$data], fn () => $offline->sync($tenant->id, $data)));
    }

    public function reconcile(Request $request, Commerce $commerce, OfflineSales $offline): RedirectResponse
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);
        $data = $request->validate(['key' => 'required|uuid', 'device_id' => 'required|uuid', 'confirmed_empty' => 'accepted', 'last_sequence' => 'required|integer|min:0|max:1000000']);
        $tenant = $request->attributes->get('tenant');
        $commerce->run($tenant->id, $data['key'], 'offline_reconcile', $data, fn () => $offline->reconcile($tenant->id, $data['device_id'], $data['last_sequence']));

        return back()->with('success', 'Dispositivo conciliado. Las unidades no consumidas vuelven a disponibilidad central.');
    }

    public function review(Request $request, Commerce $commerce, OfflineSales $offline): JsonResponse
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);
        $data = $request->validate(['key' => 'required|uuid', 'reason' => 'required|string|min:5|max:300', 'operations' => 'required|array|min:1|max:100', 'operations.*.device_id' => 'required|uuid', 'operations.*.operation_id' => 'required|uuid', 'operations.*.sequence' => 'required|integer|min:1|max:1000000', 'operations.*.captured_at' => 'required|string|max:50', 'operations.*.total_minor' => 'required|integer|min:0|max:9999999999999', 'operations.*.items' => 'required|array|min:1|max:100', 'operations.*.items.*.product_id' => 'required|integer', 'operations.*.items.*.quantity' => 'required|integer|min:1|max:10000']);
        $tenant = $request->attributes->get('tenant');
        $result = $commerce->run($tenant->id, $data['key'], 'offline_review', ['actor' => $request->user()->id, ...$data], function () use ($tenant, $request, $data, $offline, $commerce) {
            foreach ($data['operations'] as $operation) {
                abort_unless(DB::table('offline_devices')->where('tenant_id', $tenant->id)->where('id', $operation['device_id'])->whereNull('reconciled_at')->exists(), 404);
                $existing = DB::table('offline_reviews')->where('tenant_id', $tenant->id)->where('device_id', $operation['device_id'])->where('operation_id', $operation['operation_id'])->first();
                $hash = $offline->operationHash($operation);
                if ($existing) {
                    if (! hash_equals($existing->payload_hash, $hash)) {
                        $commerce->fail('Una operación ya tiene una revisión distinta.');
                    }

continue;
                }
                DB::table('offline_reviews')->insert(['tenant_id' => $tenant->id, 'device_id' => $operation['device_id'], 'operation_id' => $operation['operation_id'], 'payload_hash' => $hash, 'actor_id' => $request->user()->id, 'reason' => $data['reason']]);
            }

            return ['count' => count($data['operations'])];
        });

        return response()->json($result);
    }
}
