<?php

use App\Models\User;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = User::where('email', 'admin@test.com')->first();
if ($user) {
    $codes = [
        'RECOV-1111-2222',
        'RECOV-3333-4444',
        'RECOV-5555-6666',
        'RECOV-7777-8888',
    ];
    $user->forceFill([
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode($codes)),
        'two_factor_confirmed_at' => now(),
    ])->save();
    echo "OK: 2FA activado para {$user->email}\n";
    echo "Recovery codes: " . implode(', ', $codes) . "\n";
} else {
    echo "ERROR: Usuario no encontrado\n";
}
