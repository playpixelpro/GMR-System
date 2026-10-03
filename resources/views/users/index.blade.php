@extends('layouts.app')

@section('title', 'User Management')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">User Management</h1>
        <p class="mt-1 text-sm text-base-content/70">Create accounts with temporary passwords, manage user access, and control staff data edit permissions.</p>
    </div>

    @if (session('status'))
        <x-alert-box type="success" :message="session('status')" dismissible class="mb-4" />
    @endif
    @if (isset($errors) && $errors->any())
        <x-alert-box type="error" dismissible class="mb-4">
            @if ($errors->count() === 1)
                {{ $errors->first() }}
            @else
                <ul class="list-disc pl-4 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </x-alert-box>
    @endif

    <form method="POST" action="{{ route('users.store') }}" class="grid gap-3 rounded-lg border border-base-content/10 bg-base-100 p-4 md:grid-cols-6 items-end">
        @csrf
        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">Name</label>
            <input class="input w-full" name="name" value="{{ old('name') }}" placeholder="Full Name" required>
        </div>
        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">Email</label>
            <input class="input w-full" type="email" name="email" value="{{ old('email') }}" placeholder="user@example.com" required>
        </div>
        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">Role</label>
            <select class="select w-full" name="role" id="user-role-select" required>
                <option value="STAFF" {{ old('role', 'STAFF') === 'STAFF' ? 'selected' : '' }}>Staff</option>
                <option value="RMEC" {{ old('role') === 'RMEC' ? 'selected' : '' }}>RMEC</option>
                <option value="ADMINISTRATOR" {{ old('role') === 'ADMINISTRATOR' ? 'selected' : '' }}>Administrator</option>
                <option value="VIEWER" {{ old('role') === 'VIEWER' ? 'selected' : '' }}>Viewer</option>
            </select>
        </div>
        <div class="space-y-1" id="user-branch-wrapper">
            <label class="text-xs font-semibold text-base-content/70">
                Assigned Branch <span class="text-error" id="branch-star">*</span>
            </label>
            <select class="select w-full" name="branch_id" id="user-branch-select">
                <option value="">Select branch</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" {{ (string) old('branch_id') === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">Temporary Password</label>
            <div class="relative">
                <input class="input w-full pe-10" type="password" name="password" placeholder="Leave blank to auto-generate" minlength="8" autocomplete="off">
                <button type="button" class="absolute inset-y-0 end-0 flex items-center pe-3 text-base-content/60 hover:text-base-content focus:outline-none cursor-pointer" data-password-toggle aria-label="Toggle password visibility">
                    <span class="icon-[tabler--eye] size-5 password-eye-open"></span>
                    <span class="icon-[tabler--eye-off] size-5 password-eye-closed hidden"></span>
                </button>
            </div>
        </div>
        <div>
            <button class="btn btn-primary w-full inline-flex items-center justify-center gap-1.5" type="submit">
                <span class="icon-[tabler--user-plus] size-4"></span>
                <span>Create user</span>
            </button>
        </div>
        <div class="md:col-span-6 text-xs text-base-content/60">
            * The user will be required to change this temporary password upon their first login. Staff users are assigned per branch.
        </div>
    </form>

    <div class="overflow-x-auto rounded-lg border border-base-content/10 bg-base-100">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Assigned Branch</th>
                    <th>Status</th>
                    <th>Edit Mode (24h Window)</th>
                    <th>Edit Permissions</th>
                    <th>Account Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>
                            <div class="font-medium text-base-content">{{ $user->name }}</div>
                            <div class="text-xs text-base-content/60">{{ $user->email }}</div>
                        </td>
                        <td>
                            @if ($user->id === auth()->id())
                                <span class="badge badge-soft {{ $user->role === 'ADMINISTRATOR' ? 'badge-primary' : ($user->role === 'RMEC' ? 'badge-secondary' : ($user->role === 'VIEWER' ? 'badge-info' : 'badge-neutral')) }} text-xs inline-flex items-center gap-1" title="Current user (cannot change own role)">
                                    <span class="icon-[tabler--lock] size-3"></span>
                                    <span>{{ $user->role }} (You)</span>
                                </span>
                            @else
                                <form method="POST" action="{{ route('users.role', $user) }}" class="flex items-center gap-1">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role" data-current-role="{{ $user->role }}"
                                            class="select select-xs select-bordered font-medium w-36 {{ $user->role === 'ADMINISTRATOR' ? 'text-primary font-semibold' : ($user->role === 'RMEC' ? 'text-secondary font-semibold' : ($user->role === 'VIEWER' ? 'text-info font-semibold' : 'text-base-content')) }}"
                                            onchange="if (confirm('Are you sure you want to change the role of \x27{{ addslashes($user->name) }}\x27 from {{ $user->role }} to ' + this.value + '?')) { this.form.submit(); } else { this.value = this.dataset.currentRole; }"
                                            title="Promote or reassign user role">
                                        <option value="STAFF" @selected($user->role === 'STAFF')>Staff</option>
                                        <option value="RMEC" @selected($user->role === 'RMEC')>RMEC</option>
                                        <option value="ADMINISTRATOR" @selected($user->role === 'ADMINISTRATOR')>Administrator</option>
                                        <option value="VIEWER" @selected($user->role === 'VIEWER')>Viewer</option>
                                    </select>
                                </form>
                            @endif
                        </td>
                        <td>
                            @if ($user->role === 'STAFF')
                                <form method="POST" action="{{ route('users.branch', $user) }}" class="flex items-center gap-1">
                                    @csrf
                                    @method('PATCH')
                                    <select name="branch_id" class="select select-xs select-bordered w-36" onchange="this.form.submit()" title="Change staff branch">
                                        <option value="">Unassigned</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}" @selected($user->branch_id === $branch->id)>{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @elseif ($user->role === 'VIEWER')
                                <form method="POST" action="{{ route('users.branch', $user) }}" class="flex items-center gap-1">
                                    @csrf
                                    @method('PATCH')
                                    <select name="branch_id" class="select select-xs select-bordered w-36" onchange="this.form.submit()" title="Change viewer branch">
                                        <option value="">All Branches</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}" @selected($user->branch_id === $branch->id)>{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @else
                                <span class="text-xs text-base-content/50">All Branches</span>
                            @endif
                        </td>
                        <td>
                            @if ($user->isRegistrationPending())
                                <div class="space-y-0.5">
                                    <span class="badge badge-soft badge-warning text-xs font-semibold inline-flex items-center gap-1">
                                        <span class="icon-[tabler--clock] size-3.5"></span>
                                        <span>Pending confirmation</span>
                                    </span>
                                    <div class="text-[11px] text-warning/80">Expires {{ $user->registration_expires_at?->format('M d, Y h:i A') }}</div>
                                </div>
                            @elseif (! $user->is_active)
                                <span class="badge badge-soft badge-error text-xs">Disabled</span>
                            @elseif ($user->isAwaitingActivation())
                                <span class="badge badge-soft badge-warning text-xs">Awaiting activation</span>
                            @else
                                <span class="badge badge-soft badge-success text-xs">Active</span>
                            @endif
                        </td>
                        <td>
                            @if ($user->hasRole('ADMINISTRATOR'))
                                <span class="badge badge-soft badge-neutral text-xs">Unrestricted</span>
                            @elseif ($user->isEditLocked())
                                <div class="space-y-0.5">
                                    <span class="badge badge-soft badge-error text-xs font-semibold inline-flex items-center gap-1">
                                        <span class="icon-[tabler--lock] size-3.5"></span>
                                        <span>Locked by Admin</span>
                                    </span>
                                    <div class="text-[11px] text-error/80">Edits blocked completely</div>
                                </div>
                            @elseif ($user->isEditOverrideActive())
                                <div class="space-y-0.5">
                                    <span class="badge badge-soft badge-success text-xs font-semibold inline-flex items-center gap-1">
                                        <span class="icon-[tabler--lock-open] size-3.5"></span>
                                        <span>Unlocked by Admin</span>
                                    </span>
                                    <div class="text-[11px] text-success/90">
                                        Until {{ $user->edit_unlocked_until?->format('M d, Y h:i A') }} ({{ $user->edit_unlocked_until?->diffForHumans() }})
                                    </div>
                                </div>
                            @else
                                <div class="space-y-0.5">
                                    <span class="badge badge-soft badge-info text-xs font-medium inline-flex items-center gap-1">
                                        <span class="icon-[tabler--clock] size-3.5"></span>
                                        <span>Standard (24h)</span>
                                    </span>
                                    <div class="text-[11px] text-base-content/60">Self-edits lock 24h after save</div>
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($user->hasRole('ADMINISTRATOR'))
                                <span class="text-xs text-base-content/40">—</span>
                            @else
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    @if ($user->isEditLocked())
                                        <form method="POST" action="{{ route('users.unlock-edit', $user) }}">
                                            @csrf
                                            <input type="hidden" name="hours" value="24">
                                            <button class="btn btn-xs btn-outline btn-success inline-flex items-center gap-1" type="submit" title="Unlock edit mode for 24 hours">
                                                <span class="icon-[tabler--lock-open] size-3.5"></span>
                                                <span>Unlock (+24h)</span>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('users.reset-edit-mode', $user) }}">
                                            @csrf
                                            <button class="btn btn-xs btn-ghost text-base-content/60 hover:text-base-content" type="submit" title="Reset to standard 24h window">
                                                Reset to standard
                                            </button>
                                        </form>
                                    @elseif ($user->isEditOverrideActive())
                                        <form method="POST" action="{{ route('users.lock-edit', $user) }}">
                                            @csrf
                                            <button class="btn btn-xs btn-outline btn-error inline-flex items-center gap-1" type="submit" title="Lock edit mode immediately">
                                                <span class="icon-[tabler--lock] size-3.5"></span>
                                                <span>Lock Edit</span>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('users.unlock-edit', $user) }}">
                                            @csrf
                                            <input type="hidden" name="hours" value="24">
                                            <button class="btn btn-xs btn-outline btn-primary inline-flex items-center gap-1" type="submit" title="Extend unlock window for 24 hours from now">
                                                <span class="icon-[tabler--plus] size-3.5"></span>
                                                <span>Extend (+24h)</span>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('users.reset-edit-mode', $user) }}">
                                            @csrf
                                            <button class="btn btn-xs btn-ghost text-base-content/60 hover:text-base-content" type="submit" title="Reset to standard 24h window">
                                                Reset to standard
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('users.unlock-edit', $user) }}">
                                            @csrf
                                            <input type="hidden" name="hours" value="24">
                                            <button class="btn btn-xs btn-outline btn-success inline-flex items-center gap-1" type="submit" title="Authorize editing past 24h window (+24 hours from now)">
                                                <span class="icon-[tabler--lock-open] size-3.5"></span>
                                                <span>Unlock (+24h)</span>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('users.lock-edit', $user) }}">
                                            @csrf
                                            <button class="btn btn-xs btn-outline btn-error inline-flex items-center gap-1" type="submit" title="Lock edit mode immediately even if 24h window is not yet expired">
                                                <span class="icon-[tabler--lock] size-3.5"></span>
                                                <span>Lock Edit</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="flex items-center gap-2 flex-wrap">
                                @if ($user->id !== auth()->id())
                                    <button class="btn btn-xs {{ $user->isAwaitingActivation() ? 'btn-primary' : 'btn-outline btn-primary' }} inline-flex items-center gap-1" type="button" data-open-reset-modal="reset-pwd-modal-{{ $user->id }}" title="{{ $user->isAwaitingActivation() ? 'Resend temporary password' : 'Generate new temporary password for forgotten password' }}">
                                        <span class="icon-[tabler--key] size-3.5"></span>
                                        <span>{{ $user->isAwaitingActivation() ? 'Resend' : 'Reset password' }}</span>
                                    </button>
                                @endif
                                @if ($user->isRegistrationPending() && $user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.confirm-registration', $user) }}">
                                        @csrf
                                        <button class="btn btn-xs btn-outline btn-success inline-flex items-center gap-1" type="submit" title="Confirm this account as permanent">
                                            <span class="icon-[tabler--shield-check] size-3.5"></span>
                                            <span>Confirm permanent</span>
                                        </button>
                                    </form>
                                @endif
                                @if ($user->is_active && $user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.disable', $user) }}">
                                        @csrf
                                        <button class="btn btn-xs btn-warning btn-outline inline-flex items-center gap-1" type="submit" title="Disable user access">
                                            <span class="icon-[tabler--user-off] size-3.5"></span>
                                            <span>Disable</span>
                                        </button>
                                    </form>
                                @endif
                                @if ($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Are you sure you want to permanently delete user \x27{{ addslashes($user->name) }}\x27 ({{ addslashes($user->email) }})? This action cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-xs btn-error btn-outline inline-flex items-center gap-1" type="submit" title="Permanently delete user">
                                            <span class="icon-[tabler--trash] size-3.5"></span>
                                            <span>Delete</span>
                                        </button>
                                    </form>
                                @endif
                            </div>

                            @if ($user->id !== auth()->id())
                                <div id="reset-pwd-modal-{{ $user->id }}" class="reset-password-modal fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-xs p-4" role="dialog" aria-modal="true" aria-labelledby="reset-modal-title-{{ $user->id }}">
                                    <div class="relative w-full max-w-md rounded-xl border border-base-content/10 bg-base-100 p-5 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-200">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex items-center gap-2.5">
                                                <div class="grid size-9 place-items-center rounded-full bg-primary/10 text-primary">
                                                    <span class="icon-[tabler--key] size-5"></span>
                                                </div>
                                                <div>
                                                    <h3 id="reset-modal-title-{{ $user->id }}" class="text-sm font-bold text-base-content">
                                                        {{ $user->isAwaitingActivation() ? 'Resend Temporary Password' : 'Reset User Password' }}
                                                    </h3>
                                                    <p class="text-xs text-base-content/60">{{ $user->name }} ({{ $user->email }})</p>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-xs btn-circle btn-ghost" data-close-reset-modal aria-label="Close dialog">✕</button>
                                        </div>

                                        <p class="text-xs text-base-content/70">
                                            Generate a new temporary password for this user. They will be required to create a new persistent password immediately upon first login.
                                        </p>

                                        <form method="POST" action="{{ route('users.reset-password', $user) }}" class="space-y-3">
                                            @csrf
                                            <div class="space-y-1">
                                                <label class="text-xs font-semibold text-base-content/70">Temporary Password (optional)</label>
                                                <div class="relative">
                                                    <input class="input input-sm w-full pe-9" type="password" name="password" placeholder="Leave blank to auto-generate" minlength="8" autocomplete="off">
                                                    <button type="button" class="absolute inset-y-0 end-0 flex items-center pe-2.5 text-base-content/60 hover:text-base-content focus:outline-none cursor-pointer" data-password-toggle aria-label="Toggle password visibility">
                                                        <span class="icon-[tabler--eye] size-4 password-eye-open"></span>
                                                        <span class="icon-[tabler--eye-off] size-4 password-eye-closed hidden"></span>
                                                    </button>
                                                </div>
                                                <span class="text-[11px] text-base-content/50">Leave empty to auto-generate a secure 16-character password.</span>
                                            </div>

                                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-base-content/10">
                                                <button type="button" class="btn btn-xs btn-ghost" data-close-reset-modal>Cancel</button>
                                                <button type="submit" class="btn btn-xs btn-primary inline-flex items-center gap-1.5">
                                                    <span class="icon-[tabler--refresh] size-3.5"></span>
                                                    <span>Generate & Reset</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
    @if ($modalCreds = session('credentials_modal'))
        <div id="credentials-popup-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 transition-opacity duration-200" role="dialog" aria-modal="true" aria-labelledby="credentials-modal-title">
            <div class="relative w-full max-w-lg rounded-xl border border-base-content/10 bg-base-100 p-6 shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-200">
                <!-- Header -->
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="grid size-10 place-items-center rounded-full bg-primary/10 text-primary">
                            <span class="icon-[tabler--key] size-6"></span>
                        </div>
                        <div>
                            <h2 id="credentials-modal-title" class="text-lg font-bold text-base-content">
                                {{ $modalCreds['type'] === 'created' ? 'User Account Created' : 'Temporary Password Generated' }}
                            </h2>
                            <p class="text-xs text-base-content/70">
                                Temporary login credentials for <span class="font-semibold text-base-content">{{ $modalCreds['name'] }}</span>
                            </p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-circle btn-ghost text-base-content/70 hover:text-base-content" data-close-credentials-modal aria-label="Close dialog">
                        <span class="icon-[tabler--x] size-5"></span>
                    </button>
                </div>

                @if (! $modalCreds['email_sent'])
                    <x-alert-box type="warning" size="sm">
                        <span>Email delivery is unavailable. Please copy or send these credentials to the user directly.</span>
                    </x-alert-box>
                @else
                    <x-alert-box type="info" size="sm">
                        <span>These credentials have also been emailed to the user.</span>
                    </x-alert-box>
                @endif

                <!-- Details card -->
                <div class="space-y-3 rounded-lg border border-base-content/10 bg-base-200/50 p-4 text-sm">
                    <div class="flex items-center justify-between border-b border-base-content/10 pb-2">
                        <span class="text-xs font-semibold text-base-content/60">Full Name</span>
                        <span class="font-medium text-base-content">{{ $modalCreds['name'] }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-base-content/10 pb-2">
                        <span class="text-xs font-semibold text-base-content/60">Email / Username</span>
                        <span class="font-mono text-xs font-medium text-base-content">{{ $modalCreds['email'] }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-base-content/10 pb-2">
                        <span class="text-xs font-semibold text-base-content/60">Role</span>
                        <span class="badge badge-soft badge-primary text-xs">{{ $modalCreds['role'] }}</span>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-semibold text-base-content/60">Temporary Password</span>
                            <span class="text-[11px] text-base-content/50">Expires: {{ $modalCreds['expires_at'] ?? '7 days' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 rounded-md border border-base-content/10 bg-base-100 px-3 py-2 font-mono text-sm font-semibold tracking-wider text-primary select-all" id="temp-password-text">{{ $modalCreds['temporary_password'] }}</div>
                            <button type="button" class="btn btn-outline btn-sm gap-1" id="copy-password-btn" title="Copy password">
                                <span class="icon-[tabler--copy] size-4 copy-icon"></span>
                                <span class="copy-label">Copy</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-warning/10 p-3 text-xs text-black border border-warning/20 flex items-start gap-2">
                    <span class="icon-[tabler--shield-lock] size-4 shrink-0 mt-0.5 text-warning"></span>
                    <div>
                        <strong>Notice:</strong> The user is required to change this temporary password upon their first login before they can access the system.
                    </div>
                </div>

                @php
                    $fullCredentialText = "NFA GMR System - Account Login Credentials\n"
                        . "Name: " . $modalCreds['name'] . "\n"
                        . "Email: " . $modalCreds['email'] . "\n"
                        . "Role: " . $modalCreds['role'] . "\n"
                        . "Temporary Password: " . $modalCreds['temporary_password'] . "\n"
                        . "Login URL: " . $modalCreds['login_url'] . "\n\n"
                        . "Note: You must change your temporary password upon your first login.";

                    $mailSubject = "NFA GMR System - Your Account Credentials";
                    $mailBody = "Hello " . $modalCreds['name'] . ",\n\n"
                        . "Your account has been created on the NFA GMR System.\n\n"
                        . "Login Email: " . $modalCreds['email'] . "\n"
                        . "Temporary Password: " . $modalCreds['temporary_password'] . "\n"
                        . "Login URL: " . $modalCreds['login_url'] . "\n\n"
                        . "Please log in and update your password immediately upon first login.\n\n"
                        . "Regards,\nNFA Administrator";
                    $mailToLink = "mailto:" . rawurlencode($modalCreds['email']) . "?subject=" . rawurlencode($mailSubject) . "&body=" . rawurlencode($mailBody);
                @endphp

                <textarea id="full-credentials-text" class="sr-only" readonly>{{ $fullCredentialText }}</textarea>

                <!-- Actions -->
                <div class="flex items-center justify-between gap-2 pt-2 border-t border-base-content/10 flex-wrap">
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-primary inline-flex items-center gap-1.5" id="copy-all-btn">
                            <span class="icon-[tabler--copy] size-4 copy-icon"></span>
                            <span class="copy-label">Copy all details</span>
                        </button>
                        <a href="{{ $mailToLink }}" class="btn btn-sm btn-outline inline-flex items-center gap-1.5" title="Open in default email app">
                            <span class="icon-[tabler--mail-forward] size-4"></span>
                            <span>Send via Email</span>
                        </a>
                    </div>
                    <button type="button" class="btn btn-sm btn-ghost" data-close-credentials-modal>
                        Done
                    </button>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('credentials-popup-modal');
                if (!modal) return;

                function copyToClipboard(text, button) {
                    const doFeedback = () => {
                        if (!button) return;
                        const label = button.querySelector('.copy-label');
                        const icon = button.querySelector('.copy-icon');
                        const originalText = label ? label.textContent : '';
                        if (label) label.textContent = 'Copied!';
                        if (icon) {
                            icon.classList.remove('icon-[tabler--copy]');
                            icon.classList.add('icon-[tabler--check]');
                        }
                        button.classList.add('btn-success');
                        setTimeout(() => {
                            if (label) label.textContent = originalText;
                            if (icon) {
                                icon.classList.remove('icon-[tabler--check]');
                                icon.classList.add('icon-[tabler--copy]');
                            }
                            button.classList.remove('btn-success');
                        }, 2000);
                    };

                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(text).then(doFeedback).catch(() => {
                            fallbackCopy(text);
                            doFeedback();
                        });
                    } else {
                        fallbackCopy(text);
                        doFeedback();
                    }
                }

                function fallbackCopy(text) {
                    const textArea = document.createElement("textarea");
                    textArea.value = text;
                    textArea.style.position = "fixed";
                    textArea.style.left = "-999999px";
                    textArea.style.top = "-999999px";
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    try {
                        document.execCommand('copy');
                    } catch (err) {}
                    textArea.remove();
                }

                const copyPassBtn = document.getElementById('copy-password-btn');
                if (copyPassBtn) {
                    copyPassBtn.addEventListener('click', function () {
                        const passText = document.getElementById('temp-password-text')?.textContent?.trim() || '';
                        copyToClipboard(passText, copyPassBtn);
                    });
                }

                const copyAllBtn = document.getElementById('copy-all-btn');
                if (copyAllBtn) {
                    copyAllBtn.addEventListener('click', function () {
                        const fullText = document.getElementById('full-credentials-text')?.value || '';
                        copyToClipboard(fullText, copyAllBtn);
                    });
                }

                function closeModal() {
                    modal.classList.add('opacity-0', 'pointer-events-none');
                    setTimeout(() => modal.remove(), 200);
                }

                modal.querySelectorAll('[data-close-credentials-modal]').forEach(btn => {
                    btn.addEventListener('click', closeModal);
                });

                modal.addEventListener('click', function (e) {
                    if (e.target === modal) {
                        closeModal();
                    }
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && document.contains(modal)) {
                        closeModal();
                    }
                });
            });
        </script>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const roleSelect = document.getElementById('user-role-select');
            const branchSelect = document.getElementById('user-branch-select');
            const branchStar = document.getElementById('branch-star');
            if (roleSelect && branchSelect) {
                const updateBranchRequirement = () => {
                    const isStaff = roleSelect.value === 'STAFF';
                    branchSelect.required = isStaff;
                    if (branchStar) {
                        branchStar.style.display = isStaff ? 'inline' : 'none';
                    }
                    if (roleSelect.value === 'RMEC' || roleSelect.value === 'ADMINISTRATOR') {
                        branchSelect.value = '';
                    }
                };
                roleSelect.addEventListener('change', updateBranchRequirement);
                updateBranchRequirement();
            }

            document.querySelectorAll('[data-open-reset-modal]').forEach(btn => {
                btn.addEventListener('click', function () {
                    const modalId = this.getAttribute('data-open-reset-modal');
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                        modal.querySelector('input[name="password"]')?.focus();
                    }
                });
            });

            document.querySelectorAll('.reset-password-modal').forEach(modal => {
                function closeResetModal() {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }

                modal.querySelectorAll('[data-close-reset-modal]').forEach(closeBtn => {
                    closeBtn.addEventListener('click', closeResetModal);
                });

                modal.addEventListener('click', function (e) {
                    if (e.target === modal) {
                        closeResetModal();
                    }
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                        closeResetModal();
                    }
                });
            });
        });
    </script>
@endsection
