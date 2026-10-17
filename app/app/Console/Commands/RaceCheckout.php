<?php

namespace App\Console\Commands;

use App\Services\Commerce;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class RaceCheckout extends Command
{
    protected $signature = 'testing:stock-race {--child=} {--tenant=} {--warehouse=} {--product=} {--gate=}';

    protected $description = 'Test last-unit concurrency using separate PostgreSQL processes, isolated testing database only';

    public function handle(Commerce $commerce): int
    {
        if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'sas_san_test') {
            $this->error('Requires APP_ENV=testing and DB_DATABASE=sas_san_test.');

            return self::FAILURE;
        }
        if ($this->option('child')) {
            $gate = $this->textOption('gate');
            if (! str_starts_with($gate, sys_get_temp_dir().DIRECTORY_SEPARATOR.'sas-race-')) {
                return self::FAILURE;
            }
            file_put_contents($gate.'.'.$this->textOption('child').'.ready', 'ready');
            $limit = microtime(true) + 15;
            while (! file_exists($gate) && microtime(true) < $limit) {
                usleep(20000);
            }
            if (! file_exists($gate)) {
                return self::FAILURE;
            }
            try {
                DB::transaction(function () use ($commerce) {
                    $tenant = (int) $this->option('tenant');
                    DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant]);
                    $commerce->run($tenant, (string) Str::uuid(), 'race_checkout', [], fn () => $commerce->createOrder($tenant, ['warehouse_id' => (int) $this->option('warehouse'), 'customer_name' => 'Race fixture', 'phone' => '000000000', 'delivery_method' => 'pickup', 'items' => [['product_id' => (int) $this->option('product'), 'quantity' => 1]]], false));
                });
                $this->line('RESERVED');
            } catch (ValidationException) {
                $this->line('SOLD_OUT');
            }

            return self::SUCCESS;
        }
        $fixture = DB::transaction(function () use ($commerce) {
            $tenant = DB::table('tenants')->insertGetId(['name' => 'Isolated race fixture', 'slug' => 'race-'.Str::lower(Str::random(12))]);
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant]);
            $warehouse = DB::table('warehouses')->insertGetId(['tenant_id' => $tenant, 'name' => 'Race', 'branch' => 'Testing']);
            $product = DB::table('products')->insertGetId(['tenant_id' => $tenant, 'sku' => 'LAST', 'name' => 'Last unit', 'price_minor' => 100, 'published' => true]);
            $commerce->run($tenant, (string) Str::uuid(), 'receipt', [], fn () => $commerce->receive($tenant, ['warehouse_id' => $warehouse, 'product_id' => $product, 'quantity' => 1, 'unit_cost_minor' => 50, 'supplier' => 'Synthetic', 'reference' => 'RACE']));

            return compact('tenant', 'warehouse', 'product');
        });
        $gate = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sas-race-'.Str::uuid();
        $processes = [];
        foreach ([1, 2] as $child) {
            $args = [PHP_BINARY, base_path('artisan'), 'testing:stock-race', '--child='.$child, '--gate='.$gate, '--tenant='.$fixture['tenant'], '--warehouse='.$fixture['warehouse'], '--product='.$fixture['product']];
            $process = new Process($args, base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'sas_san_test']);
            $process->setTimeout(25);
            $process->start();
            $processes[] = $process;
        }
        $limit = microtime(true) + 15;
        while ((! file_exists($gate.'.1.ready') || ! file_exists($gate.'.2.ready')) && microtime(true) < $limit) {
            usleep(20000);
        }
        file_put_contents($gate, 'go');
        $outputs = [];
        foreach ($processes as $process) {
            $process->wait();
            $outputs[] = trim($process->getOutput());
            if (! $process->isSuccessful()) {
                $this->error($process->getErrorOutput());
            }
        }
        sort($outputs);
        $valid = DB::transaction(function () use ($fixture) {
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $fixture['tenant']]);

            return DB::table('orders')->where('tenant_id', $fixture['tenant'])->count() === 1 && (int) DB::table('stock_positions')->where('tenant_id', $fixture['tenant'])->value('reserved') === 1;
        });
        foreach ([$gate, $gate.'.1.ready', $gate.'.2.ready'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        if ($outputs !== ['RESERVED', 'SOLD_OUT'] || ! $valid) {
            $this->error('Concurrency check failed: '.json_encode($outputs));

            return self::FAILURE;
        }
        $this->info('PASS: two independent processes raced for the last unit; exactly one reservation committed. Synthetic fixture retained only in sas_san_test.');

        return self::SUCCESS;
    }

    private function textOption(string $name): string
    {
        $value = $this->option($name);
        if (! is_string($value)) {
            throw new \InvalidArgumentException('Missing option '.$name);
        }

        return $value;
    }
}
