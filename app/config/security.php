<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Verificación en dos pasos obligatoria
    |--------------------------------------------------------------------------
    |
    | Con REQUIRE_MFA=true (valor por defecto) el personal debe activar MFA
    | antes de administrar una tienda o la plataforma. Con REQUIRE_MFA=false
    | la gestión no exige MFA; las cuentas que ya lo activaron conservan su
    | desafío de inicio de sesión. En producción el valor se ignora y la
    | exigencia siempre permanece activa (ver User::mfaRequired()).
    |
    */
    'require_mfa' => (bool) env('REQUIRE_MFA', true),
];
