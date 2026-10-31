<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProvisionRuntime extends Command
{
    protected $signature = 'platform:runtime {--runtime-password-file= : Archivo privado con contraseña del runtime}';

    protected $description = 'Provision the restricted local PostgreSQL runtime role';

    public function handle(): int
    {
        if (! app()->environment('local', 'native')) {
            $this->error('This bootstrap command is local only.');

            return self::FAILURE;
        }
        $runtimePassword = (string) config('database.connections.pgsql.password');
        if ($file = $this->option('runtime-password-file')) {
            if (! is_string($file) || ! is_readable($file)) {
                $this->error('No se puede leer la contraseña privada del runtime.');

                return self::FAILURE;
            }
            $runtimePassword = trim((string) file_get_contents($file));
            if (strlen($runtimePassword) < 24 || hash_equals((string) config('database.connections.pgsql.password'), $runtimePassword)) {
                $this->error('El runtime requiere una contraseña fuerte diferente de sas_owner.');

                return self::FAILURE;
            }
        }
        $password = DB::connection()->getPdo()->quote($runtimePassword);
        if (! DB::select("SELECT 1 FROM pg_roles WHERE rolname = 'sas_runtime'")) {
            DB::statement('CREATE ROLE sas_runtime LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS');
        }
        DB::statement('ALTER ROLE sas_runtime NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS PASSWORD '.$password);
        DB::statement('GRANT USAGE ON SCHEMA public TO sas_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO sas_runtime');
        DB::statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO sas_runtime');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO sas_runtime');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO sas_runtime');
        DB::statement('REVOKE ALL ON migrations FROM sas_runtime');
        DB::statement('GRANT SELECT ON migrations TO sas_runtime');
        $this->info('Runtime provisioned; migrations must continue to use sas_owner.');

        return self::SUCCESS;
    }
}
