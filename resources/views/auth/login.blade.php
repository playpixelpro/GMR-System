@extends('layouts.guest')

@section('title', 'Login')

@section('content')
<div class="mx-auto max-w-md rounded-lg border border-base-content/10 bg-base-100 p-6 shadow-sm">
    <h1 class="text-2xl font-semibold">Sign in</h1>
    @if ($errors->any()) <div class="mt-4 alert alert-error">{{ $errors->first() }}</div> @endif
    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
        @csrf
        <label class="block">Email<input class="input mt-1 w-full" type="email" name="email" value="{{ old('email') }}" required autofocus></label>
        <label class="block">Password<input class="input mt-1 w-full" type="password" name="password" required></label>
        <label class="flex items-center gap-2"><input type="checkbox" name="remember"> Remember me</label>
        <button class="btn btn-primary w-full" type="submit">Sign in</button>
    </form>
    <a class="mt-4 block text-sm text-primary" href="{{ route('password.request') }}">Forgot Password?</a>
</div>
@endsection
