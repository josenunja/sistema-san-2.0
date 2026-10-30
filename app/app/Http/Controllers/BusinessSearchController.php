<?php

namespace App\Http\Controllers;

use App\Services\ScheduledPrices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessSearchController extends Controller
{
    public function customers(Request $request): JsonResponse
    {
        abort_unless(in_array($request->attributes->get('membership')->role, ['owner', 'cashier'], true), 403);
        $tenant = (int) $request->attributes->get('tenant')->id;
        $term = mb_substr(trim((string) $request->query('q')), 0, 100);
        $rows = DB::table('customers')->where('tenant_id', $tenant)->where(fn ($q) => $q->where('name', 'ilike', '%'.$term.'%')->orWhere('phone', 'ilike', '%'.$term.'%')->orWhere('email', 'ilike', '%'.$term.'%'))->orderBy('name')->limit(30)->get(['id', 'name', 'phone']);

        return response()->json($rows);
    }

    public function products(Request $request): JsonResponse
    {
        $tenant = (int) $request->attributes->get('tenant')->id;
        $term = mb_substr(trim((string) $request->query('q')), 0, 100);
        $products = DB::table('products')->where('tenant_id', $tenant)->where('archived', false)->where(fn ($q) => $q->where('name', 'ilike', '%'.$term.'%')->orWhere('sku', 'ilike', '%'.$term.'%')->orWhere('barcode', $term))->orderBy('name')->limit(30)->get(['id', 'name', 'sku', 'barcode', 'price_minor', 'category']);
        app(ScheduledPrices::class)->decorate($tenant, $products, 'pos');

        return response()->json($products);
    }
}
