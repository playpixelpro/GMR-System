@extends('layouts.app')

@section('title', 'Profile Settings')

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Profile settings</h1>
        <p class="mt-1 text-sm text-base-content/60">Update your name, profile photo, and password.</p>
    </div>

    @if (session('status'))
        <x-alert-box type="success" :message="session('status')" dismissible class="mb-4" />
    @endif

    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-5 rounded-lg border border-base-content/10 bg-base-100 p-6">
        @csrf
        @method('PATCH')

        <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
            <div class="relative">
                <label for="profile-photo-input" class="group relative block size-24 cursor-pointer overflow-hidden rounded-full" aria-label="Upload profile photo">
                    @if ($user->avatar_url)
                        <img src="{{ $user->avatar_url }}" alt="Profile photo" class="size-full object-cover" data-profile-preview
                             onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">
                        <div class="hidden size-full place-items-center bg-primary/10 text-3xl font-semibold text-primary" data-profile-initial aria-label="Profile photo placeholder">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                    @else
                        <div class="grid size-full place-items-center bg-primary/10 text-3xl font-semibold text-primary" data-profile-preview aria-label="Profile photo placeholder">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                    @endif
                    <span class="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/30"></span>
                    <span class="absolute bottom-0 right-0 grid size-8 place-items-center rounded-full border-2 border-base-100 bg-primary text-primary-content shadow">
                        <span class="icon-[tabler--camera] size-4"></span>
                    </span>
                </label>
                <p class="mt-2 text-center text-xs text-base-content/60">Click the camera to upload or change your photo</p>
                @error('profile_photo') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
            </div>
            <input id="profile-photo-input" class="hidden" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" data-profile-upload>
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

<script>
    (function () {
        const input = document.querySelector('[data-profile-upload]');
        const wrapper = document.querySelector('[data-profile-preview]');
        if (!input || !wrapper) return;

        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file) return;

            const url = URL.createObjectURL(file);
            if (wrapper.tagName.toLowerCase() === 'img') {
                wrapper.src = url;
                wrapper.style.display = '';
                const initial = wrapper.nextElementSibling;
                if (initial && initial.hasAttribute('data-profile-initial')) {
                    initial.style.display = 'none';
                }
            } else {
                const img = document.createElement('img');
                img.src = url;
                img.alt = 'Profile photo preview';
                img.className = 'size-full object-cover';
                wrapper.replaceWith(img);
            }
        });
    })();
</script>
@endsection
