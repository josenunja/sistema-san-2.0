<?php

namespace App\Http\Controllers;

use App\Services\Commerce;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function scope(Request $request, string $slug, int $memberId, Commerce $commerce): RedirectResponse
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);
        $tenant = (int) $request->attributes->get('tenant')->id;
        $data = $request->validate(['warehouse_ids' => 'present|nullable|array|max:100', 'warehouse_ids.*' => 'integer|distinct']);
        DB::select('SELECT pg_advisory_xact_lock(?)', [$tenant]);
        $member = DB::table('memberships')->where('tenant_id', $tenant)->where('user_id', $memberId)->first();
        abort_unless($member !== null && in_array($member->role, ['cashier', 'warehouse'], true), 422);
        if (DB::table('offline_devices')->where('tenant_id', $tenant)->where('user_id', $memberId)->whereNull('reconciled_at')->exists() || DB::table('cash_sessions')->where('tenant_id', $tenant)->where('user_id', $memberId)->whereNull('closed_at')->exists()) {
            $commerce->fail('Cierra la caja y concilia los dispositivos antes de cambiar los almacenes autorizados.');
        }
        $ids = $data['warehouse_ids'];
        if ($ids !== null) {
            abort_unless(DB::table('warehouses')->where('tenant_id', $tenant)->whereIn('id', $ids)->count() === count($ids), 422);
        }
        DB::table('memberships')->where('tenant_id', $tenant)->where('user_id', $memberId)->update(['warehouse_ids' => $ids === null ? null : json_encode(array_map('intval', $ids), JSON_THROW_ON_ERROR)]);
        $commerce->audit($tenant, 'warehouse_permissions_changed', (string) $memberId);

        return back()->with('success', 'Almacenes autorizados actualizados.');
    }

    public function remove(Request $request, string $slug, int $memberId, Commerce $commerce): RedirectResponse
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);
        $tenant = $request->attributes->get('tenant')->id;
        DB::select('SELECT pg_advisory_xact_lock(?)', [$tenant]);
        $member = DB::table('memberships')->where('tenant_id', $tenant)->where('user_id', $memberId)->first();
        abort_unless($member !== null, 404);
        if ($member->role === 'owner' && DB::table('memberships')->where('tenant_id', $tenant)->where('role', 'owner')->count() <= 1) {
            $commerce->fail('La empresa debe conservar al menos un propietario.');
        }
        if (DB::table('offline_devices')->where('tenant_id', $tenant)->where('user_id', $memberId)->whereNull('reconciled_at')->exists()) {
            $commerce->fail('Concilia primero las ventas y asignaciones offline de este miembro.');
        }
        DB::table('memberships')->where('tenant_id', $tenant)->where('user_id', $memberId)->delete();
        $commerce->audit($tenant, 'membership_revoked', (string) $memberId);

        return $memberId === $request->user()->id ? redirect('/dashboard') : back()->with('success', 'Acceso empresarial revocado. El historial se conserva.');
    }
}
