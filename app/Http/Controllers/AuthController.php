<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $user = User::where('email', $credentials['email'])->first();

        if ($user?->temporaryPasswordExpired()) {
            return back()->withErrors([
                'email' => 'Your temporary password has expired. Please contact the Administrator.',
            ]);
        }

        if (
            ! $user ||
            ! $user->is_active ||
            ! Auth::attempt($credentials, $request->boolean('remember'))
        ) {
            AuditLog::create([
                'action' => 'LOGIN_FAILED',
                'metadata' => ['email' => $credentials['email']],
            ]);

            return back()
                ->withErrors([
                    'email' => 'These credentials do not match our records.',
                ])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        AuditLog::record('LOGIN', $user);

        if ($user->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuditLog::record('LOGOUT', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function editPassword(): View
    {
        return view('auth.change-password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', 'min:12'],
        ]);
        $user = $request->user();
        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'activated_at' => $user->activated_at ?? now(),
            'temporary_password_expires_at' => null,
        ]);
        AuditLog::record('PASSWORD_CHANGED', $user);

        return redirect()
            ->route('home')
            ->with('status', 'Your password has been changed.');
    }

    public function requestReset(): View
    {
        return view('auth.forgot-password');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($validated);

        return back()->with(
            'status',
            'If the email is registered, a password reset link has been sent.',
        );
    }

    public function editReset(string $token, Request $request): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function updateReset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:12'],
        ]);
        $status = Password::reset($validated, function (
            User $user,
            string $password,
        ): void {
            $user
                ->forceFill([
                    'password' => Hash::make($password),
                    'must_change_password' => false,
                    'temporary_password_expires_at' => null,
                    'activated_at' => $user->activated_at ?? now(),
                    'remember_token' => null,
                ])
                ->save();
            AuditLog::record('PASSWORD_RESET', $user);
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset.');
    }
}
