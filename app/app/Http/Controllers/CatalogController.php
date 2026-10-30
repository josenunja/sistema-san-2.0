<?php

namespace App\Http\Controllers;

use App\Services\BusinessLimits;
use App\Services\Commerce;
use App\Services\ProductImages;
use App\Services\ScheduledPrices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $storeSettings = json_decode($tenant->settings, true) ?: [];
        $storeName = $preview ? ($storeSettings['store_name'] ?? $tenant->name) : $tenant->name;
        $managing = $request->routeIs('manage.*') && ! $preview;
        $search = mb_substr((string) $request->query('q', ''), 0, 100);
        $category = mb_substr((string) $request->query('category', ''), 0, 100);
        $section = trim((string) $request->query('section', ''));
        if (! in_array($section, ['', 'women', 'men', 'kids', 'trends', 'promotions'], true)) {
            $section = '';
        }
        $audience = in_array($section, ['women', 'men', 'kids'], true) ? $section : null;
        $products = DB::table('products')->where('tenant_id', $tenant->id)
            ->when(! $managing, fn ($q) => $q->where('published', true)->where('archived', false))
            ->when($search !== '', fn ($q) => $q->where('name', 'ilike', '%'.$search.'%'))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->when($audience !== null, fn ($q) => $q->where('audience', $audience))
            ->when($section === 'trends', fn ($q) => $q->where('trending', true))
            ->when($section === 'promotions', fn ($q) => app(ScheduledPrices::class)->promotions($q))
            ->orderBy('name')->paginate(20, ['id', 'sku', 'name', 'price_minor', 'compare_at_minor', 'published', 'archived', 'barcode', 'minimum_stock', 'category', 'audience', 'trending', 'variant_group', 'size', 'color', 'description', DB::raw('(image_path IS NOT NULL) AS has_image')])->withQueryString();
        if (! $managing) {
            app(ScheduledPrices::class)->decorate((int) $tenant->id, $products->getCollection(), 'web');
        }
        $products->through(function ($product) use ($tenant) {
            $product->image_url = $product->has_image ? '/tienda/'.$tenant->slug.'/producto/'.$product->id.'/foto' : null;

            return $product;
        });

        $importPreview = $request->session()->get('import.'.$tenant->id);
        $customer = $request->user()
            ? DB::table('customers')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->first(['name', 'phone', 'email', 'address'])
            : null;
        $featuredCoupon = null;
        if (! empty($storeSettings['featured_coupon_code'])) {
            $featuredCoupon = DB::table('coupons')
                ->where('tenant_id', $tenant->id)
                ->where('code', $storeSettings['featured_coupon_code'])
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now())
                ->whereColumn('used', '<', 'usage_limit')
                ->first(['code', 'percent', 'amount_minor', 'minimum_minor', 'ends_at']);
        }

        return Inertia::render('Catalog', [
            'tenant' => ['name' => $storeName, 'slug' => $tenant->slug],
            'products' => $products, 'managing' => $managing, 'search' => $search,
            'preview' => $preview,
            'role' => $managing ? $request->attributes->get('membership')->role : null,
            'category' => $category,
            'section' => $section,
            'categories' => DB::table('products')->where('tenant_id', $tenant->id)->when(! $managing, fn ($q) => $q->where('published', true)->where('archived', false))->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category'),
            'importPreview' => $managing && is_array($importPreview) ? array_intersect_key($importPreview, array_flip(['token', 'count', 'sample'])) : null,
            'warehouses' => DB::table('warehouses')->where('tenant_id', $tenant->id)->get(['id', 'name', 'branch']),
            'settings' => [
                ...array_intersect_key($storeSettings, array_flip([
                    'tagline', 'description', 'theme', 'storefront_theme', 'business_type',
                    'location', 'contact_email', 'whatsapp', 'delivery_minor',
                    'instagram_url', 'facebook_url', 'tiktok_url', 'primary_color', 'accent_color',
                    'section_order', 'business_hours', 'delivery_methods', 'courier_minor', 'shipping_minor',
                ])),
                'logo_url' => isset($storeSettings['logo_path'])
                    ? ($preview
                        ? '/gestion/'.$tenant->slug.'/marca/'.$request->route('versionId')
                        : '/tienda/'.$tenant->slug.'/marca')
                    : null,
                'banner_url' => isset($storeSettings['banner_path'])
                    ? ($preview
                        ? '/gestion/'.$tenant->slug.'/portada/'.$request->route('versionId')
                        : '/tienda/'.$tenant->slug.'/portada')
                    : null,
                'custom_domain' => $tenant->custom_domain,
                'marketplace_url' => $tenant->custom_domain !== null && strtolower($request->getHost()) === strtolower($tenant->custom_domain)
                    ? rtrim((string) config('app.url'), '/').'/'
                    : '/',
            ],
            'availability' => DB::table('stock_positions')->where('tenant_id', $tenant->id)->selectRaw('product_id, sum(physical - reserved - blocked - offline) as available')->groupBy('product_id')->get(),
            'customer' => $customer,
            'deliveryZones' => DB::table('delivery_zones')->where('tenant_id', $tenant->id)->where('enabled', true)->orderBy('name')->get(['id', 'method', 'name', 'address', 'fee_minor']),
            'deliverySlots' => DB::table('delivery_slots')->where('tenant_id', $tenant->id)->where('enabled', true)->where('starts_at', '>', now())->orderBy('starts_at')->limit(200)->get(['id', 'method', 'starts_at', 'ends_at']),
            'featuredCoupon' => $featuredCoupon,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');
        DB::select('SELECT pg_advisory_xact_lock(?)', [$tenant->id]);
        $limit = app(BusinessLimits::class)->limit((int) $tenant->id, 'product');
        if (DB::table('products')->where('tenant_id', $tenant->id)->count() >= $limit) {
            app(Commerce::class)->fail('Se alcanzó el límite de productos del plan.');
        }
        $data = $request->validate([
            'sku' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('products', 'sku')->where('tenant_id', $tenant->id)],
            'name' => ['required', 'string', 'max:160'],
            'price_minor' => ['required', 'integer', 'min:0', 'max:999999999'],
            'published' => ['required', 'boolean'], 'archived' => 'sometimes|boolean', 'minimum_stock' => 'sometimes|integer|min:0|max:1000000', 'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products', 'barcode')->where('tenant_id', $tenant->id)],
            'category' => ['nullable', 'string', 'max:100'],
            'audience' => ['sometimes', Rule::in(['women', 'men', 'kids', 'unisex'])],
            'trending' => ['sometimes', 'boolean'],
            'compare_at_minor' => ['nullable', 'integer', 'min:1', 'max:999999999', 'gt:price_minor'],
            'variant_group' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:3000'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);
        foreach (['category', 'variant_group', 'size', 'color', 'description'] as $field) {
            $data[$field] ??= '';
        }
        $data['audience'] ??= 'unisex';
        $data['trending'] ??= false;
        $data['sku'] ??= 'PRD-'.strtoupper((string) Str::ulid());
        unset($data['image']);
        DB::transaction(function () use ($data, $tenant, $request) {
            $id = DB::table('products')->insertGetId([...$data, 'tenant_id' => $tenant->id]);
            $file = $request->file('image');
            if ($file instanceof UploadedFile) {
                app(ProductImages::class)->store((int) $tenant->id, (int) $id, $file);
            }
            app(Commerce::class)->audit($tenant->id, 'product_created');
        });

        return back()->with('success', 'Producto guardado. Las existencias se registran desde Inventario por el propietario o el responsable de almacén.');
    }

    public function product(Request $request, string $slug, int $productId): Response
    {
        $tenant = $request->attributes->get('tenant');
        $product = DB::table('products')->where('tenant_id', $tenant->id)->where('id', $productId)->first(['id', 'name', 'sku', 'price_minor', 'compare_at_minor', 'category', 'audience', 'trending', 'variant_group', 'size', 'color', 'description', 'published', 'archived']);
        abort_unless($product !== null, 404);
        if (! $product->published || $product->archived) {
            abort_unless($request->user()?->two_factor_confirmed_at && DB::table('memberships')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->whereIn('role', ['owner', 'catalog'])->exists(), 404);
        }
        $images = DB::table('product_images')->where('tenant_id', $tenant->id)->where('product_id', $productId)->orderBy('sort_order')->orderBy('id')->pluck('id')->map(fn ($id) => '/tienda/'.$slug.'/producto/'.$productId.'/foto/'.$id);
        $variants = $product->variant_group === '' ? collect() : DB::table('products')->where('tenant_id', $tenant->id)->where('variant_group', $product->variant_group)->where('published', true)->where('archived', false)->orderBy('size')->orderBy('color')->orderBy('name')->get(['id', 'name', 'price_minor', 'size', 'color']);
        $variantAvailability = DB::table('stock_positions')->where('tenant_id', $tenant->id)->whereIn('product_id', $variants->pluck('id'))->selectRaw('product_id, sum(physical-reserved-blocked-offline) as available')->groupBy('product_id')->pluck('available', 'product_id');
        app(ScheduledPrices::class)->decorate((int) $tenant->id, collect([$product]), 'web');
        app(ScheduledPrices::class)->decorate((int) $tenant->id, $variants, 'web');
        $variants->each(function ($variant) use ($variantAvailability): void {
            $variant->available = (int) ($variantAvailability[$variant->id] ?? 0);
        });

        return Inertia::render('Product', ['tenant' => ['name' => $tenant->name, 'slug' => $slug], 'product' => $product, 'images' => $images, 'variants' => $variants, 'available' => (int) DB::table('stock_positions')->where('tenant_id', $tenant->id)->where('product_id', $productId)->selectRaw('COALESCE(SUM(physical-reserved-blocked-offline),0) AS units')->value('units')]);
    }
}
