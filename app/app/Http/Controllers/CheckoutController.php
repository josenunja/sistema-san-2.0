<?php

namespace App\Http\Controllers;

use App\Services\Commerce;
use App\Services\DeliveryOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function store(Request $request, Commerce $commerce): RedirectResponse
    {
        $base = $request->validate(['key' => 'required|uuid']);
        $data = $request->validate([...BusinessController::orderRules(), 'expected_total_minor' => 'required|integer|min:0|max:9999999999999']);
        $tenant = $request->attributes->get('tenant');
        $user = $request->user();
        abort_unless($user !== null, 401);
        $customerId = DB::table('customers')->where('tenant_id', $tenant->id)->where('user_id', $user->id)->value('id');
        if (! $customerId) {
            $commerce->fail('Crea o completa primero tu perfil de comprador en esta tienda.');
        }
        $data['customer_id'] = $customerId;
        $data['cash_minor'] = 0;
        $data['cash_session_id'] = null;
        $result = $commerce->run($tenant->id, $base['key'], 'checkout', ['session' => hash('sha256', $request->session()->getId()), ...$data], fn () => $commerce->createOrder($tenant->id, $data, false));
        $request->session()->put('tracking.'.$tenant->slug, ['id' => $result['id'], 'token' => $result['token']]);

        return redirect('/tienda/'.$tenant->slug.'/seguimiento');
    }

    public function quote(Request $request, Commerce $commerce): JsonResponse
    {
        $data = $request->validate(['items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|integer', 'items.*.quantity' => 'required|integer|min:1|max:10000', 'delivery_zone_id' => 'nullable|integer', 'delivery_slot_id' => 'nullable|integer', 'coupon' => 'nullable|string|max:40', 'delivery_method' => ['required', Rule::in(['pickup', 'delivery', 'courier', 'shipping'])]]);
        $tenant = $request->attributes->get('tenant');
        $quote = $commerce->quote($tenant->id, $data['items'], $data['coupon'] ?? null, true);
        $shipping = app(DeliveryOptions::class)->quote((int) $tenant->id, $data);
        $quote['delivery_minor'] = $shipping['fee_minor'];
        $quote['delivery_label'] = $shipping['label'];
        $quote['total_minor'] += $quote['delivery_minor'];
        unset($quote['coupon_id']);

        return response()->json($quote);
    }

    public function access(Request $request): RedirectResponse
    {
        $data = $request->validate(['id' => 'required|uuid', 'token' => 'required|string|size:64']);
        $request->session()->put('tracking.'.$request->route('slug'), $data);

        return redirect('/tienda/'.$request->route('slug').'/seguimiento');
    }

    private function authorizedOrder(Request $request, Commerce $commerce): \stdClass
    {
        if ($request->routeIs('account.order', 'account.payment')) {
            abort_unless($request->user()?->hasVerifiedEmail(), 403);
            $tenant = $request->attributes->get('tenant');
            $customer = DB::table('customers')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->value('id');
            abort_unless($customer !== null, 404);
            $order = $commerce->order($tenant->id, $request->route('orderId'));
            abort_unless((int) $order->customer_id === (int) $customer, 404);

            return $order;
        }
        $credentials = $request->session()->get('tracking.'.$request->route('slug'));
        abort_unless(is_array($credentials) && isset($credentials['id'], $credentials['token']), 403, 'Ingresa el código y la clave de seguimiento.');
        $tenant = $request->attributes->get('tenant');
        $order = $commerce->order($tenant->id, $credentials['id']);
        abort_unless(hash_equals($order->tracking_hash, hash('sha256', $credentials['token'])), 404);
        abort_if(now()->greaterThan(Carbon::parse($order->created_at)->addDays(90)), 403, 'El acceso de seguimiento venció. Contacta al negocio.');

        return $order;
    }

    public function show(Request $request, Commerce $commerce): Response
    {
        $tenant = $request->attributes->get('tenant');
        if (! $request->routeIs('account.order') && ! $request->session()->has('tracking.'.$tenant->slug)) {
            return Inertia::render('Tracking', ['slug' => $tenant->slug]);
        }
        $order = $this->authorizedOrder($request, $commerce);
        unset($order->tracking_hash, $order->customer_id);
        $lines = DB::table('order_lines')->where('tenant_id', $tenant->id)->where('order_id', $order->id)->get(['product_id', 'name', 'sku', 'quantity', 'unit_minor', 'discount_minor', 'total_minor', 'returned']);
        $settings = json_decode($tenant->settings, true) ?: [];
        $message = 'Pedido '.substr($order->id, 0, 8).' · '.$tenant->name."\n".$lines->map(fn ($l) => $l->quantity.' x '.$l->name.' · '.$l->sku."\n".url('/tienda/'.$tenant->slug.'/producto/'.$l->product_id))->implode("\n")."\nTotal S/ ".number_format($order->total_minor / 100, 2, '.', '');

        return Inertia::render('Order', ['tenant' => ['name' => $tenant->name, 'slug' => $tenant->slug], 'order' => $order, 'lines' => $lines, 'payments' => DB::table('payments')->where('tenant_id', $tenant->id)->where('order_id', $order->id)->get(['id', 'amount_minor', 'method', 'status', 'created_at']), 'events' => DB::table('order_events')->where('tenant_id', $tenant->id)->where('order_id', $order->id)->orderBy('created_at')->orderBy('id')->get(['id', 'kind', 'status', 'title', 'message', 'created_at']), 'paid' => $commerce->paid($tenant->id, $order->id), 'refunds' => [], 'managing' => false, 'role' => 'guest', 'cashSessions' => [], 'paymentUrl' => $request->routeIs('account.order') ? '/tienda/'.$tenant->slug.'/cuenta/pedido/'.$order->id.'/pago' : '/tienda/'.$tenant->slug.'/pago', 'trackingToken' => $request->routeIs('account.order') ? null : $request->session()->get('tracking.'.$tenant->slug.'.token'), 'paymentInstructions' => array_intersect_key($settings, array_flip(['yape_instructions', 'bcp_instructions'])), 'whatsapp' => empty($settings['whatsapp']) ? null : 'https://wa.me/'.$settings['whatsapp'].'?text='.rawurlencode($message)]);
    }

    public function payment(Request $request, Commerce $commerce): RedirectResponse
    {
        $order = $this->authorizedOrder($request, $commerce);
        $base = $request->validate(['key' => 'required|uuid', 'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120']);
        $data = $request->validate(['amount_minor' => 'required|integer|min:1|max:999999999', 'method' => ['required', Rule::in(['yape', 'bcp'])], 'reference' => 'required|string|max:120']);
        $tenant = $request->attributes->get('tenant');
        $file = $request->file('proof');
        abort_if($file !== null && ! $file instanceof UploadedFile, 422);
        $path = null;
        try {
            $commerce->run($tenant->id, $base['key'], 'payment_proof', ['order' => $order->id, 'proof_hash' => $file ? hash_file('sha256', $file->getRealPath()) : null, ...$data], function () use ($commerce, $tenant, $order, $data, $file, &$path) {
                $result = $commerce->pay($tenant->id, ['order_id' => $order->id, ...$data], false);
                if ($file) {
                    $path = $file->store($tenant->id.'/proofs', 'local');
                    DB::table('payments')->where('tenant_id', $tenant->id)->where('id', $result['id'])->update(['proof_path' => $path]);
                }

                return $result;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return back()->with('success', 'Constancia registrada como pendiente de revisión.');
    }
}
