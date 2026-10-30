<?php

namespace App\Http\Controllers;

use App\Services\Commerce;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductMediaController extends Controller
{
    private function product(Request $request, int $productId): \stdClass
    {
        abort_unless(in_array($request->attributes->get('membership')->role, ['owner', 'catalog'], true), 403);
        $tenant = (int) $request->attributes->get('tenant')->id;
        DB::select('SELECT pg_advisory_xact_lock(?)', [$tenant]);
        $product = DB::table('products')->where('tenant_id', $tenant)->where('id', $productId)->first();
        abort_unless($product !== null, 404);

        return $product;
    }

    public function index(Request $request, string $slug, int $productId): Response
    {
        $product = $this->product($request, $productId);
        $images = DB::table('product_images')->where('tenant_id', $product->tenant_id)->where('product_id', $productId)->orderBy('sort_order')->orderBy('id')->get()->map(fn ($image) => ['id' => $image->id, 'alt_text' => $image->alt_text, 'cover' => $image->path === $product->image_path, 'url' => '/tienda/'.$slug.'/producto/'.$productId.'/foto/'.$image->id]);

        return Inertia::render('ProductMedia', ['tenant' => ['name' => $request->attributes->get('tenant')->name, 'slug' => $slug], 'role' => $request->attributes->get('membership')->role, 'product' => ['id' => $product->id, 'name' => $product->name], 'images' => $images]);
    }

    public function update(Request $request, string $slug, int $productId, int $imageId, Commerce $commerce): RedirectResponse
    {
        $product = $this->product($request, $productId);
        $image = DB::table('product_images')->where('tenant_id', $product->tenant_id)->where('product_id', $productId)->where('id', $imageId)->first();
        abort_unless($image !== null, 404);
        $data = $request->validate(['alt_text' => 'required|string|max:160', 'sort_order' => 'required|integer|min:0|max:1000', 'cover' => 'required|boolean']);
        DB::table('product_images')->where('tenant_id', $product->tenant_id)->where('id', $imageId)->update(['alt_text' => $data['alt_text'], 'sort_order' => $data['sort_order']]);
        if ($data['cover']) {
            DB::table('products')->where('tenant_id', $product->tenant_id)->where('id', $productId)->update(['image_path' => $image->path]);
        }
        $commerce->audit($product->tenant_id, 'product_image_updated', (string) $imageId);

        return back()->with('success', 'Foto actualizada.');
    }

    public function destroy(Request $request, string $slug, int $productId, int $imageId, Commerce $commerce): RedirectResponse
    {
        $product = $this->product($request, $productId);
        $image = DB::table('product_images')->where('tenant_id', $product->tenant_id)->where('product_id', $productId)->where('id', $imageId)->first();
        abort_unless($image !== null, 404);
        DB::table('product_images')->where('tenant_id', $product->tenant_id)->where('id', $imageId)->delete();
        if ($product->image_path === $image->path) {
            $next = DB::table('product_images')->where('tenant_id', $product->tenant_id)->where('product_id', $productId)->orderBy('sort_order')->orderBy('id')->value('path');
            DB::table('products')->where('tenant_id', $product->tenant_id)->where('id', $productId)->update(['image_path' => $next]);
        }
        DB::afterCommit(fn () => Storage::disk('local')->delete($image->path));
        $commerce->audit($product->tenant_id, 'product_image_deleted', (string) $imageId);

        return back()->with('success', 'Foto eliminada. El producto y sus ventas se conservan.');
    }
}
