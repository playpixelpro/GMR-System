@extends('layouts.app')

@section('title', 'Activity Logs')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Activity Logs</h1>
            <p class="mt-1 text-sm text-base-content/70">Audit trail of meaningful business activities. Logs are retained for one month.</p>
        </div>
        <form method="GET" action="{{ route('settings.activity-logs.download') }}">
            @foreach (['from', 'to', 'branch_id', 'user_id', 'module', 'action'] as $field)
                @if (filled($filters[$field] ?? null))
                    <input type="hidden" name="{{ $field }}" value="{{ $filters[$field] }}">
                @endif
            @endforeach
            <button class="btn btn-outline btn-primary btn-sm gap-2" type="submit">
                <span class="icon-[tabler--download] size-4"></span>
                Download JSON
            </button>
        </form>
    </div>

    @if (session('status'))
        <x-alert-box type="success" :message="session('status')" dismissible class="mb-4" />
    @endif

    <form method="GET" action="{{ route('settings.activity-logs') }}" class="grid gap-3 rounded-lg border border-base-content/10 bg-base-100 p-4 md:grid-cols-6 items-end">
        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">From</label>
            <input class="input input-sm w-full" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">To</label>
            <input class="input input-sm w-full" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        </div>
        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">Branch</label>
            <select class="select select-sm w-full" name="branch_id">
                <option value="">All Branches</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">User</label>
            <select class="select select-sm w-full" name="user_id">
                <option value="">All Users</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">Module</label>
            <select class="select select-sm w-full" name="module">
                <option value="">All Modules</option>
                @foreach ($modules as $module)
                    <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ ucfirst($module) }}</option>
                @endforeach
            </select>
        </div>
        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">Action</label>
            <select class="select select-sm w-full" name="action">
                <option value="">All Actions</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-6 flex justify-end gap-2">
            <a href="{{ route('settings.activity-logs') }}" class="btn btn-sm btn-ghost">Reset</a>
            <button class="btn btn-sm btn-primary gap-1.5" type="submit">
                <span class="icon-[tabler--filter] size-4"></span>
                Apply Filters
            </button>
        </div>
    </form>

    <div class="overflow-x-auto rounded-lg border border-base-content/10 bg-base-100">
        @if ($logs->isEmpty())
            <div class="px-6 py-16 text-center text-base-content/60">No activity records found for the selected filters.</div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>User</th>
                        <th>Branch</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Record</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td class="text-xs whitespace-nowrap">{{ $log->created_at?->format('M d, Y h:i A') }}</td>
                            <td>
                                <div class="font-medium">{{ $log->user?->name ?? 'System' }}</div>
                                @if ($log->role)
                                    <div class="text-[11px] text-base-content/50">{{ $log->role }}</div>
                                @endif
                            </td>
                            <td class="text-xs">{{ $log->branch_name ?? $log->branch?->name ?? '—' }}</td>
                            <td>
                                @if ($log->module)
                                    <span class="badge badge-soft badge-neutral text-xs uppercase">{{ $log->module }}</span>
                                @else
                                    <span class="text-base-content/40">—</span>
                                @endif
                            </td>
                            <td class="text-xs font-mono">{{ $log->action }}</td>
                            <td class="text-xs">{{ $log->description }}</td>
                            <td class="text-xs">{{ $log->auditable_id ?? '—' }}</td>
                            <td class="text-xs font-mono">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div>
        {{ $logs->links() }}
    </div>
</div>
@endsection
