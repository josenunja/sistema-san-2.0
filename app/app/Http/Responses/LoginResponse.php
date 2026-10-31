<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\LoginResponse as LoginContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;

class LoginResponse implements LoginContract, TwoFactorLoginResponse
{
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }
        if ($request->session()->has('url.intended')) {
            $intendedPath = parse_url((string) $request->session()->get('url.intended'), PHP_URL_PATH);
            if (in_array($intendedPath, ['/settings', '/settings/profile', '/dashboard'], true)
                && ! $request->user()->two_factor_confirmed_at
                && DB::table('memberships')->join('tenants', 'tenants.id', '=', 'memberships.tenant_id')
                    ->where('memberships.user_id', $request->user()->id)->where('tenants.active', true)->exists()) {
                $request->session()->forget('url.intended');

                return redirect('/settings/security');
            }

            return redirect()->intended('/dashboard');
        }
        $slug = $request->session()->pull('login_store');
        if (is_string($slug) && DB::table('tenants')->where('slug', $slug)->where('active', true)->exists()) {
            return redirect('/tienda/'.$slug.'/cuenta');
        }
        $members = DB::table('memberships')->join('tenants', 'tenants.id', '=', 'memberships.tenant_id')->where('memberships.user_id', $request->user()->id)->where('tenants.active', true)->get(['tenants.slug', 'memberships.role']);
        if ($members->count() === 1) {
            if (! $request->user()->two_factor_confirmed_at) {
                return redirect('/settings/security');
            }
            $member = $members->sole();
            if ($member->role === 'catalog') {
                return redirect('/gestion/'.$member->slug);
            }
            $section = match ($member->role) {
                'cashier' => 'pos', 'warehouse' => 'inventario', default => 'resumen'
            };

            return redirect('/gestion/'.$member->slug.'/operacion/'.$section);
        }

        return redirect('/dashboard');
    }
}
