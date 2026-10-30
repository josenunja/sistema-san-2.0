<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\Commerce;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "Inicializando cuenta y datos de prueba en sas_san...\n";

DB::transaction(function () {
    // 1. Limpiar o comprobar usuario
    $slug = config('business.slug') ?: 'demo';
    $businessName = config('business.name') ?: 'Boutique San';
    $businessType = config('business.type') ?: 'fashion';

    // Eliminar datos anteriores si existen para asegurar estado limpio
    DB::table('users')->delete();
    DB::table('tenants')->delete();

    // 2. Crear Administrador sin 2FA obligatorio
    $user = new User();
    $user->forceFill([
        'name' => 'Administrador Demo',
        'email' => 'admin@test.com',
        'password' => Hash::make('Admin12345678!'),
        'email_verified_at' => now(),
        'platform_owner' => true,
    ]);
    $user->save();

    // 3. Crear Empresa Single
    $tenantId = DB::table('tenants')->insertGetId([
        'name' => $businessName,
        'slug' => $slug,
        'settings' => json_encode([
            'business_type' => $businessType,
            'storefront_theme' => 'classic',
            'delivery_methods' => ['pickup', 'delivery'],
            'delivery_minor' => 800,
            'yape_instructions' => 'Yapear al 999-888-777 (Titular: Boutique San)',
        ], JSON_THROW_ON_ERROR),
    ]);

    DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenantId]);

    // 4. Membresía de Propietario (Owner)
    DB::table('memberships')->insert([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);

    // 5. Almacén Principal
    $warehouseId = DB::table('warehouses')->insertGetId([
        'tenant_id' => $tenantId,
        'name' => 'Tienda y Almacén Central',
        'branch' => 'Sede Principal',
    ]);

    // 6. Productos con Stock y Códigos de Barra
    $commerce = app(Commerce::class);

    $items = [
        [
            'name' => 'Polera Oversize Algodón Premium',
            'sku' => 'POL-001',
            'barcode' => '7751234567890',
            'price_minor' => 4500, // S/ 45.00
            'cost_minor' => 2200,  // S/ 22.00
            'qty' => 20,
        ],
        [
            'name' => 'Pantalón Jean Slim Fit Azul',
            'sku' => 'JEA-002',
            'barcode' => '7751234567891',
            'price_minor' => 8900, // S/ 89.00
            'cost_minor' => 4500,  // S/ 45.00
            'qty' => 15,
        ],
        [
            'name' => 'Casaca Denim Vintage',
            'sku' => 'CAS-003',
            'barcode' => '7751234567892',
            'price_minor' => 13500, // S/ 135.00
            'cost_minor' => 7000,   // S/ 70.00
            'qty' => 10,
        ],
        [
            'name' => 'Zapatillas Urbanas Blancas',
            'sku' => 'ZAP-004',
            'barcode' => '7751234567893',
            'price_minor' => 12000, // S/ 120.00
            'cost_minor' => 6500,   // S/ 65.00
            'qty' => 8,
        ],
    ];

    foreach ($items as $item) {
        $pid = DB::table('products')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => $item['name'],
            'sku' => $item['sku'],
            'barcode' => $item['barcode'],
            'price_minor' => $item['price_minor'],
            'published' => true,
        ]);

        $commerce->receive($tenantId, [
            'warehouse_id' => $warehouseId,
            'product_id' => $pid,
            'quantity' => $item['qty'],
            'unit_cost_minor' => $item['cost_minor'],
            'supplier' => 'Distribuidora Textil Lima',
            'reference' => 'STOCK-INICIAL',
        ]);
    }

    // 7. Apertura de Caja Inicial (S/ 100.00)
    DB::table('cash_sessions')->insert([
        'tenant_id' => $tenantId,
        'warehouse_id' => $warehouseId,
        'user_id' => $user->id,
        'opening_minor' => 10000, // S/ 100.00
    ]);

    // 8. Cliente registrado para ventas a crédito
    DB::table('customers')->insert([
        'tenant_id' => $tenantId,
        'name' => 'Carlos Mendoza (Cliente Frecuente)',
        'phone' => '987654321',
        'email' => 'carlos.mendoza@gmail.com',
        'credit_limit_minor' => 20000, // S/ 200.00 límite de crédito
    ]);

    echo "¡Cuenta y datos de prueba creados exitosamente!\n";
});
