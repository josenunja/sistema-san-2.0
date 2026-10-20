<?php

namespace App\Http\Controllers;

use App\Services\Commerce;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoreOnboardingController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $this->owner($request);
        $tenant = $request->attributes->get('tenant');
        $settings = $this->settings($tenant->settings);
        $publicSettings = array_intersect_key($settings, array_flip([
            'store_name', 'business_type', 'tagline', 'description', 'whatsapp',
            'location', 'contact_email', 'storefront_theme', 'logo_path', 'banner_path',
            'instagram_url', 'facebook_url', 'tiktok_url', 'primary_color', 'accent_color',
            'section_order', 'business_hours', 'delivery_methods', 'featured_coupon_code',
            'onboarding_completed_at',
        ]));
        $publicSettings['custom_domain'] = $tenant->custom_domain;
        $publicSettings['delivery_methods'] ??= [
            'pickup',
            ...(isset($settings['delivery_minor']) ? ['delivery'] : []),
        ];
        foreach (['delivery', 'courier', 'shipping'] as $method) {
            $publicSettings[$method.'_price'] = isset($settings[$method.'_minor'])
                ? number_format(((int) $settings[$method.'_minor']) / 100, 2, '.', '')
                : '';
        }

        return Inertia::render('StoreOnboarding', [
            'tenant' => ['name' => $tenant->name, 'slug' => $slug],
            'settings' => $publicSettings,
            'logoUrl' => isset($settings['logo_path']) ? "/tienda/{$slug}/marca" : null,
            'bannerUrl' => isset($settings['banner_path']) ? "/tienda/{$slug}/portada" : null,
        ]);
    }

    public function store(Request $request, string $slug, Commerce $commerce): RedirectResponse
    {
        $this->owner($request);
        $tenant = $request->attributes->get('tenant');
        $domain = strtolower(trim((string) $request->input('custom_domain', '')));
        $request->merge(['custom_domain' => $domain === '' ? null : $domain]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'business_type' => ['required', Rule::in(['fashion', 'footwear', 'accessories', 'mixed', 'other'])],
            'tagline' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:1000'],
            'whatsapp' => ['nullable', 'regex:/^[1-9][0-9]{7,14}$/'],
            'location' => ['nullable', 'string', 'max:300'],
            'contact_email' => ['nullable', 'email', 'max:254'],
            'instagram_url' => ['nullable', 'url', 'starts_with:https://', 'max:500'],
            'facebook_url' => ['nullable', 'url', 'starts_with:https://', 'max:500'],
            'tiktok_url' => ['nullable', 'url', 'starts_with:https://', 'max:500'],
            'primary_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'section_order' => ['sometimes', 'array', 'size:6'],
            'section_order.*' => ['required', 'distinct', Rule::in(['all', 'women', 'men', 'kids', 'trends', 'promotions'])],
            'business_hours' => ['sometimes', 'array', 'size:7'],
            'business_hours.*.day' => ['required', 'distinct', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'business_hours.*.open' => ['required_unless:business_hours.*.closed,true', 'nullable', 'date_format:H:i'],
            'business_hours.*.close' => ['required_unless:business_hours.*.closed,true', 'nullable', 'date_format:H:i'],
            'business_hours.*.closed' => ['required', 'boolean'],
            'delivery_methods' => ['sometimes', 'array', 'min:1'],
            'delivery_methods.*' => ['required', 'distinct', Rule::in(['pickup', 'delivery', 'courier', 'shipping'])],
            'delivery_price' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999.99'],
            'courier_price' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999.99'],
            'shipping_price' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999.99'],
            'custom_domain' => [
                'nullable', 'max:253', 'regex:/^(?=.{1,253}$)(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/',
                Rule::unique('tenants', 'custom_domain')->ignore($tenant->id),
            ],
            'featured_coupon_code' => ['nullable', 'regex:/^[A-Za-z0-9_-]{3,40}$/'],
            'storefront_theme' => ['required', Rule::in([
                'classic', 'urban', 'elegant', 'feminine', 'pastel', 'glamour',
                'sport', 'footwear', 'minimal', 'vintage',
            ])],
            'publication' => ['required', Rule::in(['draft', 'publish'])],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'banner' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'confirmation' => ['exclude_if:publication,draft', 'accepted'],
        ]);

        foreach ($data['business_hours'] ?? [] as $index => $hours) {
            if (! $hours['closed'] && $hours['open'] >= $hours['close']) {
                throw ValidationException::withMessages([
                    "business_hours.{$index}.close" => 'La hora de cierre debe ser posterior a la apertura.',
                ]);
            }
        }
        foreach (['delivery', 'courier', 'shipping'] as $method) {
            if (in_array($method, $data['delivery_methods'] ?? [], true) && ($data[$method.'_price'] ?? '') === '') {
                throw ValidationException::withMessages([
                    $method.'_price' => 'Indica la tarifa para este método de entrega.',
                ]);
            }
        }
        if ($data['custom_domain'] !== null) {
            $reserved = array_filter([
                parse_url((string) config('app.url'), PHP_URL_HOST),
                'localhost', '127.0.0.1',
            ]);
            if (in_array($data['custom_domain'], $reserved, true)) {
                throw ValidationException::withMessages(['custom_domain' => 'Ese dominio está reservado por la plataforma.']);
            }
        }
        if (! empty($data['featured_coupon_code'])) {
            $data['featured_coupon_code'] = strtoupper($data['featured_coupon_code']);
            if (! DB::table('coupons')->where('tenant_id', $tenant->id)->where('code', $data['featured_coupon_code'])->exists()) {
                throw ValidationException::withMessages(['featured_coupon_code' => 'Crea primero este cupón en Promociones.']);
            }
        }

        $settings = $this->settings($tenant->settings);
        if ($request->file('logo') instanceof UploadedFile) {
            $settings['logo_path'] = $this->storeBrandImage($request->file('logo'), (int) $tenant->id, 'logo', 720, 10000000, $commerce);
        }
        if ($request->file('banner') instanceof UploadedFile) {
            $settings['banner_path'] = $this->storeBrandImage($request->file('banner'), (int) $tenant->id, 'banner', 2200, 16000000, $commerce);
        }

        $publish = $data['publication'] === 'publish';
        foreach (['delivery', 'courier', 'shipping'] as $method) {
            $priceKey = $method.'_price';
            if (! array_key_exists($priceKey, $data)) {
                continue;
            }
            $price = $data[$priceKey];
            if ($price === null || $price === '') {
                unset($settings[$method.'_minor']);
            } else {
                $settings[$method.'_minor'] = $this->minor((string) $price);
            }
        }
        unset(
            $data['publication'], $data['confirmation'], $data['logo'], $data['banner'],
            $data['delivery_price'], $data['courier_price'], $data['shipping_price'], $data['custom_domain'],
        );
        $settings = [
            ...$settings,
            ...$data,
            'store_name' => $data['name'],
            'onboarding_completed_at' => $publish
                ? ($settings['onboarding_completed_at'] ?? now()->toIso8601String())
                : ($settings['onboarding_completed_at'] ?? null),
        ];
        unset($settings['name']);
        $json = json_encode($settings, JSON_THROW_ON_ERROR);

        DB::select('SELECT pg_advisory_xact_lock(?)', [(int) $tenant->id]);
        $version = DB::table('storefront_versions')->insertGetId([
            'tenant_id' => $tenant->id,
            'actor_id' => $request->user()->id,
            'settings' => $json,
            'published_at' => $publish ? now() : null,
        ]);
        if ($publish) {
            DB::table('tenants')->where('id', $tenant->id)->update([
                'name' => $data['name'],
                'settings' => $json,
                'custom_domain' => $domain === '' ? null : $domain,
            ]);
        }
        $commerce->audit((int) $tenant->id, $publish ? 'storefront_onboarding_published' : 'storefront_onboarding_drafted', (string) $version);

        return $publish
            ? redirect("/tienda/{$slug}")->with('success', 'Tu tienda ya está publicada con su nueva identidad.')
            : redirect("/gestion/{$slug}/vista-previa/{$version}")->with('success', 'Borrador guardado. Esta vista solo es visible para ti.');
    }

    public function logo(Request $request, string $slug): StreamedResponse
    {
        $tenant = $request->attributes->get('tenant');
        $settings = $this->settings($tenant->settings);

        return $this->logoResponse((int) $tenant->id, $settings['logo_path'] ?? null);
    }

    public function previewLogo(Request $request, string $slug, int $versionId): StreamedResponse
    {
        $this->owner($request);
        $tenant = $request->attributes->get('tenant');
        $version = DB::table('storefront_versions')
            ->where('tenant_id', $tenant->id)
            ->where('id', $versionId)
            ->first();
        abort_unless($version !== null, 404);
        $settings = $this->settings($version->settings);

        return $this->logoResponse((int) $tenant->id, $settings['logo_path'] ?? null);
    }

    public function banner(Request $request, string $slug): StreamedResponse
    {
        $tenant = $request->attributes->get('tenant');
        $settings = $this->settings($tenant->settings);

        return $this->brandImageResponse((int) $tenant->id, $settings['banner_path'] ?? null);
    }

    public function previewBanner(Request $request, string $slug, int $versionId): StreamedResponse
    {
        $this->owner($request);
        $tenant = $request->attributes->get('tenant');
        $version = DB::table('storefront_versions')->where('tenant_id', $tenant->id)->where('id', $versionId)->first();
        abort_unless($version !== null, 404);
        $settings = $this->settings($version->settings);

        return $this->brandImageResponse((int) $tenant->id, $settings['banner_path'] ?? null);
    }

    private function owner(Request $request): void
    {
        abort_unless($request->attributes->get('membership')?->role === 'owner', 403);
    }

    /** @return array<string, mixed> */
    private function settings(mixed $settings): array
    {
        if (is_array($settings)) {
            return $settings;
        }

        return json_decode((string) $settings, true) ?: [];
    }

    private function storeBrandImage(UploadedFile $file, int $tenant, string $kind, int $edge, int $pixels, Commerce $commerce): string
    {
        $size = getimagesize($file->getRealPath());
        if (! $size || $pixels < $size[0] * $size[1] || max($size[0], $size[1]) > 6000) {
            $commerce->fail('La imagen es demasiado grande para la tienda.');
        }
        if (! extension_loaded('gd')) {
            $commerce->fail('El entorno no tiene disponible el procesador de imágenes GD.');
        }
        $bytes = file_get_contents($file->getRealPath());
        $image = $bytes === false ? false : imagecreatefromstring($bytes);
        if (! $image) {
            $commerce->fail('El contenido del logo no es una imagen válida.');
        }
        $ratio = min(1, $edge / max($size[0], $size[1]));
        $scaled = imagescale($image, max(1, (int) ($size[0] * $ratio)), max(1, (int) ($size[1] * $ratio)));
        if (! $scaled) {
            imagedestroy($image);
            $commerce->fail('No se pudo procesar el logo.');
        }
        ob_start();
        imagewebp($scaled, null, 88);
        $content = ob_get_clean();
        imagedestroy($image);
        imagedestroy($scaled);
        if (! $content) {
            $commerce->fail('No se pudo guardar el logo procesado.');
        }
        $path = $tenant.'/branding/'.$kind.'-'.Str::uuid().'.webp';
        Storage::disk('local')->put($path, $content);

        return $path;
    }

    private function logoResponse(int $tenant, mixed $path): StreamedResponse
    {
        return $this->brandImageResponse($tenant, $path);
    }

    private function brandImageResponse(int $tenant, mixed $path): StreamedResponse
    {
        abort_unless(is_string($path) && str_starts_with($path, $tenant.'/branding/') && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function minor(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
