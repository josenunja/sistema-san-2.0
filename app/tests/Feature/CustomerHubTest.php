<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CustomerHubTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('Isolated testing database required.');
        }
        $this->withoutVite();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_customer_sections_require_authentication(): void
    {
        $this->get('/mi-cuenta')->assertRedirect(route('login'));
        $this->get('/mis-pedidos')->assertRedirect(route('login'));
    }

    public function test_verified_customer_sees_only_their_orders_across_stores(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $first = $this->createStoreWithOrder('Casa Norte', 'casa-norte', $user, now()->subMinute());
        $second = $this->createStoreWithOrder('Distrito Sur', 'distrito-sur', $user, now());
        $this->createOrderForExistingStore($first['tenant'], $first['warehouse'], $other);

        $this->actingAs($user)
            ->get('/mis-pedidos')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('CustomerHub')
                ->where('tab', 'orders')
                ->has('profiles', 2)
                ->has('orders', 2)
                ->where('orders.0.store.name', 'Distrito Sur')
                ->where('orders.1.store.name', 'Casa Norte'));

        $this->actingAs($user)
            ->get('/mi-cuenta')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('CustomerHub')
                ->where('tab', 'account')
                ->where('user.email', $user->email)
                ->has('profiles', 2));
    }

    public function test_customer_can_read_notifications_only_from_their_account(): void
    {
        $user = User::factory()->create();
        $store = $this->createStoreWithOrder('Tienda Avisos', 'tienda-avisos', $user, now());
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $store['tenant']]);
        $orderId = DB::table('orders')->where('tenant_id', $store['tenant'])->value('id');
        DB::table('customer_notifications')->insert([
            'tenant_id' => $store['tenant'],
            'user_id' => $user->id,
            'order_id' => $orderId,
            'kind' => 'delivery_ready',
            'title' => 'Pedido listo',
            'message' => 'Tu pedido ya está listo para recoger.',
        ]);

        $this->actingAs($user)
            ->get('/mis-pedidos')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('CustomerHub')
                ->has('notifications', 1)
                ->where('notifications.0.title', 'Pedido listo')
                ->where('notifications.0.read_at', null));

        $this->post('/mis-pedidos/notificaciones/leer')->assertRedirect();
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $store['tenant']]);
        $this->assertNotNull(DB::table('customer_notifications')->where('user_id', $user->id)->value('read_at'));
    }

    /** @return array{tenant: int, warehouse: int} */
    private function createStoreWithOrder(string $name, string $slug, User $user, \DateTimeInterface $createdAt): array
    {
        $tenant = DB::table('tenants')->insertGetId(['name' => $name, 'slug' => $slug]);
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant]);
        $warehouse = DB::table('warehouses')->insertGetId(['tenant_id' => $tenant, 'name' => 'Principal', 'branch' => 'Local']);
        $customer = DB::table('customers')->insertGetId([
            'tenant_id' => $tenant,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '999999999',
        ]);
        $this->insertOrder($tenant, $warehouse, $customer, $user, $createdAt);

        return ['tenant' => $tenant, 'warehouse' => $warehouse];
    }

    private function createOrderForExistingStore(int $tenant, int $warehouse, User $user): void
    {
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant]);
        $customer = DB::table('customers')->insertGetId([
            'tenant_id' => $tenant,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $this->insertOrder($tenant, $warehouse, $customer, $user, now()->addMinute());
    }

    private function insertOrder(int $tenant, int $warehouse, int $customer, User $user, \DateTimeInterface $createdAt): void
    {
        DB::table('orders')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant,
            'warehouse_id' => $warehouse,
            'customer_id' => $customer,
            'channel' => 'web',
            'status' => 'reserved',
            'delivery_status' => 'pending',
            'delivery_method' => 'pickup',
            'customer_name' => $user->name,
            'phone' => '999999999',
            'email' => $user->email,
            'subtotal_minor' => 12900,
            'discount_minor' => 0,
            'delivery_minor' => 0,
            'total_minor' => 12900,
            'tracking_hash' => hash('sha256', (string) Str::uuid()),
            'expires_at' => now()->addHour(),
            'created_at' => $createdAt,
        ]);
    }
}
