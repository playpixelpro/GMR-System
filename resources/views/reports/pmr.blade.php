@extends('layouts.app')

@section('title', 'PMR Report')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">PMR Report</h1>
            <p class="mt-1 text-sm text-gray-500">Laboratory test milling results — one row per pile</p>
        </div>
        <a href="{{ route('records.create', ['type' => 'pmr']) }}"
           class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700">
            Add PMR Trial
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-300 bg-white shadow-sm">
        <table class="min-w-max border-collapse text-sm">
            <thead class="bg-gray-100 text-center text-xs font-semibold uppercase tracking-wide text-gray-700">
                <tr>
                    <th rowspan="2" class="border border-gray-300 px-3 py-3">No.</th>
                    <th rowspan="2" class="border border-gray-300 px-3 py-3">Branch</th>
                    <th rowspan="2" class="border border-gray-300 px-3 py-3">Warehouse Name</th>
                    <th rowspan="2" class="border border-gray-300 px-3 py-3">Pile Number</th>
                    <th rowspan="2" class="border border-gray-300 px-3 py-3">Variety</th>
                    <th rowspan="2" class="border border-gray-300 px-3 py-3">Purity</th>
                    <th rowspan="2" class="border border-gray-300 px-3 py-3">MC</th>
                    <th rowspan="2" class="border border-gray-300 px-3 py-3">Quality</th>
                    <th rowspan="2" class="border border-gray-300 px-3 py-3">Aged (months)</th>
                    <th rowspan="2" class="border border-gray-300 px-3 py-3">Volume (kg)</th>
                    @for ($trial = 1; $trial <= 5; $trial++)
                        <th class="border border-gray-300 px-3 py-3">Trial {{ $trial }} Recovery Rate (%)</th>
                    @endfor
                    <th rowspan="2" class="border border-gray-300 px-3 py-3">Mean (%)</th>
                    <th rowspan="2" class="border border-gray-300 px-3 py-3">Standard Deviation (%)</th>
                    <th rowspan="2" class="border border-gray-300 px-3 py-3">Pile Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recordGroups as $group)
                    @php($records = $group['records'])
                    @php($trialRecords = $records->keyBy('trial_number'))
                    <tr class="text-center text-gray-700 odd:bg-white even:bg-gray-50">
                        <td class="border border-gray-300 px-3 py-4 font-medium">{{ $loop->iteration }}</td>
                        <td class="border border-gray-300 px-3 py-4 text-left">{{ $records->first()->pile?->warehouse?->branch?->name ?? '—' }}</td>
                        <td class="border border-gray-300 px-3 py-4 text-left">{{ $records->first()->warehouse_name }}</td>
                        <td class="border border-gray-300 px-3 py-4">{{ $records->first()->pile_number }}</td>
                        <td class="border border-gray-300 px-3 py-4">{{ $records->first()->variety }}</td>
                        <td class="border border-gray-300 px-3 py-4">{{ number_format((float) $records->first()->purity, 2) }}</td>
                        <td class="border border-gray-300 px-3 py-4">{{ number_format((float) $records->first()->mc, 2) }}</td>
                        <td class="border border-gray-300 px-3 py-4">{{ strtoupper($records->first()->quality) }}</td>
                        <td class="border border-gray-300 px-3 py-4">{{ $records->first()->aged_months }}</td>
                        <td class="border border-gray-300 px-3 py-4 text-right">{{ number_format((float) $records->first()->volume_bags, 3) }}</td>
                        @for ($trial = 1; $trial <= 5; $trial++)
                            @php($record = $trialRecords->get($trial))
                            <td class="border border-gray-300 px-3 py-4 text-right">
                                @if ($record)
                                    {{ number_format($record->recovery_rate_percentage, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                        @endfor
                        <td class="border border-gray-300 px-3 py-4 text-right">{{ number_format($group['mean'], 2) }}</td>
                        <td class="border border-gray-300 px-3 py-4 text-right">{{ number_format($group['standard_deviation'], 2) }}</td>
                        <td class="border border-gray-300 px-3 py-4 align-middle">
                            @if ($records->first()->pile)
                                <p class="font-medium">{{ $records->count() }}/5 trials</p>
                                @if ($records->first()->pile->pmr_status)
                                    <p class="mt-1 text-xs font-semibold uppercase text-gray-600">{{ $records->first()->pile->pmr_status }}</p>
                                @else
                                    <div class="mt-2 flex min-w-28 flex-col gap-1">
                                        @foreach (['recommend' => 'Recommend', 'retest' => 'Retest', 'approved' => 'Approved'] as $action => $label)
                                            <form method="POST" action="{{ route('piles.status', $records->first()->pile) }}">
                                                @csrf
                                                <input type="hidden" name="form_type" value="pmr">
                                                <button type="submit" name="action" value="{{ $action }}" class="w-full rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-100">{{ $label }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                @endif
                            @else
                                <span class="text-xs text-gray-500">Legacy record</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="18" class="border border-gray-300 px-3 py-10 text-center text-gray-500">No PMR trial records have been entered yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
