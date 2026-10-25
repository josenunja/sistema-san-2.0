<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\BusinessInvitation;
use App\Services\BusinessTypes;
use App\Services\Commerce;
use App\Services\Invitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        $tenants = DB::table('tenants')->orderBy('name')->orderBy('id')->paginate(30, ['id', 'name', 'slug', 'active', 'plan', 'created_at']);
        $tenants->through(function ($tenant) use ($request) {
            $tenant->my_role = DB::table('memberships')->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->value('role');
            $tenant->invitations = app(Invitations::class)->listing((int) $tenant->id);

            return $tenant;
        });

        return Inertia::render('Platform', ['tenants' => $tenants, 'plans' => DB::table('plans')->orderBy('product_limit')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->owner($request);
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'slug' => 'required|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:80|unique:tenants,slug',
            'plan' => ['required', Rule::exists('plans', 'slug')],
            'owner_email' => 'required|email|max:254',
            'manage_catalog' => 'sometimes|boolean',
        ]);
        $token = Str::random(64);
        $email = strtolower($data['owner_email']);
        unset($data['owner_email']);
        $manageCatalog = $data['manage_catalog'] ?? false;
        unset($data['manage_catalog']);
        $id = DB::transaction(function () use ($data, $email, $token, $manageCatalog, $request) {
            $id = DB::table('tenants')->insertGetId($data);
            DB::table('invitations')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $id,
                'email' => $email,
                'role' => 'owner',
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(48),
                'last_sent_at' => now(),
            ]);
            if ($manageCatalog && $email !== strtolower($request->user()->email)) {
                app(Invitations::class)->ensureCapacity((int) $id);
                DB::table('memberships')->insert(['tenant_id' => $id, 'user_id' => $request->user()->id, 'role' => 'catalog']);
            }
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $id]);
            app(Commerce::class)->audit($id, 'tenant_created_with_owner_invitation');
            Notification::route('mail', $email)->notify(new BusinessInvitation($data['name'], url('/invitacion').'#'.$token));

            return $id;
        });

        // Catalog assistance is an explicit per-store membership, never a global bypass.
        $delivery = config('mail.default') === 'log'
            ? 'Invitación registrada en el correo local de desarrollo; no se envió un correo real.'
            : 'Invitación entregada al servicio de correo configurado.';

        return back()->with('success', 'Empresa creada. '.$delivery.' El propietario elegirá su propia contraseña y configurará la tienda.')->with('operation_result', ['id' => $id]);
    }

    public function catalogAccess(Request $request, int $id, Commerce $commerce): RedirectResponse
    {
        $this->owner($request);
        $data = $request->validate(['enabled' => 'required|boolean']);
        DB::transaction(function () use ($request, $id, $data, $commerce) {
            DB::select('SELECT pg_advisory_xact_lock(?)', [$id]);
            abort_unless(DB::table('tenants')->where('id', $id)->exists(), 404);
            $members = DB::table('memberships')->where('tenant_id', $id)->where('user_id', $request->user()->id);
            $role = (clone $members)->value('role');
            if ($role && $role !== 'catalog') {
                $commerce->fail('Ya tienes otro rol en esta tienda. Su propietario debe gestionar ese acceso desde Mi equipo.');
            }
            if ($data['enabled'] && ! $role) {
                abort_unless(DB::table('tenants')->where('id', $id)->where('active', true)->exists(), 404);
                app(Invitations::class)->ensureCapacity($id);
                DB::table('memberships')->insert(['tenant_id' => $id, 'user_id' => $request->user()->id, 'role' => 'catalog']);
            } elseif (! $data['enabled'] && $role === 'catalog') {
                $members->delete();
            } else {
                return;
            }
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $id]);
            $commerce->audit($id, $data['enabled'] ? 'catalog_assistance_granted' : 'catalog_assistance_revoked', (string) $request->user()->id);
        });

        return back()->with('success', $data['enabled'] ? 'Permiso de catálogo habilitado para esta tienda. Su propietario mantiene su acceso.' : 'Tu permiso de catálogo fue retirado.');
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
        abort_if(config('business.single') && DB::table('tenants')->exists(), 404);

        return Inertia::render('Setup', ['businessDefaults' => ['name' => config('business.name'), 'slug' => config('business.slug'), 'type' => config('business.type')]]);
    }

    public function initialize(Request $request, Commerce $commerce): RedirectResponse
    {
        abort_unless(app()->environment('local', 'native'), 404);
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:254', 'password' => 'required|string|min:12|max:200|confirmed', 'business' => 'required|string|max:160', 'slug' => 'required|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:80', 'local_confirmation' => 'accepted']);
        $type = $request->validate(['business_type' => ['sometimes', Rule::in(BusinessTypes::VALUES)]])['business_type'] ?? 'general';
        if (config('business.single') && config('business.slug') && $data['slug'] !== config('business.slug')) {
            throw ValidationException::withMessages(['slug' => 'Usa el identificador preparado para esta copia: '.config('business.slug')]);
        }
        DB::transaction(function () use ($data, $commerce, $type) {
            DB::select('SELECT pg_advisory_xact_lock(0)');
            abort_if(DB::table('users')->exists(), 404);
            abort_if(config('business.single') && DB::table('tenants')->exists(), 404);
            $user = new User;
            $user->forceFill(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => Hash::make($data['password']), 'email_verified_at' => now(), 'platform_owner' => true])->save();
            $tenant = DB::table('tenants')->insertGetId(['name' => $data['business'], 'slug' => $data['slug'], 'settings' => json_encode(['business_type' => $type, 'storefront_theme' => 'classic', 'tagline' => 'Bienvenido a '.$data['business']], JSON_THROW_ON_ERROR)]);
            DB::table('memberships')->insert(['tenant_id' => $tenant, 'user_id' => $user->id, 'role' => 'owner']);
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant]);
            $commerce->audit($tenant, 'local_initial_setup');
        });

        return redirect('/login')->with('status', 'Cuenta local creada. Ingresa y activa la verificación en dos pasos.');
    }
}
