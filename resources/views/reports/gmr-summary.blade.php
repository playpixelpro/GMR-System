@extends('layouts.app')

@section('title', 'GMR Summary Dashboard')

@section('content')
@php
    $formatPercentage = static fn (?float $value): string => $value === null ? 'N/A' : number_format($value, 2).'%';
    $formatRange = static fn (?float $lower, ?float $upper): string => $lower === null || $upper === null ? 'N/A' : number_format($lower, 2).'%' . '–' . number_format($upper, 2).'%';
@endphp

<div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
    <div>
        <h1 class="text-2xl font-bold leading-tight text-black sm:text-3xl">GMR Summary Dashboard</h1>
        <p class="mt-2 text-sm leading-6 text-black">Final validated AMR and PMR summarized by branch, warehouse, and pile.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if (auth()->user()?->hasRole('RMEC', 'ADMINISTRATOR'))
            <a href="{{ route('gmr.config.edit') }}" class="btn btn-outline btn-sm sm:btn-md gap-2">
                <span class="icon-[tabler--adjustments] size-5"></span>
                Report Configuration
            </a>
        @endif
    </div>
</div>

<form method="GET" action="{{ route('gmr.summary') }}" class="card mb-5 border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
    <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-3">
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
            <a href="{{ route('gmr.summary') }}" class="btn btn-outline min-h-11 px-4 text-sm">Reset Filters</a>
        </div>
    </div>
</form>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-7">
    @foreach (array_merge(
        $filters['warehouse_id'] ? [] : [
            ['label' => 'Total Warehouses', 'value' => number_format($total_warehouses), 'class' => 'text-lg xl:text-xl'],
        ],
        [
            ['label' => 'Total Piles', 'value' => number_format($summary['piles']), 'class' => 'text-lg xl:text-xl'],
            ['label' => 'Volume (bags)', 'value' => number_format($summary['volume_bags'], 3), 'class' => 'text-lg xl:text-xl'],
            ['label' => 'Average PMR', 'value' => $formatPercentage($summary['pmr']), 'class' => 'text-lg xl:text-xl'],
            ['label' => 'Average AMR', 'value' => $formatPercentage($summary['amr']), 'class' => 'text-lg xl:text-xl'],
            ['label' => 'Overall EMR Range', 'value' => $formatRange($summary['emr_lower'] ?? $summary['emr_min'] ?? null, $summary['emr_upper'] ?? $summary['emr_max'] ?? null), 'class' => 'text-lg xl:text-xl'],
            ['label' => 'GMR', 'value' => $formatPercentage($summary['gmr']), 'class' => 'text-black text-lg xl:text-xl'],
        ],
    ) as $card)
        <div class="card border border-base-content/10 bg-base-100 p-3 sm:p-4 text-black shadow-sm min-w-0 overflow-hidden">
            <p class="text-xs sm:text-sm font-semibold leading-5 text-black">{{ $card['label'] }}</p>
            <p class="mt-2 font-mono text-lg sm:text-xl xl:text-2xl font-bold {{ $card['class'] }}">{{ $card['value'] }}</p>
        </div>
    @endforeach
</div>

@if ($rows->isEmpty())
    <div class="card border border-dashed border-base-content/20 bg-base-100 p-10 text-center text-black shadow-sm">
        <h2 class="font-semibold">No GMR records found.</h2>
        <p class="mt-1 text-sm text-black">Try changing the selected branch or warehouse.</p>
    </div>
