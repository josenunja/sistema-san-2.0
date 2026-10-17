<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Commerce;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class SeedAcceptanceDemo extends Command
{
    protected $signature = 'testing:demo {output}';

    protected $description = 'Create synthetic acceptance fixtures only in sas_san_test; random credentials written to a private local file';

    public function handle(Commerce $commerce): int
    {
        if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'sas_san_test') {
            $this->error('Isolated testing database required.');

            return self::FAILURE;
        }
        $output = $this->argument('output');
        if (! is_string($output)) {
            return self::FAILURE;
        }
        $suffix = Str::lower(Str::random(8));
        $password = Str::random(32);
        $totp = (new Google2FA)->generateSecretKey();
        $result = DB::transaction(function () use ($suffix, $password, $totp, $commerce) {
            $owner = User::create(['name' => 'Operador de demostración', 'email' => 'owner-'.$suffix.'@example.test', 'password' => Hash::make($password)]);
            $owner->forceFill(['email_verified_at' => now(), 'two_factor_secret' => encrypt($totp), 'two_factor_confirmed_at' => now(), 'platform_owner' => true])->save();
            Auth::login($owner);
            $companies = [];
            foreach (['moda' => 'Moda Norte · Demostración', 'dulces' => 'Dulces del Sol · Demostración'] as $slug => $name) {
                $id = DB::table('tenants')->insertGetId(['name' => $name, 'slug' => 'demo-'.$slug.'-'.$suffix, 'settings' => json_encode(['tagline' => 'Tienda de pruebas con datos sintéticos. Sin cuentas de pago reales.', 'theme' => $slug === 'moda' ? 'verde' : 'violeta', 'yape_instructions' => 'DEMOSTRACIÓN: no realizar pagos reales.', 'bcp_instructions' => 'DEMOSTRACIÓN: no realizar pagos reales.'])]);
                DB::table('memberships')->insert(['tenant_id' => $id, 'user_id' => $owner->id, 'role' => 'owner']);
                DB::select("select set_config('app.tenant_id', ?, true)", [(string) $id]);
                $warehouse = DB::table('warehouses')->insertGetId(['tenant_id' => $id, 'name' => 'Almacén principal', 'branch' => 'Local de pruebas']);
                if ($slug === 'moda') {
                    DB::table('warehouses')->insert(['tenant_id' => $id, 'name' => 'Almacén auxiliar', 'branch' => 'Local de pruebas']);
                }
                foreach (range(1, 6) as $index) {
                    $product = DB::table('products')->insertGetId(['tenant_id' => $id, 'sku' => strtoupper($slug).'-'.$index, 'name' => $slug === 'moda' ? 'Polo algodón · '.(['Azul M', 'Azul L', 'Negro M', 'Negro L', 'Blanco M', 'Blanco L'][$index - 1]) : 'Golosina de prueba '.$index, 'variant_group' => $slug === 'moda' ? 'Polo algodón' : '', 'description' => 'Producto sintético para verificar el recorrido de compra. No corresponde a una oferta comercial real.', 'category' => $slug === 'moda' ? 'Polos' : 'Golosinas', 'price_minor' => $slug === 'moda' ? 3500 : 250, 'published' => true]);
                    $commerce->run($id, (string) Str::uuid(), 'demo_receipt', [], fn () => $commerce->receive($id, ['warehouse_id' => $warehouse, 'product_id' => $product, 'quantity' => 20, 'unit_cost_minor' => $slug === 'moda' ? 1800 : 100, 'supplier' => 'Proveedor sintético', 'reference' => 'DEMO-'.$index]));
                }
                DB::table('customers')->insert(['tenant_id' => $id, 'name' => 'Cliente de demostración', 'phone' => '000000000', 'credit_limit_minor' => 50000]);
                $companies[$slug] = ['id' => $id, 'slug' => 'demo-'.$slug.'-'.$suffix];
            }

            return ['owner_id' => $owner->id, 'email' => $owner->email, 'password' => $password, 'totp' => $totp, 'companies' => $companies];
        });
        file_put_contents($output, json_encode($result, JSON_THROW_ON_ERROR));
        $this->info('Synthetic fixtures created in sas_san_test. Credentials were not printed.');

        return self::SUCCESS;
    }
}
