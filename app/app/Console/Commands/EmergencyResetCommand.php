<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class EmergencyResetCommand extends Command
{
    protected $signature = 'auth:emergency-reset 
                            {email? : Correo electrónico del usuario} 
                            {--clear-2fa : Restablecer y desactivar autenticación de dos factores (MFA)} 
                            {--new-password= : Especificar nueva contraseña directamente}';

    protected $description = 'Restablecimiento de emergencia de credenciales y MFA para administradores locales';

    public function handle(): int
    {
        $this->warn('===========================================================');
        $this->warn('SAS SAN - RESTABLECIMIENTO DE EMERGENCIA DE ACCESO LOCAL');
        $this->warn('===========================================================');
        $this->line('Esta herramienta permite recuperar el acceso de propietarios o administradores');
        $this->line('en instalaciones locales donde el servicio de correo (SMTP) no está activo.');
        $this->line('');

        $email = $this->argument('email');
        if (! $email) {
            $users = User::query()->select(['id', 'name', 'email', 'platform_owner'])->get();
            if ($users->isEmpty()) {
                $this->error('No existen usuarios registrados en la base de datos.');

                return self::FAILURE;
            }

            $this->info('Usuarios registrados en el sistema:');
            foreach ($users as $u) {
                $role = $u->platform_owner ? 'Propietario / Admin' : 'Personal';
                $this->line(" - [ID: {$u->id}] {$u->name} ({$u->email}) [{$role}]");
            }
            $email = $this->ask('Ingrese el correo electrónico del usuario a recuperar');
        }

        $user = User::query()->where('email', strtolower(trim((string) $email)))->first();
        if (! $user) {
            $this->error("No se encontró ningún usuario con el correo: {$email}");

            return self::FAILURE;
        }

        $newPassword = $this->option('new-password');
        if (! $newPassword) {
            $newPassword = $this->secret('Ingrese la nueva contraseña (mínimo 12 caracteres)');
            $confirmation = $this->secret('Confirme la nueva contraseña');
            if ($newPassword !== $confirmation) {
                $this->error('Las contraseñas no coinciden.');

                return self::FAILURE;
            }
        }

        if (strlen((string) $newPassword) < 12) {
            $this->error('La contraseña debe tener al menos 12 caracteres por seguridad.');

            return self::FAILURE;
        }

        $clear2fa = $this->option('clear-2fa');
        if (! $clear2fa && $user->two_factor_confirmed_at) {
            $clear2fa = $this->confirm('¿Desea también restablecer y desactivar el Segundo Factor (MFA) para este usuario?', true);
        }

        DB::transaction(function () use ($user, $newPassword, $clear2fa) {
            $updates = [
                'password' => Hash::make($newPassword),
            ];

            if ($clear2fa) {
                $updates['two_factor_secret'] = null;
                $updates['two_factor_recovery_codes'] = null;
                $updates['two_factor_confirmed_at'] = null;
            }

            $user->forceFill($updates)->save();

            // Auditoría interna
            Log::warning("EMERGENCY_ACCESS_RESET: Acceso restablecido vía consola para el usuario {$user->email} (MFA limpiado: ".($clear2fa ? 'SI' : 'NO').')');
        });

        $this->info('');
        $this->info('===========================================================');
        $this->info("¡ÉXITO! Las credenciales de {$user->email} han sido actualizadas.");
        if ($clear2fa) {
            $this->warn('El segundo factor (MFA) fue desactivado. Se recomienda volver a configurarlo en Seguridad tras iniciar sesión.');
        }
        $this->info('Ya puede iniciar sesión normalmente en la aplicación.');
        $this->info('===========================================================');

        return self::SUCCESS;
    }
}
