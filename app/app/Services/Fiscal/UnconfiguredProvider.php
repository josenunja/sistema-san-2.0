<?php

namespace App\Services\Fiscal;

use App\Contracts\FiscalProvider;

final class UnconfiguredProvider implements FiscalProvider
{
    public function submit(int $tenant, string $documentId, array $document): FiscalResult
    {
        return $this->status($tenant, $documentId);
    }

    public function status(int $tenant, string $documentId): FiscalResult
    {
        return new FiscalResult('unconfigured', message: 'No se ha contratado ni configurado un proveedor fiscal. No se transmitió ningún documento.');
    }
}
