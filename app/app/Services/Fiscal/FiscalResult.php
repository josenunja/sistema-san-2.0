<?php

namespace App\Services\Fiscal;

final readonly class FiscalResult
{
    public function __construct(public string $status, public ?string $providerReference = null, public ?string $message = null)
    {
        if (! in_array($status, ['unconfigured', 'pending', 'sent', 'accepted', 'rejected', 'error'], true)) {
            throw new \InvalidArgumentException('Unknown fiscal state.');
        }
        if ($status === 'accepted' && ! $providerReference) {
            throw new \InvalidArgumentException('Acceptance requires an authoritative provider reference.');
        }
    }
}
