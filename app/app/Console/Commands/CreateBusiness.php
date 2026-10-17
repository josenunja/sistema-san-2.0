<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateBusiness extends Command
{
    protected $signature = 'platform:business';

    protected $description = 'Create a business and its owner interactively';

    public function handle(): int
    {
        $data = [
            'name' => $this->ask('Nombre del negocio'),
            'slug' => $this->ask('Identificador de tienda (ejemplo: mi-negocio)'),
            'owner' => $this->ask('Nombre del propietario'),
            'email' => $this->ask('Correo del propietario'),
            'password' => $this->secret('Contraseña (mínimo 12 caracteres)'),
        ];
        $validator = Validator::make($data, [
            'name' => 'required|string|max:160',
            'slug' => 'required|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:80|unique:tenants,slug',
            'owner' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:12|max:200',
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['owner'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
            $tenant = DB::table('tenants')->insertGetId(['name' => $data['name'], 'slug' => $data['slug']]);
            DB::table('memberships')->insert(['tenant_id' => $tenant, 'user_id' => $user->id, 'role' => 'owner']);
        });
        $this->info('Empresa creada. Verifique el correo del propietario antes de gestionar. En local el correo se registra en storage/logs/laravel.log.');

        return self::SUCCESS;
    }
}
