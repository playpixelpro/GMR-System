@extends('layouts.guest')

@section('title', 'Check your email')

@section('content')
<div class="rounded-xl border border-base-content/10 bg-base-100 p-6 shadow-sm sm:p-8">
    <div class="mb-5 flex justify-center">
        <img src="{{ asset('new-nfa-logo.webp') }}" alt="NFA logo" class="size-16 object-contain">
    </div>

    <div class="mx-auto grid size-12 place-items-center rounded-full bg-primary/10 text-primary">
        <span class="icon-[tabler--mail-check] size-6" aria-hidden="true"></span>
    </div>

    <h1 class="mt-4 text-center text-2xl font-semibold">Check your email</h1>
    <p class="mt-2 text-center text-sm text-base-content/70">
        Your staff account has been created. We sent your <strong>temporary login credentials</strong> to:
    </p>
    <p class="mt-3 rounded-md border border-base-content/10 bg-base-200/50 px-3 py-2 text-center font-mono text-sm font-semibold">
        {{ $email }}
    </p>

    <div class="mt-5 alert alert-info text-xs">
        <ul class="list-disc pl-4 space-y-1">
            <li>Use the temporary password from the email to sign in.</li>
            <li>You will be required to change it on your first login.</li>
            <li>An Administrator must confirm your account within <strong>8 hours</strong> or it will be disabled.</li>
        </ul>
    </div>

    <div class="mt-6 text-center">
        <a href="{{ route('login') }}" class="btn btn-primary w-full">Go to login</a>
    </div>
</div>
@endsection
