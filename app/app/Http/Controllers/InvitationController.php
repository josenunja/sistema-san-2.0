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

class InvitationController extends Controller
{
    public function store(Request $request, Commerce $commerce): RedirectResponse
    {
        $data = $request->validate(['tenant_id' => 'required|integer', 'email' => 'required|email|max:254', 'role' => ['required', Rule::in(['owner', 'cashier', 'warehouse'])]]);
        $user = $request->user();
        abort_unless($user->two_factor_secret && $user->two_factor_confirmed_at, 403);
        $member = DB::table('memberships')->where('tenant_id', $data['tenant_id'])->where('user_id', $user->id)->where('role', 'owner')->exists();
        abort_unless($user->platform_owner || $member, 403);
        if (! $member) {
            abort_if(DB::table('memberships')->where('tenant_id', $data['tenant_id'])->exists(), 403);
        }
        DB::transaction(function () use ($data, $commerce) {
            DB::select('SELECT pg_advisory_xact_lock(?)', [$data['tenant_id']]);
            $tenant = DB::table('tenants')->where('id', $data['tenant_id'])->where('active', true)->first();
            abort_unless($tenant !== null, 404);
            $limit = DB::table('plans')->where('slug', $tenant->plan)->value('user_limit');
            if (DB::table('memberships')->where('tenant_id', $tenant->id)->count() + DB::table('invitations')->where('tenant_id', $tenant->id)->whereNull('accepted_at')->where('expires_at', '>', now())->count() >= $limit) {
                $commerce->fail('El plan no permite más usuarios o invitaciones pendientes.');
            }
            $token = Str::random(64);
            DB::table('invitations')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'email' => strtolower($data['email']), 'role' => $data['role'], 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(48)]);
            DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenant->id]);
            $commerce->audit($tenant->id, 'invitation_created');
            // Local mail goes to the configured log mailer. Never expose invitation secrets in public props.
            Notification::route('mail', $data['email'])->notify(new BusinessInvitation($tenant->name, url('/invitacion').'#'.$token));
        });

        return back()->with('success', 'Invitación creada por 48 horas. En local, consulta el correo en el registro de desarrollo.');
    }

    public function accept(Request $request, Commerce $commerce): RedirectResponse
    {
        $data = $request->validate(['token' => 'required|string|size:64', 'name' => 'required|string|max:100', 'password' => 'required|string|min:12|max:200']);
        DB::transaction(function () use ($data, $commerce) {
            $invite = DB::table('invitations')->where('token_hash', hash('sha256', $data['token']))->lockForUpdate()->first();
            abort_unless($invite && ! $invite->accepted_at && now()->lessThan($invite->expires_at), 403, 'Invitación inválida o vencida.');
            DB::select('SELECT pg_advisory_xact_lock(?)', [$invite->tenant_id]);
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
