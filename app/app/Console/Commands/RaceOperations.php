<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Commerce;
use App\Services\OfflineSales;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class RaceOperations extends Command
{
    protected $signature = 'testing:operation-races {--fixture=} {--child=}';

    protected $description = 'Race coupon, payment, return and offline replay in separate testing processes';

    public function handle(Commerce $commerce, OfflineSales $offline): int
    {
        if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'sas_san_test') {
            $this->error('Requires isolated testing database.');

            return self::FAILURE;
        }
        if ($this->option('child')) {
            $child = $this->option('child');
            if (! is_string($child) || ! in_array($child, ['1', '2'], true)) {
                return self::FAILURE;
            }
            $path = $this->option('fixture');
            if (! is_string($path) || ! str_starts_with($path, sys_get_temp_dir().DIRECTORY_SEPARATOR.'sas-operations-')) {
                return self::FAILURE;
            }
            $fixture = json_decode(file_get_contents($path) ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            file_put_contents($path.'.'.$child.'.ready', 'ready');
            $limit = microtime(true) + 20;
            while (! file_exists($path.'.go') && microtime(true) < $limit) {
                usleep(20000);
            }
            if (! file_exists($path.'.go')) {
                return self::FAILURE;
            }
            Auth::loginUsingId($fixture['user']);
            try {
                DB::transaction(function () use ($fixture, $commerce, $offline) {
                    DB::select("SELECT set_config('app.tenant_id',?,true)", [(string) $fixture['tenant']]);
                    $kind = $fixture['kind'];
                    $data = $fixture['data'];
                    $key = in_array($kind, ['payment', 'offline'], true) ? $fixture['key'] : (string) Str::uuid();
                    $commerce->run($fixture['tenant'], $key, 'race_'.$kind, $data, fn () => match ($kind) {
                        'coupon' => $commerce->createOrder($fixture['tenant'], $data, false),
                        'payment' => $commerce->pay($fixture['tenant'], $data, true),
                        'return' => $commerce->returnItems($fixture['tenant'], $data),
                        'offline' => $offline->sync($fixture['tenant'], $data),
                        default => throw new \InvalidArgumentException('Unknown race fixture kind.'),
                    });
                });
                $this->line('OK');
            } catch (ValidationException) {
                $this->line('REJECTED');
            }

            return self::SUCCESS;
        }
        foreach (['coupon', 'payment', 'return', 'offline'] as $kind) {
            $fixture = $this->fixture($kind, $commerce, $offline);
            $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sas-operations-'.Str::uuid();
            file_put_contents($path, json_encode($fixture, JSON_THROW_ON_ERROR));
            $children = [];
            try {
                foreach ([1, 2] as $child) {
                    $process = new Process([PHP_BINARY, base_path('artisan'), 'testing:operation-races', '--fixture='.$path, '--child='.$child], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'sas_san_test']);
                    $process->setTimeout(35);
                    $process->start();
                    $children[] = $process;
                }
                $limit = microtime(true) + 20;
                while ((! file_exists($path.'.1.ready') || ! file_exists($path.'.2.ready')) && microtime(true) < $limit) {
                    usleep(20000);
                }
                file_put_contents($path.'.go', 'go');
                $outputs = [];
                foreach ($children as $process) {
                    $process->wait();
                    if (! $process->isSuccessful()) {
                        throw new \RuntimeException($process->getErrorOutput());
                    }$outputs[] = trim($process->getOutput());
                }
                sort($outputs);
                $expected = in_array($kind, ['payment', 'offline'], true) ? ['OK', 'OK'] : ['OK', 'REJECTED'];
                $valid = DB::transaction(function () use ($fixture, $kind) {
                    DB::select("SELECT set_config('app.tenant_id',?,true)", [(string) $fixture['tenant']]);

                    return match ($kind) {
                        'coupon' => DB::table('orders')->count() === 1 && (int) DB::table('coupons')->value('used') === 1,
                        'payment' => DB::table('payments')->count() === 1 && (int) DB::table('cash_entries')->sum('amount_minor') === 100,
                        'return' => DB::table('returns')->count() === 1 && (int) DB::table('stock_positions')->sum('physical') === 2,
                        'offline' => DB::table('offline_sales')->count() === 1 && DB::table('sales')->count() === 1 && (int) DB::table('stock_positions')->sum('physical') === 1 && (int) DB::table('stock_positions')->sum('offline') === 0,
                    };
                });
                if ($outputs !== $expected || ! $valid) {
                    throw new \RuntimeException('Race failed: '.$kind.' '.json_encode($outputs));
                }
                $this->info('PASS '.$kind.': independent processes, one commercial effect.');
            } finally {
                foreach ($children as $process) {
                    if ($process->isRunning()) {
                        $process->stop();
                    }
                }
                foreach ([$path, $path.'.1.ready', $path.'.2.ready', $path.'.go'] as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }
        }

        return self::SUCCESS;
    }

    /** @return array<string,mixed> */
    private function fixture(string $kind, Commerce $commerce, OfflineSales $offline): array
    {
        return DB::transaction(function () use ($kind, $commerce, $offline) {
            $user = User::create(['name' => 'Synthetic concurrency operator', 'email' => 'race-'.Str::uuid().'@example.test', 'password' => Str::random(64)]);
            Auth::login($user);
            $tenant = DB::table('tenants')->insertGetId(['name' => 'Isolated '.$kind.' race', 'slug' => 'race-'.Str::lower(Str::random(16))]);
            DB::select("SELECT set_config('app.tenant_id',?,true)", [(string) $tenant]);
            $warehouse = DB::table('warehouses')->insertGetId(['tenant_id' => $tenant, 'name' => 'Race', 'branch' => 'Testing']);
            $product = DB::table('products')->insertGetId(['tenant_id' => $tenant, 'sku' => 'RACE', 'name' => 'Synthetic item', 'price_minor' => 100, 'published' => true]);
            $cash = DB::table('cash_sessions')->insertGetId(['tenant_id' => $tenant, 'user_id' => $user->id, 'warehouse_id' => $warehouse, 'opening_minor' => 0]);
            $commerce->run($tenant, (string) Str::uuid(), 'receive', [], fn () => $commerce->receive($tenant, ['warehouse_id' => $warehouse, 'product_id' => $product, 'quantity' => 2, 'unit_cost_minor' => 50, 'supplier' => 'Synthetic', 'reference' => 'RACE']));
            $orderData = ['warehouse_id' => $warehouse, 'customer_name' => 'Synthetic', 'phone' => '000000000', 'delivery_method' => 'pickup', 'items' => [['product_id' => $product, 'quantity' => 1]], 'cash_session_id' => $cash, 'cash_minor' => 100];
            $key = (string) Str::uuid();
            if ($kind === 'coupon') {
                DB::table('coupons')->insert(['tenant_id' => $tenant, 'code' => 'LAST', 'percent' => 10, 'usage_limit' => 1, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
                $data = [...$orderData, 'coupon' => 'LAST'];
            } elseif ($kind === 'offline') {
                $device = $commerce->run($tenant, (string) Str::uuid(), 'grant', [], fn () => $offline->grant($tenant, ['cash_session_id' => $cash, 'name' => 'Synthetic offline', 'items' => [['product_id' => $product, 'quantity' => 1]]]));
                $data = ['device_id' => $device['id'], 'operation_id' => $key, 'sequence' => 1, 'captured_at' => now()->toIso8601String(), 'total_minor' => 100, 'items' => [['product_id' => $product, 'quantity' => 1]]];
            } else {
                $order = $commerce->run($tenant, (string) Str::uuid(), 'order', [], fn () => $commerce->createOrder($tenant, $orderData, $kind === 'return'));
                $data = $kind === 'payment' ? ['order_id' => $order['id'], 'amount_minor' => 100, 'method' => 'cash', 'cash_session_id' => $cash] : ['line_id' => DB::table('order_lines')->value('id'), 'quantity' => 1, 'condition' => 'available', 'reason' => 'Synthetic concurrent return'];
            }

            return ['tenant' => $tenant, 'user' => $user->id, 'kind' => $kind, 'key' => $key, 'data' => $data];
        });
    }
}
