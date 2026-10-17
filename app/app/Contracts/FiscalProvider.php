<?php

namespace App\Contracts;

use App\Services\Fiscal\FiscalResult;

interface FiscalProvider
{
    /** The provider must deduplicate the stable document UUID; never allocate a new fiscal number on retry.
     * @param  array<string, mixed>  $document
     */
    public function submit(int $tenant, string $documentId, array $document): FiscalResult;

    public function status(int $tenant, string $documentId): FiscalResult;
}
