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
        $adminPassword = Str::random(32);
        $superAdminPassword = Str::random(32);
        $adminTotp = (new Google2FA)->generateSecretKey();
        $superAdminTotp = (new Google2FA)->generateSecretKey();
        $result = DB::transaction(function () use ($suffix, $adminPassword, $superAdminPassword, $adminTotp, $superAdminTotp, $commerce) {
            DB::table('tenants')
                ->where(fn ($query) => $query->where('slug', 'like', 'demo-%')->orWhere('slug', 'like', 'race-%'))
                ->update(['active' => false]);
            $admin = User::create(['name' => 'Administrador de tienda · Demostración', 'email' => 'admin-'.$suffix.'@example.test', 'password' => Hash::make($adminPassword)]);
            $admin->forceFill(['email_verified_at' => now(), 'two_factor_secret' => encrypt($adminTotp), 'two_factor_confirmed_at' => now(), 'platform_owner' => false])->save();
            $superAdmin = User::create(['name' => 'Superadministrador · Demostración', 'email' => 'superadmin-'.$suffix.'@example.test', 'password' => Hash::make($superAdminPassword)]);
            $superAdmin->forceFill(['email_verified_at' => now(), 'two_factor_secret' => encrypt($superAdminTotp), 'two_factor_confirmed_at' => now(), 'platform_owner' => true])->save();
            Auth::login($admin);
            $companies = [];
            foreach (['moda' => 'Moda Norte · Demostración', 'calzado' => 'Atelier Paso · Demostración'] as $slug => $name) {
                $settings = [
                    'store_name' => $name,
                    'tagline' => 'Tienda de pruebas con datos sintéticos. Sin cuentas de pago reales.',
                    'description' => 'Identidad visual de demostración para revisar el catálogo sin utilizar información comercial real.',
                    'theme' => $slug === 'moda' ? 'rosa' : 'arena',
                    'storefront_theme' => $slug === 'moda' ? 'feminine' : 'footwear',
                    'primary_color' => $slug === 'moda' ? '#8f3158' : '#3f3024',
                    'accent_color' => $slug === 'moda' ? '#f4bfd3' : '#d9b77e',
                    'section_order' => $slug === 'moda'
                        ? ['women', 'promotions', 'trends', 'all', 'men', 'kids']
                        : ['all', 'promotions', 'trends', 'men', 'women', 'kids'],
                    'business_hours' => collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
                        ->map(fn (string $day) => ['day' => $day, 'open' => '10:00', 'close' => '20:00', 'closed' => $day === 'sunday'])
                        ->all(),
                    'delivery_methods' => ['pickup', 'delivery', 'courier', 'shipping'],
                    'delivery_minor' => 800,
                    'courier_minor' => 1200,
                    'shipping_minor' => 1890,
                    'featured_coupon_code' => 'DEMO10',
                    'location' => 'Local sintético · Lima',
                    'contact_email' => 'demostracion@example.test',
                    'onboarding_completed_at' => now()->toIso8601String(),
                    'yape_instructions' => 'DEMOSTRACIÓN: no realizar pagos reales.',
                    'bcp_instructions' => 'DEMOSTRACIÓN: no realizar pagos reales.',
                ];
                $id = DB::table('tenants')->insertGetId(['name' => $name, 'slug' => 'demo-'.$slug.'-'.$suffix, 'settings' => json_encode($settings)]);
                DB::table('memberships')->insert(['tenant_id' => $id, 'user_id' => $admin->id, 'role' => 'owner']);
                DB::select("select set_config('app.tenant_id', ?, true)", [(string) $id]);
                DB::table('coupons')->insert([
                    'tenant_id' => $id,
                    'code' => 'DEMO10',
                    'percent' => 10,
                    'minimum_minor' => 3000,
                    'usage_limit' => 100,
                    'starts_at' => now()->subDay(),
                    'ends_at' => now()->addMonth(),
                    'channel' => 'web',
                ]);
                $warehouse = DB::table('warehouses')->insertGetId(['tenant_id' => $id, 'name' => 'Almacén principal', 'branch' => 'Local de pruebas']);
                if ($slug === 'moda') {
                    DB::table('warehouses')->insert(['tenant_id' => $id, 'name' => 'Almacén auxiliar', 'branch' => 'Local de pruebas']);
                }
                $fashionVariants = [
                    ['label' => 'Azul M', 'size' => 'M', 'color' => 'Azul'],
                    ['label' => 'Azul L', 'size' => 'L', 'color' => 'Azul'],
                    ['label' => 'Negro M', 'size' => 'M', 'color' => 'Negro'],
                    ['label' => 'Negro L', 'size' => 'L', 'color' => 'Negro'],
                    ['label' => 'Blanco M', 'size' => 'M', 'color' => 'Blanco'],
                    ['label' => 'Blanco L', 'size' => 'L', 'color' => 'Blanco'],
                ];
                $footwearVariants = [
                    ['label' => 'Oxford', 'size' => '39', 'color' => 'Café'],
                    ['label' => 'Mocasín', 'size' => '40', 'color' => 'Avellana'],
                    ['label' => 'Botín', 'size' => '41', 'color' => 'Negro'],
                    ['label' => 'Derby', 'size' => '42', 'color' => 'Cognac'],
                    ['label' => 'Sneaker', 'size' => '40', 'color' => 'Blanco'],
                    ['label' => 'Loafer', 'size' => '41', 'color' => 'Borgoña'],
                ];
                foreach (range(1, 6) as $index) {
                    $variant = ($slug === 'moda' ? $fashionVariants : $footwearVariants)[$index - 1];
                    $price = $slug === 'moda' ? 3500 : 28900;
                    $product = DB::table('products')->insertGetId(['tenant_id' => $id, 'sku' => strtoupper($slug).'-'.$index, 'name' => ($slug === 'moda' ? 'Polo algodón · ' : 'Calzado editorial · ').$variant['label'], 'variant_group' => $slug === 'moda' ? 'Polo algodón' : 'Colección Atelier', 'size' => $variant['size'], 'color' => $variant['color'], 'description' => 'Producto sintético para verificar el recorrido de compra. No corresponde a una oferta comercial real.', 'category' => $slug === 'moda' ? 'Polos' : 'Calzado', 'audience' => $slug === 'moda' ? ($index <= 3 ? 'women' : 'men') : 'unisex', 'trending' => $index === 3 || $index === 5, 'price_minor' => $price, 'compare_at_minor' => $index % 2 === 0 ? $price + ($slug === 'moda' ? 1400 : 6000) : null, 'published' => true]);
                    $commerce->run($id, (string) Str::uuid(), 'demo_receipt', [], fn () => $commerce->receive($id, ['warehouse_id' => $warehouse, 'product_id' => $product, 'quantity' => 20, 'unit_cost_minor' => $slug === 'moda' ? 1800 : 15000, 'supplier' => 'Proveedor sintético', 'reference' => 'DEMO-'.$index]));
                }
                DB::table('customers')->insert(['tenant_id' => $id, 'name' => 'Cliente de demostración', 'phone' => '000000000', 'credit_limit_minor' => 50000]);
                $companies[$slug] = ['id' => $id, 'slug' => 'demo-'.$slug.'-'.$suffix];
            }

            return [
                'accounts' => [
                    'admin' => ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email, 'password' => $adminPassword, 'totp' => $adminTotp, 'role' => 'store_owner'],
                    'superadmin' => ['id' => $superAdmin->id, 'name' => $superAdmin->name, 'email' => $superAdmin->email, 'password' => $superAdminPassword, 'totp' => $superAdminTotp, 'role' => 'platform_owner'],
                ],
                'companies' => $companies,
            ];
        });
        file_put_contents($output, json_encode($result, JSON_THROW_ON_ERROR));
        $this->info('Synthetic fixtures created in sas_san_test. Credentials were not printed.');

        return self::SUCCESS;
    }
}
