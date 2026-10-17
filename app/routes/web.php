<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogToolsController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OfflineController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\TeamController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

Route::get('/', function () {
    if (app()->environment('local', 'native') && ! DB::table('users')->exists()) {
        return redirect('/instalacion');
    }

    return Inertia::render('Welcome', ['stores' => DB::table('tenants')->where('active', true)->get(['name', 'slug'])]);
})->name('home');
Route::get('/instalacion', [PlatformController::class, 'setup'])->name('setup');
Route::post('/instalacion', [PlatformController::class, 'initialize'])->middleware('throttle:5,1')->name('setup.initialize');
Route::inertia('/invitacion', 'Invitation')->name('invitation');
Route::post('/invitacion', [InvitationController::class, 'accept'])->middleware('throttle:5,1')->name('invitation.accept');
Route::get('/tienda/{slug}', [CatalogController::class, 'index'])->middleware(ResolveTenant::class)->name('store');
Route::get('/tienda/{slug}/producto/{productId}', [CatalogController::class, 'product'])->middleware(ResolveTenant::class)->whereNumber('productId')->name('product');
Route::get('/tienda/{slug}/producto/{productId}/foto/{imageId?}', function (Request $request, string $slug, int $productId, ?int $imageId = null) {
    $tenant = $request->attributes->get('tenant');
    $product = DB::table('products')->where('tenant_id', $tenant->id)->where('id', $productId)->first();
    abort_unless($product !== null, 404);
    if (! $product->published) {
        abort_unless($request->user()?->two_factor_confirmed_at && DB::table('memberships')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('role', 'owner')->exists(), 404);
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
    Route::post('/tienda/{slug}/pedido', [CheckoutController::class, 'store'])->name('checkout');
    Route::post('/tienda/{slug}/cotizar', [CheckoutController::class, 'quote'])->name('quote');
    Route::get('/tienda/{slug}/seguimiento', [CheckoutController::class, 'show'])->name('tracking');
    Route::post('/tienda/{slug}/seguimiento', [CheckoutController::class, 'access'])->name('tracking.access');
    Route::post('/tienda/{slug}/pago', [CheckoutController::class, 'payment'])->name('tracking.payment');
});
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        $stores = DB::table('tenants')->join('memberships', 'tenants.id', '=', 'memberships.tenant_id')->where('memberships.user_id', auth()->id())->where('tenants.active', true)->get(['tenants.name', 'tenants.slug', 'memberships.role']);

        return Inertia::render('Welcome', ['stores' => $stores]);
    })->name('dashboard');
    Route::get('/plataforma', [PlatformController::class, 'index'])->name('platform.index');
    Route::post('/plataforma', [PlatformController::class, 'store'])->name('platform.store');
    Route::patch('/plataforma/{id}', [PlatformController::class, 'update'])->whereNumber('id')->name('platform.update');
    Route::post('/invitaciones', [InvitationController::class, 'store'])->middleware('password.confirm')->name('invitations.store');
    Route::middleware(ResolveTenant::class)->group(function () {
        Route::get('/gestion/{slug}', [CatalogController::class, 'index'])->name('manage.catalog');
        Route::post('/gestion/{slug}/productos', [CatalogController::class, 'store'])->name('manage.products.store');
        Route::patch('/gestion/{slug}/productos/{productId}', [CatalogToolsController::class, 'update'])->whereNumber('productId')->name('manage.products.update');
        Route::post('/gestion/{slug}/productos/{productId}/foto', [CatalogToolsController::class, 'image'])->whereNumber('productId')->name('manage.products.image');
        Route::post('/gestion/{slug}/importar', [CatalogToolsController::class, 'preview'])->name('manage.import.preview');
        Route::post('/gestion/{slug}/importar/confirmar', [CatalogToolsController::class, 'commit'])->name('manage.import.commit');
        Route::get('/gestion/{slug}/exportar/{scope}', ExportController::class)->name('manage.export');
        Route::delete('/gestion/{slug}/equipo/{memberId}', [TeamController::class, 'remove'])->whereNumber('memberId')->middleware('password.confirm')->name('manage.team.remove');
        Route::get('/gestion/{slug}/vista-previa/{versionId}', [CatalogController::class, 'index'])->whereNumber('versionId')->name('manage.preview');
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
