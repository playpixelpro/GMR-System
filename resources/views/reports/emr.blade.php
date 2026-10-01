@extends('layouts.app')

@section('title', 'Expected Milling Recovery Dashboard')

@section('content')
@php
    $formatPercentage = static fn (?float $value): string => $value === null ? 'N/A' : number_format($value, 2).'%' ;
    $formatRange = static fn (?float $lower, ?float $upper): string => $lower === null || $upper === null ? 'N/A' : number_format($lower, 2).'% – '.number_format($upper, 2).'%';

@endphp

<div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
    <div>
        <h1 class="text-2xl font-bold leading-tight text-black sm:text-3xl">EMR - Dashboard</h1>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if (auth()->user()?->hasRole('RMEC', 'ADMINISTRATOR'))
            <a href="{{ route('emr.export.excel', request()->query()) }}" class="btn btn-outline btn-success btn-sm" title="Export EMR report to Excel (.xlsx)">
                <span class="icon-[tabler--file-spreadsheet] size-4"></span>
                Excel Export
            </a>
            <a href="{{ route('emr.export.pdf', request()->query()) }}" class="btn btn-outline btn-error btn-sm" title="Download EMR report as PDF">
                <span class="icon-[tabler--file-type-pdf] size-4"></span>
                PDF Download
            </a>
        @endif
    </div>
</div>

