<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        // Laravel excludes AJAX visits from the previous URL. Remember actual
        // Inertia navigation so back() works with our no-referrer policy.
        if ($request->isMethod('GET') && $request->header('X-Inertia')
            && ! str_contains(strtolower((string) $request->header('Purpose')), 'prefetch')
            && ! str_contains(strtolower((string) $request->header('Sec-Purpose')), 'prefetch')
            && $response->isSuccessful() && $response->headers->has('X-Inertia')) {
            $request->session()->setPreviousUrl($request->fullUrl());
        }

        return $response;
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'environment' => app()->environment(),
            'singleBusiness' => (bool) config('business.single'),
            'businessAccess' => fn () => $this->businessAccess($request),
            'success' => $request->session()->get('success'),
            'operationResult' => $request->session()->get('operation_result'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /** @return array{name:string,slug:string,role:string,storeUrl:string,manageUrl:string,requiresMfa:bool}|null */
    private function businessAccess(Request $request): ?array
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }
        $query = DB::table('memberships')->join('tenants', 'tenants.id', '=', 'memberships.tenant_id')
            ->where('memberships.user_id', $user->id)->where('tenants.active', true);
        if (config('business.single')) {
            $tenant = $request->attributes->get('instance_tenant');
            if (! $tenant) {
                return null;
            }
            $query->where('tenants.id', $tenant->id);
        }
        $store = $query->orderBy('tenants.name')->first(['tenants.name', 'tenants.slug', 'memberships.role']);
        if (! $store) {
            return null;
        }
        $section = match ($store->role) {
            'cashier' => '/operacion/pos', 'warehouse' => '/operacion/inventario', 'catalog' => '', default => '/operacion/resumen',
        };

        return ['name' => $store->name, 'slug' => $store->slug, 'role' => $store->role,
            'storeUrl' => '/tienda/'.$store->slug, 'manageUrl' => '/gestion/'.$store->slug.$section,
            'requiresMfa' => ! $user->two_factor_secret || ! $user->two_factor_confirmed_at];
    }
}
