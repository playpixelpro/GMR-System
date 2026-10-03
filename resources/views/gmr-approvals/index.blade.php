@extends('layouts.app')

@section('title', 'GMR Central Office Approvals')

@section('content')
@php
    $formatPercentage = static fn (?float $value): string => $value === null ? 'N/A' : number_format($value, 2).'%';
@endphp

<div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
    <div>
        <h1 class="text-2xl font-bold leading-tight text-black sm:text-3xl">GMR Central Office Approvals</h1>
        <p class="mt-2 text-sm leading-6 text-black">Submissions sent to the Central Office for approval. Approving a submission permanently locks the included piles and records the official Central Office Approved GMR.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('gmr.summary') }}" class="btn btn-outline btn-sm sm:btn-md gap-2">
            <span class="icon-[tabler--arrow-left] size-5"></span>
            Back to GMR Summary
        </a>
    </div>
</div>

<form method="GET" action="{{ route('gmr-approvals.index') }}" class="card mb-5 border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
    <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-3">
        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Branch</span>
            <select name="branch_id" class="select select-bordered min-h-11 w-full text-base text-black" onchange="this.form.submit()">
                <option value="">All Branches</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((int) ($filters['branch_id'] ?? 0) === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Status</span>
            <select name="status" class="select select-bordered min-h-11 w-full text-base text-black" onchange="this.form.submit()">
                <option value="">All</option>
                <option value="submitted" @selected(($filters['status'] ?? null) === 'submitted')>Submitted</option>
                <option value="approved" @selected(($filters['status'] ?? null) === 'approved')>Approved</option>
                <option value="rejected" @selected(($filters['status'] ?? null) === 'rejected')>Rejected</option>
            </select>
        </label>
        <div class="flex gap-2">
            <a href="{{ route('gmr-approvals.index') }}" class="btn btn-outline min-h-11 px-4 text-sm">Reset Filters</a>
        </div>
    </div>
</form>

@if ($approvals->isEmpty())
    <div class="card border border-dashed border-base-content/20 bg-base-100 p-10 text-center text-black shadow-sm">
        <h2 class="font-semibold">No GMR submissions found.</h2>
        <p class="mt-1 text-sm text-black">Submit GMR records from the GMR Summary to create a Central Office approval request.</p>
    </div>
@else
    <section class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <div class="overflow-x-auto">
            <table class="table table-sm min-w-[64rem] text-sm">
                <thead>
                    <tr class="border-b border-base-content/15 text-sm font-semibold text-black">
                        <th>Recommendation Memo No.</th>
                        <th>Branch</th>
                        <th class="text-end">Piles</th>
                        <th>CO Memo No.</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Approved</th>
                        <th class="text-end">Approved GMR</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($approvals as $approval)
                        @php
                            $finalGmrs = $approval->piles->map(fn ($p) => $p->finalGmr())->filter();
                            $gmrMin = $finalGmrs->min();
                            $gmrMax = $finalGmrs->max();
                            $approvedGmrDisplay = $approval->isApproved() && $finalGmrs->isNotEmpty()
                                ? ($gmrMin === $gmrMax
                                    ? number_format((float) $gmrMin, 2) . '%'
                                    : number_format((float) $gmrMin, 2) . '%–' . number_format((float) $gmrMax, 2) . '%')
                                : '—';
                            $coSource = $approval->piles->contains(fn ($p) => $p->finalGmrSource() === 'co_approved');
                        @endphp
                        <tr class="border-b border-base-content/10 hover:bg-base-200/30">
                            <td class="font-medium">{{ $approval->reference_number ?? '—' }}</td>
                            <td>{{ $approval->branch?->name ?? '—' }}</td>
                            <td class="text-end font-mono">{{ $approval->piles->count() }}</td>
                            <td class="font-mono text-xs">{{ $approval->co_approval_memo_no ?? '—' }}</td>
                            <td>
                                @if ($approval->isApproved())
                                    <span class="badge badge-soft badge-success text-xs font-medium">Approved</span>
                                @elseif ($approval->isRejected())
                                    <span class="badge badge-soft badge-error text-xs font-medium">Rejected</span>
                                @else
                                    <span class="badge badge-soft badge-info text-xs font-medium">Submitted</span>
                                @endif
                            </td>
                            <td class="text-sm">{{ $approval->submitted_at?->format('M d, Y H:i') ?? '—' }}<br><span class="text-xs text-base-content/60">{{ $approval->submittedBy?->name }}</span></td>
                            <td class="text-sm">{{ $approval->approved_at?->format('M d, Y H:i') ?? '—' }}<br><span class="text-xs text-base-content/60">{{ $approval->approvedBy?->name }}</span></td>
                            <td class="text-end font-mono font-semibold {{ $coSource ? 'text-success' : '' }}" title="{{ $coSource ? 'Sourced from Central Office Approved GMR' : ($approval->isApproved() ? 'Recommended GMR' : '') }}">
                                {{ $approvedGmrDisplay }}
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-ghost btn-xs" data-approval-open="approval-modal-{{ $approval->id }}">View</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $approvals->links() }}
        </div>
    </section>
@endif

@foreach ($approvals as $approval)
    @php
        $canApprove = auth()->user()?->hasRole('RMEC', 'ADMINISTRATOR') && $approval->isSubmitted();
    @endphp
    <div id="approval-modal-{{ $approval->id }}" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="approval-modal-{{ $approval->id }}-title">
        <div class="w-full max-w-5xl max-h-[90vh] overflow-y-auto rounded-xl bg-base-100 p-5 text-black shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-base-content/10 pb-3 sticky top-0 bg-base-100">
                <div>
                    <h3 id="approval-modal-{{ $approval->id }}-title" class="text-lg font-bold">GMR Submission Detail</h3>
                    <p class="mt-1 text-sm">{{ $approval->branch?->name ?? '—' }} @if ($approval->reference_number) · Recommendation Memo: {{ $approval->reference_number }} @endif</p>
                </div>
                <button type="button" class="btn btn-circle btn-text btn-sm" data-approval-close="approval-modal-{{ $approval->id }}" aria-label="Close">&times;</button>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg bg-base-200 p-3">
                    <span class="block text-xs font-semibold uppercase">Status</span>
                    <span class="mt-1 block">
                        @if ($approval->isApproved())
                            <span class="badge badge-soft badge-success">Approved</span>
                        @elseif ($approval->isRejected())
                            <span class="badge badge-soft badge-error">Rejected</span>
                        @else
                            <span class="badge badge-soft badge-info">Submitted</span>
                        @endif
                    </span>
                </div>
                <div class="rounded-lg bg-base-200 p-3">
                    <span class="block text-xs font-semibold uppercase">Submitted</span>
                    <span class="mt-1 block text-sm">{{ $approval->submitted_at?->format('M d, Y H:i') ?? '—' }}</span>
                    <span class="block text-xs text-base-content/60">{{ $approval->submittedBy?->name }}</span>
                </div>
                <div class="rounded-lg bg-base-200 p-3">
                    <span class="block text-xs font-semibold uppercase">Approved</span>
                    <span class="mt-1 block text-sm">{{ $approval->approved_at?->format('M d, Y H:i') ?? '—' }}</span>
                    <span class="block text-xs text-base-content/60">{{ $approval->approvedBy?->name }}</span>
                </div>
                <div class="rounded-lg bg-base-200 p-3">
                    <span class="block text-xs font-semibold uppercase">CO Memo No.</span>
                    <span class="mt-1 block text-sm font-mono">{{ $approval->co_approval_memo_no ?? '—' }}</span>
                </div>
                <div class="rounded-lg bg-base-200 p-3">
                    <span class="block text-xs font-semibold uppercase">Recommendation Memo No.</span>
                    <span class="mt-1 block text-sm font-mono">{{ $approval->reference_number ?? '—' }}</span>
                </div>
            </div>

            @if ($approval->remarks)
                <x-alert-box type="info" class="mt-3">
                    <strong>Remarks:</strong> {{ $approval->remarks }}
                </x-alert-box>
            @endif
            @if ($approval->rejection_reason)
                <x-alert-box type="error" class="mt-3">
                    <strong>Rejection reason:</strong> {{ $approval->rejection_reason }}
                </x-alert-box>
            @endif

            @if ($canApprove)
                <form method="POST" action="{{ route('gmr-approvals.approve', $approval) }}" id="approve-form-{{ $approval->id }}">
                    @csrf
                    <div class="mt-4 rounded-lg border border-secondary/30 bg-secondary/5 p-4">
                        <h4 class="text-sm font-semibold uppercase text-secondary">Central Office Approval</h4>
                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <label class="form-control">
                                <span class="label-text mb-1 text-xs font-semibold text-black">CO Approval Memorandum No. *</span>
                                <input type="text" name="co_approval_memo_no" class="input input-bordered min-h-10 text-black" placeholder="e.g. AO-2026-XX-XXX" required />
                                @error('co_approval_memo_no') <span class="text-xs text-error">{{ $message }}</span> @enderror
                            </label>
                            <label class="form-control">
                                <span class="label-text mb-1 text-xs font-semibold text-black">Remarks (optional)</span>
                                <input type="text" name="remarks" class="input input-bordered min-h-10 text-black" value="{{ old('remarks') }}" />
                            </label>
                        </div>
                    </div>
            @endif

            <div class="mt-4 overflow-x-auto">
                <table class="table table-sm min-w-[60rem] text-sm">
                    <thead>
                        <tr class="border-b border-base-content/15 text-xs font-semibold text-black">
                            <th>Pile</th>
                            <th class="text-end">Recommended GMR</th>
                            <th class="text-end">CO Approved GMR</th>
                            <th class="text-end">Final GMR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($approval->piles as $pileRow)
                            @php
                                $recommended = $pileRow->gmr !== null ? (float) $pileRow->gmr : null;
                                $coApproved = $pileRow->co_approved_gmr !== null ? (float) $pileRow->co_approved_gmr : null;
                                $final = $pileRow->finalGmr();
                                $source = $pileRow->finalGmrSource();
                            @endphp
                            <tr class="border-b border-base-content/10">
                                <td class="font-semibold">
                                    {{ $pileRow->pile?->pile_number ?? ($pileRow->pile?->number ?? '—') }}
                                    <span class="block text-xs text-base-content/60">{{ $pileRow->pile?->warehouse?->name ?? '' }}</span>
                                </td>
                                <td class="text-end font-mono">
                                    <span class="badge badge-soft badge-neutral">{{ $formatPercentage($recommended) }}</span>
                                </td>
                                <td class="text-end font-mono">
                                    @if ($canApprove)
                                        <input type="number" step="0.01" min="0" max="100" name="co_approved_gmr[{{ $pileRow->id }}]" value="{{ old("co_approved_gmr.{$pileRow->id}") }}" class="input input-bordered input-sm w-28 text-end font-mono text-black" placeholder="{{ $recommended !== null ? number_format($recommended, 2) : '0.00' }}" />
                                        @error("co_approved_gmr.{$pileRow->id}") <span class="block text-xs text-error">{{ $message }}</span> @enderror
                                    @else
                                        <span class="badge badge-soft {{ $coApproved !== null ? 'badge-success' : 'badge-neutral' }}">{{ $formatPercentage($coApproved) }}</span>
                                    @endif
                                </td>
                                <td class="text-end font-mono font-bold">
                                    @if ($source === 'co_approved')
                                        <span class="text-success" title="Sourced from Central Office Approved GMR">{{ $formatPercentage($final) }}</span>
                                        <span class="block text-[10px] font-semibold uppercase text-success">CO Approved</span>
                                    @else
                                        <span class="text-secondary">{{ $formatPercentage($final) }}</span>
                                        <span class="block text-[10px] font-semibold uppercase text-base-content/50">Recommended</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($canApprove)
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <button type="button" class="btn btn-primary btn-sm gap-2" data-approve-confirm-trigger="{{ $approval->id }}">
                            <span class="icon-[tabler--check] size-4"></span>
                            Approve &amp; Lock Piles
                        </button>
                        <button type="button" class="btn btn-outline btn-error btn-sm gap-2" data-reject-toggle="reject-form-{{ $approval->id }}">
                            <span class="icon-[tabler--x] size-4"></span>
                            Reject
                        </button>
                    </div>
                </form>

                <!-- Custom Confirmation Dialog Modal for Approval -->
                <div id="approve-confirm-modal-{{ $approval->id }}"
                     class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/70 backdrop-blur-xs p-4 transition-opacity duration-200"
                     role="dialog"
                     aria-modal="true"
                     aria-labelledby="approve-confirm-modal-title-{{ $approval->id }}">
                    <div class="w-full max-w-md transform overflow-hidden rounded-2xl bg-base-100 p-6 text-black shadow-2xl border border-base-content/15 text-left transition-all">
                        <div class="flex items-start gap-4">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary ring-8 ring-primary/5">
                                <span class="icon-[tabler--shield-lock] size-6"></span>
                            </div>
                            <div class="flex-1">
                                <h3 id="approve-confirm-modal-title-{{ $approval->id }}" class="text-lg font-bold text-black leading-snug">
                                    Approve &amp; Lock Piles?
                                </h3>
                                <p class="mt-0.5 text-xs text-base-content/60">
                                    Central Office Final GMR Approval
                                </p>
                            </div>
                            <button type="button" class="btn btn-circle btn-text btn-xs text-base-content/50 hover:text-black" data-confirm-close="approve-confirm-modal-{{ $approval->id }}" aria-label="Close">
                                &times;
                            </button>
                        </div>

                        <div class="mt-4 space-y-3">
                            <p class="text-sm leading-relaxed text-black">
                                Are you sure you want to approve this GMR report and permanently lock the <strong class="font-semibold text-primary">{{ $approval->piles->count() }} included pile(s)</strong>?
                            </p>

                            <div class="rounded-xl border border-warning/30 bg-warning/10 p-3 text-xs text-black">
                                <div class="flex gap-2.5">
                                    <span class="icon-[tabler--alert-triangle] size-5 text-warning shrink-0 mt-0.5"></span>
                                    <div class="space-y-1">
                                        <span class="font-bold block text-black">This action cannot be undone.</span>
                                        <span class="text-base-content/80 block leading-relaxed">
                                            All {{ $approval->piles->count() }} pile(s) will be locked with their official GMR and immediately become eligible for Rice Milling.
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-xl bg-base-200/70 p-3 text-xs space-y-1.5 border border-base-content/5">
                                <div class="flex justify-between items-center">
                                    <span class="text-base-content/60">Branch</span>
                                    <span class="font-semibold text-black">{{ $approval->branch?->name ?? '—' }}</span>
                                </div>
                                @if ($approval->reference_number)
                                    <div class="flex justify-between items-center">
                                        <span class="text-base-content/60">Recommendation Memo</span>
                                        <span class="font-mono text-black">{{ $approval->reference_number }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between items-center">
                                    <span class="text-base-content/60">Piles to Lock</span>
                                    <span class="badge badge-soft badge-primary font-mono text-xs font-bold">{{ $approval->piles->count() }} pile(s)</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-3 border-t border-base-content/10 pt-4">
                            <button type="button"
                                    class="btn btn-outline min-h-10 px-4 text-sm"
                                    data-confirm-close="approve-confirm-modal-{{ $approval->id }}">
                                Cancel
                            </button>
                            <button type="button"
                                    class="btn btn-primary min-h-10 px-5 text-sm gap-2"
                                    data-confirm-submit="approve-form-{{ $approval->id }}">
                                <span class="icon-[tabler--check] size-4"></span>
                                Yes, Approve &amp; Lock
                            </button>
                        </div>
                    </div>
                </div>

                <form id="reject-form-{{ $approval->id }}" method="POST" action="{{ route('gmr-approvals.reject', $approval) }}" class="mt-3 hidden rounded-lg border border-error/30 p-4">
                    @csrf
                    <label class="form-control">
                        <span class="label-text mb-2 text-sm font-semibold text-black">Rejection reason *</span>
                        <textarea name="rejection_reason" class="textarea textarea-bordered min-h-20 text-black" required></textarea>
                    </label>
                    <div class="mt-3 flex gap-2">
                        <button type="submit" class="btn btn-error btn-sm">Confirm Reject</button>
                        <button type="button" class="btn btn-ghost btn-sm" data-reject-toggle="reject-form-{{ $approval->id }}">Cancel</button>
                    </div>
                </form>
            @endif

            <div class="mt-5 text-xs text-base-content/60 border-t border-base-content/10 pt-3">
                @if ($approval->isApproved())
                    Final GMR is locked. The Central Office Approved GMR takes precedence over the Recommended GMR as the official milling basis.
                @else
                    Recommended GMR is the system recommendation. The Central Office Approved GMR, when entered and confirmed, becomes the Final GMR.
                @endif
            </div>
        </div>
    </div>
@endforeach

<script>
    document.addEventListener('click', function (event) {
        const openButton = event.target.closest('[data-approval-open]');
        const closeButton = event.target.closest('[data-approval-close]');
        const rejectToggle = event.target.closest('[data-reject-toggle]');
        const confirmTrigger = event.target.closest('[data-approve-confirm-trigger]');
        const confirmClose = event.target.closest('[data-confirm-close]');
        const confirmSubmit = event.target.closest('[data-confirm-submit]');

        if (openButton) {
            const modal = document.getElementById(openButton.dataset.approvalOpen);
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }

        if (closeButton) {
            const modal = document.getElementById(closeButton.dataset.approvalClose);
            modal?.classList.add('hidden');
            modal?.classList.remove('flex');
        }

        if (confirmTrigger) {
            const approvalId = confirmTrigger.dataset.approveConfirmTrigger;
            const form = document.getElementById('approve-form-' + approvalId);
            if (form) {
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                const confirmModal = document.getElementById('approve-confirm-modal-' + approvalId);
                if (confirmModal) {
                    confirmModal.classList.remove('hidden');
                    confirmModal.classList.add('flex');
                }
            }
        }

        if (confirmClose) {
            const modal = document.getElementById(confirmClose.dataset.confirmClose);
            modal?.classList.add('hidden');
            modal?.classList.remove('flex');
        }

        if (confirmSubmit) {
            const form = document.getElementById(confirmSubmit.dataset.confirmSubmit);
            if (form) {
                confirmSubmit.disabled = true;
                confirmSubmit.innerHTML = '<span class="loading loading-spinner loading-xs"></span> Processing...';
                form.submit();
            }
        }

        if (event.target.id && event.target.id.startsWith('approve-confirm-modal-')) {
            event.target.classList.add('hidden');
            event.target.classList.remove('flex');
        } else if (event.target.matches('[id^="approval-modal-"]') && !event.target.id.startsWith('approve-confirm-modal-')) {
            const approvalId = event.target.id.replace('approval-modal-', '');
            const confirmModal = document.getElementById('approve-confirm-modal-' + approvalId);
            if (!confirmModal || confirmModal.classList.contains('hidden')) {
                event.target.classList.add('hidden');
                event.target.classList.remove('flex');
            }
        }

        if (rejectToggle) {
            const form = document.getElementById(rejectToggle.dataset.rejectToggle);
            form?.classList.toggle('hidden');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            const openConfirmModal = document.querySelector('[id^="approve-confirm-modal-"]:not(.hidden)');
            if (openConfirmModal) {
                openConfirmModal.classList.add('hidden');
                openConfirmModal.classList.remove('flex');
                return;
            }
            const openModal = document.querySelector('[id^="approval-modal-"]:not(.hidden)');
            if (openModal) {
                openModal.classList.add('hidden');
                openModal.classList.remove('flex');
            }
        }
    });
</script>
@endsection
