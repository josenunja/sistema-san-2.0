<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProvisionRuntime extends Command
{
    protected $signature = 'platform:runtime';

    protected $description = 'Provision the restricted local PostgreSQL runtime role';

    public function handle(): int
    {
        if (! app()->environment('local', 'native')) {
            $this->error('This bootstrap command is local only.');

            return self::FAILURE;
        }
        $password = DB::connection()->getPdo()->quote(config('database.connections.pgsql.password'));
        if (! DB::select("SELECT 1 FROM pg_roles WHERE rolname = 'sas_runtime'")) {
            DB::statement('CREATE ROLE sas_runtime LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS');
        }
        DB::statement('ALTER ROLE sas_runtime PASSWORD '.$password);
        DB::statement('GRANT USAGE ON SCHEMA public TO sas_runtime');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO sas_runtime');
        DB::statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO sas_runtime');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO sas_runtime');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO sas_runtime');
        $this->info('Runtime provisioned; migrations must continue to use sas_owner.');

        return self::SUCCESS;
    }
}
