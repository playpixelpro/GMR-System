@extends('layouts.app')

@section('title', 'Milling Detail')

@section('content')
@php
    $fmtKg = static fn (?float $v): string => $v === null ? 'N/A' : number_format($v, 3).' kg';
    $fmtPct = static fn (?float $v): string => $v === null ? 'N/A' : number_format($v, 2).'%';
@endphp

<div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
    <div>
        <h1 class="text-2xl font-bold leading-tight text-black sm:text-3xl">Milling Detail</h1>
        <p class="mt-2 text-sm leading-6 text-black">
            {{ $milling->branch?->name ?? '—' }} · Pile {{ $milling->pile?->pile_number ?? ($milling->pile?->number ?? '—') }}
            @if ($milling->reference_number) · Project/Memo Ref: {{ $milling->reference_number }} @endif
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('millings.index') }}" class="btn btn-outline btn-sm sm:btn-md gap-2">
            <span class="icon-[tabler--arrow-left] size-5"></span>
            Back
        </a>
    </div>
</div>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
    <div class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <p class="text-xs font-semibold uppercase text-black">Status</p>
        <p class="mt-2">
            @if ($milling->status === 'assigned')
                <span class="badge badge-soft badge-neutral">Assigned</span>
            @elseif ($milling->status === 'ongoing')
                <span class="badge badge-soft badge-info">Ongoing</span>
            @elseif ($milling->status === 'completed')
                <span class="badge badge-soft badge-success">Completed</span>
            @else
                <span class="badge badge-soft badge-error">Cancelled</span>
            @endif
        </p>
    </div>
    <div class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <p class="text-xs font-semibold uppercase text-black">Project / Memo Ref.</p>
        <p class="mt-2 text-sm font-mono font-medium">{{ $milling->reference_number ?? '—' }}</p>
        <p class="text-xs text-base-content/60">{{ $milling->miller ?? '—' }}</p>
    </div>
    <div class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <p class="text-xs font-semibold uppercase text-black">Final GMR (Milling Basis)</p>
        <p class="mt-2 text-lg font-bold font-mono">
            @if ($milling->final_gmr !== null)
                {{ $fmtPct((float) $milling->final_gmr) }}
            @else
                N/A
            @endif
        </p>
        <p class="text-xs">
            @if ($milling->final_gmr_source === 'co_approved')
                <span class="font-semibold uppercase text-success">Central Office Approved</span>
            @elseif ($milling->final_gmr_source === 'recommended')
                <span class="uppercase text-base-content/60">Recommended</span>
            @else
                <span class="text-base-content/60">—</span>
            @endif
        </p>
    </div>
    <div class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <p class="text-xs font-semibold uppercase text-black">Target Volume</p>
        <p class="mt-2 text-lg font-bold font-mono">{{ $fmtKg($milling->target_volume_kg !== null ? (float) $milling->target_volume_kg : null) }}</p>
        <p class="text-xs text-base-content/60">{{ $milling->target_volume_bags !== null ? number_format((float) $milling->target_volume_bags, 3).' bags' : '' }}</p>
    </div>
    <div class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <p class="text-xs font-semibold uppercase text-black">Cumulative Milled</p>
        <p class="mt-2 text-lg font-bold font-mono">{{ $fmtKg($cumulativeMilled) }}</p>
        <p class="text-xs text-base-content/60">Progress: {{ $fmtPct($progressPct) }}</p>
    </div>
    <div class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <p class="text-xs font-semibold uppercase text-black">Cumulative Palay Input</p>
        <p class="mt-2 text-lg font-bold font-mono">{{ $fmtKg($cumulativePalay) }}</p>
        <p class="text-xs text-base-content/60">Miller: {{ $milling->miller ?? '—' }}</p>
    </div>
</div>

@if ($milling->pile)
    <div class="mb-5 card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <h2 class="mb-2 text-sm font-semibold uppercase text-black">Pile Details</h2>
        <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div><span class="text-base-content/60">Pile:</span> <span class="font-semibold">{{ $milling->pile->pile_number ?? $milling->pile->number }}</span></div>
            <div><span class="text-base-content/60">Variety:</span> {{ $milling->pile->variety ?? '—' }}</div>
            <div><span class="text-base-content/60">Quality:</span> {{ ! empty($milling->pile->quality) ? strtoupper($milling->pile->quality) : 'GQA' }}</div>
            <div><span class="text-base-content/60">Warehouse:</span> {{ $milling->pile->warehouse?->name ?? '—' }}</div>
        </div>
    </div>
@endif

