<?php

namespace App\Console\Commands;

use App\Services\Commerce;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExpireReservations extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Release unpaid expired reservations with isolated tenant transactions';

    public function handle(Commerce $commerce): int
    {
        $count = 0;
        foreach (DB::table('tenants')->orderBy('id')->cursor() as $tenant) {
            DB::transaction(function () use ($tenant, $commerce, &$count) {
                DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant->id]);
                DB::select('SELECT pg_advisory_xact_lock(?)', [$tenant->id]);
                foreach (DB::table('orders')->where('tenant_id', $tenant->id)->where('status', 'reserved')->where('expires_at', '<=', now())->orderBy('id')->limit(500)->get() as $order) {
                    if ($commerce->paid($tenant->id, $order->id) > 0) {
                        continue;
                    }
                    $commerce->run($tenant->id, (string) Str::uuid(), 'expire_reservation', ['id' => $order->id], fn () => $commerce->cancel($tenant->id, $order->id, 'Reserva vencida sin cobros verificados', true));
                    $count++;
                }
            });
        }
        $this->info($count.' reservas liberadas. Las reservas pagadas requieren revisión manual.');

        return self::SUCCESS;
    }
}
