<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogOperationsTest extends TestCase
{
    private int $tenant;

    private int $product;

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
        $user = User::factory()->create();
        $user->forceFill(['two_factor_secret' => encrypt('TEST'), 'two_factor_confirmed_at' => now()])->save();
        DB::table('memberships')->insert(['tenant_id' => $this->tenant, 'user_id' => $user->id, 'role' => 'owner']);
        $this->actingAs($user);
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