@else

    <section class="card mb-5 border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-black">Warehouse Summary</h2>
        <div class="overflow-x-auto">
            <table class="table table-sm min-w-[70rem] text-sm">
                <thead>
                    <tr class="border-b border-base-content/15 text-sm font-semibold text-black">
                        <th>Warehouse</th>
                        <th class="text-end">Piles</th>
                        <th class="text-end">Total Volume (bags)</th>
                        <th class="text-end">Average PMR</th>
                        <th class="text-end">Average AMR</th>
                        <th class="text-end">EMR Range</th>
                        <th class="text-end">GMR</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows->groupBy('warehouse') as $warehouseName => $warehouseRows)
                        @php
                            $warehouseComplete = $warehouseRows->filter(fn (array $row): bool => $row['amr'] !== null && $row['pmr'] !== null);
                        @endphp
                        <tr class="border-b border-base-content/10">
                            <td class="py-3 font-medium">{{ $warehouseName }}</td>
                            <td class="text-end">{{ $warehouseRows->count() }}</td>
                            <td class="text-end font-mono">{{ number_format($warehouseRows->sum(fn (array $row): float => (float) ($row['volume_bags'] ?? 0)), 3) }}</td>
                            <td class="text-end font-mono">{{ $formatPercentage($warehouseComplete->avg('pmr')) }}</td>
                            <td class="text-end font-mono">{{ $formatPercentage($warehouseComplete->avg('amr')) }}</td>
                            <td class="text-end font-mono">{{ $formatRange($warehouseComplete->min('amr'), $warehouseComplete->max('pmr')) }}</td>
                            <td class="text-end font-mono font-bold text-secondary">{{ $formatPercentage($warehouseComplete->avg('gmr')) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <!-- Detailed GMR Results with Branch-Scoped Report Printing -->
    <form id="gmr-print-form" method="POST" action="{{ route('gmr.report.print') }}" target="_blank">
        @csrf
        <input type="hidden" name="report_branch_id" id="report-branch-id" value="" />
        <section class="card border border-base-content/10 bg-base-100 text-black shadow-sm">
            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between border-b border-base-content/10">
                <div>
                    <h2 class="text-lg font-semibold text-black">Detailed GMR Results</h2>
                    <p class="mt-1 text-sm text-black">GMR is the midpoint of the final AMR and PMR values. Select a branch first, then check records to include in the printable report.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-black whitespace-nowrap">Print Branch:</span>
                        <select id="report-branch-select" class="select select-bordered select-sm min-w-[180px] text-sm text-black @if($isStaffUser) bg-gray-100 text-gray-500 cursor-not-allowed @endif" @disabled($isStaffUser)>
                            @unless($isStaffUser)
                                <option value="">Select branch</option>
                            @endunless
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected($isStaffUser && auth()->user()->branch_id === $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <span class="badge badge-neutral text-xs font-semibold" id="selection-counter">0 selected</span>
                    @if (auth()->user()?->hasRole('RMEC', 'ADMINISTRATOR'))
                        <button type="submit" id="btn-print-report" class="btn btn-primary btn-sm gap-2" disabled>
                            <span class="icon-[tabler--printer] size-4"></span>
                            Print Report
                        </button>
                        <button type="button" id="btn-submit-approval" class="btn btn-outline btn-secondary btn-sm gap-2" disabled>
                            <span class="icon-[tabler--send] size-4"></span>
                            Submit to Central Office
                        </button>
                    @endif
                    <span class="text-xs text-base-content/60">{{ $rows->count() }} total piles</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table table-sm min-w-[88rem] text-sm">
                    <thead>
                        <tr class="border-y border-base-content/15 bg-base-200/60 text-sm font-semibold text-black">
                            <th>Branch</th>
                            <th>Warehouse</th>
                            <th>Pile No.</th>
                            <th class="text-end">Volume (bags)</th>
                            <th class="text-end">PMR</th>
                            <th class="text-end">AMR</th>
                            <th>EMR</th>
                            <th class="text-end">GMR</th>
                            <th>Status</th>
                            <th>Approved GMR</th>
                            <th class="text-center w-28">
                                <label class="flex items-center justify-center gap-1 cursor-pointer" title="Select All for Report">
                                    <input type="checkbox" id="select-all-checkbox" class="checkbox checkbox-primary checkbox-xs" disabled />
                                    <span class="text-xs font-bold text-black">Select</span>
                                </label>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b border-base-content/10 hover:bg-base-200/30" data-row-branch-id="{{ $row['branch_id'] }}">
                                <td>{{ $row['branch'] }}</td>
                                <td class="font-medium">{{ $row['warehouse'] }}</td>
                                <td class="font-semibold">{{ $row['pile'] }}</td>
                                <td class="text-end font-mono">{{ $row['volume_bags'] !== null ? number_format($row['volume_bags'], 3) : 'N/A' }}</td>
                                <td class="text-end font-mono">{{ $formatPercentage($row['pmr']) }}</td>
                                <td class="text-end font-mono">{{ $formatPercentage($row['amr']) }}</td>
                                <td class="font-mono">{{ $formatRange($row['amr'], $row['pmr']) }}</td>
                                <td class="text-end font-mono text-lg font-bold text-secondary">
                                    @if ($row['gmr'] !== null)
                                        <button type="button" class="cursor-pointer underline decoration-secondary/40 underline-offset-2 hover:decoration-secondary" data-gmr-open="gmr-breakdown-{{ $row['id'] }}" aria-haspopup="dialog" title="Show GMR computation">
                                            {{ $formatPercentage($row['gmr']) }}
                                        </button>
                                    @else
                                        <span class="text-base-content/50 font-mono text-sm">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($row['status'] === 'Re-establish')
                                        <span class="badge badge-soft badge-secondary text-xs font-medium" title="{{ implode('; ', $row['review_reasons']) }}">Re-establish</span>
                                    @elseif ($row['status'] === 'Review')
                                        <span class="badge badge-soft badge-warning text-xs font-medium" title="{{ implode('; ', $row['review_reasons']) }}">Review</span>
                                    @elseif ($row['status'] === 'Validated')
                                        <span class="badge badge-soft badge-primary text-xs font-medium">Validated</span>
                                    @elseif ($row['status'] === 'Incomplete')
                                        <span class="badge badge-soft badge-neutral text-xs font-medium">Incomplete</span>
                                    @else
                                        <span class="badge badge-soft {{ str_contains($row['status'], 'Blocked') ? 'badge-error' : 'badge-neutral' }} text-xs font-medium" title="{{ implode('; ', $row['review_reasons'] ?? []) }}">{{ $row['status'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if (($row['gmr_status'] ?? null) === 'approved')
                                        @php $approvedGmr = $row['approved_gmr'] ?? null; @endphp
                                        <span class="font-mono font-bold text-success text-sm" title="Central-Office approved Final GMR (pile permanently locked)">
                                            {{ $approvedGmr !== null ? number_format((float) $approvedGmr, 2) . '%' : '—' }}
                                        </span>
                                    @elseif (($row['gmr_status'] ?? null) === 'submitted')
                                        <span class="badge badge-soft badge-info text-xs font-medium" title="Awaiting Central Office approval">Submitted</span>
                                    @else
                                        <span class="badge badge-soft badge-neutral text-xs font-medium text-base-content/50">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($row['gmr'] === null || $row['status'] === 'Incomplete')
                                        <input type="checkbox" class="checkbox checkbox-sm pile-checkbox" disabled title="Uncomputed records cannot be included in report" />
                                    @elseif (($row['gmr_status'] ?? null) === 'submitted' || ($row['gmr_status'] ?? null) === 'approved')
                                        <input type="checkbox" class="checkbox checkbox-sm pile-checkbox" disabled title="This pile is already submitted/approved and cannot be re-submitted" />
                                    @else
                                        <input type="checkbox" name="selected_piles[]" value="{{ $row['id'] }}" data-branch-id="{{ $row['branch_id'] }}" data-status="{{ $row['status'] }}" class="checkbox checkbox-primary checkbox-sm pile-checkbox" aria-label="Include pile {{ $row['pile'] }} in report" disabled />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </form>

    @if (auth()->user()?->hasRole('RMEC', 'ADMINISTRATOR'))
        <form id="gmr-submit-form" method="POST" action="{{ route('gmr-approvals.store') }}" class="hidden">
            @csrf
            <input type="hidden" name="branch_id" id="submit-branch-id" value="" />
            <input type="hidden" name="reference_number" id="submit-reference-number" value="" />
            <input type="hidden" name="remarks" id="submit-remarks" value="" />
        </form>

        <!-- Submit to Central Office popup -->
        <div id="gmr-submit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="gmr-submit-modal-title">
            <div class="w-full max-w-lg rounded-xl bg-base-100 p-5 text-black shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-base-content/10 pb-3">
                    <div>
                        <h3 id="gmr-submit-modal-title" class="text-lg font-bold">Submit GMR to Central Office</h3>
                        <p class="mt-1 text-sm" id="gmr-submit-modal-context">—</p>
                    </div>
                    <button type="button" class="btn btn-circle btn-text btn-sm" data-submit-close="gmr-submit-modal" aria-label="Close">&times;</button>
                </div>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="alert alert-soft alert-info">
                        Enter the <strong>Recommendation Memo No.</strong> of the GMR report memo submitted to the Central Office. This becomes the reference recorded for this submission.
                    </div>
                    <label class="form-control">
                        <span class="label-text mb-2 text-sm font-semibold text-black">Recommendation Memo No. *</span>
                        <input type="text" id="submit-memo-input" class="input input-bordered min-h-11 text-black" placeholder="e.g. RM-2026-01-001" required />
                        <span id="submit-memo-error" class="mt-1 hidden text-xs text-error">Please enter the Recommendation Memo No.</span>
                    </label>
                    <label class="form-control">
                        <span class="label-text mb-2 text-sm font-semibold text-black">Remarks (optional)</span>
                        <textarea id="submit-remarks-input" class="textarea textarea-bordered min-h-20 text-black" placeholder="Optional notes for the Central Office"></textarea>
                    </label>
                </div>
                <div class="mt-5 flex items-center justify-end gap-2">
                    <button type="button" class="btn btn-ghost btn-sm" data-submit-close="gmr-submit-modal">Cancel</button>
                    <button type="button" id="btn-confirm-submit" class="btn btn-secondary btn-sm gap-2">
                        <span class="icon-[tabler--send] size-4"></span>
                        Submit to Central Office
                    </button>
                </div>
            </div>
        </div>
    @endif

    @foreach ($rows as $row)
        <div id="gmr-breakdown-{{ $row['id'] }}" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="gmr-breakdown-{{ $row['id'] }}-title">
            <div class="w-full max-w-lg rounded-xl bg-base-100 p-5 text-black shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-base-content/10 pb-3">
                    <div>
                        <h3 id="gmr-breakdown-{{ $row['id'] }}-title" class="text-lg font-bold">GMR Computation</h3>
                        <p class="mt-1 text-sm">{{ $row['branch'] }} · {{ $row['warehouse'] }} · Pile {{ $row['pile'] }}</p>
                    </div>
                    <button type="button" class="btn btn-circle btn-text btn-sm" data-gmr-close="gmr-breakdown-{{ $row['id'] }}" aria-label="Close GMR computation">&times;</button>
                </div>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-base-200 p-3"><span class="block text-xs font-semibold uppercase">AMR</span><span class="mt-1 block font-mono text-lg font-bold">{{ $formatPercentage($row['amr']) }}</span></div>
                        <div class="rounded-lg bg-base-200 p-3"><span class="block text-xs font-semibold uppercase">PMR</span><span class="mt-1 block font-mono text-lg font-bold">{{ $formatPercentage($row['pmr']) }}</span></div>
                    </div>
                    <div class="rounded-lg border border-secondary/30 bg-secondary/10 p-4">
                        <p class="font-semibold">GMR = (AMR + PMR) / 2</p>
                        <p class="mt-2 font-mono text-base">({{ $formatPercentage($row['amr']) }} + {{ $formatPercentage($row['pmr']) }}) / 2</p>
                        <p class="mt-2 text-xl font-bold text-secondary">GMR = {{ $formatPercentage($row['gmr']) }}</p>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-base-content/10 p-3">
                        <span class="font-semibold">EMR reference range</span>
                        <span class="font-mono">{{ $formatRange($row['amr'], $row['pmr']) }}</span>
                    </div>
                    @if ($row['status'] === 'Re-establish')
                        <div class="alert alert-soft alert-secondary text-sm"><strong>Re-establish required:</strong> {{ implode('; ', $row['review_reasons']) }}.</div>
                    @elseif ($row['status'] === 'Review')
                        <div class="alert alert-soft alert-warning text-sm"><strong>Review required:</strong> {{ implode('; ', $row['review_reasons']) }}.</div>
                    @elseif ($row['status'] === 'Incomplete')
                        <div class="alert alert-soft alert-neutral text-sm"><strong>Incomplete:</strong> AMR and PMR are both required to compute GMR.</div>
                    @else
                        <div class="alert alert-soft alert-primary text-sm">AMR and PMR meet the current GMR validation rules.</div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
@endif

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAllCheckbox = document.getElementById('select-all-checkbox');
        const pileCheckboxes = document.querySelectorAll('.pile-checkbox');
        const selectionCounter = document.getElementById('selection-counter');
        const printForm = document.getElementById('gmr-print-form');
        const printButton = document.getElementById('btn-print-report');
        const submitButton = document.getElementById('btn-submit-approval');
        const submitForm = document.getElementById('gmr-submit-form');
        const reportBranchSelect = document.getElementById('report-branch-select');
        const reportBranchInput = document.getElementById('report-branch-id');
        const tableRows = document.querySelectorAll('[data-row-branch-id]');

        function getSelectedBranch() {
            @if($isStaffUser)
                return "{{ auth()->user()->branch_id }}";
            @else
                return reportBranchSelect ? reportBranchSelect.value : '';
            @endif
        }

        function updateCheckboxStates() {
            const selectedBranch = getSelectedBranch();
            reportBranchInput.value = selectedBranch;

            pileCheckboxes.forEach(cb => {
                const cbBranch = cb.dataset.branchId;
                const isSelectable = cb.hasAttribute('name');
                if (!isSelectable) {
                    // Incomplete rows stay permanently disabled
                    cb.disabled = true;
                    cb.checked = false;
                } else if (!selectedBranch) {
                    cb.disabled = true;
                    cb.checked = false;
                } else if (String(cbBranch) !== String(selectedBranch)) {
                    cb.disabled = true;
                    cb.checked = false;
                } else {
                    cb.disabled = false;
                }
            });

            // Dim rows not belonging to the selected branch
            tableRows.forEach(row => {
                const rowBranch = row.dataset.rowBranchId;
                if (selectedBranch && String(rowBranch) !== String(selectedBranch)) {
                    row.classList.add('opacity-40');
                } else {
                    row.classList.remove('opacity-40');
                }
            });

            if (selectAllCheckbox) {
                selectAllCheckbox.disabled = !selectedBranch;
                if (!selectedBranch) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                }
            }

            updateCount();
        }

        function updateCount() {
            const checkedCount = document.querySelectorAll('.pile-checkbox:checked').length;
            if (selectionCounter) {
                selectionCounter.textContent = checkedCount + ' selected';
            }
            if (printButton) {
                printButton.disabled = checkedCount === 0;
            }
            if (submitButton) {
                submitButton.disabled = checkedCount === 0;
            }

            const selectedBranch = getSelectedBranch();
            const branchCheckboxes = Array.from(pileCheckboxes).filter(cb => cb.hasAttribute('name') && String(cb.dataset.branchId) === String(selectedBranch));

            if (selectAllCheckbox && branchCheckboxes.length > 0) {
                const branchCheckedCount = branchCheckboxes.filter(cb => cb.checked).length;
                selectAllCheckbox.checked = branchCheckedCount === branchCheckboxes.length;
                selectAllCheckbox.indeterminate = branchCheckedCount > 0 && branchCheckedCount < branchCheckboxes.length;
            }
        }

        if (reportBranchSelect) {
            reportBranchSelect.addEventListener('change', function () {
                // Uncheck all when switching branches
                pileCheckboxes.forEach(cb => cb.checked = false);
                if (selectAllCheckbox) selectAllCheckbox.checked = false;
                updateCheckboxStates();
            });
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function () {
                const selectedBranch = getSelectedBranch();
                pileCheckboxes.forEach(cb => {
                    if (cb.hasAttribute('name') && String(cb.dataset.branchId) === String(selectedBranch)) {
                        cb.checked = selectAllCheckbox.checked;
                    }
                });
                updateCount();
            });
        }

        pileCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateCount);
        });

        if (printForm) {
            printForm.addEventListener('submit', function (e) {
                const selectedBranch = getSelectedBranch();
                if (!selectedBranch) {
                    e.preventDefault();
                    alert('Please select a branch before printing the report.');
                    return;
                }
                const checkedCount = document.querySelectorAll('.pile-checkbox:checked').length;
                if (checkedCount === 0) {
                    e.preventDefault();
                    alert('Please select at least one record to include in the printable GMR report.');
                }
            });
        }

        if (submitButton && submitForm) {
            const submitModal = document.getElementById('gmr-submit-modal');
            const submitMemoInput = document.getElementById('submit-memo-input');
            const submitRemarksInput = document.getElementById('submit-remarks-input');
            const submitMemoError = document.getElementById('submit-memo-error');
            const submitModalContext = document.getElementById('gmr-submit-modal-context');
            const confirmSubmitButton = document.getElementById('btn-confirm-submit');

            let pendingPiles = [];
            let pendingBranch = '';

            function openSubmitModal(branchName, pileCount) {
                if (submitModalContext) {
                    submitModalContext.textContent = branchName + ' · ' + pileCount + ' pile(s) selected';
                }
                if (submitMemoInput) submitMemoInput.value = '';
                if (submitRemarksInput) submitRemarksInput.value = '';
                if (submitMemoError) submitMemoError.classList.add('hidden');
                submitModal?.classList.remove('hidden');
                submitModal?.classList.add('flex');
                submitMemoInput?.focus();
            }

            function closeSubmitModal() {
                submitModal?.classList.add('hidden');
                submitModal?.classList.remove('flex');
            }

            submitButton.addEventListener('click', function () {
                const selectedBranch = getSelectedBranch();
                if (!selectedBranch) {
                    alert('Please select a branch before submitting the GMR report to the Central Office.');
                    return;
                }
                const checkedPiles = Array.from(document.querySelectorAll('.pile-checkbox:checked'))
                    .map(cb => cb.value);
                if (checkedPiles.length === 0) {
                    alert('Please select at least one record to submit to the Central Office.');
                    return;
                }
                pendingPiles = checkedPiles;
                pendingBranch = selectedBranch;
                const branchOption = reportBranchSelect ? reportBranchSelect.querySelector('option[value="' + selectedBranch + '"]') : null;
                const branchName = branchOption ? branchOption.textContent : 'Selected branch';
                openSubmitModal(branchName, checkedPiles.length);
            });

            if (confirmSubmitButton) {
                confirmSubmitButton.addEventListener('click', function () {
                    const memo = submitMemoInput ? submitMemoInput.value.trim() : '';
                    if (!memo) {
                        if (submitMemoError) submitMemoError.classList.remove('hidden');
                        submitMemoInput?.focus();
                        return;
                    }
                    document.getElementById('submit-branch-id').value = pendingBranch;
                    document.getElementById('submit-reference-number').value = memo;
                    document.getElementById('submit-remarks').value = submitRemarksInput ? submitRemarksInput.value.trim() : '';
                    // Remove any previously injected pile inputs
                    submitForm.querySelectorAll('input[name="selected_piles[]"]').forEach(el => el.remove());
                    pendingPiles.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'selected_piles[]';
                        input.value = id;
                        submitForm.appendChild(input);
                    });
                    submitForm.submit();
                });
            }

            submitModal?.querySelectorAll('[data-submit-close]').forEach(btn => {
                btn.addEventListener('click', closeSubmitModal);
            });
            submitModal?.addEventListener('click', function (e) {
                if (e.target === submitModal) closeSubmitModal();
            });
        }

        // Initialize state on load
        updateCheckboxStates();
    });

    document.addEventListener('click', function (event) {
        const openButton = event.target.closest('[data-gmr-open]');
        const closeButton = event.target.closest('[data-gmr-close]');

        if (openButton) {
            const modal = document.getElementById(openButton.dataset.gmrOpen);
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                modal.querySelector('[data-gmr-close]')?.focus();
            }
        }

        if (closeButton) {
            const modal = document.getElementById(closeButton.dataset.gmrClose);
            modal?.classList.add('hidden');
            modal?.classList.remove('flex');
        }

        if (event.target.matches('[role="dialog"]')) {
            event.target.classList.add('hidden');
            event.target.classList.remove('flex');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('[id^="gmr-breakdown-"]:not(.hidden)').forEach(function (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            });
        }
    });
</script>
@endsection
