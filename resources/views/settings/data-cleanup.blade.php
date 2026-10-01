@extends('layouts.app')

@section('title', 'Data Cleanup')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Data Cleanup</h1>
        <p class="mt-1 text-sm text-base-content/70">Administrator-only maintenance for removing test data. Deletions work bottom-up: laboratory trials first, then the pile, then its warehouse — a parent is blocked while it still owns children. Piles whose GMR is submitted to or approved by the Central Office are frozen. Every deletion is recorded in the Activity Logs.</p>
    </div>

    @if (session('status'))
        <x-alert-box type="success" :message="session('status')" dismissible class="mb-4" />
    @endif
    @if (isset($errors) && $errors->any())
        <x-alert-box type="error" dismissible class="mb-4">
            @if ($errors->count() === 1)
                {{ $errors->first() }}
            @else
                <ul class="list-disc pl-4 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </x-alert-box>
    @endif

    @php
        $tabLinks = array_filter($filters, fn ($value) => $value !== null && $value !== '');
    @endphp
    <div class="flex flex-wrap gap-2 border-b border-base-content/10 pb-2">
        @foreach ([
            'warehouses' => 'Warehouses',
            'piles' => 'Piles',
            'test-milling' => 'Test Milling Data (AMR / PMR)',
        ] as $key => $label)
            <a href="{{ route('settings.data-cleanup', array_merge($tabLinks, ['tab' => $key])) }}"
               class="btn btn-sm {{ $tab === $key ? 'btn-primary' : 'btn-ghost' }}">{{ $label }}</a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('settings.data-cleanup') }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-base-content/10 bg-base-100 p-4">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="space-y-1 grow min-w-52">
            <label class="text-xs font-semibold text-base-content/70">Branch</label>
            <select class="select w-full" name="branch_id">
                <option value="">All branches</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($filters['branch_id'] === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        @if ($tab !== 'warehouses')
            <div class="space-y-1 grow min-w-52">
                <label class="text-xs font-semibold text-base-content/70">Warehouse</label>
                <select class="select w-full" name="warehouse_id">
                    <option value="">All warehouses</option>
                    @foreach ($warehouseOptions as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected($filters['warehouse_id'] === $warehouse->id)>{{ $warehouse->name }}{{ $warehouse->branch ? ' — '.$warehouse->branch->name : '' }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($tab !== 'warehouses')
            <div class="space-y-1 grow min-w-52">
                <label class="text-xs font-semibold text-base-content/70">Search</label>
                <input class="input w-full" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search by pile number">
            </div>
        @endif
        <div class="flex gap-2">
            <button class="btn btn-outline" type="submit">
                <span class="icon-[tabler--search] size-4"></span>
                <span>Filter</span>
            </button>
            <a href="{{ route('settings.data-cleanup', ['tab' => $tab]) }}" class="btn btn-ghost">Clear</a>
        </div>
    </form>

    @if ($tab === 'warehouses')
        <div class="overflow-x-auto rounded-lg border border-base-content/10 bg-base-100">
            @if ($warehouseRows->isEmpty())
                <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                    <span class="icon-[tabler--building-warehouse] size-10 text-base-content/40"></span>
                    <p class="text-base-content/70">No warehouses match the current filters.</p>
                </div>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>Warehouse</th>
                            <th>Piles</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($warehouseRows as $warehouse)
                            <tr>
                                <td>{{ $warehouse->branch?->name ?? '—' }}</td>
                                <td class="font-medium">{{ $warehouse->name }}</td>
                                <td>
                                    @if ($warehouse->piles_count > 0)
                                        <span class="badge badge-soft badge-info text-xs">{{ $warehouse->piles_count }}</span>
                                    @else
                                        <span class="text-base-content/50">0</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($warehouse->piles_count > 0)
                                        <button class="btn btn-xs btn-outline btn-error inline-flex items-center gap-1" type="submit" disabled title="Delete all {{ $warehouse->piles_count }} pile(s) in this warehouse first.">
                                            <span class="icon-[tabler--trash] size-3.5"></span>
                                            <span>Delete</span>
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('settings.data-cleanup.warehouses.destroy', $warehouse) }}"
                                              onsubmit="return confirm('Delete warehouse \x27{{ addslashes($warehouse->name) }}\x27 permanently? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-xs btn-outline btn-error inline-flex items-center gap-1" type="submit" title="Delete this warehouse">
                                                <span class="icon-[tabler--trash] size-3.5"></span>
                                                <span>Delete</span>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 pb-4">{{ $warehouseRows->links() }}</div>
            @endif
        </div>
    @endif

    @if ($tab === 'piles')
        <div class="overflow-x-auto rounded-lg border border-base-content/10 bg-base-100">
            @if ($piles->isEmpty())
                <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                    <span class="icon-[tabler--stack-2] size-10 text-base-content/40"></span>
                    <p class="text-base-content/70">No piles match the current filters.</p>
                </div>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>Pile No.</th>
                            <th>Warehouse</th>
                            <th>AMR Trials</th>
                            <th>PMR Trials</th>
                            <th>Millings</th>
                            <th>GMR Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($piles as $pile)
                            @php
                                $blockers = [];
                                if ($pile->gmr_status === 'submitted') {
                                    $blockers[] = 'GMR submitted to the Central Office';
                                } elseif ($pile->gmr_status === 'approved') {
                                    $blockers[] = 'GMR approved by the Central Office';
                                }
                                if ($pile->amr_records_count > 0) {
                                    $blockers[] = 'Delete all AMR trials first';
                                }
                                if ($pile->pmr_records_count > 0) {
                                    $blockers[] = 'Delete all PMR trials first';
                                }
                                if ($pile->millings_count > 0) {
                                    $blockers[] = 'Has milling assignments';
                                }
                                $blockerText = implode(' · ', $blockers);
                            @endphp
                            <tr>
                                <td class="font-medium">{{ $pile->pile_number ?? $pile->number }}</td>
                                <td>{{ $pile->warehouse?->name ?? '—' }}</td>
                                <td>
                                    @if ($pile->amr_records_count > 0)
                                        <span class="badge badge-soft badge-warning text-xs">{{ $pile->amr_records_count }}</span>
                                    @else
                                        <span class="text-base-content/50">0</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($pile->pmr_records_count > 0)
                                        <span class="badge badge-soft badge-warning text-xs">{{ $pile->pmr_records_count }}</span>
                                    @else
                                        <span class="text-base-content/50">0</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($pile->millings_count > 0)
                                        <span class="badge badge-soft badge-info text-xs">{{ $pile->millings_count }}</span>
                                    @else
                                        <span class="text-base-content/50">0</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($pile->gmr_status === 'submitted')
                                        <span class="badge badge-soft badge-info text-xs">Submitted</span>
                                    @elseif ($pile->gmr_status === 'approved')
                                        <span class="badge badge-soft badge-success text-xs">Approved</span>
                                    @else
                                        <span class="text-base-content/50">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($blockers !== [])
                                        <button class="btn btn-xs btn-outline btn-error inline-flex items-center gap-1" type="submit" disabled title="{{ $blockerText }}">
                                            <span class="icon-[tabler--trash] size-3.5"></span>
                                            <span>Delete</span>
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('settings.data-cleanup.piles.destroy', $pile) }}"
                                              onsubmit="return confirm('Delete pile {{ addslashes((string) ($pile->pile_number ?? $pile->number)) }} and all of its data permanently? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-xs btn-outline btn-error inline-flex items-center gap-1" type="submit" title="Delete this pile">
                                                <span class="icon-[tabler--trash] size-3.5"></span>
                                                <span>Delete</span>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 pb-4">{{ $piles->links() }}</div>
            @endif
        </div>
    @endif

    @if ($tab === 'test-milling')
        @if ($piles->isEmpty())
            <div class="flex flex-col items-center justify-center gap-2 rounded-lg border border-base-content/10 bg-base-100 px-6 py-16 text-center">
                <span class="icon-[tabler--flask] size-10 text-base-content/40"></span>
                <p class="text-base-content/70">No piles with laboratory test data match the current filters.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($piles as $pile)
                    @php
                        $frozen = in_array($pile->gmr_status, ['submitted', 'approved'], true);
                        $frozenReason = $pile->gmr_status === 'submitted'
                            ? 'GMR submitted to the Central Office — data is frozen.'
                            : 'GMR approved by the Central Office — data is frozen.';
                    @endphp
                    <details class="rounded-lg border border-base-content/10 bg-base-100">
                        <summary class="cursor-pointer select-none p-3 flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium">
                                Pile {{ $pile->pile_number ?? $pile->number }}
                                <span class="text-sm font-normal text-base-content/60">— {{ $pile->warehouse?->name ?? '—' }}</span>
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="badge badge-soft badge-warning text-xs">AMR {{ $pile->amr_records_count }}</span>
                                <span class="badge badge-soft badge-warning text-xs">PMR {{ $pile->pmr_records_count }}</span>
                                @if ($frozen)
                                    <span class="badge badge-soft badge-info text-xs" title="{{ $frozenReason }}">Frozen</span>
                                @endif
                            </span>
                        </summary>
                        <div class="space-y-5 border-t border-base-content/10 p-3">
                            @foreach (['amr', 'pmr'] as $formType)
                                @php($trials = $formType === 'amr' ? $pile->amrRecords : $pile->pmrRecords)
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h3 class="text-sm font-semibold uppercase tracking-wide text-base-content/70">
                                            {{ strtoupper($formType) }} trials
                                            <span class="badge badge-soft badge-warning text-xs ml-1">{{ $trials->count() }}</span>
                                        </h3>
                                        <div class="flex items-center gap-2">
                                            @if ($frozen)
                                                <button class="btn btn-xs btn-outline btn-error inline-flex items-center gap-1" type="submit" disabled title="{{ $frozenReason }}">
                                                    <span class="icon-[tabler--trash] size-3.5"></span>
                                                    <span>Delete all {{ strtoupper($formType) }}</span>
                                                </button>
                                            @elseif ($trials->isEmpty())
                                                <button class="btn btn-xs btn-outline btn-error inline-flex items-center gap-1" type="submit" disabled title="No {{ strtoupper($formType) }} trials to delete.">
                                                    <span class="icon-[tabler--trash] size-3.5"></span>
                                                    <span>Delete all {{ strtoupper($formType) }}</span>
                                                </button>
                                            @else
                                                <form method="POST" action="{{ route('settings.data-cleanup.'.strtolower($formType).'.destroy', $pile) }}"
                                                      onsubmit="return confirm('Delete all {{ strtoupper($formType) }} laboratory trials for pile {{ addslashes((string) ($pile->pile_number ?? $pile->number)) }}? The pile will return to a &quot;{{ strtoupper($formType) }} not entered&quot; state. This cannot be undone.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-xs btn-outline btn-error inline-flex items-center gap-1" type="submit">
                                                        <span class="icon-[tabler--trash] size-3.5"></span>
                                                        <span>Delete all {{ strtoupper($formType) }}</span>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($trials->isEmpty())
                                        <p class="text-sm text-base-content/50">No {{ strtoupper($formType) }} trials recorded for this pile.</p>
                                    @else
                                        <div class="overflow-x-auto rounded-lg border border-base-content/10">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Trial</th>
                                                        <th>Date</th>
                                                        <th>Palay Input (kg)</th>
                                                        <th>Rice Recovery (kg)</th>
                                                        <th>Milling Recovery (%)</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($trials as $trial)
                                                        <tr>
                                                            <td class="font-mono text-sm">#{{ $trial->trial_number }}</td>
                                                            <td class="text-sm">{{ $trial->test_milling_date?->format('M d, Y') ?? '—' }}</td>
                                                            <td class="font-mono text-sm">{{ $trial->palay_input_kg !== null ? number_format((float) $trial->palay_input_kg, 2) : '—' }}</td>
                                                            <td class="font-mono text-sm">{{ $trial->rice_recovery_kg !== null ? number_format((float) $trial->rice_recovery_kg, 2) : '—' }}</td>
                                                            <td class="font-mono text-sm">{{ $trial->milling_recovery !== null ? number_format((float) $trial->milling_recovery, 2) : '—' }}</td>
                                                            <td>
                                                                @if ($frozen)
                                                                    <button class="btn btn-xs btn-outline btn-error inline-flex items-center gap-1" type="submit" disabled title="{{ $frozenReason }}">
                                                                        <span class="icon-[tabler--trash] size-3.5"></span>
                                                                        <span>Delete</span>
                                                                    </button>
                                                                @else
                                                                    <form method="POST" action="{{ route('settings.data-cleanup.trials.destroy', [$formType, $trial->id]) }}"
                                                                          onsubmit="return confirm('Delete {{ strtoupper($formType) }} trial #{{ $trial->trial_number }} for pile {{ addslashes((string) ($pile->pile_number ?? $pile->number)) }}? Remaining trials are renumbered. This cannot be undone.')">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button class="btn btn-xs btn-outline btn-error inline-flex items-center gap-1" type="submit" title="Delete this laboratory trial">
                                                                            <span class="icon-[tabler--trash] size-3.5"></span>
                                                                            <span>Delete</span>
                                                                        </button>
                                                                    </form>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endforeach
                <div>{{ $piles->links() }}</div>
            </div>
        @endif
    @endif
</div>
@endsection
