<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogOperationsTest extends TestCase
{
    private int $tenant;

    private int $product;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('Isolated testing database required.');
        }
        DB::beginTransaction();
        $this->withoutVite();
        Storage::fake('local');
        $this->tenant = DB::table('tenants')->insertGetId(['name' => 'Catalog test', 'slug' => 'catalog-tools-test']);
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenant]);
        $this->owner = User::factory()->create();
        $this->owner->forceFill(['two_factor_secret' => encrypt('TEST'), 'two_factor_confirmed_at' => now()])->save();
        DB::table('memberships')->insert(['tenant_id' => $this->tenant, 'user_id' => $this->owner->id, 'role' => 'owner']);
        $this->actingAs($this->owner);
        $this->product = DB::table('products')->insertGetId(['tenant_id' => $this->tenant, 'sku' => 'SKU', 'name' => 'Product', 'price_minor' => 100, 'published' => true]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_csv_preview_is_read_only_then_confirm_is_exact_and_repeatable(): void
    {
        $csv = "sku,nombre,precio,categoria,grupo,publicado\nNEW,=1+1,10.50,Prueba,Grupo,1\n";
        $this->post('/gestion/catalog-tools-test/importar', ['file' => UploadedFile::fake()->createWithContent('catalog.csv', $csv)])->assertRedirect();
        $this->assertSame(1, DB::table('products')->count());
        $preview = session('import.'.$this->tenant);
        $this->assertIsArray($preview);
        $this->post('/gestion/catalog-tools-test/importar/confirmar', ['token' => $preview['token']])->assertRedirect();
        $this->post('/gestion/catalog-tools-test/importar/confirmar', ['token' => $preview['token']])->assertRedirect();
        $this->assertSame(2, DB::table('products')->count());
        $this->assertSame(1050, (int) DB::table('products')->where('sku', 'NEW')->value('price_minor'));
        $export = $this->get('/gestion/catalog-tools-test/exportar/productos')->assertOk();
        $this->assertStringContainsString("'=1+1", $export->streamedContent());
    }

    public function test_store_owner_and_platform_owner_can_publish_marketplace_sections_for_their_store(): void
    {
        $this->owner->forceFill(['platform_owner' => true])->save();

        $this->post('/gestion/catalog-tools-test/productos', [
            'sku' => 'URBAN-W',
            'name' => 'Casaca urbana mujer',
            'price_minor' => 9900,
            'compare_at_minor' => 13900,
            'published' => true,
            'category' => 'Casacas',
            'audience' => 'women',
            'trending' => true,
        ])->assertRedirect();

        $product = DB::table('products')->where('sku', 'URBAN-W')->firstOrFail();
        $this->assertSame('women', $product->audience);
        $this->assertTrue($product->trending);
        $this->assertSame(13900, (int) $product->compare_at_minor);
        $this->get('/?section=promotions')->assertOk()->assertSee('Casaca urbana mujer');
    }

    public function test_size_color_and_stock_remain_independent_for_each_variant(): void
    {
        foreach ([['M', 'Negro', 'VAR-M'], ['L', 'Azul', 'VAR-L']] as [$size, $color, $sku]) {
            $this->post('/gestion/catalog-tools-test/productos', [
                'sku' => $sku,
                'name' => 'Polo esencial',
                'price_minor' => 5900,
                'published' => true,
                'category' => 'Polos',
                'variant_group' => 'Polo esencial',
                'size' => $size,
                'color' => $color,
            ])->assertRedirect();
        }

        $medium = DB::table('products')->where('sku', 'VAR-M')->firstOrFail();
        $large = DB::table('products')->where('sku', 'VAR-L')->firstOrFail();
        $warehouse = DB::table('warehouses')->insertGetId(['tenant_id' => $this->tenant, 'name' => 'Variantes', 'branch' => 'Principal']);
        DB::table('stock_positions')->insert([
            ['tenant_id' => $this->tenant, 'warehouse_id' => $warehouse, 'product_id' => $medium->id, 'physical' => 7],
            ['tenant_id' => $this->tenant, 'warehouse_id' => $warehouse, 'product_id' => $large->id, 'physical' => 2],
        ]);

        $this->assertSame('M', $medium->size);
        $this->assertSame('Negro', $medium->color);
        $this->get('/tienda/catalog-tools-test/producto/'.$medium->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.size', 'M')
                ->where('product.color', 'Negro')
                ->where('available', 7)
                ->where('variants', fn ($variants) => collect($variants)->contains(fn ($variant) => $variant['id'] === $large->id && $variant['size'] === 'L' && $variant['color'] === 'Azul' && $variant['available'] === 2)));
    }

    public function test_uploaded_photo_is_reencoded_and_served_only_from_its_company(): void
    {
        $this->post('/gestion/catalog-tools-test/productos/'.$this->product.'/foto', ['image' => UploadedFile::fake()->image('photo.jpg', 200, 150)])->assertRedirect();
        $path = DB::table('products')->value('image_path');
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('local')->assertExists($path);
        $this->get('/tienda/catalog-tools-test/producto/'.$this->product.'/foto')->assertOk()->assertHeader('Content-Type', 'image/webp');
        $other = DB::table('tenants')->insertGetId(['name' => 'Other', 'slug' => 'catalog-other-test']);
        $this->get('/tienda/catalog-other-test/producto/'.$this->product.'/foto')->assertNotFound();
    }

    public function test_security_headers_restrict_scripts_and_untrusted_hosts(): void
    {
        $response = $this->get('/tienda/catalog-tools-test')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->get('http://untrusted.invalid/tienda/catalog-tools-test')->assertStatus(400);
    }
}
