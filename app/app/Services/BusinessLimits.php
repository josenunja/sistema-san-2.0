<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class BusinessLimits
{
    public function limit(int $tenant, string $resource): int
    {
        if (config('business.single')) {
            return (int) config('business.limits.'.$resource);
        }
        $plan = DB::table('tenants')->where('id', $tenant)->value('plan');

        return (int) DB::table('plans')->where('slug', $plan)->value($resource.'_limit');
    }

    /** @return array<string, array<string,int>> */
    public function usage(int $tenant): array
    {
        $result = [];
        foreach (['product' => 'products', 'warehouse' => 'warehouses', 'user' => 'memberships'] as $resource => $table) {
            $used = DB::table($table)->where('tenant_id', $tenant)->count();
            $pending = $resource === 'user' ? DB::table('invitations')->where('tenant_id', $tenant)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count() : 0;
            $result[$resource] = ['used' => $used, 'pending' => $pending, 'limit' => $this->limit($tenant, $resource)];
        }

        return $result;
    }
}
