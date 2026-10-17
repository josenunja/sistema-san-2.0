<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantContextCleanupTest extends TestCase
{
    public function test_database_connection_uses_utc_even_when_host_timezone_differs(): void
    {
        $this->assertSame('UTC', DB::selectOne('SHOW TIME ZONE')->TimeZone);
    }

    public function test_context_does_not_leak_after_commit_or_exception(): void
    {
        $this->assertSame('sas_san_test', DB::connection()->getDatabaseName());
        DB::transaction(fn () => DB::select("select set_config('app.tenant_id', '99', true)"));
        $this->assertContains(DB::selectOne("select current_setting('app.tenant_id', true) as tenant")->tenant, [null, '']);
        try {
            DB::transaction(function () {
                DB::select("select set_config('app.tenant_id', '100', true)");
                throw new \RuntimeException('Expected test exception');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Expected test exception', $exception->getMessage());
        }
        $this->assertContains(DB::selectOne("select current_setting('app.tenant_id', true) as tenant")->tenant, [null, '']);
    }
}
