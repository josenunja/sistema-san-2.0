<?php

namespace App\Http\Controllers;

use App\Services\Commerce;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->attributes->get('tenant');
        $preview = $request->routeIs('manage.preview');
        if ($preview) {
            abort_unless($request->attributes->get('membership')->role === 'owner', 403);
            $version = DB::table('storefront_versions')->where('tenant_id', $tenant->id)->where('id', $request->route('versionId'))->first();
            abort_unless($version !== null, 404);
            $tenant->settings = $version->settings;
        }
        $managing = $request->routeIs('manage.*') && ! $preview;
        $search = mb_substr((string) $request->query('q', ''), 0, 100);
        $category = mb_substr((string) $request->query('category', ''), 0, 100);
        $products = DB::table('products')->where('tenant_id', $tenant->id)
            ->when(! $managing, fn ($q) => $q->where('published', true))
            ->when($search !== '', fn ($q) => $q->where('name', 'ilike', '%'.$search.'%'))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->orderBy('name')->paginate(20, ['id', 'sku', 'name', 'price_minor', 'published', 'category', 'variant_group', 'description', DB::raw('(image_path IS NOT NULL) AS has_image')])->withQueryString();
        $products->through(function ($product) use ($tenant) {
            $product->image_url = $product->has_image ? '/tienda/'.$tenant->slug.'/producto/'.$product->id.'/foto' : null;

            return $product;
        });

        $importPreview = $request->session()->get('import.'.$tenant->id);

        return Inertia::render('Catalog', [
            'tenant' => ['name' => $tenant->name, 'slug' => $tenant->slug],
            'products' => $products, 'managing' => $managing, 'search' => $search,
            'preview' => $preview,
            'category' => $category,
            'categories' => DB::table('products')->where('tenant_id', $tenant->id)->when(! $managing, fn ($q) => $q->where('published', true))->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category'),
            'importPreview' => $managing && is_array($importPreview) ? array_intersect_key($importPreview, array_flip(['token', 'count', 'sample'])) : null,
            'warehouses' => DB::table('warehouses')->where('tenant_id', $tenant->id)->get(['id', 'name', 'branch']),
            'settings' => array_intersect_key(json_decode($tenant->settings, true) ?: [], array_flip(['tagline', 'theme', 'delivery_minor'])),
            'availability' => DB::table('stock_positions')->where('tenant_id', $tenant->id)->selectRaw('product_id, sum(physical - reserved - blocked - offline) as available')->groupBy('product_id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');
        DB::select('SELECT pg_advisory_xact_lock(?)', [$tenant->id]);
        $limit = DB::table('plans')->where('slug', $tenant->plan)->value('product_limit');
        if (DB::table('products')->where('tenant_id', $tenant->id)->count() >= $limit) {
            app(Commerce::class)->fail('Se alcanzó el límite de productos del plan.');
        }
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('products', 'sku')->where('tenant_id', $tenant->id)],
            'name' => ['required', 'string', 'max:160'],
            'price_minor' => ['required', 'integer', 'min:0', 'max:999999999'],
            'published' => ['required', 'boolean'],
            'category' => ['nullable', 'string', 'max:100'],
            'variant_group' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);
        foreach (['category', 'variant_group', 'description'] as $field) {
            $data[$field] ??= '';
        }
        DB::table('products')->insert([...$data, 'tenant_id' => $tenant->id]);
        app(Commerce::class)->audit($tenant->id, 'product_created');

        return back();
    }

    public function product(Request $request, string $slug, int $productId): Response
    {
        $tenant = $request->attributes->get('tenant');
        $product = DB::table('products')->where('tenant_id', $tenant->id)->where('id', $productId)->first(['id', 'name', 'sku', 'price_minor', 'category', 'variant_group', 'description', 'published']);
        abort_unless($product !== null, 404);
        if (! $product->published) {
            abort_unless($request->user()?->two_factor_confirmed_at && DB::table('memberships')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('role', 'owner')->exists(), 404);
        }
        $images = DB::table('product_images')->where('tenant_id', $tenant->id)->where('product_id', $productId)->orderBy('id')->pluck('id')->map(fn ($id) => '/tienda/'.$slug.'/producto/'.$productId.'/foto/'.$id);
        $variants = $product->variant_group === '' ? collect() : DB::table('products')->where('tenant_id', $tenant->id)->where('variant_group', $product->variant_group)->where('published', true)->orderBy('name')->get(['id', 'name', 'price_minor']);

        return Inertia::render('Product', ['tenant' => ['name' => $tenant->name, 'slug' => $slug], 'product' => $product, 'images' => $images, 'variants' => $variants, 'available' => (int) DB::table('stock_positions')->where('tenant_id', $tenant->id)->where('product_id', $productId)->selectRaw('COALESCE(SUM(physical-reserved-blocked-offline),0) AS units')->value('units')]);
    }
}
