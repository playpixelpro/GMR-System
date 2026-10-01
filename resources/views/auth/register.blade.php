@extends('layouts.guest')

@section('title', 'Register')

@section('content')
<div class="mx-auto max-w-md rounded-lg border border-base-content/10 bg-base-100 p-6 shadow-sm">
    <div class="mb-5 flex justify-center">
        <img src="{{ asset('new-nfa-logo.webp') }}" alt="NFA logo" class="size-16 object-contain">
    </div>
    <h1 class="text-2xl font-semibold">Register Staff Account</h1>
    <p class="mt-1 text-sm text-base-content/70">Create an account to access your assigned branch. A temporary password will be emailed to you, and an Administrator must confirm your account.</p>

    @if ($errors->any())
        <x-alert-box type="error" class="mt-4" dismissible>
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

    <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4">
        @csrf
        <label class="block">
            <span class="label-text mb-1 p-0 font-medium">Full Name</span>
            <input class="input mt-1 w-full" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
        </label>
        <label class="block">
            <span class="label-text mb-1 p-0 font-medium">Email address</span>
            <input class="input mt-1 w-full" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
        </label>
        <label class="block">
            <span class="label-text mb-1 p-0 font-medium">Branch to access</span>
            <select class="select mt-1 w-full" name="branch_id" required>
                <option value="">Select branch</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </label>

        <x-alert-box type="info" size="sm">
            Your account is active for <strong>8 hours</strong> from creation. It will be disabled if an Administrator does not confirm it within that time.
        </x-alert-box>

        <button class="btn btn-primary w-full" type="submit">Register</button>
    </form>

    <p class="mt-4 text-center text-sm">
        Already have an account?
        <a class="link link-primary" href="{{ route('login') }}">Sign in</a>
    </p>
</div>
@endsection
