@extends('layouts.guest')

@section('title', 'Change Password')

@section('content')
<div class="mx-auto max-w-md rounded-lg border border-base-content/10 bg-base-100 p-6 shadow-sm">
    <div class="mb-5 flex justify-center">
        <img src="{{ asset('new-nfa-logo.webp') }}" alt="NFA logo" class="size-16 object-contain">
    </div>
    <h1 class="text-2xl font-semibold">Change Password</h1>
    <p class="mt-2 text-sm">You must change your temporary password before continuing.</p>
    @if ($errors->any()) <div class="mt-4 alert alert-error">{{ $errors->first() }}</div> @endif
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        @method('PUT')
        <label class="block">
            <span class="label-text mb-1 p-0 font-medium">New password</span>
            <div class="relative mt-1">
                <input class="input w-full pe-10" type="password" name="password" required autocomplete="new-password">
                <button type="button" class="absolute inset-y-0 end-0 flex items-center pe-3 text-base-content/60 hover:text-base-content focus:outline-none cursor-pointer" data-password-toggle aria-label="Toggle password visibility">
                    <span class="icon-[tabler--eye] size-5 password-eye-open"></span>
                    <span class="icon-[tabler--eye-off] size-5 password-eye-closed hidden"></span>
                </button>
            </div>
        </label>
        <label class="block">
            <span class="label-text mb-1 p-0 font-medium">Confirm password</span>
            <div class="relative mt-1">
                <input class="input w-full pe-10" type="password" name="password_confirmation" required autocomplete="new-password">
                <button type="button" class="absolute inset-y-0 end-0 flex items-center pe-3 text-base-content/60 hover:text-base-content focus:outline-none cursor-pointer" data-password-toggle aria-label="Toggle password visibility">
                    <span class="icon-[tabler--eye] size-5 password-eye-open"></span>
                    <span class="icon-[tabler--eye-off] size-5 password-eye-closed hidden"></span>
                </button>
            </div>
        </label>
        <button class="btn btn-primary w-full" type="submit">Change password</button>
    </form>
</div>
@endsection
