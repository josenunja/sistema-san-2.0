<?php

namespace App\Http\Controllers;

use App\Services\Commerce;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __invoke(Request $request, string $slug, string $scope): StreamedResponse
    {
        abort_unless($request->attributes->get('membership')->role === 'owner', 403);
        $tenant = $request->attributes->get('tenant')->id;
        $query = match ($scope) {
            'productos' => DB::table('products')->where('tenant_id', $tenant)->orderBy('sku')->select(['sku', 'name', 'category', 'variant_group', 'price_minor', 'published']),
            'clientes' => DB::table('customers')->where('tenant_id', $tenant)->orderBy('id')->select(['id', 'name', 'phone', 'email', 'address', 'marketing_consent']),
            'ventas' => DB::table('orders')->where('tenant_id', $tenant)->where('status', 'confirmed')->orderBy('created_at')->select(['id', 'created_at', 'customer_name', 'channel', 'subtotal_minor', 'discount_minor', 'total_minor', 'returned_minor']),
            'inventario' => DB::table('stock_positions')->where('tenant_id', $tenant)->orderBy('product_id')->select(['warehouse_id', 'product_id', 'physical', 'reserved', 'blocked', 'offline']),
            default => abort(404),
        };
        // Materialize within the tenant transaction; never execute tenant SQL from a deferred stream callback.
        $rows = $query->limit(10000)->get()->map(fn ($row) => (array) $row)->all();
        app(Commerce::class)->audit($tenant, 'export_'.$scope);

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'wb');
            if (! $stream) {
                return;
            }
            fwrite($stream, "\xEF\xBB\xBF");
            if ($rows) {
                fputcsv($stream, array_keys($rows[0]), ',', '"', '');
            }
            foreach ($rows as $row) {
                fputcsv($stream, array_map(fn ($value) => is_string($value) && preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value, $row), ',', '"', '');
            }
            fclose($stream);
        }, $scope.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }
}
