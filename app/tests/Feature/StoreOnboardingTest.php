<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreOnboardingTest extends TestCase
{
    private int $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('Isolated database required.');
        }
        $this->withoutVite();
        DB::beginTransaction();
        $this->tenant = DB::table('tenants')->insertGetId(['name' => 'Tienda inicial', 'slug' => 'onboarding-test']);
        $this->owner = User::factory()->create();
        $this->owner->forceFill(['two_factor_secret' => encrypt('TEST'), 'two_factor_confirmed_at' => now()])->save();
        DB::table('memberships')->insert(['tenant_id' => $this->tenant, 'user_id' => $this->owner->id, 'role' => 'owner']);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_owner_can_open_store_designer(): void
    {
        $this->actingAs($this->owner)
            ->get('/gestion/onboarding-test/configurar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StoreOnboarding')
                ->where('tenant.slug', 'onboarding-test'));
    }

    public function test_owner_can_publish_branded_theme_without_changing_other_settings(): void
    {
        DB::table('tenants')->where('id', $this->tenant)->update(['settings' => json_encode(['delivery_minor' => 900])]);

        $this->actingAs($this->owner)
            ->post('/gestion/onboarding-test/configurar', [
                'name' => 'Distrito Norte',
                'business_type' => 'fashion',
                'tagline' => 'Moda con carácter propio.',
                'description' => 'Prendas urbanas seleccionadas en Lima.',
                'whatsapp' => '51987654321',
                'location' => 'Miraflores, Lima',
                'contact_email' => 'ventas@example.test',
                'storefront_theme' => 'urban',
                'publication' => 'publish',
                'confirmation' => true,
            ])
            ->assertRedirect('/tienda/onboarding-test');

        $tenant = DB::table('tenants')->where('id', $this->tenant)->first();
        $settings = json_decode($tenant->settings, true);
        $this->assertSame('Distrito Norte', $tenant->name);
        $this->assertSame('urban', $settings['storefront_theme']);
        $this->assertSame(900, $settings['delivery_minor']);
        $this->assertNotEmpty($settings['onboarding_completed_at']);
        $this->assertSame(1, DB::table('storefront_versions')->where('tenant_id', $this->tenant)->whereNotNull('published_at')->count());

        $this->get('/tienda/onboarding-test')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalog')
                ->where('tenant.name', 'Distrito Norte')
                ->where('settings.storefront_theme', 'urban')
                ->where('settings.marketplace_url', '/'));
    }

    public function test_draft_creates_private_preview_without_replacing_public_identity(): void
    {
        $response = $this->actingAs($this->owner)
            ->post('/gestion/onboarding-test/configurar', [
                'name' => 'Nombre todavía privado',
                'business_type' => 'accessories',
                'tagline' => 'Borrador de una nueva identidad.',
                'description' => '',
                'whatsapp' => '',
                'location' => '',
                'contact_email' => '',
                'storefront_theme' => 'elegant',
                'publication' => 'draft',
                'confirmation' => false,
            ]);

        $version = DB::table('storefront_versions')->where('tenant_id', $this->tenant)->first();
        $response->assertRedirect('/gestion/onboarding-test/vista-previa/'.$version->id);
        $this->assertNull($version->published_at);
        $this->assertSame('Tienda inicial', DB::table('tenants')->where('id', $this->tenant)->value('name'));
        $this->actingAs($this->owner)
            ->get('/gestion/onboarding-test/vista-previa/'.$version->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenant.name', 'Nombre todavía privado')
                ->where('settings.storefront_theme', 'elegant'));
    }

    public function test_every_curated_storefront_theme_can_be_saved(): void
    {
        $themes = ['classic', 'urban', 'elegant', 'feminine', 'pastel', 'glamour', 'sport', 'footwear', 'minimal', 'vintage'];

        foreach ($themes as $theme) {
            $this->actingAs($this->owner)
                ->post('/gestion/onboarding-test/configurar', [
                    'name' => 'Tienda '.$theme,
                    'business_type' => 'fashion',
                    'tagline' => 'Identidad visual '.$theme.'.',
                    'description' => '',
                    'whatsapp' => '',
                    'location' => '',
                    'contact_email' => '',
                    'storefront_theme' => $theme,
                    'publication' => 'draft',
                    'confirmation' => false,
                ])
                ->assertRedirect();
        }

        $this->assertSame(10, DB::table('storefront_versions')->where('tenant_id', $this->tenant)->count());
    }

    public function test_owner_can_publish_complete_storefront_customization_and_custom_domain(): void
    {
        Storage::fake('local');
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenant]);
        DB::table('coupons')->insert([
            'tenant_id' => $this->tenant,
            'code' => 'BIENVENIDA10',
            'percent' => 10,
            'usage_limit' => 100,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addWeek(),
        ]);
        $hours = collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
            ->map(fn (string $day) => ['day' => $day, 'open' => '09:00', 'close' => '18:00', 'closed' => $day === 'sunday'])
            ->all();

        $this->actingAs($this->owner)
            ->post('/gestion/onboarding-test/configurar', [
                'name' => 'Boutique Completa',
                'business_type' => 'fashion',
                'tagline' => 'Una tienda con identidad propia.',
                'description' => 'Colecciones y atención personalizada.',
                'whatsapp' => '51987654321',
                'location' => 'Lima, Perú',
                'contact_email' => 'ventas@example.test',
                'instagram_url' => 'https://instagram.com/boutiquecompleta',
                'facebook_url' => 'https://facebook.com/boutiquecompleta',
                'tiktok_url' => 'https://tiktok.com/@boutiquecompleta',
                'primary_color' => '#283618',
                'accent_color' => '#dda15e',
                'section_order' => ['promotions', 'women', 'trends', 'all', 'men', 'kids'],
                'business_hours' => $hours,
                'delivery_methods' => ['pickup', 'delivery', 'courier', 'shipping'],
                'delivery_price' => '8.50',
                'courier_price' => '12.00',
                'shipping_price' => '18.90',
                'custom_domain' => 'boutique.example.test',
                'featured_coupon_code' => 'bienvenida10',
                'storefront_theme' => 'feminine',
                'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
                'banner' => UploadedFile::fake()->image('portada.jpg', 1200, 400),
                'publication' => 'publish',
                'confirmation' => true,
            ])
            ->assertRedirect('/tienda/onboarding-test');

        $tenant = DB::table('tenants')->where('id', $this->tenant)->first();
        $settings = json_decode($tenant->settings, true);
        $this->assertSame('boutique.example.test', $tenant->custom_domain);
        $this->assertSame('#283618', $settings['primary_color']);
        $this->assertSame(['promotions', 'women', 'trends', 'all', 'men', 'kids'], $settings['section_order']);
        $this->assertSame(850, $settings['delivery_minor']);
        $this->assertSame(1200, $settings['courier_minor']);
        $this->assertSame(1890, $settings['shipping_minor']);
        $this->assertSame('BIENVENIDA10', $settings['featured_coupon_code']);
        Storage::disk('local')->assertExists($settings['logo_path']);
        Storage::disk('local')->assertExists($settings['banner_path']);

        $this->get('http://boutique.example.test/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalog')
                ->where('tenant.name', 'Boutique Completa')
                ->where('settings.custom_domain', 'boutique.example.test')
                ->where('featuredCoupon.code', 'BIENVENIDA10'));
    }

    public function test_unknown_storefront_theme_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post('/gestion/onboarding-test/configurar', [
                'name' => 'Tema no permitido',
                'business_type' => 'fashion',
                'tagline' => 'No debe guardarse.',
                'description' => '',
                'whatsapp' => '',
                'location' => '',
                'contact_email' => '',
                'storefront_theme' => 'tema-inyectado',
                'publication' => 'draft',
                'confirmation' => false,
            ])
            ->assertSessionHasErrors('storefront_theme');

        $this->assertSame(0, DB::table('storefront_versions')->where('tenant_id', $this->tenant)->count());
    }

    public function test_non_owner_cannot_configure_store(): void
    {
        $cashier = User::factory()->create();
        $cashier->forceFill(['two_factor_secret' => encrypt('TEST'), 'two_factor_confirmed_at' => now()])->save();
        DB::table('memberships')->insert(['tenant_id' => $this->tenant, 'user_id' => $cashier->id, 'role' => 'cashier']);

        $this->actingAs($cashier)->get('/gestion/onboarding-test/configurar')->assertForbidden();
    }
}
