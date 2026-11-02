<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FreshInstallation;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MarketplaceHomeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('MarketplaceHome requires sas_san_test.');
        }
        DB::beginTransaction();
        $this->withoutVite();
        config(['business.single' => false]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_platform_home_shows_the_marketplace_when_the_installation_already_has_accounts(): void
    {
        User::factory()->create();
        $this->app->detectEnvironment(fn () => 'native');

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Welcome'));
    }

    public function test_fresh_platform_installation_guides_to_the_initial_setup(): void
    {
        $this->mock(FreshInstallation::class, fn ($mock) => $mock->shouldReceive('pending')->andReturn(true));

        $this->get('/')->assertRedirect('/instalacion');
    }

    public function test_fresh_installation_is_detected_only_in_local_environments_without_accounts(): void
    {
        $detector = new FreshInstallation;
        $hasUsers = DB::table('users')->exists();

        $this->app->detectEnvironment(fn () => 'production');
        $this->assertFalse($detector->pending());

        $this->app->detectEnvironment(fn () => 'native');
        $this->assertSame(! $hasUsers, $detector->pending());
    }
}
