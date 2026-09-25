@extends('layouts.guest')

@section('title', 'Change Password')

@section('content')
<div class="mx-auto max-w-md rounded-lg border border-base-content/10 bg-base-100 p-6 shadow-sm">
    <h1 class="text-2xl font-semibold">Change Password</h1>
    <p class="mt-2 text-sm">You must change your temporary password before continuing.</p>
    @if ($errors->any()) <div class="mt-4 alert alert-error">{{ $errors->first() }}</div> @endif
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        @method('PUT')
        <label class="block">New password<input class="input mt-1 w-full" type="password" name="password" required></label>
        <label class="block">Confirm password<input class="input mt-1 w-full" type="password" name="password_confirmation" required></label>
        <button class="btn btn-primary w-full" type="submit">Change password</button>
    </form>
</div>
@endsection
