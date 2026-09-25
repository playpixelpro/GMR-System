@extends('layouts.app')

@section('title', 'User Management')

@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-semibold">User Management</h1>
    @if (session('status')) <div class="alert alert-success">{{ session('status') }}</div> @endif
    <form method="POST" action="{{ route('users.store') }}" class="grid gap-3 rounded-lg border border-base-content/10 bg-base-100 p-4 md:grid-cols-4">
        @csrf
        <input class="input" name="name" placeholder="Name" required>
        <input class="input" type="email" name="email" placeholder="Email" required>
        <select class="select" name="role" required><option value="STAFF">Staff</option><option value="RMEC">RMEC</option><option value="ADMINISTRATOR">Administrator</option></select>
        <button class="btn btn-primary" type="submit">Create user</button>
    </form>
    <div class="overflow-x-auto rounded-lg border border-base-content/10 bg-base-100"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody>@foreach ($users as $user)<tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ $user->role }}</td><td>{{ $user->is_active ? 'Active' : 'Disabled' }}</td><td>@if ($user->is_active)<form method="POST" action="{{ route('users.disable', $user) }}">@csrf<button class="btn btn-sm btn-error" type="submit">Disable</button></form>@endif</td></tr>@endforeach</tbody></table></div>
</div>
@endsection
