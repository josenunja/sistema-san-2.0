<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SingleBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('business.single')) {
            return $next($request);
        }
        $slug = trim((string) config('business.slug'));
        $query = DB::table('tenants');
        if ($slug !== '') {
            $tenant = $query->where('slug', $slug)->first();
            abort_if($tenant === null && DB::table('tenants')->exists(), 503, 'La empresa configurada no pertenece a esta instalación.');
        } else {
            $tenants = $query->limit(2)->get();
            abort_if($tenants->count() > 1, 503, 'Esta instalación necesita identificar su única empresa en BUSINESS_SLUG.');
            $tenant = $tenants->first();
        }
        $request->attributes->set('instance_tenant', $tenant);
        abort_if($request->routeIs('platform.*'), 404);
        if ($tenant === null) {
            return $next($request);
        }
        $host = strtolower($request->getHost());
        $platformHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $companyHost = strtolower((string) ($tenant->custom_domain ?? ''));
        if (! in_array($host, ['localhost', '127.0.0.1'], true) && $host !== $platformHost && $host !== $companyHost) {
            abort(400);
        }
        if ($request->route('slug') !== null) {
            abort_unless($request->route('slug') === $tenant->slug, 404);
        }
        if ($request->route('tenantId') !== null) {
            abort_unless((int) $request->route('tenantId') === (int) $tenant->id, 404);
        }
        if ($request->routeIs('invitations.store')) {
            abort_unless((int) $request->input('tenant_id') === (int) $tenant->id, 404);
        }
        if ($request->routeIs('invitations.resend', 'invitations.revoke')) {
            abort_unless((int) DB::table('invitations')->where('id', $request->route('id'))->value('tenant_id') === (int) $tenant->id, 404);
        }
        if ($request->isMethod('POST') && $request->is('invitacion')) {
            $inviteTenant = DB::table('invitations')->where('token_hash', hash('sha256', (string) $request->input('token')))->value('tenant_id');
            abort_if($inviteTenant !== null && (int) $inviteTenant !== (int) $tenant->id, 403);
        }

        return $next($request);
    }
}
