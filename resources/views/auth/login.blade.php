@extends('layouts.guest')

@section('title', 'Login')

@section('content')
<div class="rounded-xl border border-base-content/10 bg-base-100 p-6 shadow-sm sm:p-8">
    <div class="mb-6 text-center">
        <span class="mx-auto mb-4 flex size-16 items-center justify-center rounded-xl border border-primary/20 bg-base-100 p-2 shadow-sm">
            <img src="{{ asset('new-nfa-logo.webp') }}" alt="NFA logo" class="size-full object-contain">
        </span>
        <h1 class="text-2xl font-bold text-black">Welcome Back</h1>
        <p class="mt-1 text-sm text-base-content/70">Please enter your details to sign in</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <label class="block">
            <span class="label-text mb-1 p-0 font-medium">Email address</span>
            <input class="input w-full" type="email" name="email" value="{{ old('email') }}" placeholder="Enter your email address" required autofocus autocomplete="username">
        </label>

        <label class="block">
            <span class="label-text mb-1 p-0 font-medium">Password</span>
            <div class="relative">
                <input class="input w-full pe-10" type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                <button type="button" class="absolute inset-y-0 end-0 flex items-center pe-3 text-base-content/60 hover:text-base-content focus:outline-none cursor-pointer" data-password-toggle aria-label="Toggle password visibility">
                    <span class="icon-[tabler--eye] size-5 password-eye-open"></span>
                    <span class="icon-[tabler--eye-off] size-5 password-eye-closed hidden"></span>
                </button>
            </div>
        </label>

        <div class="flex items-center justify-between">
            <label class="flex cursor-pointer items-center gap-2">
                <input class="checkbox checkbox-primary checkbox-sm" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                <span class="text-sm">Remember Me</span>
            </label>
            <a class="link link-primary text-sm" href="{{ route('password.request') }}">Forgot Password?</a>
        </div>

        <button class="btn btn-primary w-full" type="submit">Sign in</button>
    </form>
</div>
@endsection
