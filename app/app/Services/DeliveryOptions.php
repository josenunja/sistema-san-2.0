<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DeliveryOptions
{
    /** @param array<string,mixed> $data
     * @return array{fee_minor:int,zone_id:?int,slot_id:?int,label:string}
     */
    public function quote(int $tenant, array $data): array
    {
        $settings = json_decode(DB::table('tenants')->where('id', $tenant)->value('settings'), true) ?: [];
        $methods = $settings['delivery_methods'] ?? ['pickup', ...(isset($settings['delivery_minor']) ? ['delivery'] : [])];
        $method = $data['delivery_method'];
        if (! in_array($method, $methods, true)) {
            app(Commerce::class)->fail('Este método de entrega no está disponible.');
        }
        $zone = null;
        $slot = null;
        $label = '';
        $zones = DB::table('delivery_zones')->where('tenant_id', $tenant)->where('method', $method)->where('enabled', true);
        if (! empty($data['delivery_zone_id']) || (clone $zones)->exists()) {
            $zone = $zones->where('id', $data['delivery_zone_id'] ?? 0)->first();
            if (! $zone) {
                app(Commerce::class)->fail('Selecciona una zona o punto de recojo disponible.');
            }
            $label = $zone->name.($zone->address ? ' · '.$zone->address : '');
        }
        $slots = DB::table('delivery_slots')->where('tenant_id', $tenant)->where('method', $method)->where('enabled', true);
        if (! empty($data['delivery_slot_id']) || (clone $slots)->exists()) {
            $slot = $slots->where('id', $data['delivery_slot_id'] ?? 0)->where('starts_at', '>', now())->first();
            if (! $slot || DB::table('orders')->where('tenant_id', $tenant)->where('delivery_slot_id', $slot->id)->whereNotIn('status', ['cancelled', 'expired'])->count() >= $slot->capacity) {
                app(Commerce::class)->fail('La franja de atención no está disponible o agotó sus cupos.');
            }
            $label .= ' · '.Carbon::parse($slot->starts_at)->timezone('America/Lima')->format('d/m/Y H:i');
        }
        if ($zone === null && $method !== 'pickup' && ! isset($settings[$method.'_minor'])) {
            app(Commerce::class)->fail('No se ha configurado la tarifa de este método.');
        }

        return ['fee_minor' => $zone ? (int) $zone->fee_minor : ($method === 'pickup' ? 0 : (int) $settings[$method.'_minor']), 'zone_id' => $zone ? (int) $zone->id : null, 'slot_id' => $slot ? (int) $slot->id : null, 'label' => mb_substr(trim($label, ' ·'), 0, 500)];
    }
}