@can('manage-millings')
    <div class="mb-5 card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <h2 class="mb-3 text-sm font-semibold uppercase text-black">Change Status</h2>
        <form method="POST" action="{{ route('millings.update', $milling) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            @method('PATCH')
            <label class="form-control">
                <span class="label-text mb-2 text-sm font-semibold text-black">Status</span>
                <select name="status" class="select select-bordered min-h-11 text-base text-black">
                    <option value="assigned" @selected($milling->status === 'assigned')>Assigned</option>
                    <option value="ongoing" @selected($milling->status === 'ongoing')>Ongoing</option>
                    <option value="completed" @selected($milling->status === 'completed')>Completed</option>
                    <option value="cancelled" @selected($milling->status === 'cancelled')>Cancelled</option>
                </select>
            </label>
            <label class="form-control flex-1 min-w-[16rem]">
                <span class="label-text mb-2 text-sm font-semibold text-black">Remarks</span>
                <input type="text" name="remarks" class="input input-bordered min-h-11 text-black" value="{{ $milling->remarks ?? '' }}" />
            </label>
            <button type="submit" class="btn btn-primary btn-sm">Update Status</button>
        </form>
    </div>
@endcan

@can('record-milling-progress')
    @if (in_array($milling->status, ['assigned', 'ongoing'], true))
        <div class="mb-5 card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
            <h2 class="mb-3 text-sm font-semibold uppercase text-black">Log Accomplishment</h2>
            <form method="POST" action="{{ route('millings.progress.store', $milling) }}" class="grid grid-cols-1 gap-3 md:grid-cols-4">
                @csrf
                <label class="form-control">
                    <span class="label-text mb-2 text-xs font-semibold text-black">Date *</span>
                    <input type="date" name="progress_date" value="{{ old('progress_date', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" class="input input-bordered min-h-11 text-black" required />
                </label>
                <label class="form-control">
                    <span class="label-text mb-2 text-xs font-semibold text-black">Palay Input (kg)</span>
                    <input type="number" step="0.001" min="0" name="palay_input_kg" class="input input-bordered min-h-11 text-black" value="{{ old('palay_input_kg') }}" />
                </label>
                <label class="form-control">
                    <span class="label-text mb-2 text-xs font-semibold text-black">Milled Rice (kg)</span>
                    <input type="number" step="0.001" min="0" name="milled_rice_kg" class="input input-bordered min-h-11 text-black" value="{{ old('milled_rice_kg') }}" />
                </label>
                <label class="form-control md:col-span-1">
                    <span class="label-text mb-2 text-xs font-semibold text-black">Remarks</span>
                    <input type="text" name="remarks" class="input input-bordered min-h-11 text-black" value="{{ old('remarks') }}" />
                </label>
                <div class="md:col-span-4">
                    <button type="submit" class="btn btn-primary btn-sm gap-2">
                        <span class="icon-[tabler--plus] size-4"></span>
                        Record Progress
                    </button>
                </div>
            </form>
        </div>
    @endif
@endcan

<section class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
    <h2 class="mb-4 text-lg font-semibold text-black">Accomplishment Log</h2>
    @if ($milling->progress->isEmpty())
        <p class="text-sm text-base-content/60">No progress entries have been recorded yet.</p>
    @else
        <div class="overflow-x-auto">
            <table class="table table-sm min-w-[48rem] text-sm">
                <thead>
                    <tr class="border-b border-base-content/15 text-sm font-semibold text-black">
                        <th>Date</th>
                        <th class="text-end">Palay Input (kg)</th>
                        <th class="text-end">Milled Rice (kg)</th>
                        <th class="text-end">Recovery</th>
                        <th>Recorded By</th>
                        <th>Remarks</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($milling->progress as $entry)
                        <tr class="border-b border-base-content/10">
                            <td class="font-medium">{{ $entry->progress_date?->format('M d, Y') }}</td>
                            <td class="text-end font-mono">{{ $entry->palay_input_kg !== null ? number_format((float) $entry->palay_input_kg, 3) : '—' }}</td>
                            <td class="text-end font-mono">{{ $entry->milled_rice_kg !== null ? number_format((float) $entry->milled_rice_kg, 3) : '—' }}</td>
                            <td class="text-end font-mono">{{ $entry->recovery_percentage !== null ? $fmtPct((float) $entry->recovery_percentage) : '—' }}</td>
                            <td class="text-sm">{{ $entry->recordedBy?->name ?? '—' }}</td>
                            <td class="text-sm text-base-content/70">{{ $entry->remarks ?? '—' }}</td>
                            <td class="text-end">
                                @can('manage-millings')
                                    <form method="POST" action="{{ route('millings.progress.destroy', $entry) }}" onsubmit="return confirm('Delete this progress entry?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-xs text-error" title="Delete entry">
                                            <span class="icon-[tabler--trash] size-4"></span>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
