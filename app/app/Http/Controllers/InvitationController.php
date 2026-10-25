<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\BusinessInvitation;
use App\Services\Commerce;
use App\Services\Invitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function index(Request $request, int $tenantId): Response
    {
        $user = $request->user();
        abort_unless($user->two_factor_secret && $user->two_factor_confirmed_at, 403);
        abort_unless($user->platform_owner || DB::table('memberships')->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('role', 'owner')->exists(), 403);
        $tenant = DB::table('tenants')->where('id', $tenantId)->first(['id', 'name', 'slug']);
        abort_unless($tenant !== null, 404);
        $invitations = DB::table('invitations')->where('tenant_id', $tenantId)->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(25, ['id', 'email', 'role', 'expires_at', 'last_sent_at', 'accepted_at', 'revoked_at'])
            ->through(function ($invite) {
                $invite->status = app(Invitations::class)->status($invite);

                return $invite;
            });

        return Inertia::render('Invitations', ['tenant' => $tenant, 'invitations' => $invitations, 'backHref' => $user->platform_owner ? '/plataforma' : '/gestion/'.$tenant->slug.'/operacion/configuracion']);
    }

    public function store(Request $request, Commerce $commerce): RedirectResponse
    {
        $data = $request->validate(['tenant_id' => 'required|integer', 'email' => 'required|email|max:254', 'role' => ['required', Rule::in(['owner', 'cashier', 'warehouse', 'catalog'])]]);
        $user = $request->user();
        abort_unless($user->two_factor_secret && $user->two_factor_confirmed_at, 403);
        $member = DB::table('memberships')->where('tenant_id', $data['tenant_id'])->where('user_id', $user->id)->where('role', 'owner')->exists();
        abort_unless($user->platform_owner || $member, 403);
        if (! $member) {
            abort_if(DB::table('memberships')->where('tenant_id', $data['tenant_id'])->where('role', 'owner')->exists(), 403);
            abort_unless($data['role'] === 'owner', 403);
        }
        DB::transaction(function () use ($data, $commerce, $user) {
            DB::select('SELECT pg_advisory_xact_lock(?)', [$data['tenant_id']]);
            $member = DB::table('memberships')->where('tenant_id', $data['tenant_id'])->where('user_id', $user->id)->where('role', 'owner')->exists();
            abort_unless($member || ($user->platform_owner && $data['role'] === 'owner' && ! DB::table('memberships')->where('tenant_id', $data['tenant_id'])->where('role', 'owner')->exists()), 403);
            $tenant = DB::table('tenants')->where('id', $data['tenant_id'])->where('active', true)->first();
            abort_unless($tenant !== null, 404);
            $existing = User::where('email', strtolower($data['email']))->value('id');
            if ($existing && DB::table('memberships')->where('tenant_id', $tenant->id)->where('user_id', $existing)->exists()) {
                $commerce->fail('Esa cuenta ya tiene acceso a esta tienda. Revisa Mi equipo.');
            }
            app(Invitations::class)->ensureCapacity((int) $tenant->id);
            if (DB::table('invitations')->where('tenant_id', $tenant->id)->where('email', strtolower($data['email']))->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists()) {
                $commerce->fail('Ya existe una invitación pendiente para ese correo. Puedes reenviarla o revocarla.');
            }
            $token = Str::random(64);
            DB::table('invitations')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'email' => strtolower($data['email']), 'role' => $data['role'], 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(48), 'last_sent_at' => now()]);
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant->id]);
            $commerce->audit($tenant->id, 'invitation_created');
            // Local mail goes to the configured log mailer. Never expose invitation secrets in public props.
            Notification::route('mail', $data['email'])->notify(new BusinessInvitation($tenant->name, url('/invitacion').'#'.$token));
        });

        return back()->with('success', 'Invitación creada por 48 horas. En local, consulta el correo en el registro de desarrollo.');
    }

    public function resend(Request $request, string $id, Commerce $commerce): RedirectResponse
    {
        return $this->manage($request, $id, $commerce, false);
    }

    public function revoke(Request $request, string $id, Commerce $commerce): RedirectResponse
    {
        return $this->manage($request, $id, $commerce, true);
    }

    private function manage(Request $request, string $id, Commerce $commerce, bool $revoke): RedirectResponse
    {
        $invite = DB::table('invitations')->where('id', $id)->first();
        abort_unless($invite !== null, 404);
        $user = $request->user();
        abort_unless($user->two_factor_secret && $user->two_factor_confirmed_at, 403);
        abort_unless($user->platform_owner || DB::table('memberships')->where('tenant_id', $invite->tenant_id)->where('user_id', $user->id)->where('role', 'owner')->exists(), 403);
        DB::transaction(function () use ($id, $invite, $commerce, $revoke, $user) {
            DB::select('SELECT pg_advisory_xact_lock(?)', [$invite->tenant_id]);
            abort_unless($user->platform_owner || DB::table('memberships')->where('tenant_id', $invite->tenant_id)->where('user_id', $user->id)->where('role', 'owner')->exists(), 403);
            $current = DB::table('invitations')->where('id', $id)->lockForUpdate()->first();
            if ($current->accepted_at) {
                $commerce->fail('La invitación ya fue aceptada. Revoca la membresía desde Mi equipo si corresponde.');
            }
            if ($current->revoked_at) {
                if ($revoke) {
                    return;
                }
                $commerce->fail('La invitación está revocada. Crea una nueva si deseas volver a dar acceso.');
            }
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $invite->tenant_id]);
            if ($revoke) {
                DB::table('invitations')->where('id', $id)->update(['revoked_at' => now()]);
                $commerce->audit($invite->tenant_id, 'invitation_revoked', $id);

                return;
            }
            $tenant = DB::table('tenants')->where('id', $invite->tenant_id)->where('active', true)->first();
            abort_unless($tenant !== null, 404);
            if ($current->last_sent_at && now()->lessThan(Carbon::parse($current->last_sent_at)->addMinute())) {
                $commerce->fail('Espera un minuto antes de reenviar esta invitación.');
            }
            app(Invitations::class)->ensureCapacity((int) $invite->tenant_id, $id);
            $token = Str::random(64);
            DB::table('invitations')->where('id', $id)->update(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(48), 'last_sent_at' => now()]);
            Notification::route('mail', $current->email)->notify(new BusinessInvitation($tenant->name, url('/invitacion').'#'.$token));
            $commerce->audit($invite->tenant_id, 'invitation_resent', $id);
        });

        return back()->with('success', $revoke ? 'Invitación revocada. Su enlace ya no permite entrar.' : 'Invitación renovada por 48 horas. El enlace anterior dejó de funcionar. En local, consulta el correo de desarrollo.');
    }

    public function accept(Request $request, Commerce $commerce): RedirectResponse
    {
        $data = $request->validate(['token' => 'required|string|size:64', 'name' => 'required|string|max:100', 'password' => 'required|string|min:12|max:200']);
        DB::transaction(function () use ($data, $commerce) {
            $invite = DB::table('invitations')->where('token_hash', hash('sha256', $data['token']))->first();
            abort_unless($invite !== null, 403, 'Invitación inválida o vencida.');
            DB::select('SELECT pg_advisory_xact_lock(?)', [$invite->tenant_id]);
            $invite = DB::table('invitations')->where('token_hash', hash('sha256', $data['token']))->lockForUpdate()->first();
            abort_unless($invite && ! $invite->accepted_at && ! $invite->revoked_at && now()->lessThan($invite->expires_at), 403, 'Invitación inválida, revocada o vencida.');
            $tenant = DB::table('tenants')->where('id', $invite->tenant_id)->where('active', true)->first();
            abort_unless($tenant !== null, 403);
            if (DB::table('memberships')->where('tenant_id', $tenant->id)->count() >= DB::table('plans')->where('slug', $tenant->plan)->value('user_limit')) {
                $commerce->fail('Se alcanzó el límite de usuarios del plan.');
            }
            $user = User::where('email', $invite->email)->first();
            if ($user && ! Hash::check($data['password'], $user->password)) {
                $commerce->fail('Para tu cuenta existente debes ingresar su contraseña actual.');
            }
            if (! $user) {
                $user = new User;
                $user->forceFill(['name' => $data['name'], 'email' => $invite->email, 'password' => Hash::make($data['password']), 'email_verified_at' => now()])->save();
            }
            if (DB::table('memberships')->where('tenant_id', $tenant->id)->where('user_id', $user->id)->exists()) {
                $commerce->fail('La cuenta ya tiene una membresía en esta empresa.');
            }
            DB::table('memberships')->insert(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => $invite->role]);
            DB::table('invitations')->where('id', $invite->id)->update(['accepted_at' => now()]);
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant->id]);
            $commerce->audit($tenant->id, 'invitation_accepted', (string) $user->id);
        });

        return redirect('/login')->with('status', 'Invitación aceptada. Inicia sesión y configura MFA para trabajar.');
    }
}
