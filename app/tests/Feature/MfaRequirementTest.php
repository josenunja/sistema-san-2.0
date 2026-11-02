<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MfaRequirementTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('MfaRequirement requires sas_san_test.');
        }
        DB::beginTransaction();
        $this->withoutVite();
        $tenant = DB::table('tenants')->insertGetId(['name' => 'Tienda sin MFA', 'slug' => 'mfa-switch-store']);
        $this->owner = User::factory()->create();
        DB::table('memberships')->insert(['tenant_id' => $tenant, 'user_id' => $this->owner->id, 'role' => 'owner']);
        config(['business.single' => true, 'business.slug' => 'mfa-switch-store']);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_mfa_is_required_by_default(): void
    {
        $this->assertTrue(User::mfaRequired());
        $this->assertFalse($this->owner->meetsMfaRequirement());
        $this->actingAs($this->owner)->get('/gestion/mfa-switch-store')->assertRedirect('/settings/security');
    }

    public function test_staff_can_manage_without_mfa_when_the_local_install_disables_it(): void
    {
        config(['security.require_mfa' => false]);

        $this->assertTrue($this->owner->meetsMfaRequirement());
        $this->actingAs($this->owner)->get('/gestion/mfa-switch-store')->assertOk();
        $this->get('/settings/profile')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('businessAccess.requiresMfa', false));
    }

    public function test_login_does_not_force_the_security_page_when_mfa_is_not_required(): void
    {
        config(['security.require_mfa' => false]);

        $this->withSession(['url.intended' => url('/settings/profile')])->post('/login', [
            'email' => $this->owner->email, 'password' => 'password',
        ])->assertRedirect(url('/settings/profile'));
        $this->assertAuthenticatedAs($this->owner);
    }

    public function test_production_ignores_the_switch(): void
    {
        config(['security.require_mfa' => false]);
        $this->app->detectEnvironment(fn () => 'production');

        $this->assertTrue(User::mfaRequired());
        $this->assertFalse($this->owner->meetsMfaRequirement());
        $this->actingAs($this->owner)->get('/gestion/mfa-switch-store')->assertRedirect('/settings/security');
    }
}
