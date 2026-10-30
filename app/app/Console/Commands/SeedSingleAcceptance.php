<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Commerce;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class SeedSingleAcceptance extends Command
{
    protected $signature = 'testing:single-demo {output}';

    protected $description = 'Preparar una empresa sintética únicamente en sas_san_test vacío';

    public function handle(Commerce $commerce): int
    {
        if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'sas_san_test' || DB::table('users')->exists() || DB::table('tenants')->exists()) {
            $this->error('Requiere testing y una base sas_san_test sin cuentas ni empresas. No se borró nada.');

            return self::FAILURE;
        }
        $password = Str::random(36);
        $totp = (new Google2FA)->generateSecretKey();
        $slug = config('business.slug') ?: 'negocio-demo';
        $result = DB::transaction(function () use ($password, $totp, $slug, $commerce) {
            $owner = new User;
            $owner->forceFill(['name' => 'Propietario sintético', 'email' => 'owner-'.Str::lower(Str::random(10)).'@example.test', 'password' => $password, 'email_verified_at' => now(), 'two_factor_secret' => encrypt($totp), 'two_factor_confirmed_at' => now(), 'platform_owner' => true])->save();
            Auth::login($owner);
            $id = DB::table('tenants')->insertGetId(['name' => config('business.name') ?: 'Negocio de demostración', 'slug' => $slug, 'settings' => json_encode(['business_type' => config('business.type'), 'storefront_theme' => 'classic', 'yape_instructions' => 'Demostración; no realizar pagos', 'delivery_methods' => ['pickup']], JSON_THROW_ON_ERROR)]);
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $id]);
            DB::table('memberships')->insert(['tenant_id' => $id, 'user_id' => $owner->id, 'role' => 'owner']);
            $warehouse = DB::table('warehouses')->insertGetId(['tenant_id' => $id, 'name' => 'Local de prueba', 'branch' => 'Demostración']);
            foreach (['Producto de demostración', 'Segundo producto'] as $index => $name) {
                $product = DB::table('products')->insertGetId(['tenant_id' => $id, 'name' => $name, 'sku' => 'DEMO-'.($index + 1), 'price_minor' => 1500 + $index * 500, 'published' => true]);
                $commerce->receive($id, ['warehouse_id' => $warehouse, 'product_id' => $product, 'quantity' => 10, 'unit_cost_minor' => 500, 'supplier' => 'Proveedor sintético', 'reference' => 'APERTURA-DEMO']);
            }
            DB::table('cash_sessions')->insert(['tenant_id' => $id, 'warehouse_id' => $warehouse, 'user_id' => $owner->id, 'opening_minor' => 0]);

            return ['accounts' => ['admin' => ['email' => $owner->email, 'password' => $password, 'totp' => $totp]], 'companies' => ['moda' => ['id' => $id, 'slug' => $slug]]];
        });
        $output = $this->argument('output');
        if (! is_string($output) || file_put_contents($output, json_encode($result, JSON_THROW_ON_ERROR)) === false) {
            $this->error('No se pudo escribir el archivo privado de aceptación.');

            return self::FAILURE;
        }
        $this->info('Datos sintéticos preparados; accesos guardados en el archivo privado, sin imprimir secretos.');

        return self::SUCCESS;
    }
}
