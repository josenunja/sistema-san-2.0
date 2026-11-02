<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FreshInstallation
{
    /**
     * Una instalación local recién descargada aún no tiene cuentas: debe pasar primero por /instalacion.
     */
    public function pending(): bool
    {
        return app()->environment('local', 'native') && ! DB::table('users')->exists();
    }
}
