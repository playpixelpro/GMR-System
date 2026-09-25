@extends('layouts.guest')

@section('title', 'Forgot Password')

@section('content')
<div class="mx-auto max-w-md rounded-lg border border-base-content/10 bg-base-100 p-6 shadow-sm">
    <h1 class="text-2xl font-semibold">Reset Password</h1>
    @if (session('status')) <div class="mt-4 alert alert-success">{{ session('status') }}</div> @endif
    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf
        <label class="block">Registered email<input class="input mt-1 w-full" type="email" name="email" required></label>
        <button class="btn btn-primary w-full" type="submit">Email reset link</button>
    </form>
</div>
@endsection
