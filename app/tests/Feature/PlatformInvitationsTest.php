<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\BusinessInvitation;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformInvitationsTest extends TestCase
{
    private User $admin;

    private User $owner;

    private int $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('Isolated database required.');
        }
        DB::beginTransaction();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
        Notification::fake();
        Storage::fake('local');
        $this->admin = $this->account(true);
        $this->owner = $this->account(false);
        $this->tenant = DB::table('tenants')->insertGetId(['name' => 'Cliente con catálogo', 'slug' => 'invitations-catalog']);
        DB::table('memberships')->insert(['tenant_id' => $this->tenant, 'user_id' => $this->owner->id, 'role' => 'owner']);
        $this->actingAs($this->owner)->withSession(['auth.password_confirmed_at' => time()]);
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenant]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function account(bool $platform): User
    {
        $user = User::factory()->create();
        $user->forceFill(['platform_owner' => $platform, 'two_factor_secret' => encrypt('TEST'), 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }

    private function invite(string $email = 'invited@example.test', string $role = 'catalog'): object
    {
        $this->post('/invitaciones', ['tenant_id' => $this->tenant, 'email' => $email, 'role' => $role])->assertRedirect()->assertSessionHasNoErrors();

        return DB::table('invitations')->where('tenant_id', $this->tenant)->where('email', $email)->orderByDesc('created_at')->first();
    }

    private function token(): string
    {
        $token = '';
        Notification::assertSentOnDemand(BusinessInvitation::class, function ($notification, $channels, $notifiable) use (&$token) {
            $token = parse_url($notification->toMail($notifiable)->actionUrl, PHP_URL_FRAGMENT);

            return strlen($token) === 64;
        });

        return $token;
    }

    public function test_resend_rotates_the_secret_and_only_the_new_link_can_be_accepted(): void
    {
        $invite = $this->invite();
        $old = $this->token();
        $this->post('/invitaciones/'.$invite->id.'/reenviar')->assertSessionHasErrors('operation');
        $this->travel(2)->minutes();
        Notification::fake();
        $this->post('/invitaciones/'.$invite->id.'/reenviar')->assertRedirect()->assertSessionHasNoErrors();
        $new = $this->token();
        $this->assertNotSame($old, $new);
        $this->post('/invitacion', ['token' => $old, 'name' => 'Invitado', 'password' => 'synthetic-password-123'])->assertForbidden();
        $this->post('/invitacion', ['token' => $new, 'name' => 'Invitado', 'password' => 'synthetic-password-123'])->assertRedirect('/login')->assertSessionHasNoErrors();
        $user = User::where('email', 'invited@example.test')->firstOrFail();
        $this->assertSame('catalog', DB::table('memberships')->where('tenant_id', $this->tenant)->where('user_id', $user->id)->value('role'));
        $this->post('/invitaciones/'.$invite->id.'/revocar')->assertSessionHasErrors('operation');
        $this->assertSame(1, DB::table('memberships')->where('tenant_id', $this->tenant)->where('user_id', $user->id)->count());
        $this->travelBack();
    }

    public function test_revocation_is_repeatable_blocks_acceptance_and_is_visible_without_secrets(): void
    {
        $invite = $this->invite();
        $token = $this->token();
        $this->post('/invitaciones/'.$invite->id.'/revocar')->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/invitaciones/'.$invite->id.'/revocar')->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/invitacion', ['token' => $token, 'name' => 'Invitado', 'password' => 'synthetic-password-123'])->assertForbidden();
        $this->post('/invitaciones/'.$invite->id.'/reenviar')->assertSessionHasErrors('operation');
        $this->get('/gestion/invitations-catalog/operacion/configuracion')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('data.invitations.0.status', 'revoked')->missing('data.invitations.0.token_hash'));
        $this->actingAs($this->admin)->get('/plataforma')->assertOk()->assertDontSee($token);
    }

    public function test_other_tenants_cashiers_and_unconfirmed_sessions_cannot_manage_invitations(): void
    {
        $invite = $this->invite();
        $other = $this->account(false);
        foreach (['reenviar', 'revocar'] as $action) {
            $this->actingAs($other)->post('/invitaciones/'.$invite->id.'/'.$action)->assertForbidden();
        }
        DB::table('memberships')->insert(['tenant_id' => $this->tenant, 'user_id' => $other->id, 'role' => 'cashier']);
        $this->post('/invitaciones/'.$invite->id.'/revocar')->assertForbidden();
        $this->actingAs($this->owner)->withSession(['auth.password_confirmed_at' => 0])->post('/invitaciones/'.$invite->id.'/revocar')->assertRedirect('/user/confirm-password');
        $this->withSession(['auth.password_confirmed_at' => time()]);
        $this->owner->forceFill(['two_factor_confirmed_at' => null])->save();
        $this->post('/invitaciones/'.$invite->id.'/revocar')->assertForbidden();
        $this->assertNull(DB::table('invitations')->where('id', $invite->id)->value('revoked_at'));
    }

    public function test_expired_invitation_renewal_obeys_capacity_and_revoked_invites_release_their_slot(): void
    {
        DB::table('plans')->where('slug', 'inicial')->update(['user_limit' => 2]);
        $invite = $this->invite();
        $this->post('/invitaciones', ['tenant_id' => $this->tenant, 'email' => 'another@example.test', 'role' => 'catalog'])->assertSessionHasErrors('operation');
        $this->post('/invitaciones/'.$invite->id.'/revocar')->assertRedirect();
        $new = $this->invite('another@example.test');
        DB::table('invitations')->where('id', $new->id)->update(['expires_at' => now()->subHour(), 'last_sent_at' => now()->subDays(3)]);
        $this->post('/invitaciones/'.$new->id.'/reenviar')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(now()->lessThan(DB::table('invitations')->where('id', $new->id)->value('expires_at')));
        DB::table('invitations')->where('id', $new->id)->update(['expires_at' => now()->subHour(), 'last_sent_at' => now()->subDays(3)]);
        $this->actingAs($this->admin)->post('/plataforma/'.$this->tenant.'/catalogo', ['enabled' => true])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/invitaciones/'.$new->id.'/reenviar')->assertSessionHasErrors('operation');
    }

    public function test_admin_and_client_can_both_upload_products_with_limited_catalog_permission(): void
    {
        $this->actingAs($this->admin)->get('/gestion/invitations-catalog')->assertForbidden();
        $this->post('/plataforma/'.$this->tenant.'/catalogo', ['enabled' => true])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/plataforma/'.$this->tenant.'/catalogo', ['enabled' => true])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('memberships')->where('tenant_id', $this->tenant)->where('user_id', $this->admin->id)->count());
        $this->get('/gestion/invitations-catalog')->assertOk()->assertInertia(fn (Assert $page) => $page->where('role', 'catalog'));
        foreach ([$this->admin, $this->owner] as $index => $user) {
            $this->actingAs($user)->post('/gestion/invitations-catalog/productos', [
                'name' => 'Producto sencillo '.$index, 'price_minor' => 5990, 'published' => true, 'image' => UploadedFile::fake()->image('producto.png', 100, 100),
            ])->assertRedirect()->assertSessionHasNoErrors();
        }
        $products = DB::table('products')->where('tenant_id', $this->tenant)->get();
        $this->assertCount(2, $products);
        $this->assertNotSame($products[0]->sku, $products[1]->sku);
        $this->assertStringStartsWith('PRD-', $products[0]->sku);
        $this->assertSame(2, DB::table('product_images')->where('tenant_id', $this->tenant)->count());
        $this->assertSame(0, DB::table('stock_positions')->where('tenant_id', $this->tenant)->count());
        $this->actingAs($this->admin)->get('/gestion/invitations-catalog/exportar/productos')->assertOk();
        foreach (['clientes', 'ventas', 'inventario'] as $scope) {
            $this->get('/gestion/invitations-catalog/exportar/'.$scope)->assertForbidden();
        }
        foreach (['resumen', 'caja', 'clientes', 'pedidos', 'configuracion'] as $section) {
            $this->get('/gestion/invitations-catalog/operacion/'.$section)->assertForbidden();
        }
        $this->get('/gestion/invitations-catalog/configurar')->assertForbidden();
        $this->get('/gestion/invitations-catalog/offline')->assertForbidden();
        $foreign = DB::table('tenants')->insertGetId(['name' => 'Otra empresa', 'slug' => 'foreign-catalog']);
        $this->get('/gestion/foreign-catalog')->assertForbidden();
        $this->post('/gestion/foreign-catalog/productos', ['name' => 'Ataque', 'price_minor' => 1, 'published' => true])->assertForbidden();
        $this->actingAs($this->owner)->delete('/gestion/invitations-catalog/equipo/'.$this->admin->id)->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get('/gestion/invitations-catalog')->assertForbidden();
        $this->assertSame('owner', DB::table('memberships')->where('tenant_id', $this->tenant)->where('user_id', $this->owner->id)->value('role'));
    }

    public function test_creation_can_include_catalog_assistance_without_granting_ownership(): void
    {
        $this->actingAs($this->admin)->post('/plataforma', ['name' => 'Cliente nuevo', 'slug' => 'assisted-client', 'plan' => 'inicial', 'owner_email' => 'client-new@example.test', 'manage_catalog' => true])
            ->assertRedirect()->assertSessionHasNoErrors();
        $tenant = (int) DB::table('tenants')->where('slug', 'assisted-client')->value('id');
        $this->assertSame('catalog', DB::table('memberships')->where('tenant_id', $tenant)->where('user_id', $this->admin->id)->value('role'));
        $this->assertSame('owner', DB::table('invitations')->where('tenant_id', $tenant)->value('role'));
        $this->get('/gestion/assisted-client')->assertOk();
        $this->get('/gestion/assisted-client/operacion/caja')->assertForbidden();
        $this->post('/plataforma/'.$tenant.'/catalogo', ['enabled' => false])->assertRedirect();
        $this->get('/gestion/assisted-client')->assertForbidden();
        $this->actingAs($this->owner)->post('/plataforma/'.$tenant.'/catalogo', ['enabled' => true])->assertForbidden();
    }

    public function test_invalid_photo_rolls_back_quick_creation_and_edit_with_photo_keeps_the_product(): void
    {
        $this->post('/gestion/invitations-catalog/productos', ['name' => 'Inválido', 'price_minor' => 5990, 'published' => true, 'image' => UploadedFile::fake()->image('wide.png', 4001, 1)])
            ->assertSessionHasErrors('operation');
        $this->assertSame(0, DB::table('products')->where('tenant_id', $this->tenant)->count());
        $this->post('/gestion/invitations-catalog/productos', ['name' => 'Simple', 'price_minor' => 5990, 'published' => false])->assertSessionHasNoErrors();
        $product = DB::table('products')->where('tenant_id', $this->tenant)->first();
        $this->post('/gestion/invitations-catalog/productos/'.$product->id, [
            '_method' => 'patch', 'sku' => $product->sku, 'name' => 'Editado con foto', 'price_minor' => 6990, 'published' => true, 'image' => UploadedFile::fake()->image('edited.png', 100, 100),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('products')->where('tenant_id', $this->tenant)->count());
        $this->get('/tienda/invitations-catalog/producto/'.$product->id.'/foto')->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_full_invitation_history_is_paginated_and_private_to_its_tenant(): void
    {
        foreach (range(1, 31) as $index) {
            DB::table('invitations')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $this->tenant,
                'email' => 'history-'.$index.'@example.test', 'role' => 'catalog',
                'token_hash' => hash('sha256', 'synthetic-history-'.$index),
                'expires_at' => now()->subDay(), 'revoked_at' => now()->subDay(),
            ]);
        }
        $this->get('/invitaciones/tienda/'.$this->tenant.'?page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Invitations')->where('invitations.total', 31)->where('invitations.current_page', 2)
            ->has('invitations.data', 6)->where('invitations.data.0.status', 'revoked')->missing('invitations.data.0.token_hash'));
        $this->actingAs($this->account(false))->get('/invitaciones/tienda/'.$this->tenant)->assertForbidden();
        $this->actingAs($this->admin)->get('/invitaciones/tienda/'.$this->tenant)->assertOk();
    }
}
