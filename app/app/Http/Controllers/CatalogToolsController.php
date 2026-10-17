<?php

namespace App\Http\Controllers;

use App\Services\Commerce;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CatalogToolsController extends Controller
{
    private function owner(Request $request): int
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);

        return $request->attributes->get('tenant')->id;
    }

    public function update(Request $request, string $slug, int $productId, Commerce $commerce): RedirectResponse
    {
        $tenant = $this->owner($request);
        DB::select('SELECT pg_advisory_xact_lock(?)', [$tenant]);
        abort_unless(DB::table('products')->where('tenant_id', $tenant)->where('id', $productId)->exists(), 404);
        $data = $request->validate(['sku' => ['required', 'regex:/^[A-Za-z0-9._-]{1,64}$/', Rule::unique('products', 'sku')->where('tenant_id', $tenant)->ignore($productId)], 'name' => 'required|string|max:160', 'price_minor' => 'required|integer|min:0|max:999999999', 'published' => 'required|boolean', 'category' => 'nullable|string|max:100', 'variant_group' => 'nullable|string|max:100', 'description' => 'nullable|string|max:3000']);
        foreach (['category', 'variant_group', 'description'] as $field) {
            $data[$field] ??= '';
        }
        DB::table('products')->where('tenant_id', $tenant)->where('id', $productId)->update($data);
        $commerce->audit($tenant, 'product_updated', (string) $productId);

        return back()->with('success', 'Producto actualizado. Las ventas anteriores conservan sus precios.');
    }

    public function preview(Request $request, Commerce $commerce): RedirectResponse
    {
        $tenant = $this->owner($request);
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $handle = fopen($file->getRealPath(), 'rb');
        if (! $handle) {
            $commerce->fail('No se pudo leer el archivo.');
        }
        try {
            $header = fgetcsv($handle, 8192, ',', '"', '');
            if ($header) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }
            if ($header !== ['sku', 'nombre', 'precio', 'categoria', 'grupo', 'publicado']) {
                $commerce->fail('Encabezado requerido: sku,nombre,precio,categoria,grupo,publicado. El precio va en soles con punto decimal.');
            }
            $rows = [];
            $seen = [];
            while (($row = fgetcsv($handle, 8192, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                if (count($rows) >= 2000 || count($row) !== 6) {
                    $commerce->fail('Máximo 2000 filas; cada fila debe tener seis columnas.');
                }
                [$sku,$name,$price,$category,$group,$published] = $row;
                if (! preg_match('/^([0-9]{1,7})(?:\.([0-9]{1,2}))?$/', $price, $parts)) {
                    $commerce->fail('Precio inválido en la fila '.(count($rows) + 2));
                }
                $minor = (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
                $data = ['sku' => $sku, 'name' => $name, 'price_minor' => $minor, 'category' => $category, 'variant_group' => $group, 'published' => $published];
                $validator = Validator::make($data, ['sku' => 'required|regex:/^[A-Za-z0-9._-]{1,64}$/', 'name' => 'required|string|max:160', 'price_minor' => 'required|integer|min:0|max:999999999', 'category' => 'nullable|string|max:100', 'variant_group' => 'nullable|string|max:100', 'published' => 'required|boolean']);
                if ($validator->fails() || isset($seen[$sku])) {
                    $commerce->fail('Fila inválida o SKU repetido en la fila '.(count($rows) + 2));
                }
                $seen[$sku] = true;
                $data['published'] = $published === '1';
                $rows[] = $data;
            }
        } finally {
            fclose($handle);
        }
        if (! $rows) {
            $commerce->fail('El archivo no contiene productos.');
        }
        $token = (string) Str::uuid();
        $path = $tenant.'/imports/'.$token.'.json';
        Storage::disk('local')->put($path, json_encode($rows, JSON_THROW_ON_ERROR));
        $request->session()->put('import.'.$tenant, ['token' => $token, 'path' => $path, 'expires' => time() + 3600, 'count' => count($rows), 'sample' => array_slice($rows, 0, 15)]);

        return back()->with('success', 'Archivo validado. Revisa la muestra y confirma para aplicar.');
    }

    public function commit(Request $request, Commerce $commerce): RedirectResponse
    {
        $tenant = $this->owner($request);
        $data = $request->validate(['token' => 'required|uuid']);
        $preview = $request->session()->get('import.'.$tenant);
        abort_unless(is_array($preview) && hash_equals($preview['token'], $data['token']) && $preview['expires'] > time(), 403);
        $commerce->run($tenant, $data['token'], 'catalog_import', ['actor' => $request->user()->id, 'token' => $data['token']], function () use ($preview, $tenant, $commerce) {
            abort_unless(Storage::disk('local')->exists($preview['path']), 410);
            $rows = json_decode(Storage::disk('local')->get($preview['path']), true, 512, JSON_THROW_ON_ERROR);
            $existing = DB::table('products')->where('tenant_id', $tenant)->pluck('sku')->all();
            $new = count(array_filter($rows, fn ($row) => ! in_array($row['sku'], $existing, true)));
            $plan = DB::table('tenants')->where('id', $tenant)->value('plan');
            if (count($existing) + $new > DB::table('plans')->where('slug', $plan)->value('product_limit')) {
                $commerce->fail('El archivo supera el límite de productos del plan.');
            }
            foreach ($rows as $row) {
                DB::table('products')->updateOrInsert(['tenant_id' => $tenant, 'sku' => $row['sku']], $row);
            }
            DB::afterCommit(fn () => Storage::disk('local')->delete($preview['path']));

            return ['count' => count($rows)];
        });

        return back()->with('success', 'Catálogo importado sin alterar costos ni historial de ventas.');
    }

    public function image(Request $request, string $slug, int $productId, Commerce $commerce): RedirectResponse
    {
        $tenant = $this->owner($request);
        DB::select('SELECT pg_advisory_xact_lock(?)', [$tenant]);
        abort_unless(DB::table('products')->where('tenant_id', $tenant)->where('id', $productId)->exists(), 404);
        if (DB::table('product_images')->where('tenant_id', $tenant)->where('product_id', $productId)->count() >= 8) {
            $commerce->fail('Máximo ocho imágenes por producto.');
        }
        $request->validate(['image' => 'required|file|mimes:jpg,jpeg,png,webp|max:3072']);
        $file = $request->file('image');
        abort_unless($file instanceof UploadedFile, 422);
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
        Storage::disk('local')->put($path, $content);
        DB::table('product_images')->insert(['tenant_id' => $tenant, 'product_id' => $productId, 'path' => $path]);
        DB::table('products')->where('tenant_id', $tenant)->where('id', $productId)->whereNull('image_path')->update(['image_path' => $path]);
        $commerce->audit($tenant, 'product_image_uploaded', (string) $productId);

        return back()->with('success', 'Foto procesada y guardada sin metadatos originales.');
    }
}
