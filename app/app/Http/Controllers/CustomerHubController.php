<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerHubController extends Controller
{
    public function account(Request $request): Response
    {
        return $this->render($request, 'account');
    }

    public function orders(Request $request): Response
    {
        return $this->render($request, 'orders');
    }

    public function readNotifications(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $tenantIds = DB::table('tenants')->where('active', true)->pluck('id');

        foreach ($tenantIds as $tenantId) {
            DB::transaction(function () use ($tenantId, $user): void {
                DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenantId]);
                DB::table('customer_notifications')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $user->id)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            });
        }

        return back()->with('success', 'Notificaciones marcadas como leídas.');
    }

    private function render(Request $request, string $tab): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $profiles = [];
        $orders = [];
        $notifications = [];
        $tenants = DB::table('tenants')
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        foreach ($tenants as $tenant) {
            $result = DB::transaction(function () use ($user, $tenant): ?array {
                DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant->id]);
                $customer = DB::table('customers')
                    ->where('tenant_id', $tenant->id)
                    ->where('user_id', $user->id)
                    ->first(['id', 'name', 'email', 'phone', 'address']);

                if ($customer === null) {
                    return null;
                }

                return [
                    'profile' => [
                        'store' => ['name' => $tenant->name, 'slug' => $tenant->slug],
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                        'address' => $customer->address,
                    ],
                    'orders' => DB::table('orders')
                        ->where('tenant_id', $tenant->id)
                        ->where('customer_id', $customer->id)
                        ->orderByDesc('created_at')
                        ->limit(50)
                        ->get(['id', 'status', 'delivery_status', 'delivery_method', 'total_minor', 'created_at'])
                        ->map(fn ($order) => [
                            'id' => $order->id,
                            'status' => $order->status,
                            'delivery_status' => $order->delivery_status,
                            'delivery_method' => $order->delivery_method,
                            'total_minor' => $order->total_minor,
                            'created_at' => $order->created_at,
                            'store' => ['name' => $tenant->name, 'slug' => $tenant->slug],
                        ])->all(),
                    'notifications' => DB::table('customer_notifications')
                        ->where('tenant_id', $tenant->id)
                        ->where('user_id', $user->id)
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->limit(30)
                        ->get(['id', 'order_id', 'kind', 'title', 'message', 'read_at', 'created_at'])
                        ->map(fn ($notification) => [
                            'id' => $notification->id,
                            'order_id' => $notification->order_id,
                            'kind' => $notification->kind,
                            'title' => $notification->title,
                            'message' => $notification->message,
                            'read_at' => $notification->read_at,
                            'created_at' => $notification->created_at,
                            'store' => ['name' => $tenant->name, 'slug' => $tenant->slug],
                        ])->all(),
                ];
            });

            if ($result === null) {
                continue;
            }

            $profiles[] = $result['profile'];
            array_push($orders, ...$result['orders']);
            array_push($notifications, ...$result['notifications']);
        }

        usort($orders, fn (array $left, array $right): int => strcmp((string) $right['created_at'], (string) $left['created_at']));
        usort($notifications, fn (array $left, array $right): int => strcmp((string) $right['created_at'], (string) $left['created_at']));

        return Inertia::render('CustomerHub', [
            'tab' => $tab,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'profiles' => $profiles,
            'orders' => $orders,
            'notifications' => array_slice($notifications, 0, 50),
        ]);
    }
}
