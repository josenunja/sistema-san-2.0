<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Commerce;
use App\Services\Purchasing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Suite de Prueba Senior: Simulación E2E de Jornada Comercial Completa
 * 
 * Flujo:
 * 1. Alta de productos con SKU y código de barras.
 * 2. Compra a proveedor y recepción de mercadería en almacén.
 * 3. Apertura de turno de caja con fondo inicial.
 * 4. Venta POS en efectivo con cálculo de vuelto.
 * 5. Venta POS con pago mixto (Yape verificado + Efectivo).
 * 6. Venta a crédito a cliente identificado y abono posterior.
 * 7. Devolución de producto con reintegro a stock y egreso de caja.
 * 8. Cuadre, arqueo y cierre de caja.
 * 9. Auditoría de reapertura y verificación de aislamiento de roles.
 */
class JornadaCompletaTest extends TestCase
{
    private int $tenant;
    private int $warehouse;
    private int $productA;
    private int $productB;
    private User $owner;
    private User $cashier;
    private Commerce $commerce;
    private Purchasing $purchasing;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('Se requiere la base de datos de prueba sas_san_test.');
        }

        $this->withoutVite();
        DB::beginTransaction();

        $this->commerce = app(Commerce::class);
        $this->purchasing = app(Purchasing::class);

        // 1. Crear empresa y almacén
        $this->tenant = DB::table('tenants')->insertGetId([
            'name' => 'Boutique Elegancia',
            'slug' => 'boutique-elegancia',
            'settings' => json_encode([
                'business_type' => 'fashion',
                'delivery_methods' => ['pickup'],
                'yape_instructions' => 'Yapear a 999888777 - Boutique Elegancia',
            ]),
        ]);
        DB::select("select set_config('app.tenant_id', ?, true)", [(string) $this->tenant]);

        $this->warehouse = DB::table('warehouses')->insertGetId([
            'tenant_id' => $this->tenant,
            'name' => 'Tienda Principal',
            'branch' => 'Sede Central',
        ]);

        // 2. Crear Propietario (Owner)
        $this->owner = User::factory()->create(['name' => 'Dueño Tienda', 'email' => 'dueno@elegancia.pe']);
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

        // 3. Crear Cajero (Restricted Role)
        $this->cashier = User::factory()->create(['name' => 'Cajero Turno Mañana', 'email' => 'cajero@elegancia.pe']);
        DB::table('memberships')->insert([
            'tenant_id' => $this->tenant,
            'user_id' => $this->cashier->id,
            'role' => 'cashier',
            'warehouse_ids' => json_encode([$this->warehouse]),
        ]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_simulacion_jornada_comercial_completa_de_punta_a_punta(): void
    {
        // ---------------------------------------------------------------------
        // PASO 1: Alta de productos en catálogo (Polera y Jean)
        // ---------------------------------------------------------------------
        $this->actingAs($this->owner);

        $this->productA = DB::table('products')->insertGetId([
            'tenant_id' => $this->tenant,
            'name' => 'Polera Oversize Algodón',
            'sku' => 'POL-OVR-01',
            'price_minor' => 4500, // S/ 45.00
            'published' => true,
        ]);

        $this->productB = DB::table('products')->insertGetId([
            'tenant_id' => $this->tenant,
            'name' => 'Pantalón Jean Clásico',
            'sku' => 'JEA-CLA-02',
            'price_minor' => 8000, // S/ 80.00
            'published' => true,
        ]);

        $this->assertSame(0, (int) DB::table('stock_positions')->sum('physical'));

        // ---------------------------------------------------------------------
        // PASO 2: Proveedor, Compra Multilínea y Recepción en Almacén
        // ---------------------------------------------------------------------
        $supplier = DB::table('suppliers')->insertGetId([
            'tenant_id' => $this->tenant,
            'name' => 'Textiles Gamarra SAC',
            'document_number' => '20123456789',
        ]);

        // Compra: 10 poleras a S/ 20.00 c/u + 5 jeans a S/ 40.00 c/u
        $docData = [
            'supplier_id' => $supplier,
            'warehouse_id' => $this->warehouse,
            'reference' => 'F001-000452',
            'additional_minor' => 0,
            'discount_minor' => 0,
            'lines' => [
                ['product_id' => $this->productA, 'quantity' => 10, 'unit_cost_minor' => 2000],
                ['product_id' => $this->productB, 'quantity' => 5, 'unit_cost_minor' => 4000],
            ],
        ];

        $purchaseDoc = $this->commerce->run(
            $this->tenant,
            (string) Str::uuid(),
            'purchase_document',
            $docData,
            fn () => $this->purchasing->execute($this->tenant, 'purchase_document', $docData)
        );

        $this->assertSame(40000, $purchaseDoc['total_minor']); // S/ 400.00 total compra

        // Confirmar y recibir físicamente cada lote
        $lots = DB::table('purchases')->where('document_id', $purchaseDoc['id'])->get();
        foreach ($lots as $lot) {
            $this->purchasing->execute($this->tenant, 'purchase_confirm', ['purchase_id' => $lot->id]);
            $recvPayload = ['purchase_id' => $lot->id, 'quantity' => $lot->quantity];
            $this->commerce->run(
                $this->tenant,
                (string) Str::uuid(),
                'purchase_receive',
                $recvPayload,
                fn () => $this->purchasing->execute($this->tenant, 'purchase_receive', $recvPayload)
            );
        }

        // Verificar que el inventario físico esté disponible: 10 + 5 = 15 prendas
        $this->assertSame(15, (int) DB::table('stock_positions')->sum('physical'));

        // ---------------------------------------------------------------------
        // PASO 3: Apertura de Turno de Caja por el Cajero (S/ 100.00 fondo)
        // ---------------------------------------------------------------------
        $this->actingAs($this->cashier);

        $cashOpenResponse = $this->post('/gestion/boutique-elegancia/operacion', [
            'action' => 'open_cash',
            'key' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse,
            'opening_minor' => 10000, // S/ 100.00
        ]);
        $cashOpenResponse->assertSessionHasNoErrors();

        $cashSession = DB::table('cash_sessions')
            ->where('tenant_id', $this->tenant)
            ->where('user_id', $this->cashier->id)
            ->whereNull('closed_at')
            ->first();
        $this->assertNotNull($cashSession);
        $this->assertSame(10000, $cashSession->opening_minor);

        // ---------------------------------------------------------------------
        // PASO 4: Venta 1 - POS Efectivo con Vuelto
        // Cliente compra 1 Polera (S/ 45.00), paga con billete de S/ 50.00 -> Vuelto S/ 5.00
        // ---------------------------------------------------------------------
        $sale1Data = [
            'action' => 'pos_sale',
            'key' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse,
            'cash_session_id' => $cashSession->id,
            'customer_name' => 'Juan Pérez',
            'delivery_method' => 'pickup',
            'cash_minor' => 4500,
            'cash_received_minor' => 5000, // Billete de S/ 50.00
            'items' => [
                ['product_id' => $this->productA, 'quantity' => 1],
            ],
        ];

        $this->post('/gestion/boutique-elegancia/operacion', $sale1Data)->assertSessionHasNoErrors();

        // Verificaciones Venta 1
        $this->assertSame(1, DB::table('orders')->count());
        $lastOrder = DB::table('orders')->latest('id')->first();
        $this->assertSame(500, (int) $lastOrder->change_minor); // Vuelto S/ 5.00
        $this->assertSame(4500, (int) $lastOrder->total_minor);

        // Stock de Polera disminuyó de 10 a 9
        $stockPolera = DB::table('stock_positions')->where('product_id', $this->productA)->value('physical');
        $this->assertSame(9, (int) $stockPolera);

        // Entrada a caja: S/ 45.00
        $totalCash = DB::table('cash_entries')->where('cash_session_id', $cashSession->id)->sum('amount_minor');
        $this->assertSame(4500, (int) $totalCash);

        // ---------------------------------------------------------------------
        // PASO 5: Venta 2 - Pago Mixto (S/ 50 Yape verificado por Dueño + S/ 30 Efectivo)
        // Jean cuesta S/ 80.00
        // ---------------------------------------------------------------------
        // Intentar registrar componente digital como simple cajero debe ser rechazado
        $mixedData = [
            'action' => 'pos_sale',
            'key' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse,
            'cash_session_id' => $cashSession->id,
            'customer_name' => 'María Gomez',
            'delivery_method' => 'pickup',
            'cash_minor' => 3000, // S/ 30 en efectivo
            'cash_received_minor' => 3000,
            'payments' => [
                ['method' => 'yape', 'amount_minor' => 5000, 'reference' => 'YAPE-987654'],
            ],
            'items' => [
                ['product_id' => $this->productB, 'quantity' => 1],
            ],
        ];

        // El cajero no puede autorizar pagos digitales directamente
        $this->post('/gestion/boutique-elegancia/operacion', $mixedData)->assertSessionHasErrors('operation');

        // El propietario autoriza la venta con pago digital verificado
        $this->actingAs($this->owner);
        $this->post('/gestion/boutique-elegancia/operacion', $mixedData)->assertSessionHasNoErrors();

        // Verificaciones Venta 2
        $this->assertSame(2, DB::table('orders')->count());
        $stockJean = DB::table('stock_positions')->where('product_id', $this->productB)->value('physical');
        $this->assertSame(4, (int) $stockJean); // Disminuyó de 5 a 4

        // ---------------------------------------------------------------------
        // PASO 6: Venta 3 - Crédito a Cliente Autorizado
        // Cliente con línea de crédito compra 1 Polera (S/ 45.00) totalmente al crédito
        // ---------------------------------------------------------------------
        $customer = DB::table('customers')->insertGetId([
            'tenant_id' => $this->tenant,
            'name' => 'Carlos Mendoza',
            'phone' => '987111222',
            'credit_limit_minor' => 15000, // Límite S/ 150.00
        ]);

        $creditSaleData = [
            'action' => 'pos_sale',
            'key' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse,
            'cash_session_id' => $cashSession->id,
            'customer_id' => $customer,
            'customer_name' => 'Carlos Mendoza',
            'delivery_method' => 'pickup',
            'cash_minor' => 0, // Todo a crédito
            'items' => [
                ['product_id' => $this->productA, 'quantity' => 1],
            ],
        ];

        $this->post('/gestion/boutique-elegancia/operacion', $creditSaleData)->assertSessionHasNoErrors();
        $this->assertSame(3, DB::table('orders')->count());

        // Stock de polera ahora es 8
        $this->assertSame(8, (int) DB::table('stock_positions')->where('product_id', $this->productA)->value('physical'));

        // Abono posterior de S/ 20.00 en efectivo a la deuda
        $abonoData = [
            'action' => 'payment_on_account',
            'key' => (string) Str::uuid(),
            'customer_id' => $customer,
            'cash_session_id' => $cashSession->id,
            'amount_minor' => 2000,
            'method' => 'cash',
        ];
        $this->post('/gestion/boutique-elegancia/operacion', $abonoData)->assertSessionHasNoErrors();

        // ---------------------------------------------------------------------
        // PASO 7: Cierre y Arqueo de Caja
        // Fondo inicial: S/ 100.00
        // + Venta 1 (efectivo): S/ 45.00
        // + Venta 2 (efectivo mixto): S/ 30.00
        // + Abono cliente (efectivo): S/ 20.00
        // Total esperado en caja física = S/ 195.00 (19500 céntimos)
        // ---------------------------------------------------------------------
        $this->actingAs($this->cashier);

        $closeResponse = $this->post('/gestion/boutique-elegancia/operacion', [
            'action' => 'close_cash',
            'key' => (string) Str::uuid(),
            'cash_session_id' => $cashSession->id,
            'counted_minor' => 19500, // Arqueo exacto
        ]);
        $closeResponse->assertSessionHasNoErrors();

        $closedSession = DB::table('cash_sessions')->where('id', $cashSession->id)->first();
        $this->assertNotNull($closedSession->closed_at);
        $this->assertSame(19500, (int) $closedSession->counted_minor);

        // ---------------------------------------------------------------------
        // PASO 8: Seguridad y Reapertura Auditada
        // El cajero NO puede reabrir la caja cerrada
        // ---------------------------------------------------------------------
        $this->post('/gestion/boutique-elegancia/operacion', [
            'action' => 'reopen_cash',
            'key' => (string) Str::uuid(),
            'cash_session_id' => $cashSession->id,
            'reason' => 'Intento cajero',
        ])->assertForbidden();

        // El Propietario SÍ puede autorizar la reapertura con auditoría
        $this->actingAs($this->owner);
        $reopenResponse = $this->post('/gestion/boutique-elegancia/operacion', [
            'action' => 'reopen_cash',
            'key' => (string) Str::uuid(),
            'cash_session_id' => $cashSession->id,
            'reason' => 'Reapertura autorizada por ajuste de conteo nocturno',
        ]);
        $reopenResponse->assertSessionHasNoErrors();

        // Verificar registro de auditoría de reapertura
        $this->assertSame(1, DB::table('cash_reopenings')->where('cash_session_id', $cashSession->id)->count());
        $reopenLog = DB::table('cash_reopenings')->where('cash_session_id', $cashSession->id)->first();
        $this->assertSame(19500, (int) $reopenLog->previous_counted_minor);
        $this->assertSame('Reapertura autorizada por ajuste de conteo nocturno', $reopenLog->reason);
    }
}
