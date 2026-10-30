<?php

namespace App\Support;

final class EnvironmentSecret
{
    public static function value(string $name, mixed $file, mixed $fallback): ?string
    {
        if (is_string($file) && $file !== '') {
            if (! is_readable($file)) {
                throw new \RuntimeException('No se puede leer el archivo privado de '.$name.'.');
            }
            $value = file_get_contents($file);
            if ($value === false || trim($value) === '') {
                throw new \RuntimeException('El archivo privado de '.$name.' está vacío o no se pudo leer.');
            }

            return trim($value);
        }

        return is_string($fallback) ? $fallback : null;
    }
}
