<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use App\Notifications\TemporaryPasswordNotification;
use App\Support\AuthThrottle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function createRegistration(): View
    {
        return view('auth.register', [
            'branches' => Branch::query()->orderBy('name')->get(),
        ]);
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $temporaryPassword = Str::password(16);
        $expiresAt = now()->addHours(8);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => 'STAFF',
            'branch_id' => $validated['branch_id'],
            'password' => $temporaryPassword,
            'must_change_password' => true,
            'temporary_password_expires_at' => $expiresAt,
            'registration_expires_at' => $expiresAt,
        ]);

        AuditLog::record('USER_REGISTERED', $user, [
            'branch_id' => $user->branch_id,
        ], 'auth', 'User self-registered a staff account');

        try {
            $user->notify(new TemporaryPasswordNotification($temporaryPassword));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()
            ->route('register.confirmation', ['email' => $user->email]);
    }

    public function registerConfirmation(Request $request): View
    {
        return view('auth.register-confirmation', [
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(Request $request, AuthThrottle $throttle): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($blockedUntil = $throttle->blockedUntil($request->ip())) {
            return back()
                ->withErrors([
                    'email' => $this->blockedMessage($blockedUntil),
                ])
                ->onlyInput('email');
        }

        $user = User::where('email', $credentials['email'])->first();

        if ($user?->isRegistrationExpired()) {
            $user->forceFill([
                'is_active' => false,
                'disabled_at' => $user->disabled_at ?? now(),
                'registration_expires_at' => null,
            ])->save();

            return back()->withErrors([
                'email' => 'Your registration window has expired before an Administrator confirmed your account. Please contact the Administrator.',
            ]);
        }

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
            $throttle->recordFailure($request->ip(), AuthThrottle::REASON_LOGIN);

            AuditLog::create([
                'module' => 'auth',
                'action' => 'LOGIN_FAILED',
                'description' => 'Failed login attempt',
                'ip_address' => $request->ip(),
                'metadata' => ['email' => $credentials['email']],
            ]);

            return back()
                ->withErrors([
                    'email' => 'These credentials do not match our records.',
                ])
                ->onlyInput('email');
        }

        $throttle->clear($request->ip());
        $request->session()->regenerate();
        AuditLog::record('LOGIN', $user, [], 'auth', 'User logged in');

        if ($user->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuditLog::record('LOGOUT', $request->user(), [], 'auth', 'User logged out');
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
        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'activated_at' => $user->activated_at ?? now(),
            'temporary_password_expires_at' => null,
        ])->save();
        AuditLog::record('PASSWORD_CHANGED', $user, [], 'auth', 'User changed their password');

        return redirect()
            ->route('home')
            ->with('status', 'Your password has been changed.');
    }

    public function requestReset(): View
    {
        return view('auth.forgot-password');
    }

    public function sendReset(Request $request, AuthThrottle $throttle): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        if ($blockedUntil = $throttle->blockedUntil($request->ip())) {
            return back()
                ->withErrors([
                    'email' => $this->blockedMessage($blockedUntil),
                ])
                ->onlyInput('email');
        }

        // Every submission counts toward the lockout so that the reset link
        // endpoint cannot be abused to spam emails or probe for accounts.
        $throttle->recordFailure($request->ip(), AuthThrottle::REASON_PASSWORD_RESET);

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
        if ($request->isMethod('PUT') || $request->user()) {
            return $this->updatePassword($request);
        }

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
            AuditLog::record('PASSWORD_RESET', $user, [], 'auth', 'Password reset via email link');
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset.');
    }

    private function blockedMessage(Carbon $blockedUntil): string
    {
        return 'Too many failed attempts. Your IP address has been locked. '
            .'Please try again after '.$blockedUntil->format('M d, Y h:i A')
            .' ('.$blockedUntil->diffForHumans().'). '
            .'If you believe this is a mistake, contact the Administrator to unlock your IP.';
    }
}
