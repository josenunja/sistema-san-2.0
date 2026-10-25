<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Notifications\BusinessInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformJourneyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('Isolated database required.');
        }
        DB::beginTransaction();
        $this->withoutVite();
        Storage::fake('local');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['platform_owner' => true, 'two_factor_secret' => encrypt('TEST'), 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }

    private function invitationToken(string $email): string
    {
        $token = '';
        Notification::assertSentOnDemand(BusinessInvitation::class, function ($notification, $channels, $notifiable) use ($email, &$token) {
            if ($notifiable->routes['mail'] !== $email) {
                return false;
            }
            $token = parse_url($notification->toMail($notifiable)->actionUrl, PHP_URL_FRAGMENT);

            return strlen($token) === 64;
        });

        return $token;
    }

    public function test_password_confirmation_happens_before_the_creation_form(): void
    {
        $this->actingAs($this->administrator())->get('/plataforma')->assertRedirect('/user/confirm-password');
        $this->assertSame(url('/plataforma'), session('url.intended'));
        $this->withSession(['auth.password_confirmed_at' => time()])->get('/plataforma')->assertOk();
    }

    public function test_administrator_owner_product_inventory_and_access_journey(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $this->from('/plataforma')->post('/plataforma', [
            'name' => 'Tienda recorrido completo', 'slug' => 'journey-store', 'plan' => 'inicial', 'owner_email' => 'journey-owner@example.test',
        ])->assertRedirect('/plataforma')->assertSessionHasNoErrors();
        $tenant = (int) DB::table('tenants')->where('slug', 'journey-store')->value('id');
        $this->assertGreaterThan(0, $tenant);
        $this->get('/gestion/journey-store')->assertForbidden();
        $token = $this->invitationToken('journey-owner@example.test');
        $this->post('/invitacion', ['token' => $token, 'name' => 'Propietario de prueba', 'password' => 'synthetic-password-123456'])->assertRedirect('/login')->assertSessionHasNoErrors();
        $owner = User::where('email', 'journey-owner@example.test')->firstOrFail();
        $this->assertFalse($owner->platform_owner);
        $this->assertSame('owner', DB::table('memberships')->where('tenant_id', $tenant)->where('user_id', $owner->id)->value('role'));
        $this->actingAs($owner)->get('/gestion/journey-store')->assertRedirect('/settings/security');
        $owner->forceFill(['two_factor_secret' => encrypt('TEST'), 'two_factor_confirmed_at' => now()])->save();
        $this->get('/gestion/journey-store/configurar')->assertOk();
        $this->post('/gestion/journey-store/configurar', [
            'name' => 'Tienda lista para vender', 'business_type' => 'fashion', 'storefront_theme' => 'urban',
            'tagline' => 'Recorrido de aceptación', 'publication' => 'publish', 'confirmation' => true,
            'logo' => UploadedFile::fake()->image('logo.png', 80, 80),
        ])->assertRedirect('/tienda/journey-store')->assertSessionHasNoErrors();
        $this->post('/gestion/journey-store/productos', [
            'sku' => 'JOURNEY-M', 'name' => 'Polo del recorrido', 'price_minor' => 5900, 'published' => true,
            'variant_group' => 'Polo recorrido', 'size' => 'M', 'color' => 'Azul', 'audience' => 'men',
        ])->assertRedirect()->assertSessionHasNoErrors();
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant]);
        $product = (int) DB::table('products')->where('sku', 'JOURNEY-M')->value('id');
        $this->assertGreaterThan(0, $product);
        $this->post('/gestion/journey-store/productos/'.$product.'/foto', ['image' => UploadedFile::fake()->image('polo.png', 80, 80)])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/tienda/journey-store/producto/'.$product.'/foto')->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->post('/gestion/journey-store/operacion', ['action' => 'warehouse', 'key' => (string) Str::uuid(), 'name' => 'Principal', 'branch' => 'Lima'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $warehouse = (int) DB::table('warehouses')->where('tenant_id', $tenant)->value('id');
        $this->post('/gestion/journey-store/operacion', [
            'action' => 'receipt', 'key' => (string) Str::uuid(), 'warehouse_id' => $warehouse, 'product_id' => $product,
            'quantity' => 8, 'unit_cost_minor' => 3000, 'supplier' => 'Proveedor sintético', 'reference' => 'ACEPTACION-001',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(8, (int) DB::table('stock_positions')->where('tenant_id', $tenant)->where('product_id', $product)->value('physical'));
        $this->get('/tienda/journey-store')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tenant.name', 'Tienda lista para vender')
            ->where('products.data.0.sku', 'JOURNEY-M')
            ->where('products.data.0.price_minor', 5900));
        $this->get('/?q=Polo%20del%20recorrido')->assertOk()->assertSee('Polo del recorrido');
        foreach (['resumen', 'inventario', 'compras', 'pedidos', 'pos', 'caja', 'clientes', 'promociones', 'configuracion', 'auditoria'] as $section) {
            $this->get('/gestion/journey-store/operacion/'.$section)->assertOk();
        }
        $this->post('/invitaciones', ['tenant_id' => $tenant, 'email' => 'journey-cashier@example.test', 'role' => 'cashier'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $cashierToken = $this->invitationToken('journey-cashier@example.test');
        $this->post('/invitacion', ['token' => $cashierToken, 'name' => 'Cajero de prueba', 'password' => 'synthetic-password-123456', 'role' => 'owner'])
            ->assertRedirect('/login')->assertSessionHasNoErrors();
        $cashier = User::where('email', 'journey-cashier@example.test')->firstOrFail();
        $cashier->forceFill(['two_factor_secret' => encrypt('TEST'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($cashier)->get('/gestion/journey-store/operacion/pos')->assertOk();
        $this->get('/gestion/journey-store')->assertForbidden();
        $this->get('/plataforma')->assertForbidden();
        $this->actingAs($owner)->delete('/gestion/journey-store/equipo/'.$owner->id)->assertSessionHasErrors('operation');
        $this->delete('/gestion/journey-store/equipo/'.$cashier->id)->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($cashier)->get('/gestion/journey-store/operacion/pos')->assertForbidden();
        $this->actingAs($admin)->patch('/plataforma/'.$tenant, ['active' => false, 'plan' => 'negocio'])->assertRedirect();
        $this->get('/tienda/journey-store')->assertNotFound();
        $this->patch('/plataforma/'.$tenant, ['active' => true, 'plan' => 'inicial'])->assertRedirect();
        $this->get('/tienda/journey-store')->assertOk();
    }

    public function test_administrator_profile_and_password_changes_preserve_permissions(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $this->get('/settings/profile')->assertOk();
        $this->get('/settings/security')->assertOk();
        $this->patch('/settings/profile', ['name' => 'Administrador actualizado', 'email' => $admin->email, 'platform_owner' => false])
            ->assertRedirect('/settings/profile')->assertSessionHasNoErrors();
        $this->assertSame('Administrador actualizado', $admin->refresh()->name);
        $this->assertTrue($admin->platform_owner);
        $this->assertNotNull($admin->email_verified_at);
        $this->from('/settings/security')->put('/settings/password', [
            'current_password' => 'wrong-password', 'password' => 'new-synthetic-password-123', 'password_confirmation' => 'new-synthetic-password-123',
        ])->assertSessionHasErrors('current_password');
        $this->put('/settings/password', [
            'current_password' => 'password', 'password' => 'new-synthetic-password-123', 'password_confirmation' => 'new-synthetic-password-123',
        ])->assertRedirect('/settings/security')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-synthetic-password-123', $admin->refresh()->password));
        $this->assertTrue($admin->platform_owner);
        $this->assertNotNull($admin->two_factor_confirmed_at);
    }

    public function test_inertia_navigation_keeps_product_submission_in_the_catalog_without_a_referrer(): void
    {
        $owner = $this->administrator();
        $tenant = DB::table('tenants')->insertGetId(['name' => 'Navegación', 'slug' => 'journey-navigation']);
        DB::table('memberships')->insert(['tenant_id' => $tenant, 'user_id' => $owner->id, 'role' => 'owner']);
        $this->actingAs($owner)->get('/dashboard')->assertOk();
        $version = app(HandleInertiaRequests::class)->version(Request::create('/'));
        $this->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $version])
            ->get('/gestion/journey-navigation')->assertOk();
        $this->assertSame(url('/gestion/journey-navigation'), session('_previous.url'));
        $this->withHeaders(['Purpose' => 'prefetch'])->get('/tienda/journey-navigation')->assertOk();
        $this->assertSame(url('/gestion/journey-navigation'), session('_previous.url'));
        $this->withoutHeader('Purpose');
        $this->post('/gestion/journey-navigation/productos', [
            'sku' => 'NAV-001', 'name' => 'Producto navegación', 'price_minor' => 5990, 'published' => true,
        ])->assertRedirect('/gestion/journey-navigation')->assertSessionHasNoErrors();
    }

    public function test_existing_account_invitation_never_resets_its_password_and_expires(): void
    {
        $admin = $this->administrator();
        $existing = User::factory()->create();
        $existing->forceFill(['password' => Hash::make('existing-password-123')])->save();
        $hash = $existing->password;
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $this->post('/plataforma', [
            'name' => 'Acceso cuenta existente', 'slug' => 'journey-existing', 'plan' => 'inicial', 'owner_email' => $existing->email,
        ])->assertSessionHasNoErrors();
        $token = $this->invitationToken($existing->email);
        $this->post('/invitacion', ['token' => $token, 'name' => 'Nombre ajeno', 'password' => 'wrong-existing-password'])
            ->assertSessionHasErrors('operation');
        $this->assertSame($hash, $existing->refresh()->password);
        $this->travel(49)->hours();
        $this->post('/invitacion', ['token' => $token, 'name' => 'Nombre ajeno', 'password' => 'long-enough-password'])
            ->assertForbidden();
        $this->travelBack();
        $this->assertSame(0, DB::table('memberships')->where('user_id', $existing->id)->count());
        $this->assertSame($hash, $existing->refresh()->password);
        $this->post('/invitacion', ['token' => $token, 'name' => 'Nombre ajeno', 'password' => 'existing-password-123'])
            ->assertRedirect('/login')->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('memberships')->where('user_id', $existing->id)->count());
        $this->assertSame($hash, $existing->refresh()->password);
        $this->post('/invitacion', ['token' => $token, 'name' => 'Nombre ajeno', 'password' => 'existing-password-123'])->assertForbidden();
    }
}
