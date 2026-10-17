<?php

namespace Tests\Unit;

use App\Services\Fiscal\FiscalResult;
use App\Services\Fiscal\UnconfiguredProvider;
use PHPUnit\Framework\TestCase;

class FiscalTest extends TestCase
{
    public function test_unconfigured_provider_never_reports_acceptance(): void
    {
        $provider = new UnconfiguredProvider;
        $this->assertSame('unconfigured', $provider->submit(1, 'document-a', [])->status);
        $this->assertSame('unconfigured', $provider->status(2, 'document-b')->status);
    }

    public function test_acceptance_without_provider_evidence_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FiscalResult('accepted');
    }
}
