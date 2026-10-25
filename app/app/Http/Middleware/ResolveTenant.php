<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = DB::table('tenants')->where('slug', $request->route('slug'))->first();
        abort_unless($tenant !== null, 404);
        abort_unless($tenant->active || $request->routeIs('manage.offline', 'manage.offline.sync', 'manage.offline.reconcile', 'manage.offline.review'), 404);
        if ($request->routeIs('manage.*')) {
            $membership = DB::table('memberships')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->first();
            abort_unless($membership !== null, 403);
            $request->attributes->set('membership', $membership);
            if ($request->routeIs('manage.catalog', 'manage.products.*')) {
                abort_unless(in_array($membership->role, ['owner', 'catalog'], true), 403);
            }
            if (! $request->user()->two_factor_secret || ! $request->user()->two_factor_confirmed_at) {
                return redirect('/settings/security');
            }
        }

        return DB::transaction(function () use ($request, $next, $tenant) {
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant->id]);
            $request->attributes->set('tenant', $tenant);

            return $next($request);
        });
    }
}
