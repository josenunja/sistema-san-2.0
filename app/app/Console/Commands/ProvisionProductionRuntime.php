<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProvisionProductionRuntime extends Command
{
    protected $signature = 'business:provision-runtime';

    protected $description = 'Provisionar runtime con secreto separado del propietario SQL';

    public function handle(): int
    {
        $file = getenv('RUNTIME_DB_PASSWORD_FILE');
        if (! $file || ! is_readable($file)) {
            $this->error('Falta el archivo privado de contraseña runtime.');

            return self::FAILURE;
        }
        $content = file_get_contents($file);
        if ($content === false) {
            $this->error('No se pudo leer el secreto runtime.');

            return self::FAILURE;
        }
        $password = trim($content);
        if (strlen($password) < 24 || hash_equals((string) config('database.connections.pgsql.password'), $password)) {
            $this->error('Runtime requiere una contraseña fuerte distinta de la del propietario SQL.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($password) {
            if (! DB::select("SELECT 1 FROM pg_roles WHERE rolname='sas_runtime'")) {
                DB::statement('CREATE ROLE sas_runtime LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS');
            }
            DB::statement('ALTER ROLE sas_runtime NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS PASSWORD '.DB::connection()->getPdo()->quote($password));
            DB::statement('GRANT USAGE ON SCHEMA public TO sas_runtime');
            DB::statement('GRANT SELECT,INSERT,UPDATE,DELETE ON ALL TABLES IN SCHEMA public TO sas_runtime');
            DB::statement('REVOKE ALL ON migrations FROM sas_runtime');
            DB::statement('GRANT SELECT ON migrations TO sas_runtime');
            DB::statement('GRANT USAGE,SELECT ON ALL SEQUENCES IN SCHEMA public TO sas_runtime');
        });
        $this->info('Runtime provisionado. Las migraciones siguen siendo responsabilidad de sas_owner.');

        return self::SUCCESS;
    }
}