<form method="GET" action="{{ route('emr.index') }}" class="card mb-5 border border-base-content/10 bg-base-100 p-4 shadow-sm">
    <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-3">
        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Branch</span>
            @php
                $isStaffUser = (bool) auth()->user()?->isBranchRestricted();
            @endphp
            <select name="branch_id" class="select select-bordered min-h-11 w-full text-base text-black @if($isStaffUser) bg-gray-100 text-gray-500 cursor-not-allowed @endif" onchange="this.form.submit()" @disabled($isStaffUser)>
                @unless($isStaffUser)
                    <option value="">All Branches</option>
                @endunless
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($filters['branch_id'] === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
            @if($isStaffUser)
                <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
            @endif
        </label>
        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Warehouse</span>
            <select name="warehouse_id" class="select select-bordered min-h-11 w-full text-base text-black" onchange="this.form.submit()">
                <option value="">All Warehouses</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected($filters['warehouse_id'] === $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex gap-2">
            <a href="{{ route('emr.index') }}" class="btn btn-outline min-h-11 px-4 text-sm">Reset Filters</a>
        </div>
    </div>
</form>

<div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 sm:gap-4">
    @foreach (array_merge(
        $filters['warehouse_id'] ? [] : [
            ['label' => 'Total Warehouses', 'value' => number_format($total_warehouses), 'accent' => 'primary', 'size' => 'text-lg xl:text-xl'],
        ],
        [
            ['label' => 'Total Piles', 'value' => number_format($summary['piles']), 'accent' => 'primary', 'size' => 'text-lg xl:text-xl'],
            ['label' => 'Volume in Bags', 'value' => number_format($summary['volume_bags'], 3), 'accent' => 'secondary', 'size' => 'text-base xl:text-lg'],
            ['label' => 'Average Purity', 'value' => $formatPercentage($summary['purity']), 'accent' => 'accent', 'size' => 'text-lg xl:text-xl'],
            ['label' => 'Average AMR', 'value' => $formatPercentage($summary['amr']), 'accent' => 'primary', 'size' => 'text-lg xl:text-xl'],
            ['label' => 'Average PMR', 'value' => $formatPercentage($summary['pmr']), 'accent' => 'secondary', 'size' => 'text-lg xl:text-xl'],
            ['label' => 'EMR', 'value' => $formatRange($summary['emr_lower'], $summary['emr_upper']), 'accent' => 'accent', 'size' => 'text-sm xl:text-base'],
        ],
    ) as $card)
        <div class="card min-w-0 overflow-hidden border border-base-content/10 bg-base-100 p-3 sm:p-4 text-black shadow-sm">
            <p class="text-xs sm:text-sm font-semibold text-black">{{ $card['label'] }}</p>
            <p class="mt-2 {{ $card['size'] }} font-bold text-black">{{ $card['value'] }}</p>
        </div>
    @endforeach
</div>

@if ($allRows->isEmpty())
    <div class="card mb-5 border border-dashed border-base-content/20 bg-base-100 p-10 text-center text-black shadow-sm">
        <span class="icon-[tabler--database-off] mx-auto size-10 text-black"></span>
        <h2 class="mt-3 font-semibold">No EMR records found.</h2>
        <p class="mt-1 text-sm text-black">Try changing the selected branch or warehouse.</p>
    </div>
@else

    <section class="card mb-5 border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-black">EMR Summary by Warehouse</h2>
        <div class="overflow-x-auto">
            <table class="table table-sm min-w-208 text-sm">
                <thead><tr class="border-b border-base-content/15 text-sm font-semibold text-black"><th>Warehouse</th><th class="text-end">Piles</th><th class="text-end">Volume (bags)</th><th class="text-end">Avg Purity</th><th class="text-end">Avg AMR</th><th class="text-end">Avg PMR</th><th class="text-end">EMR</th></tr></thead>
                <tbody>
                    @foreach ($allRows->groupBy('warehouse') as $warehouseName => $warehouseRows)
                        @php $complete = $warehouseRows->filter(fn (array $row): bool => $row['amr'] !== null && $row['pmr'] !== null); @endphp
                        <tr class="border-b border-base-content/10"><td class="py-3 font-medium text-black">{{ $warehouseName }}</td><td class="text-end">{{ $warehouseRows->count() }}</td><td class="text-end font-mono">{{ number_format($warehouseRows->sum(fn (array $row): float => (float) $row['volume_bags']), 3) }}</td><td class="text-end font-mono">{{ $formatPercentage($warehouseRows->pluck('purity')->filter(fn ($value): bool => $value !== null)->avg()) }}</td><td class="text-end font-mono">{{ $formatPercentage($warehouseRows->pluck('amr')->filter(fn ($value): bool => $value !== null)->avg()) }}</td><td class="text-end font-mono">{{ $formatPercentage($warehouseRows->pluck('pmr')->filter(fn ($value): bool => $value !== null)->avg()) }}</td><td class="text-end font-mono">{{ $formatRange($complete->min('amr'), $complete->max('pmr')) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card border border-base-content/10 bg-base-100 text-black shadow-sm">
        <div class="flex flex-col gap-1 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-lg font-semibold text-black">Detailed EMR Results</h2><p class="mt-1 text-sm text-black">{{ $summary['valid'] }} valid, {{ $summary['questionable'] }} questionable, {{ $summary['incomplete'] }} incomplete</p></div>
            <span class="text-sm text-black">{{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="table table-sm min-w-328 text-sm">
                <thead><tr class="border-y border-base-content/15 bg-base-200/60 text-sm font-semibold text-black"><th class="text-center">No.</th><th>Branch</th><th>Warehouse</th><th>Pile</th><th>Variety</th><th class="text-center">Age</th><th class="text-center">Volume <br>(bags)</th><th class="text-center">Purity</th><th class="text-center">Quality</th><th class="text-center">AMR</th><th class="text-center">PMR</th><th class="text-center">EMR</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-base-content/10 hover:bg-base-200/30"><td class="text-center py-3">{{ ($rows->firstItem() ?? 1) + $loop->index }}</td><td>{{ $row['branch'] }}</td><td class=" text-start font-medium">{{ $row['warehouse'] }}</td><td class="text-center font-semibold">{{ $row['pile'] }}</td><td class="text-center">{{ $row['variety'] }}</td><td class="text-center">{{ $row['age'] ?? '—' }}</td><td class="text-center font-mono">{{ number_format((float) $row['volume_bags'], 3) }}</td><td class="text-center font-mono">{{ $row['purity'] !== null ? number_format((float) $row['purity'], 2).'%' : 'N/A' }}</td><td class="text-center">{{ $row['quality'] }}</td><td class="text-center font-mono">{{ $formatPercentage($row['amr']) }}</td><td class="text-center font-mono">{{ $formatPercentage($row['pmr']) }}</td><td class="text-center font-mono">{{ $row['emr_display'] }}</td><td><span class="badge badge-soft text-xs font-semibold !text-black {{ $row['status'] === 'VALID' ? 'badge-primary' : ($row['status'] === 'QUESTIONABLE' ? 'badge-warning' : (str_contains($row['status'], 'Blocked') ? 'badge-error' : 'badge-neutral')) }}">{{ $row['status'] }}</span></td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="border-t border-base-content/10 p-4">{{ $rows->links() }}</div>
        @endif
    </section>
@endif
@endsection
