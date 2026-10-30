<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Commerce;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FiscalReceiptTest extends TestCase
{
    private int $tenant;
    private int $warehouse;
    private int $product;
    private int $cashSession;
    private User $owner;
    private Commerce $commerce;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('Se requiere la base de datos de prueba sas_san_test.');
        }

        $this->withoutVite();
        DB::beginTransaction();

        $this->commerce = app(Commerce::class);

        $this->tenant = DB::table('tenants')->insertGetId([
            'name' => 'Comercial Andina S.A.C.',
            'slug' => 'comercial-andina',
            'settings' => json_encode(['business_type' => 'retail']),
        ]);
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenant]);

        $this->owner = User::factory()->create([
            'name' => 'Gerente General',
            'email' => 'gerente@andina.pe',
        ]);
        $this->owner->forceFill([
            'platform_owner' => true,
            'two_factor_secret' => encrypt('TESTSECRET'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        DB::table('memberships')->insert([
            'tenant_id' => $this->tenant,
            'user_id' => $this->owner->id,
            'role' => 'owner',
        ]);

        $this->warehouse = DB::table('warehouses')->insertGetId([
            'tenant_id' => $this->tenant,
            'name' => 'Tienda Principal',
            'branch' => 'Sede Central',
        ]);

        $this->product = DB::table('products')->insertGetId([
            'tenant_id' => $this->tenant,
            'sku' => 'PROD-001',
            'name' => 'Mochila Urbana Resistente',
            'category' => 'Accesorios',
            'price_minor' => 11800, // S/ 118.00 (Base S/ 100 + IGV S/ 18)
            'cost_units' => 50,
            'cost_value_minor' => 250000,
            'cost_complete' => true,
        ]);

        $this->commerce->move($this->tenant, $this->warehouse, $this->product, 50, 0, 0, 0, 'initial', 'Stock inicial');

        $this->cashSession = DB::table('cash_sessions')->insertGetId([
            'tenant_id' => $this->tenant,
            'warehouse_id' => $this->warehouse,
            'user_id' => $this->owner->id,
            'opening_minor' => 20000,
            'opened_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_emitir_boleta_de_venta_con_correlativo_y_desglose_igv(): void
    {
        $this->actingAs($this->owner);

        $payload = [
            'action' => 'pos_sale',
            'key' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse,
            'cash_session_id' => $this->cashSession,
            'document_type' => 'boleta',
            'customer_document_type' => 'dni',
            'customer_document_number' => '44556677',
            'customer_name' => 'Juan Pérez Quispe',
            'phone' => '987654321',
            'delivery_method' => 'pickup',
            'items' => [
                ['product_id' => $this->product, 'quantity' => 1],
            ],
            'cash_minor' => 11800,
            'cash_received_minor' => 12000,
        ];

        $response = $this->post("/gestion/comercial-andina/operacion", $payload);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $order = DB::table('orders')->where('tenant_id', $this->tenant)->latest('created_at')->first();
        $this->assertNotNull($order);
        $this->assertSame('boleta', $order->document_type);
        $this->assertSame('B001', $order->document_series);
        $this->assertSame(1, (int) $order->document_number);
        $this->assertSame('dni', $order->customer_document_type);
        $this->assertSame('44556677', $order->customer_document_number);

        // Desglose tributario: S/ 118.00 = S/ 100.00 base + S/ 18.00 IGV
        $this->assertSame(11800, (int) $order->total_minor);
        $this->assertSame(10000, (int) $order->tax_base_minor);
        $this->assertSame(1800, (int) $order->tax_igv_minor);

        // Verificamos documento fiscal registrado
        $fiscalDoc = DB::table('fiscal_documents')->where('tenant_id', $this->tenant)->where('order_id', $order->id)->first();
        $this->assertNotNull($fiscalDoc);
        $this->assertSame('B001-00000001', $fiscalDoc->provider_reference);

        // Emitimos una segunda boleta para verificar correlativo consecutivo B001-00000002
        $payload2 = [
            'action' => 'pos_sale',
            'key' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse,
            'cash_session_id' => $this->cashSession,
            'document_type' => 'boleta',
            'customer_document_type' => 'dni',
            'customer_document_number' => '88776655',
            'customer_name' => 'Ana Torres',
            'phone' => '912345678',
            'delivery_method' => 'pickup',
            'items' => [
                ['product_id' => $this->product, 'quantity' => 1],
            ],
            'cash_minor' => 11800,
            'cash_received_minor' => 11800,
        ];

        $this->post("/gestion/comercial-andina/operacion", $payload2)->assertSessionHasNoErrors();
        $order2 = DB::table('orders')->where('tenant_id', $this->tenant)->where('id', '!=', $order->id)->first();
        $this->assertSame('boleta', $order2->document_type);
        $this->assertSame('B001', $order2->document_series);
        $this->assertSame(2, (int) $order2->document_number);
    }

    public function test_emitir_factura_con_validacion_de_ruc_y_razon_social(): void
    {
        $this->actingAs($this->owner);

        // 1. Intento con RUC inválido (no 11 dígitos)
        $invalidRucPayload = [
            'action' => 'pos_sale',
            'key' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse,
            'cash_session_id' => $this->cashSession,
            'document_type' => 'factura',
            'customer_document_type' => 'ruc',
            'customer_document_number' => '12345',
            'customer_legal_name' => 'Empresa Test',
            'customer_name' => 'Empresa Test',
            'phone' => '999111222',
            'delivery_method' => 'pickup',
            'items' => [['product_id' => $this->product, 'quantity' => 1]],
            'cash_minor' => 11800,
        ];

        $this->post("/gestion/comercial-andina/operacion", $invalidRucPayload)
            ->assertSessionHasErrors('operation');

        // 2. Factura válida con RUC 20601234567 y Razón Social
        $validFacturaPayload = [
            'action' => 'pos_sale',
            'key' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse,
            'cash_session_id' => $this->cashSession,
            'document_type' => 'factura',
            'customer_document_type' => 'ruc',
            'customer_document_number' => '20601234567',
            'customer_legal_name' => 'SERVICIOS INDUSTRIALES DEL PERU S.A.C.',
            'customer_name' => 'SERVICIOS INDUSTRIALES DEL PERU S.A.C.',
            'phone' => '999111222',
            'delivery_method' => 'pickup',
            'items' => [['product_id' => $this->product, 'quantity' => 2]], // 2 x 118 = 236.00
            'cash_minor' => 23600,
        ];

        $res = $this->post("/gestion/comercial-andina/operacion", $validFacturaPayload);
        $res->assertSessionHasNoErrors();

        $factura = DB::table('orders')->where('tenant_id', $this->tenant)->where('document_type', 'factura')->first();
        $this->assertNotNull($factura);
        $this->assertSame('factura', $factura->document_type);
        $this->assertSame('F001', $factura->document_series);
        $this->assertSame(1, (int) $factura->document_number);
        $this->assertSame('ruc', $factura->customer_document_type);
        $this->assertSame('20601234567', $factura->customer_document_number);
        $this->assertSame('SERVICIOS INDUSTRIALES DEL PERU S.A.C.', $factura->customer_legal_name);

        // Desglose de 236.00: Base = 200.00 (20000), IGV = 36.00 (3600)
        $this->assertSame(23600, (int) $factura->total_minor);
        $this->assertSame(20000, (int) $factura->tax_base_minor);
        $this->assertSame(3600, (int) $factura->tax_igv_minor);

        $fiscalDoc = DB::table('fiscal_documents')->where('tenant_id', $this->tenant)->where('order_id', $factura->id)->first();
        $this->assertSame('F001-00000001', $fiscalDoc->provider_reference);
    }

    public function test_emitir_nota_de_venta_ticket_por_defecto(): void
    {
        $this->actingAs($this->owner);

        $payload = [
            'action' => 'pos_sale',
            'key' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse,
            'cash_session_id' => $this->cashSession,
            'customer_name' => 'Cliente Ocasional',
            'phone' => '900000000',
            'delivery_method' => 'pickup',
            'items' => [['product_id' => $this->product, 'quantity' => 1]],
            'cash_minor' => 11800,
        ];

        $this->post("/gestion/comercial-andina/operacion", $payload)->assertSessionHasNoErrors();

        $ticket = DB::table('orders')->where('tenant_id', $this->tenant)->first();
        $this->assertSame('ticket', $ticket->document_type);
        $this->assertSame('T001', $ticket->document_series);
        $this->assertSame(1, (int) $ticket->document_number);
    }

    public function test_boleta_mayor_a_700_soles_exige_identificacion_del_cliente(): void
    {
        $this->actingAs($this->owner);

        // 7 unidades de S/ 118 = S/ 826.00 (mayor a S/ 700)
        $sinDniPayload = [
            'action' => 'pos_sale',
            'key' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse,
            'cash_session_id' => $this->cashSession,
            'document_type' => 'boleta',
            'customer_name' => 'Comprador Anónimo',
            'phone' => '911222333',
            'delivery_method' => 'pickup',
            'items' => [['product_id' => $this->product, 'quantity' => 7]],
            'cash_minor' => 82600,
        ];

        $this->post("/gestion/comercial-andina/operacion", $sinDniPayload)
            ->assertSessionHasErrors('operation');

        // Con DNI de 8 dígitos debe proceder con éxito
        $conDniPayload = [
            'action' => 'pos_sale',
            'key' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse,
            'cash_session_id' => $this->cashSession,
            'document_type' => 'boleta',
            'customer_document_type' => 'dni',
            'customer_document_number' => '72345678',
            'customer_name' => 'Carlos Mendoza',
            'phone' => '911222333',
            'delivery_method' => 'pickup',
            'items' => [['product_id' => $this->product, 'quantity' => 7]],
            'cash_minor' => 82600,
        ];

        $this->post("/gestion/comercial-andina/operacion", $conDniPayload)
            ->assertSessionHasNoErrors();

        $boleta = DB::table('orders')->where('tenant_id', $this->tenant)->where('customer_document_number', '72345678')->first();
        $this->assertNotNull($boleta);
        $this->assertSame(82600, (int) $boleta->total_minor);
        $this->assertSame(70000, (int) $boleta->tax_base_minor);
        $this->assertSame(12600, (int) $boleta->tax_igv_minor);
    }
}
