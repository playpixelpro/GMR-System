@extends('layouts.app')

@section('title', 'Blocked IPs')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Blocked IPs</h1>
        <p class="mt-1 text-sm text-base-content/70">IP addresses locked out after too many failed login or password reset attempts. Unlock an address to allow the user to try again immediately.</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success flex items-start gap-2">
            <span class="icon-[tabler--circle-check] size-5 shrink-0 mt-0.5" aria-hidden="true"></span>
            <div>{{ session('status') }}</div>
        </div>
    @endif

    <div class="overflow-x-auto rounded-lg border border-base-content/10 bg-base-100">
        @if ($blocks->isEmpty())
            <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                <span class="icon-[tabler--shield-check] size-10 text-success"></span>
                <p class="text-base-content/70">No IP addresses are currently blocked.</p>
            </div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Reason</th>
                        <th>Attempts</th>
                        <th>Locked Until</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($blocks as $block)
                        <tr>
                            <td class="font-mono text-sm">{{ $block->ip_address }}</td>
                            <td>
                                <span class="badge badge-soft badge-neutral text-xs capitalize">
                                    {{ str_replace('_', ' ', $block->reason ?? 'unknown') }}
                                </span>
                            </td>
                            <td>{{ $block->attempts }}</td>
                            <td>
                                {{ $block->blocked_until?->format('M d, Y h:i A') ?? '—' }}
                            </td>
                            <td>
                                @if ($block->isCurrentlyBlocked())
                                    <span class="badge badge-soft badge-error text-xs">Blocked</span>
                                @else
                                    <span class="badge badge-soft badge-success text-xs">Expired</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('settings.blocked-ips.unlock', $block->ip_address) }}">
                                    @csrf
                                    <button class="btn btn-xs btn-outline btn-success inline-flex items-center gap-1" type="submit" title="Unlock this IP address">
                                        <span class="icon-[tabler--lock-open] size-3.5"></span>
                                        <span>Unlock</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
