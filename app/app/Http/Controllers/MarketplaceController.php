<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceController extends Controller
{
    /** @var array<string, string> */
    private const AUDIENCE_SECTIONS = [
        'women' => 'women',
        'men' => 'men',
        'kids' => 'kids',
    ];

    public function index(Request $request): Response|RedirectResponse
    {
        if (config('business.single')) {
            $tenant = $request->attributes->get('instance_tenant');
            if ($tenant === null) {
                return redirect('/instalacion');
            }
            abort_unless($tenant->active, 404);

            return DB::transaction(function () use ($request, $tenant): Response {
                DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant->id]);
                $request->attributes->set('tenant', $tenant);

                return app(CatalogController::class)->index($request);
            });
        }
        $customStorefront = $this->customStorefront($request);
        if ($customStorefront instanceof Response) {
            return $customStorefront;
        }

        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $category = mb_substr(trim((string) $request->query('category', '')), 0, 100);
        $section = trim((string) $request->query('section', ''));
        if (! in_array($section, ['', ...array_keys(self::AUDIENCE_SECTIONS), 'trends', 'promotions'], true)) {
            $section = '';
        }
        $page = max(1, $request->integer('page', 1));
        $perPage = 24;
        $stores = DB::table('tenants')->where('active', true)->orderBy('name')->get(['id', 'name', 'slug']);
        $products = collect();
        $categories = collect();
        $total = 0;

        foreach ($stores as $store) {
            $catalog = $this->catalogForStore((int) $store->id, $search, $category, $section, $page * $perPage);
            $total += $catalog['total'];
            $categories = $categories->merge($catalog['categories']);
            foreach ($catalog['products'] as $product) {
                $products->push([
                    'id' => (int) $product->id,
                    'name' => $product->name,
                    'price_minor' => (int) $product->price_minor,
                    'compare_at_minor' => $product->compare_at_minor === null ? null : (int) $product->compare_at_minor,
                    'category' => $product->category,
                    'audience' => $product->audience,
                    'trending' => (bool) $product->trending,
                    'size' => $product->size,
                    'color' => $product->color,
                    'description' => $product->description,
                    'image_url' => $product->has_image ? '/tienda/'.$store->slug.'/producto/'.$product->id.'/foto' : null,
                    'store' => ['name' => $store->name, 'slug' => $store->slug],
                    'created_at' => $product->created_at,
                ]);
            }
        }

        $products = $products->sortByDesc('created_at')->values();
        $paginated = new LengthAwarePaginator(
            $products->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Welcome', [
            'mode' => 'marketplace',
            'stores' => $stores->map(fn ($store) => ['name' => $store->name, 'slug' => $store->slug]),
            'products' => $paginated,
            'categories' => $categories->filter()->unique()->sort()->values(),
            'search' => $search,
            'category' => $category,
            'section' => $section,
        ]);
    }

    private function customStorefront(Request $request): ?Response
    {
        $host = strtolower($request->getHost());
        $platformHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if (in_array($host, ['', 'localhost', '127.0.0.1', $platformHost], true)) {
            return null;
        }
        $tenant = DB::table('tenants')
            ->where('active', true)
            ->whereRaw('lower(custom_domain) = ?', [$host])
            ->first();
        if ($tenant === null) {
            return null;
        }

        return DB::transaction(function () use ($request, $tenant): Response {
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant->id]);
            $request->attributes->set('tenant', $tenant);

            return app(CatalogController::class)->index($request);
        });
    }

    /**
     * Read one tenant at a time so the public marketplace keeps the same RLS
     * isolation as every individual storefront.
     *
     * @return array{products: Collection<int, \stdClass>, categories: Collection<int, string>, total: int}
     */
    private function catalogForStore(int $tenant, string $search, string $category, string $section, int $limit): array
    {
        return DB::transaction(function () use ($tenant, $search, $category, $section, $limit) {
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant]);

            $audience = self::AUDIENCE_SECTIONS[$section] ?? null;
            $query = DB::table('products')
                ->where('tenant_id', $tenant)
                ->where('published', true)
                ->when($search !== '', fn ($query) => $query->where('name', 'ilike', '%'.$search.'%'))
                ->when($category !== '', fn ($query) => $query->where('category', $category))
                ->when($audience !== null, fn ($query) => $query->where('audience', $audience))
                ->when($section === 'trends', fn ($query) => $query->where('trending', true))
                ->when($section === 'promotions', fn ($query) => $query->whereNotNull('compare_at_minor')->whereColumn('compare_at_minor', '>', 'price_minor'));
            $total = (clone $query)->count();
            $products = $query
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get(['id', 'name', 'price_minor', 'compare_at_minor', 'category', 'audience', 'trending', 'size', 'color', 'description', 'created_at', DB::raw('(image_path IS NOT NULL) AS has_image')]);

            $categories = DB::table('products')
                ->where('tenant_id', $tenant)
                ->where('published', true)
                ->where('category', '!=', '')
                ->distinct()
                ->pluck('category')
                ->map(fn ($category): string => (string) $category);

            return ['products' => $products, 'categories' => $categories, 'total' => $total];
        });
    }
}
