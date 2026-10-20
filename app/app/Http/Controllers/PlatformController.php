<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\BusinessInvitation;
use App\Services\Commerce;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlatformController extends Controller
{
    private function owner(Request $request): void
    {
        abort_unless($request->user()?->platform_owner, 403);
        abort_unless($request->user()->two_factor_secret && $request->user()->two_factor_confirmed_at, 403, 'Activa MFA en configuración de seguridad.');
    }

    public function index(Request $request): Response
    {
        $this->owner($request);

        return Inertia::render('Platform', ['tenants' => DB::table('tenants')->orderBy('name')->paginate(30, ['id', 'name', 'slug', 'active', 'plan', 'created_at']), 'plans' => DB::table('plans')->orderBy('product_limit')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->owner($request);
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'slug' => 'required|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:80|unique:tenants,slug',
            'plan' => ['required', Rule::exists('plans', 'slug')],
            'owner_email' => 'required|email|max:254',
        ]);
        $token = Str::random(64);
        $email = strtolower($data['owner_email']);
        unset($data['owner_email']);
        $id = DB::transaction(function () use ($data, $email, $token) {
            $id = DB::table('tenants')->insertGetId($data);
            DB::table('invitations')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $id,
                'email' => $email,
                'role' => 'owner',
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(48),
            ]);
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $id]);
            app(Commerce::class)->audit($id, 'tenant_created_with_owner_invitation');
            Notification::route('mail', $email)->notify(new BusinessInvitation($data['name'], url('/invitacion').'#'.$token));

            return $id;
        });

        // Platform ownership does not grant membership or commercial access.
        return back()->with('success', 'Empresa creada e invitación enviada. El propietario elegirá su propia contraseña y configurará la tienda.')->with('operation_result', ['id' => $id]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->owner($request);
        $data = $request->validate(['active' => 'required|boolean', 'plan' => ['required', Rule::exists('plans', 'slug')]]);
        abort_unless(DB::table('tenants')->where('id', $id)->exists(), 404);
        DB::table('tenants')->where('id', $id)->update($data);

        return back()->with('success', 'Estado y plan actualizados. No se eliminaron datos.');
    }

    public function setup(Request $request): Response
    {
        abort_unless(app()->environment('local', 'native') && ! DB::table('users')->exists(), 404);

        return Inertia::render('Setup');
    }

    public function initialize(Request $request, Commerce $commerce): RedirectResponse
    {
        abort_unless(app()->environment('local', 'native'), 404);
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:254', 'password' => 'required|string|min:12|max:200|confirmed', 'business' => 'required|string|max:160', 'slug' => 'required|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:80', 'local_confirmation' => 'accepted']);
        DB::transaction(function () use ($data, $commerce) {
            DB::select('SELECT pg_advisory_xact_lock(0)');
            abort_if(DB::table('users')->exists(), 404);
            $user = new User;
            $user->forceFill(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => Hash::make($data['password']), 'email_verified_at' => now(), 'platform_owner' => true])->save();
            $tenant = DB::table('tenants')->insertGetId(['name' => $data['business'], 'slug' => $data['slug']]);
            DB::table('memberships')->insert(['tenant_id' => $tenant, 'user_id' => $user->id, 'role' => 'owner']);
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant]);
            $commerce->audit($tenant, 'local_initial_setup');
        });

        return redirect('/login')->with('status', 'Cuenta local creada. Ingresa y activa la verificación en dos pasos.');
    }
}
