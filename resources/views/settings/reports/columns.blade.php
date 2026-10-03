@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <!-- Header / Title -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-base-content sm:text-3xl">Report Column Visibility</h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('amr.index') }}" class="btn btn-outline btn-sm sm:btn-md gap-2">
                <span class="icon-[tabler--chart-bar] size-4"></span>
                AMR Report
            </a>
            <a href="{{ route('pmr.index') }}" class="btn btn-outline btn-sm sm:btn-md gap-2">
                <span class="icon-[tabler--chart-dots] size-4"></span>
                PMR Report
            </a>
            <form action="{{ route('settings.reports.columns.reset') }}" method="POST" onsubmit="return confirm('Reset all AMR and PMR columns to their default visibility?');" class="inline">
                @csrf
                <button type="submit" class="btn btn-outline btn-error btn-sm sm:btn-md gap-2">
                    <span class="icon-[tabler--refresh] size-4"></span>
                    Reset to Defaults
                </button>
            </form>
        </div>
    </div>

    @if (session('status'))
        <x-alert-box type="success" :message="session('status')" dismissible class="mb-4" />
    @endif

    @if (session('error'))
        <x-alert-box type="error" :message="session('error')" dismissible class="mb-4" />
    @endif

    <form action="{{ route('settings.reports.columns.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- AMR Column Visibility Card -->
            <div class="card border border-base-content/10 bg-base-100 shadow-sm flex flex-col">
                <div class="card-header border-b border-base-content/10 px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-base-200/30">
                    <div class="flex items-center gap-2.5">
                        <span class="icon-[tabler--chart-bar] size-5 text-primary"></span>
                        <div>
                            <h2 class="text-base font-bold text-base-content">Actual Milling Recovery (AMR)</h2>
                            <p class="text-xs text-base-content/60">19 available columns</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button type="button" class="btn btn-xs btn-outline" onclick="toggleAllColumns('amr', true)">All</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="applyCompactPreset('amr')">Compact</button>
                        <button type="button" class="btn btn-xs btn-outline text-error" onclick="toggleAllColumns('amr', false)">None</button>
                    </div>
                </div>

                <div class="card-body p-6 space-y-3 flex-1 overflow-y-auto max-h-[640px]">
                    @php
                        $amrCategories = collect($amrAvailable)->groupBy('category');
                    @endphp

                    @foreach ($amrCategories as $category => $columns)
                        <div class="space-y-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-base-content/50 border-b border-base-content/10 pb-1 flex items-center justify-between">
                                <span>{{ $category }}</span>
                                <span class="text-[10px] font-normal lowercase text-base-content/40">{{ count($columns) }} columns</span>
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach ($columns as $key => $meta)
                                    @php
                                        $isChecked = in_array($key, $amrVisible, true);
                                    @endphp
                                    <label class="flex items-start gap-3 rounded-lg border border-base-content/10 p-3 hover:bg-base-200/50 cursor-pointer transition-colors has-[:checked]:border-primary/40 has-[:checked]:bg-primary/5">
                                        <input type="checkbox"
                                               name="amr_columns[]"
                                               value="{{ $key }}"
                                               data-amr-col
                                               class="checkbox checkbox-primary checkbox-sm mt-0.5"
                                               @checked($isChecked)>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-xs font-semibold text-base-content leading-tight">{{ $meta['label'] }}</div>
                                            <div class="text-[11px] text-base-content/60 truncate mt-0.5">{{ $meta['description'] }}</div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- PMR Column Visibility Card -->
            <div class="card border border-base-content/10 bg-base-100 shadow-sm flex flex-col">
                <div class="card-header border-b border-base-content/10 px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-base-200/30">
                    <div class="flex items-center gap-2.5">
                        <span class="icon-[tabler--chart-dots] size-5 text-secondary"></span>
                        <div>
                            <h2 class="text-base font-bold text-base-content">Potential Milling Recovery (PMR)</h2>
                            <p class="text-xs text-base-content/60">16 available columns</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button type="button" class="btn btn-xs btn-outline" onclick="toggleAllColumns('pmr', true)">All</button>
                        <button type="button" class="btn btn-xs btn-outline" onclick="applyCompactPreset('pmr')">Compact</button>
                        <button type="button" class="btn btn-xs btn-outline text-error" onclick="toggleAllColumns('pmr', false)">None</button>
                    </div>
                </div>

                <div class="card-body p-6 space-y-3 flex-1 overflow-y-auto max-h-[640px]">
                    @php
                        $pmrCategories = collect($pmrAvailable)->groupBy('category');
                    @endphp

                    @foreach ($pmrCategories as $category => $columns)
                        <div class="space-y-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-base-content/50 border-b border-base-content/10 pb-1 flex items-center justify-between">
                                <span>{{ $category }}</span>
                                <span class="text-[10px] font-normal lowercase text-base-content/40">{{ count($columns) }} columns</span>
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach ($columns as $key => $meta)
                                    @php
                                        $isChecked = in_array($key, $pmrVisible, true);
                                    @endphp
                                    <label class="flex items-start gap-3 rounded-lg border border-base-content/10 p-3 hover:bg-base-200/50 cursor-pointer transition-colors has-[:checked]:border-secondary/40 has-[:checked]:bg-secondary/5">
                                        <input type="checkbox"
                                               name="pmr_columns[]"
                                               value="{{ $key }}"
                                               data-pmr-col
                                               class="checkbox checkbox-secondary checkbox-sm mt-0.5"
                                               @checked($isChecked)>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-xs font-semibold text-base-content leading-tight">{{ $meta['label'] }}</div>
                                            <div class="text-[11px] text-base-content/60 truncate mt-0.5">{{ $meta['description'] }}</div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Sticky Bottom Action Footer -->
        <div class="sticky bottom-4 z-20 rounded-xl border border-base-content/15 bg-base-100/95 p-4 shadow-xl backdrop-blur-md flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-xs text-base-content/70">
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button type="submit" class="btn btn-primary w-full sm:w-auto gap-2">
                    <span class="icon-[tabler--check] size-4"></span>
                    Save Column Settings
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    function toggleAllColumns(type, isChecked) {
        document.querySelectorAll(`input[data-${type}-col]`).forEach(cb => {
            cb.checked = isChecked;
        });
    }

    function applyCompactPreset(type) {
        // Essential columns for compact view
        const essential = ['no', 'branch', 'warehouse', 'pile_number', 'variety', 'trial', 'recovery_rate', 'mean', 'amr_rate', 'pmr_rate', 'status', 'actions'];
        document.querySelectorAll(`input[data-${type}-col]`).forEach(cb => {
            cb.checked = essential.includes(cb.value);
        });
    }
</script>
@endsection
