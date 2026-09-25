<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\TemporaryPasswordNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        abort_unless($currentUser?->hasRole('ADMINISTRATOR'), 403);

        return view('users.index', ['users' => User::query()->latest()->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        abort_unless($currentUser?->hasRole('ADMINISTRATOR'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'role' => ['required', 'in:STAFF,RMEC,ADMINISTRATOR'],
        ]);
        $temporaryPassword = Str::password(20);
        $user = User::create([
            ...$validated,
            'password' => $temporaryPassword,
            'must_change_password' => true,
            'temporary_password_expires_at' => now()->addDay(),
        ]);
        AuditLog::record('USER_CREATED', $user, ['role' => $user->role]);

        $user->notify(new TemporaryPasswordNotification($temporaryPassword));

        return back()->with(
            'status',
            'User created and temporary password emailed.',
        );
    }

    public function disable(User $user): RedirectResponse
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        abort_unless($currentUser?->hasRole('ADMINISTRATOR'), 403);
        $user->update(['is_active' => false, 'disabled_at' => now()]);
        AuditLog::record('USER_DISABLED', $user);

        return back()->with('status', 'User disabled.');
    }
}
