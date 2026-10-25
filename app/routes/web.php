<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogToolsController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerHubController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\OfflineController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\StoreOnboardingController;
use App\Http\Controllers\TeamController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

Route::get('/', [MarketplaceController::class, 'index'])->name('home');
Route::get('/instalacion', [PlatformController::class, 'setup'])->name('setup');
Route::post('/instalacion', [PlatformController::class, 'initialize'])->middleware('throttle:5,1')->name('setup.initialize');
Route::inertia('/invitacion', 'Invitation')->name('invitation');
Route::post('/invitacion', [InvitationController::class, 'accept'])->middleware('throttle:5,1')->name('invitation.accept');
Route::get('/tienda/{slug}', [CatalogController::class, 'index'])->middleware(ResolveTenant::class)->name('store');
Route::get('/tienda/{slug}/marca', [StoreOnboardingController::class, 'logo'])->middleware(ResolveTenant::class)->name('store.logo');
Route::get('/tienda/{slug}/portada', [StoreOnboardingController::class, 'banner'])->middleware(ResolveTenant::class)->name('store.banner');
Route::get('/tienda/{slug}/producto/{productId}', [CatalogController::class, 'product'])->middleware(ResolveTenant::class)->whereNumber('productId')->name('product');
Route::get('/tienda/{slug}/producto/{productId}/foto/{imageId?}', function (Request $request, string $slug, int $productId, ?int $imageId = null) {
    $tenant = $request->attributes->get('tenant');
    $product = DB::table('products')->where('tenant_id', $tenant->id)->where('id', $productId)->first();
    abort_unless($product !== null, 404);
    if (! $product->published) {
        abort_unless($request->user()?->two_factor_confirmed_at && DB::table('memberships')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->whereIn('role', ['owner', 'catalog'])->exists(), 404);
    }
    $path = $imageId ? DB::table('product_images')->where('tenant_id', $tenant->id)->where('product_id', $productId)->where('id', $imageId)->value('path') : $product->image_path;
    abort_unless($path, 404);

    return Storage::disk('local')->response($path, null, ['Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff']);
})->middleware(ResolveTenant::class)->whereNumber('productId')->whereNumber('imageId')->name('product.image');
Route::middleware([ResolveTenant::class, 'throttle:30,1'])->group(function () {
    Route::get('/tienda/{slug}/cuenta', [CustomerAccountController::class, 'index'])->name('account');
    Route::post('/tienda/{slug}/cuenta/registro', [CustomerAccountController::class, 'register'])->name('account.register');
    Route::post('/tienda/{slug}/cuenta/perfil', [CustomerAccountController::class, 'profile'])->middleware(['auth', 'verified'])->name('account.profile');
    Route::get('/tienda/{slug}/cuenta/pedido/{orderId}', [CheckoutController::class, 'show'])->middleware(['auth', 'verified'])->whereUuid('orderId')->name('account.order');
    Route::post('/tienda/{slug}/cuenta/pedido/{orderId}/pago', [CheckoutController::class, 'payment'])->middleware(['auth', 'verified'])->whereUuid('orderId')->name('account.payment');
    Route::post('/tienda/{slug}/pedido', [CheckoutController::class, 'store'])->middleware('auth')->name('checkout');
    Route::post('/tienda/{slug}/cotizar', [CheckoutController::class, 'quote'])->name('quote');
    Route::get('/tienda/{slug}/seguimiento', [CheckoutController::class, 'show'])->name('tracking');
    Route::post('/tienda/{slug}/seguimiento', [CheckoutController::class, 'access'])->name('tracking.access');
    Route::post('/tienda/{slug}/pago', [CheckoutController::class, 'payment'])->name('tracking.payment');
});
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/mi-cuenta', [CustomerHubController::class, 'account'])->name('customer.hub.account');
    Route::get('/mis-pedidos', [CustomerHubController::class, 'orders'])->name('customer.hub.orders');
    Route::post('/mis-pedidos/notificaciones/leer', [CustomerHubController::class, 'readNotifications'])->name('customer.hub.notifications.read');
    Route::get('/dashboard', function () {
        if (config('business.single')) {
            $tenant = request()->attributes->get('instance_tenant');
            abort_unless($tenant !== null, 404);
            $role = DB::table('memberships')->where('tenant_id', $tenant->id)->where('user_id', auth()->id())->value('role');
            $destination = match ($role) {
                'owner' => '/operacion/resumen',
                'cashier' => '/operacion/pos',
                'warehouse' => '/operacion/inventario',
                'catalog' => '',
                default => null,
            };

            return redirect($destination === null ? '/tienda/'.$tenant->slug.'/cuenta' : '/gestion/'.$tenant->slug.$destination);
        }
        $stores = DB::table('tenants')->join('memberships', 'tenants.id', '=', 'memberships.tenant_id')->where('memberships.user_id', auth()->id())->where('tenants.active', true)->get(['tenants.name', 'tenants.slug', 'tenants.settings', 'memberships.role'])->map(function ($store) {
            $settings = json_decode($store->settings, true) ?: [];
            $store->onboarding_complete = isset($settings['onboarding_completed_at']);
            unset($store->settings);

            return $store;
        });

        return Inertia::render('Welcome', [
            'mode' => 'dashboard',
            'stores' => $stores,
            'products' => ['data' => [], 'links' => [], 'total' => 0],
            'categories' => [],
            'search' => '',
            'category' => '',
            'section' => '',
        ]);
    })->name('dashboard');
    Route::get('/plataforma', [PlatformController::class, 'index'])->middleware('password.confirm')->name('platform.index');
    Route::post('/plataforma', [PlatformController::class, 'store'])->middleware('password.confirm')->name('platform.store');
    Route::patch('/plataforma/{id}', [PlatformController::class, 'update'])->whereNumber('id')->name('platform.update');
    Route::post('/plataforma/{id}/catalogo', [PlatformController::class, 'catalogAccess'])->whereNumber('id')->middleware('password.confirm')->name('platform.catalog-access');
    Route::post('/invitaciones', [InvitationController::class, 'store'])->middleware('password.confirm')->name('invitations.store');
    Route::get('/invitaciones/tienda/{tenantId}', [InvitationController::class, 'index'])->whereNumber('tenantId')->middleware('password.confirm')->name('invitations.index');
    Route::post('/invitaciones/{id}/reenviar', [InvitationController::class, 'resend'])->whereUuid('id')->middleware(['password.confirm', 'throttle:6,1'])->name('invitations.resend');
    Route::post('/invitaciones/{id}/revocar', [InvitationController::class, 'revoke'])->whereUuid('id')->middleware(['password.confirm', 'throttle:20,1'])->name('invitations.revoke');
    Route::middleware(ResolveTenant::class)->group(function () {
        Route::get('/gestion/{slug}', [CatalogController::class, 'index'])->name('manage.catalog');
        Route::get('/gestion/{slug}/configurar', [StoreOnboardingController::class, 'show'])->name('manage.onboarding');
        Route::post('/gestion/{slug}/configurar', [StoreOnboardingController::class, 'store'])->name('manage.onboarding.store');
        Route::get('/gestion/{slug}/marca/{versionId}', [StoreOnboardingController::class, 'previewLogo'])->whereNumber('versionId')->name('manage.onboarding.logo');
        Route::get('/gestion/{slug}/portada/{versionId}', [StoreOnboardingController::class, 'previewBanner'])->whereNumber('versionId')->name('manage.onboarding.banner');
        Route::post('/gestion/{slug}/productos', [CatalogController::class, 'store'])->name('manage.products.store');
        Route::patch('/gestion/{slug}/productos/{productId}', [CatalogToolsController::class, 'update'])->whereNumber('productId')->name('manage.products.update');
        Route::post('/gestion/{slug}/productos/{productId}/foto', [CatalogToolsController::class, 'image'])->whereNumber('productId')->name('manage.products.image');
        Route::post('/gestion/{slug}/importar', [CatalogToolsController::class, 'preview'])->name('manage.import.preview');
        Route::post('/gestion/{slug}/importar/confirmar', [CatalogToolsController::class, 'commit'])->name('manage.import.commit');
        Route::get('/gestion/{slug}/exportar/{scope}', ExportController::class)->name('manage.export');
        Route::delete('/gestion/{slug}/equipo/{memberId}', [TeamController::class, 'remove'])->whereNumber('memberId')->middleware('password.confirm')->name('manage.team.remove');
        Route::get('/gestion/{slug}/vista-previa/{versionId}', [CatalogController::class, 'index'])->whereNumber('versionId')->name('manage.preview');
        Route::get('/gestion/{slug}/operacion/configuracion', [BusinessController::class, 'index'])->defaults('section', 'configuracion')->middleware('password.confirm')->name('manage.configuration');
        Route::get('/gestion/{slug}/operacion/{section?}', [BusinessController::class, 'index'])->name('manage.business');
        Route::post('/gestion/{slug}/operacion', [BusinessController::class, 'mutate'])->middleware('throttle:90,1')->name('manage.mutate');
        Route::get('/gestion/{slug}/pedido/{orderId}', [BusinessController::class, 'detail'])->whereUuid('orderId')->name('manage.order');
        Route::get('/gestion/{slug}/offline', [OfflineController::class, 'index'])->name('manage.offline');
        Route::post('/gestion/{slug}/offline/asignar', [OfflineController::class, 'grant'])->name('manage.offline.grant');
        Route::post('/gestion/{slug}/offline/sincronizar', [OfflineController::class, 'sync'])->middleware('throttle:120,1')->name('manage.offline.sync');
        Route::post('/gestion/{slug}/offline/conciliar', [OfflineController::class, 'reconcile'])->name('manage.offline.reconcile');
        Route::post('/gestion/{slug}/offline/aprobar', [OfflineController::class, 'review'])->middleware('password.confirm')->name('manage.offline.review');
        Route::get('/gestion/{slug}/constancia/{paymentId}', function (Request $request, string $slug, int $paymentId) {
            abort_unless($request->attributes->get('membership')->role === 'owner', 403);
            $tenant = $request->attributes->get('tenant');
            $path = DB::table('payments')->where('tenant_id', $tenant->id)->where('id', $paymentId)->value('proof_path');
            abort_unless($path, 404);

            return Storage::disk('local')->download($path, 'constancia-privada', ['X-Content-Type-Options' => 'nosniff']);
        })->name('manage.proof');
    });
});
require __DIR__.'/settings.php';
