<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Commerce;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InstallBusiness extends Command
{
    protected $signature = 'business:install';

    protected $description = 'Crear interactivamente la primera empresa y su propietario, sin contraseña universal';

    public function handle(): int
    {
        if (! config('business.single') || DB::table('users')->exists() || DB::table('tenants')->exists()) {
            $this->error('La instalación requiere modo single y una base sin usuarios ni empresas. No se modificaron datos.');

            return self::FAILURE;
        }
        $data = ['business' => $this->ask('Nombre del negocio', config('business.name') ?: null), 'slug' => config('business.slug') ?: $this->ask('Identificador (minúsculas y guiones)'), 'name' => $this->ask('Nombre del propietario'), 'email' => $this->ask('Correo del propietario'), 'password' => $this->secret('Contraseña propia de al menos 12 caracteres'), 'password_confirmation' => $this->secret('Repite tu contraseña')];
        $validator = Validator::make($data, ['business' => 'required|string|max:160', 'slug' => 'required|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:80', 'name' => 'required|string|max:100', 'email' => 'required|email|max:254', 'password' => 'required|string|min:12|max:200|confirmed']);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($data) {
            DB::select('SELECT pg_advisory_xact_lock(0)');
            if (DB::table('users')->exists() || DB::table('tenants')->exists()) {
                throw new \RuntimeException('La instalación ya fue completada en otra sesión.');
            }
            $user = new User;
            $user->forceFill(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password'], 'email_verified_at' => now(), 'platform_owner' => true])->save();
            $id = DB::table('tenants')->insertGetId(['name' => $data['business'], 'slug' => $data['slug'], 'settings' => json_encode(['business_type' => config('business.type'), 'storefront_theme' => 'classic'], JSON_THROW_ON_ERROR)]);
            DB::table('memberships')->insert(['tenant_id' => $id, 'user_id' => $user->id, 'role' => 'owner']);
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $id]);
            app(Commerce::class)->audit($id, 'console_initial_setup');
        });
        $this->info('Cuenta creada por el operador de la instalación. Inicia sesión y activa MFA. La contraseña no se imprime ni se guarda en un archivo de accesos.');

        return self::SUCCESS;
    }
}
