@extends('layouts.app')

@section('title', 'Rice Milling')

@section('content')
<div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
    <div>
        <h1 class="text-2xl font-bold leading-tight text-black sm:text-3xl">Rice Milling Progress</h1>
        <p class="mt-2 text-sm leading-6 text-black">Milling assignments for piles with an approved GMR, with per-pile accomplishment tracking.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @can('manage-millings')
            <button type="button" class="btn btn-primary btn-sm sm:btn-md gap-2" data-milling-modal-open="assign-milling-modal">
                <span class="icon-[tabler--plus] size-5"></span>
                Assign Milling
            </button>
        @endcan
    </div>
</div>

<form method="GET" action="{{ route('millings.index') }}" class="card mb-5 border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
    <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Branch</span>
            @php
                $isStaffUser = auth()->user()?->hasRole('STAFF') && auth()->user()?->branch_id;
            @endphp
            <select name="branch_id" class="select select-bordered min-h-11 w-full text-base text-black @if($isStaffUser) bg-gray-100 text-gray-500 cursor-not-allowed @endif" onchange="this.form.submit()" @disabled($isStaffUser)>
                @unless($isStaffUser)
                    <option value="">All Branches</option>
                @endunless
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((int) ($filters['branch_id'] ?? 0) === $branch->id)>{{ $branch->name }}</option>
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
                    <option value="{{ $warehouse->id }}" @selected((int) ($filters['warehouse_id'] ?? 0) === $warehouse->id)>
                        {{ $warehouse->name }}{{ empty($filters['branch_id']) && $warehouse->branch ? ' (' . $warehouse->branch->name . ')' : '' }}
                    </option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Status</span>
            <select name="status" class="select select-bordered min-h-11 w-full text-base text-black" onchange="this.form.submit()">
                <option value="">All</option>
                <option value="assigned" @selected(($filters['status'] ?? null) === 'assigned')>Assigned</option>
                <option value="ongoing" @selected(($filters['status'] ?? null) === 'ongoing')>Ongoing</option>
                <option value="completed" @selected(($filters['status'] ?? null) === 'completed')>Completed</option>
                <option value="cancelled" @selected(($filters['status'] ?? null) === 'cancelled')>Cancelled</option>
            </select>
        </label>
        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Date From</span>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="input input-bordered min-h-11 w-full text-base text-black" />
        </label>
        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Date To</span>
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="input input-bordered min-h-11 w-full text-base text-black" />
        </label>
    </div>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-base-content/10 pt-3">
        <div class="text-xs text-base-content/60">
            @if(!empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['warehouse_id']) || !empty($filters['branch_id']) || !empty($filters['status']))
                Filtering by assigned date, branch, warehouse, and status.
            @else
                Filter rice milling progress by branch, warehouse, status, or date range.
            @endif
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="btn btn-primary min-h-11 px-5 text-sm gap-2">
                <span class="icon-[tabler--filter] size-4"></span>
                Filter
            </button>
            <a href="{{ route('millings.index') }}" class="btn btn-outline min-h-11 px-4 text-sm">Reset Filters</a>
        </div>
    </div>
</form>

@if ($millings->isEmpty())
    <div class="card border border-dashed border-base-content/20 bg-base-100 p-10 text-center text-black shadow-sm">
        <h2 class="font-semibold">No rice-milling assignments found.</h2>
        <p class="mt-1 text-sm text-black">
            @if(!empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['warehouse_id']) || !empty($filters['branch_id']) || !empty($filters['status']))
                No records match the selected filter criteria. Try resetting or adjusting the filters.
            @else
                @can('manage-millings')Assign a milling to an approved-GMR pile to begin. @elseNo milling assignments have been created yet.@endcan
            @endif
        </p>
    </div>
@else
    <section class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <div class="overflow-x-auto">
            <table class="table table-sm min-w-[64rem] text-sm">
                <thead>
                    <tr class="border-b border-base-content/15 text-sm font-semibold text-black">
                        <th>Project / Memo Ref.</th>
                        <th>Branch</th>
                        <th>Pile</th>
                        <th>Warehouse</th>
                        <th>Miller</th>
                        <th class="text-end">Target (kg)</th>
                        <th>Status</th>
                        <th>Assigned</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($millings as $milling)
                        <tr class="border-b border-base-content/10 hover:bg-base-200/30">
                            <td class="font-medium font-mono text-xs">{{ $milling->reference_number ?? '—' }}</td>
                            <td>{{ $milling->branch?->name ?? '—' }}</td>
                            <td class="font-semibold">{{ $milling->pile?->pile_number ?? ($milling->pile?->number ?? '—') }}</td>
                            <td class="text-sm">{{ $milling->pile?->warehouse?->name ?? '—' }}</td>
                            <td>{{ $milling->miller ?? '—' }}</td>
                            <td class="text-end font-mono">{{ $milling->target_volume_kg !== null ? number_format((float) $milling->target_volume_kg, 3) : 'N/A' }}</td>
                            <td>
                                @if ($milling->status === 'assigned')
                                    <span class="badge badge-soft badge-neutral text-xs">Assigned</span>
                                @elseif ($milling->status === 'ongoing')
                                    <span class="badge badge-soft badge-info text-xs">Ongoing</span>
                                @elseif ($milling->status === 'completed')
                                    <span class="badge badge-soft badge-success text-xs">Completed</span>
                                @else
                                    <span class="badge badge-soft badge-error text-xs">Cancelled</span>
                                @endif
                            </td>
                            <td class="text-sm">{{ $milling->assigned_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('millings.show', $milling) }}" class="btn btn-ghost btn-xs">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $millings->links() }}
        </div>
    </section>
