@extends('layouts.app')

@section('title', 'Profile Settings')

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Profile settings</h1>
        <p class="mt-1 text-sm text-base-content/60">Update your name, profile photo, and password.</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-5 rounded-lg border border-base-content/10 bg-base-100 p-6">
        @csrf
        @method('PUT')

        <div class="flex items-center gap-4">
            @if ($user->profile_photo_path)
                <img src="{{ Storage::disk('public')->url($user->profile_photo_path) }}" alt="Profile photo" class="size-20 rounded-full object-cover">
            @else
                <div class="grid size-20 place-items-center rounded-full bg-primary/10 text-2xl font-semibold text-primary" aria-label="Profile photo placeholder">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
            @endif
            <label class="form-control flex-1">
                <span class="label-text mb-2 font-medium">Profile photo</span>
                <input class="file-input file-input-bordered w-full" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp">
                <span class="mt-1 text-xs text-base-content/60">JPG, PNG, or WebP; maximum 2 MB.</span>
                @error('profile_photo') <span class="mt-1 text-sm text-error">{{ $message }}</span> @enderror
            </label>
        </div>

        <label class="form-control">
            <span class="label-text mb-2 font-medium">Name</span>
            <input class="input input-bordered w-full" type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="191">
            @error('name') <span class="mt-1 text-sm text-error">{{ $message }}</span> @enderror
        </label>

        <div class="grid gap-1 text-sm">
            <span class="font-medium">Email</span>
            <span class="text-base-content/60">{{ $user->email }}</span>
        </div>

        <div class="grid gap-1 text-sm">
            <span class="font-medium">Role</span>
            <span class="text-base-content/60">{{ $user->role }}</span>
        </div>

        <div class="divider text-xs text-base-content/50 uppercase tracking-wider font-semibold">Change Password</div>

        <p class="text-xs text-base-content/60">Leave password fields blank if you do not want to change your current password.</p>

        <label class="form-control">
            <span class="label-text mb-2 font-medium">Current password</span>
            <div class="relative">
                <input class="input input-bordered w-full pe-10" type="password" name="current_password" autocomplete="current-password" placeholder="Enter current password to change">
                <button type="button" class="absolute inset-y-0 end-0 flex items-center pe-3 text-base-content/60 hover:text-base-content focus:outline-none cursor-pointer" data-password-toggle aria-label="Toggle current password visibility">
                    <span class="icon-[tabler--eye] size-5 password-eye-open"></span>
                    <span class="icon-[tabler--eye-off] size-5 password-eye-closed hidden"></span>
                </button>
            </div>
            @error('current_password') <span class="mt-1 text-sm text-error">{{ $message }}</span> @enderror
        </label>

        <div class="grid gap-4 md:grid-cols-2">
            <label class="form-control">
                <span class="label-text mb-2 font-medium">New password</span>
                <div class="relative">
                    <input class="input input-bordered w-full pe-10" type="password" name="password" autocomplete="new-password" placeholder="Minimum 12 characters">
                    <button type="button" class="absolute inset-y-0 end-0 flex items-center pe-3 text-base-content/60 hover:text-base-content focus:outline-none cursor-pointer" data-password-toggle aria-label="Toggle new password visibility">
                        <span class="icon-[tabler--eye] size-5 password-eye-open"></span>
                        <span class="icon-[tabler--eye-off] size-5 password-eye-closed hidden"></span>
                    </button>
                </div>
                @error('password') <span class="mt-1 text-sm text-error">{{ $message }}</span> @enderror
            </label>

            <label class="form-control">
                <span class="label-text mb-2 font-medium">Confirm new password</span>
                <div class="relative">
                    <input class="input input-bordered w-full pe-10" type="password" name="password_confirmation" autocomplete="new-password" placeholder="Re-type new password">
                    <button type="button" class="absolute inset-y-0 end-0 flex items-center pe-3 text-base-content/60 hover:text-base-content focus:outline-none cursor-pointer" data-password-toggle aria-label="Toggle confirm password visibility">
                        <span class="icon-[tabler--eye] size-5 password-eye-open"></span>
                        <span class="icon-[tabler--eye-off] size-5 password-eye-closed hidden"></span>
                    </button>
                </div>
            </label>
        </div>

        <button class="btn btn-primary" type="submit">Save profile</button>
    </form>
</div>
@endsection
