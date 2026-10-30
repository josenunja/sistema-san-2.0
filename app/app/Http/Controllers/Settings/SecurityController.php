<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $props = [
            'sessions' => config('session.driver') === 'database'
                ? DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->limit(100)->get(['id', 'user_agent', 'last_activity'])->map(fn ($session) => ['key' => hash_hmac('sha256', $session->id, config('app.key')), 'agent' => mb_substr($session->user_agent ?? 'Navegador', 0, 160), 'last_activity' => $session->last_activity, 'current' => hash_equals($request->session()->getId(), $session->id)])->all()
                : [],
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys()
                ? $request->user()
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }

    public function revokeSession(Request $request, string $key): RedirectResponse
    {
        abort_unless(config('session.driver') === 'database', 404);
        $sessions = DB::table('sessions')->where('user_id', $request->user()->id)->get(['id']);
        $session = $sessions->first(fn ($session) => hash_equals(hash_hmac('sha256', $session->id, config('app.key')), $key));
        abort_unless($session !== null && ! hash_equals($request->session()->getId(), $session->id), 404);
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', $session->id)->delete();

        return back()->with('success', 'Sesión cerrada.');
    }
}
