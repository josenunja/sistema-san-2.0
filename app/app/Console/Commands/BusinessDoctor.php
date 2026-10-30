<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BusinessDoctor extends Command
{
    protected $signature = 'business:doctor {--production : Exigir condiciones mínimas de despliegue}';

    protected $description = 'Comprobar la instalación sin mostrar secretos ni enviar mensajes';

    public function handle(): int
    {
        $checks = [
            'PostgreSQL configurado' => config('database.default') === 'pgsql',
            'Clave de aplicación configurada' => strlen((string) config('app.key')) >= 32,
            'Procesador de imágenes' => extension_loaded('gd'),
            'Cifrado de respaldos' => extension_loaded('sodium'),
            'Almacenamiento escribible' => is_writable(storage_path('app')) && is_writable(storage_path('framework')),
        ];
        try {
            $role = DB::selectOne('SELECT rolsuper,rolbypassrls FROM pg_roles WHERE rolname=current_user');
            $checks['Runtime sin superusuario ni BYPASSRLS'] = $role && ! $role->rolsuper && ! $role->rolbypassrls;
            $migrated = DB::table('migrations')->pluck('migration')->all();
            $checks['Migraciones completas'] = count(array_diff(array_map(fn ($file) => pathinfo($file, PATHINFO_FILENAME), glob(database_path('migrations/*.php')) ?: []), $migrated)) === 0;
            if (config('business.single')) {
                $tenant = DB::table('tenants')->first();
                $checks['Una empresa configurada'] = DB::table('tenants')->count() === 1 && (! config('business.slug') || $tenant?->slug === config('business.slug'));
            }
        } catch (\Throwable) {
            $checks['Base de datos disponible y esquema preparado'] = false;
        }
        if ($this->option('production')) {
            $checks['Entorno production'] = app()->isProduction();
            $checks['Debug desactivado'] = config('app.debug') === false;
            $checks['Dirección HTTPS'] = str_starts_with((string) config('app.url'), 'https://');
            $checks['Cookie segura'] = (bool) config('session.secure');
            $checks['Correo real configurado'] = ! in_array(config('mail.default'), ['log', 'array'], true);
            $checks['Scheduler activo (últimos tres minutos)'] = (int) Cache::get('scheduler:last_heartbeat', 0) > time() - 180;
        }
        foreach ($checks as $name => $passed) {
            $this->line(($passed ? 'OK' : 'PENDIENTE').' · '.$name);
        }
        $this->line('Este diagnóstico no certifica entrega de correo, restauración externa, seguridad integral ni aceptación del negocio.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
