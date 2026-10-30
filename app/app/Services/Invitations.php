<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Invitations
{
    public function ensureCapacity(int $tenant, ?string $except = null): void
    {
        $limit = app(BusinessLimits::class)->limit($tenant, 'user');
        $pending = DB::table('invitations')->where('tenant_id', $tenant)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())
            ->when($except !== null, fn ($query) => $query->where('id', '!=', $except))->count();
        if (DB::table('memberships')->where('tenant_id', $tenant)->count() + $pending >= $limit) {
            app(Commerce::class)->fail('El plan no permite más usuarios o invitaciones pendientes.');
        }
    }

    /** @return Collection<int, \stdClass> */
    public function listing(int $tenant): Collection
    {
        return DB::table('invitations')->where('tenant_id', $tenant)->orderByDesc('created_at')->orderByDesc('id')->limit(100)
            ->get(['id', 'tenant_id', 'email', 'role', 'expires_at', 'accepted_at', 'revoked_at', 'created_at', 'last_sent_at'])
            ->map(function ($invite) {
                $invite->status = $this->status($invite);

                return $invite;
            });
    }

    public function status(\stdClass $invite): string
    {
        return $invite->accepted_at ? 'accepted' : ($invite->revoked_at ? 'revoked' : (now()->greaterThanOrEqualTo($invite->expires_at) ? 'expired' : 'pending'));
    }
}
