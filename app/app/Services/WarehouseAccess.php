<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class WarehouseAccess
{
    /** @return list<int>|null */
    public function ids(int $tenant): ?array
    {
        $member = request()->attributes->get('membership');
        if (! $member || (int) $member->tenant_id !== $tenant || $member->role === 'owner') {
            return null;
        }
        if ($member->warehouse_ids === null) {
            return null;
        }

        return array_values(array_map('intval', json_decode($member->warehouse_ids, true, 512, JSON_THROW_ON_ERROR)));
    }

    public function check(int $tenant, int $warehouse): void
    {
        $ids = $this->ids($tenant);
        abort_unless($ids === null || in_array($warehouse, $ids, true), 403, 'No tienes acceso a este almacén.');
        abort_unless(DB::table('warehouses')->where('tenant_id', $tenant)->where('id', $warehouse)->exists(), 404);
    }
}
