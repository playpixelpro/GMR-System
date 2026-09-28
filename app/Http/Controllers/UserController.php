<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use App\Notifications\TemporaryPasswordNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        abort_unless($currentUser?->hasRole('ADMINISTRATOR'), 403);

        return view('users.index', [
            'users' => User::query()->with('branch')->latest()->get(),
            'branches' => Branch::query()->orderBy('name')->get(),
        ]);
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
            'branch_id' => [
                'nullable',
                Rule::requiredIf(fn () => $request->input('role') === 'STAFF'),
                'exists:branches,id',
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:191'],
        ]);

        $temporaryPassword = ! empty($validated['password'])
            ? $validated['password']
            : Str::password(16);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'branch_id' => $validated['role'] === 'STAFF' ? ($validated['branch_id'] ?? null) : null,
            'password' => $temporaryPassword,
            'must_change_password' => true,
            'temporary_password_expires_at' => now()->addDays(7),
        ]);
        AuditLog::record('USER_CREATED', $user, [
            'role' => $user->role,
            'branch_id' => $user->branch_id,
        ]);

        $emailSent = false;
        try {
            $user->notify(new TemporaryPasswordNotification($temporaryPassword));
            $emailSent = true;
        } catch (\Throwable $e) {
            report($e);
        }

        $status = "User created successfully. Temporary password: {$temporaryPassword} (The user must change this password upon first login).";
        if (! $emailSent) {
            $status .= ' Note: Email delivery is unavailable, so please share this temporary password with the user directly.';
        }

        return back()
            ->with('status', $status)
            ->with('credentials_modal', [
                'type' => 'created',
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'temporary_password' => $temporaryPassword,
                'login_url' => route('login'),
                'email_sent' => $emailSent,
                'expires_at' => $user->temporary_password_expires_at?->format('M d, Y h:i A'),
            ]);
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        abort_unless($currentUser?->hasRole('ADMINISTRATOR'), 403);

        if ($user->id === $currentUser->id) {
            return back()->withErrors([
                'user' => 'You cannot reset your own password from user management. Please use Profile settings.',
            ]);
        }

        $validated = $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'max:191'],
        ]);

        $temporaryPassword = ! empty($validated['password'])
            ? $validated['password']
            : Str::password(16);

        $isAwaiting = $user->isAwaitingActivation();

        $user->forceFill([
            'password' => $temporaryPassword,
            'must_change_password' => true,
            'temporary_password_expires_at' => now()->addDays(7),
        ])->save();

        AuditLog::record($isAwaiting ? 'TEMPORARY_PASSWORD_RESENT' : 'TEMPORARY_PASSWORD_RESET', $user, [
            'reset_by' => $currentUser->id,
            'email' => $user->email,
        ]);

        $emailSent = false;
        try {
            $user->notify(new TemporaryPasswordNotification($temporaryPassword));
            $emailSent = true;
        } catch (\Throwable $e) {
            report($e);
        }

        if ($emailSent) {
            $status = $isAwaiting
                ? 'A new temporary password was emailed to the user.'
                : "A new temporary password was emailed to {$user->name}.";
        } else {
            $status = "A new temporary password was generated for {$user->name}: {$temporaryPassword}. Note: Email delivery is unavailable, please share this password directly with the user.";
        }

        return back()
            ->with('status', $status)
            ->with('credentials_modal', [
                'type' => $isAwaiting ? 'resent' : 'reset',
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'temporary_password' => $temporaryPassword,
                'login_url' => route('login'),
                'email_sent' => $emailSent,
                'expires_at' => $user->temporary_password_expires_at?->format('M d, Y h:i A'),
            ]);
    }

    public function resendTemporaryPassword(Request $request, User $user): RedirectResponse
    {
        return $this->resetPassword($request, $user);
    }

    public function profile(): View
    {
        return view('users.profile', ['user' => Auth::user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', 'min:12'],
        ]);

        $user->name = $validated['name'];
        if ($request->hasFile('profile_photo')) {
            $oldPhotoPath = $user->profile_photo_path;
            $user->profile_photo_path = $request->file('profile_photo')->store('profile-photos', 'public');

            if ($oldPhotoPath !== null) {
                Storage::disk('public')->delete($oldPhotoPath);
            }
        }

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $user->must_change_password = false;
            $user->temporary_password_expires_at = null;
            $user->activated_at = $user->activated_at ?? now();
            AuditLog::record('PASSWORD_CHANGED', $user);
        }

        $user->save();

        return back()->with('status', 'Profile updated.');
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

    public function confirmRegistration(User $user): RedirectResponse
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        abort_unless($currentUser?->hasRole('ADMINISTRATOR'), 403);

        if (! $user->isRegistrationPending()) {
            return back()->withErrors([
                'user' => 'This account does not have a pending registration to confirm.',
            ]);
        }

        $user->forceFill([
            'registration_confirmed_at' => now(),
            'registration_expires_at' => null,
        ])->save();

        AuditLog::record('USER_REGISTRATION_CONFIRMED', $user, [
            'confirmed_by' => $currentUser->id,
        ]);

        return back()->with('status', "Registration for {$user->name} has been confirmed as permanent.");
    }

    public function unlockEdit(Request $request, User $user): RedirectResponse
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        abort_unless($currentUser?->hasRole('ADMINISTRATOR'), 403);

        $validated = $request->validate([
            'hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        $hours = (int) ($validated['hours'] ?? 24);
        $expiresAt = now()->addHours($hours);

        $user->update([
            'is_edit_locked' => false,
            'edit_unlocked_until' => $expiresAt,
        ]);

        AuditLog::record('STAFF_EDIT_UNLOCKED', $user, [
            'hours' => $hours,
            'expires_at' => $expiresAt->toDateTimeString(),
        ]);

        return back()->with('status', "Edit mode unlocked for {$user->name} until {$expiresAt->format('M d, Y h:i A')} (+{$hours}h).");
    }

    public function lockEdit(User $user): RedirectResponse
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        abort_unless($currentUser?->hasRole('ADMINISTRATOR'), 403);

        $user->update([
            'is_edit_locked' => true,
            'edit_unlocked_until' => null,
        ]);

        AuditLog::record('STAFF_EDIT_LOCKED', $user);

        return back()->with('status', "Edit mode locked for {$user->name}. Editing is disabled.");
    }

    public function resetEditMode(User $user): RedirectResponse
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        abort_unless($currentUser?->hasRole('ADMINISTRATOR'), 403);

        $user->update([
            'is_edit_locked' => false,
            'edit_unlocked_until' => null,
        ]);

        AuditLog::record('STAFF_EDIT_RESET_DEFAULT', $user);

        return back()->with('status', "Edit mode for {$user->name} reset to default 24-hour window.");
    }

    public function destroy(User $user): RedirectResponse
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        abort_unless($currentUser?->hasRole('ADMINISTRATOR'), 403);

        if ($user->id === $currentUser->id) {
            return back()->withErrors([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        if ($user->hasRole('ADMINISTRATOR') && User::where('role', 'ADMINISTRATOR')->count() <= 1) {
            return back()->withErrors([
                'user' => 'Cannot delete the only administrator account.',
            ]);
        }

        $userName = $user->name;
        $userEmail = $user->email;
        $userRole = $user->role;
        $photoPath = $user->profile_photo_path;

        AuditLog::record('USER_DELETED', $user, [
            'deleted_user_id' => $user->id,
            'name' => $userName,
            'email' => $userEmail,
            'role' => $userRole,
        ]);

        if ($photoPath !== null) {
            Storage::disk('public')->delete($photoPath);
        }

        $user->delete();

        return back()->with('status', "User '{$userName}' ({$userEmail}) was permanently deleted.");
    }

    public function updateBranch(Request $request, User $user): RedirectResponse
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        abort_unless($currentUser?->hasRole('ADMINISTRATOR'), 403);

        $validated = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $user->update([
            'branch_id' => $validated['branch_id'] ?: null,
        ]);

        AuditLog::record('USER_BRANCH_UPDATED', $user, [
            'branch_id' => $user->branch_id,
            'branch_name' => $user->branch?->name,
        ]);

        return back()->with('status', "Branch updated for {$user->name}.");
    }
}