@endif

@can('manage-millings')
    <div id="assign-milling-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="assign-milling-modal-title">
        <div class="w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-xl bg-base-100 p-5 text-black shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-base-content/10 pb-3">
                <div>
                    <h3 id="assign-milling-modal-title" class="text-lg font-bold">Assign Rice Milling</h3>
                    <p class="mt-1 text-sm">Select the branch and warehouse, then an approved-GMR pile, the miller, and the project/memo reference. The pile's volume is frozen as the milling target.</p>
                </div>
                <button type="button" class="btn btn-circle btn-text btn-sm" data-milling-modal-close="assign-milling-modal" aria-label="Close">&times;</button>
            </div>

            <form method="POST" action="{{ route('millings.store') }}" id="assign-milling-form">
                @csrf
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="form-control">
                        <span class="label-text mb-2 text-sm font-semibold text-black">Branch *</span>
                        <select name="branch_id" id="assign-branch-select" class="select select-bordered min-h-11 w-full text-base text-black" required onchange="filterAssignWarehouses()">
                            <option value="">Select branch</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="form-control">
                        <span class="label-text mb-2 text-sm font-semibold text-black">Warehouse *</span>
                        <select name="warehouse_id" id="assign-warehouse-select" class="select select-bordered min-h-11 w-full text-base text-black" required onchange="filterAssignPiles()">
                            <option value="">Select branch first</option>
                            @foreach ($assignWarehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" data-branch-id="{{ $warehouse->branch_id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="form-control">
                        <span class="label-text mb-2 text-sm font-semibold text-black">Pile (approved GMR only) *</span>
                        <select name="pile_id" id="assign-pile-select" class="select select-bordered min-h-11 w-full text-base text-black" required onchange="updateAssignPileInfo()">
                            <option value="">Select branch first</option>
                            @foreach ($availablePiles as $pile)
                                @php
                                    $pileBranchId = $pile->branch_id ?? $pile->warehouse?->branch_id;
                                    $volumeBags = $pile->volume_kg !== null ? round((float) $pile->volume_kg / 50, 3) : null;
                                    $approvedGmr = $pile->finalGmr();
                                    $pileLabel = ($pile->pile_number ?? ($pile->number ?? '#'.$pile->id))
                                        . ($volumeBags !== null ? ' · ' . number_format($volumeBags, 3) . ' bags' : '');
                                @endphp
                                <option value="{{ $pile->id }}" data-branch-id="{{ $pileBranchId }}" data-warehouse-id="{{ $pile->warehouse_id }}" data-warehouse="{{ $pile->warehouse?->name ?? '—' }}" data-volume="{{ $volumeBags !== null ? number_format($volumeBags, 3) . ' bags' : 'N/A' }}" data-approved-gmr="{{ $approvedGmr !== null ? number_format($approvedGmr, 2) . '%' : '—' }}" data-variety="{{ $pile->variety ?? '' }}" data-quality="{{ $pile->quality ?? '' }}">
                                    {{ $pileLabel }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="form-control">
                        <span class="label-text mb-2 text-sm font-semibold text-black">Miller / Rice Mill</span>
                        <x-miller-combobox name="miller" class="input input-bordered min-h-11 w-full text-black" placeholder="e.g. North Cotabato Rice Mill" />
                    </label>

                    <label class="form-control md:col-span-2">
                        <span class="label-text mb-2 text-sm font-semibold text-black">Project / Memo Reference No. *</span>
                        <input type="text" name="reference_number" class="input input-bordered min-h-11 w-full text-black" placeholder="Bidding project ref. no. or NFA memo no." required />
                        <span class="mt-1 text-xs text-base-content/60">For contracted millers: the bidding project reference. For NFA-owned rice mills: the memorandum no.</span>
                    </label>
                </div>

                <div id="assign-pile-info" class="mt-4 hidden rounded-lg border border-base-content/10 bg-base-200/50 p-3 text-sm">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                        <div><span class="text-xs font-semibold uppercase text-base-content/60">Warehouse</span><span id="info-warehouse" class="block font-medium">—</span></div>
                        <div><span class="text-xs font-semibold uppercase text-base-content/60">Volume (bags)</span><span id="info-volume" class="block font-medium font-mono">—</span></div>
                        <div><span class="text-xs font-semibold uppercase text-base-content/60">Variety</span><span id="info-variety" class="block font-medium">—</span></div>
                        <div><span class="text-xs font-semibold uppercase text-base-content/60">Quality</span><span id="info-quality" class="block font-medium">—</span></div>
                        <div><span class="text-xs font-semibold uppercase text-base-content/60">Approved GMR</span><span id="info-approved-gmr" class="block font-medium font-mono">—</span></div>
                    </div>
                </div>

                <label class="form-control mt-4">
                    <span class="label-text mb-2 text-sm font-semibold text-black">Remarks</span>
                    <textarea name="remarks" class="textarea textarea-bordered min-h-20 text-black" placeholder="Optional notes about this milling assignment"></textarea>
                </label>

                @if ($availablePiles->isEmpty())
                    <x-alert-box type="warning" message="No piles with an approved GMR are currently available for milling." class="mt-4" />
                @endif

                <div class="mt-5 flex items-center justify-end gap-2">
                    <button type="button" class="btn btn-ghost btn-sm" data-milling-modal-close="assign-milling-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm gap-2" @disabled($availablePiles->isEmpty())>
                        <span class="icon-[tabler--check] size-4"></span>
                        Assign Milling
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('assign-milling-modal');
            if (!modal) return;

            function openModal() {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                filterAssignWarehouses();
            }
            function closeModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            document.querySelectorAll('[data-milling-modal-open]').forEach(btn => {
                btn.addEventListener('click', () => openModal());
            });
            document.querySelectorAll('[data-milling-modal-close]').forEach(btn => {
                btn.addEventListener('click', closeModal);
            });
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });

            window.filterAssignWarehouses = function () {
                const branchId = document.getElementById('assign-branch-select').value;
                const warehouseSelect = document.getElementById('assign-warehouse-select');
                const options = warehouseSelect.querySelectorAll('option[data-branch-id]');
                let anyVisible = false;
                options.forEach(opt => {
                    const match = !branchId || String(opt.dataset.branchId) === String(branchId);
                    opt.hidden = !match;
                    if (match && opt.value) anyVisible = true;
                });
                const placeholder = warehouseSelect.querySelector('option:not([data-branch-id])');
                if (placeholder) {
                    placeholder.textContent = branchId ? (anyVisible ? 'Select a warehouse' : 'No warehouses in this branch') : 'Select branch first';
                }
                warehouseSelect.value = '';
                filterAssignPiles();
            };

            window.filterAssignPiles = function () {
                const branchId = document.getElementById('assign-branch-select').value;
                const warehouseId = document.getElementById('assign-warehouse-select').value;
                const pileSelect = document.getElementById('assign-pile-select');
                const options = pileSelect.querySelectorAll('option[data-branch-id]');
                let anyVisible = false;
                options.forEach(opt => {
                    const match = (!branchId || String(opt.dataset.branchId) === String(branchId))
                        && (!warehouseId || String(opt.dataset.warehouseId) === String(warehouseId));
                    opt.hidden = !match;
                    if (match && opt.value) anyVisible = true;
                });
                const placeholder = pileSelect.querySelector('option:not([data-branch-id])');
                if (placeholder) {
                    if (!branchId) {
                        placeholder.textContent = 'Select branch first';
                    } else if (!warehouseId) {
                        placeholder.textContent = 'Select warehouse first';
                    } else {
                        placeholder.textContent = anyVisible ? 'Select a pile' : 'No approved piles in this warehouse';
                    }
                }
                pileSelect.value = '';
                updateAssignPileInfo();
            };

            window.updateAssignPileInfo = function () {
                const pileSelect = document.getElementById('assign-pile-select');
                const infoBox = document.getElementById('assign-pile-info');
                const selected = pileSelect.options[pileSelect.selectedIndex];
                if (!selected || !selected.value || !selected.dataset.warehouse) {
                    infoBox.classList.add('hidden');
                    return;
                }
                document.getElementById('info-warehouse').textContent = selected.dataset.warehouse || '—';
                document.getElementById('info-volume').textContent = selected.dataset.volume || '—';
                document.getElementById('info-variety').textContent = selected.dataset.variety || '—';
                document.getElementById('info-quality').textContent = selected.dataset.quality || '—';
                document.getElementById('info-approved-gmr').textContent = selected.dataset.approvedGmr || '—';
                infoBox.classList.remove('hidden');
            };
        })();
    </script>
@endcan
@endsection
