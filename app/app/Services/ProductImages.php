<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImages
{
    public function store(int $tenant, int $productId, UploadedFile $file): void
    {
        $commerce = app(Commerce::class);
        $size = getimagesize($file->getRealPath());
        if (! $size || $size[0] * $size[1] > 10000000 || max($size[0], $size[1]) > 4000) {
            $commerce->fail('Imagen demasiado grande: máximo 4000 px y 10 megapíxeles.');
        }
        if (! extension_loaded('gd')) {
            $commerce->fail('El entorno no tiene disponible el procesador de imágenes GD.');
        }
        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) {
            $commerce->fail('No se pudo leer la imagen. Intenta cargarla nuevamente.');
        }
        $image = imagecreatefromstring($bytes);
        if (! $image) {
            $commerce->fail('El contenido no es una imagen válida.');
        }
        $ratio = min(1, 1200 / max($size[0], $size[1]));
        $scaled = imagescale($image, max(1, (int) ($size[0] * $ratio)), max(1, (int) ($size[1] * $ratio)));
        if (! $scaled) {
            $commerce->fail('No se pudo procesar la imagen.');
        }
        ob_start();
        imagewebp($scaled, null, 82);
        $content = ob_get_clean();
        imagedestroy($image);
        imagedestroy($scaled);
        if (! $content) {
            $commerce->fail('No se pudo guardar la imagen procesada.');
        }
        $path = $tenant.'/catalog/'.Str::uuid().'.webp';
        if (! Storage::disk('local')->put($path, $content)) {
            $commerce->fail('No se pudo guardar la foto. Intenta nuevamente.');
        }
        DB::table('product_images')->insert(['tenant_id' => $tenant, 'product_id' => $productId, 'path' => $path]);
        DB::table('products')->where('tenant_id', $tenant)->where('id', $productId)->whereNull('image_path')->update(['image_path' => $path]);
        $commerce->audit($tenant, 'product_image_uploaded', (string) $productId);

    }
}
