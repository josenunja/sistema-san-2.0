<?php

namespace App\Http\Controllers;

use App\Services\Commerce;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
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
