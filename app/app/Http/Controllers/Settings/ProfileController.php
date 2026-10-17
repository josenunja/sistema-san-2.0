<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->platform_owner && User::where('platform_owner', true)->count() <= 1) {
            throw ValidationException::withMessages(['password' => 'La plataforma debe conservar al menos un propietario.']);
        }

        if (DB::table('memberships')->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'password' => 'Tu cuenta pertenece a una empresa. Debes resolver sus membresías antes de eliminarla.',
            ]);
        }

        try {
            DB::transaction(fn () => $user->delete());
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23503') {
                throw ValidationException::withMessages(['password' => 'Tu cuenta tiene referencias históricas de negocio que deben conservarse. Solicita una revisión antes de eliminarla.']);
            }
            throw $exception;
        }
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
