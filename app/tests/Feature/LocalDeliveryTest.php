<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LocalDeliveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== 'sas_san_test') {
            throw new \RuntimeException('LocalDelivery requiere sas_san_test.');
        }
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_legacy_tools_exit_without_bootstrapping_or_mutating_data(): void
    {
        $before = DB::table('users')->count();
        foreach (['crear-datos-prueba.php', 'activar-2fa.php'] as $file) {
            $process = new Process([PHP_BINARY, base_path('scripts/'.$file)], null, ['DB_DATABASE' => 'never_connect_here']);
            $process->run();
            $this->assertSame(1, $process->getExitCode());
            $this->assertStringNotContainsString('Admin123', $process->getErrorOutput());
        }
        $this->assertSame($before, DB::table('users')->count());
    }

    public function test_recovery_rotates_password_and_sessions_but_preserves_mfa_by_default(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_secret' => encrypt('SYNTHETIC-SECRET'), 'two_factor_confirmed_at' => now()])->save();
        $before = $user->getRawOriginal('two_factor_secret');
        DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $password = Str::random(36);
        $this->artisan('auth:emergency-reset', ['email' => $user->email])
            ->expectsConfirmation('¿Confirma que está autorizado para recuperar esta cuenta en este equipo?', 'yes')
            ->expectsQuestion('Ingrese la nueva contraseña (mínimo 12 caracteres)', $password)
            ->expectsQuestion('Confirme la nueva contraseña', $password)
            ->expectsConfirmation('¿Desea también restablecer y desactivar el Segundo Factor (MFA) para este usuario?', 'no')
            ->assertSuccessful();
        $user->refresh();
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertSame($before, $user->getRawOriginal('two_factor_secret'));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_recovery_cannot_run_in_production(): void
    {
        app()->instance('env', 'production');
        $this->artisan('auth:emergency-reset', ['email' => 'nobody@example.test'])->assertFailed();
    }
}
