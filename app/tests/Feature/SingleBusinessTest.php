<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SingleBusinessTest extends TestCase
{
    private int $own;

    private int $other;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('Isolated database required.');
        }
        DB::beginTransaction();
        $this->withoutVite();
        config(['business.single' => true, 'business.slug' => 'single-bakery']);
        $this->own = DB::table('tenants')->insertGetId(['name' => 'Panadería independiente', 'slug' => 'single-bakery', 'settings' => json_encode(['business_type' => 'bakery'])]);
        $this->other = DB::table('tenants')->insertGetId(['name' => 'Mecánica privada', 'slug' => 'single-mechanic', 'custom_domain' => 'other-single.example.test']);
        $this->owner = User::factory()->create();
        $this->owner->forceFill(['platform_owner' => true, 'two_factor_secret' => encrypt('TEST'), 'two_factor_confirmed_at' => now()])->save();
        foreach ([$this->own, $this->other] as $tenant) {
            DB::table('memberships')->insert(['tenant_id' => $tenant, 'user_id' => $this->owner->id, 'role' => 'owner']);
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant]);
            DB::table('products')->insert(['tenant_id' => $tenant, 'sku' => 'ONLY-'.$tenant, 'name' => $tenant === $this->own ? 'Pan artesanal' : 'Producto de otra empresa', 'price_minor' => 150, 'published' => true]);
        }
        $this->actingAs($this->owner)->withSession(['auth.password_confirmed_at' => time()]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_home_is_the_company_catalog_and_never_a_shared_marketplace(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Catalog')->where('tenant.name', 'Panadería independiente')->where('singleBusiness', true)
            ->where('settings.business_type', 'bakery')->where('products.data.0.name', 'Pan artesanal'))
            ->assertDontSee('Producto de otra empresa')->assertDontSee('Mecánica privada');
        $this->get('/dashboard')->assertRedirect('/gestion/single-bakery/operacion/resumen');
        $this->get('/plataforma')->assertNotFound();
        $this->post('/plataforma', ['name' => 'No crear segunda empresa'])->assertNotFound();
    }

    public function test_foreign_urls_and_hostnames_stay_blocked_even_for_a_global_owner(): void
    {
        foreach (['/tienda/single-mechanic', '/gestion/single-mechanic', '/gestion/single-mechanic/exportar/productos', '/invitaciones/tienda/'.$this->other] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->post('/gestion/single-mechanic/productos', ['name' => 'Forbidden', 'price_minor' => 100, 'published' => true])->assertNotFound();
        $this->post('/invitaciones', ['tenant_id' => $this->other, 'email' => 'foreign@example.test', 'role' => 'owner'])->assertNotFound();
        $token = Str::random(64);
        $id = (string) Str::uuid();
        DB::table('invitations')->insert(['id' => $id, 'tenant_id' => $this->other, 'email' => 'foreign@example.test', 'role' => 'owner', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()]);
        $this->post('/invitaciones/'.$id.'/reenviar')->assertNotFound();
        $this->post('/invitaciones/'.$id.'/revocar')->assertNotFound();
        $this->post('/invitacion', ['token' => $token, 'name' => 'Foreign', 'password' => 'synthetic-password-123'])->assertForbidden();
        $this->assertNull(DB::table('invitations')->where('id', $id)->value('accepted_at'));
        $this->get('http://other-single.example.test/')->assertStatus(400);
    }

    public function test_buyer_profiles_and_notifications_do_not_aggregate_other_companies(): void
    {
        foreach ([$this->own, $this->other] as $tenant) {
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant]);
            DB::table('customers')->insert(['tenant_id' => $tenant, 'user_id' => $this->owner->id, 'name' => 'Customer '.$tenant]);
        }
        $this->get('/mi-cuenta')->assertOk()->assertDontSee('Mecánica privada');
        $this->get('/mis-pedidos')->assertOk()->assertDontSee('Mecánica privada');
    }

    public function test_missing_or_ambiguous_company_configuration_fails_closed(): void
    {
        config(['business.slug' => 'does-not-exist']);
        $this->get('/')->assertStatus(503);
        config(['business.slug' => '']);
        $this->get('/')->assertStatus(503);
    }

    public function test_bakery_mechanic_and_general_profiles_can_publish_without_fashion_requirements(): void
    {
        foreach (['bakery', 'mechanic', 'grocery', 'hardware', 'restaurant', 'services', 'general'] as $type) {
            $this->post('/gestion/single-bakery/configurar', [
                'name' => 'Negocio '.$type, 'business_type' => $type, 'tagline' => 'Productos y atención para nuestros clientes.',
                'storefront_theme' => 'classic', 'publication' => 'publish', 'confirmation' => true,
            ])->assertRedirect('/tienda/single-bakery')->assertSessionHasNoErrors();
            $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('settings.business_type', $type));
        }
    }
}
