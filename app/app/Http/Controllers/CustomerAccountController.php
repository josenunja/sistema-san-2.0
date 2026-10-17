<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Commerce;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->attributes->get('tenant');
        $request->session()->put('login_store', $tenant->slug);
        $customer = $request->user() ? DB::table('customers')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->first() : null;
        $orders = $customer && $request->user()->hasVerifiedEmail() ? DB::table('orders')->where('tenant_id', $tenant->id)->where('customer_id', $customer->id)->orderByDesc('created_at')->paginate(20, ['id', 'total_minor', 'status', 'created_at']) : null;

        return Inertia::render('CustomerAccount', ['tenant' => ['name' => $tenant->name, 'slug' => $tenant->slug], 'customer' => $customer, 'orders' => $orders, 'verified' => $request->user()?->hasVerifiedEmail() ?? false]);
    }

    public function register(Request $request, Commerce $commerce): RedirectResponse
    {
        abort_if($request->user() !== null, 403);
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:254', 'password' => 'required|string|min:12|max:200|confirmed', 'phone' => 'required|string|max:30', 'consent' => 'accepted']);
        $tenant = $request->attributes->get('tenant');
        $user = DB::transaction(function () use ($tenant, $data, $commerce) {
            DB::select('SELECT pg_advisory_xact_lock(?)', [$tenant->id]);
            if (User::where('email', strtolower($data['email']))->exists()) {
                $commerce->fail('No se pudo crear la cuenta. Si ya tienes una, inicia sesión o recupera tu contraseña.');
            }
            $user = User::create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => Hash::make($data['password'])]);
            DB::table('customers')->insert(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone']]);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();
        DB::afterCommit(fn () => event(new Registered($user)));

        return back()->with('success', 'Cuenta de comprador creada. Verifica tu correo para consultar pedidos asociados.');
    }

    public function profile(Request $request, Commerce $commerce): RedirectResponse
    {
        abort_unless($request->user()?->hasVerifiedEmail(), 403, 'Verifica tu correo antes de asociar tu perfil comercial.');
        $data = $request->validate(['name' => 'required|string|max:160', 'phone' => 'required|string|max:30', 'address' => 'nullable|string|max:1000', 'marketing_consent' => 'required|boolean', 'consent' => 'accepted']);
        unset($data['consent']);
        $data['address'] ??= '';
        $tenant = $request->attributes->get('tenant');
        DB::select('SELECT pg_advisory_xact_lock(?)', [$tenant->id]);
        DB::table('customers')->updateOrInsert(['tenant_id' => $tenant->id, 'user_id' => $request->user()->id], [...$data, 'email' => $request->user()->email]);
        $commerce->audit($tenant->id, 'customer_profile_updated');

        return back()->with('success', 'Perfil guardado únicamente en esta empresa.');
    }
}
