@extends('layouts.app')

@section('title', 'GMR Submission Detail')

@section('content')
@php
    $formatPercentage = static fn (?float $value): string => $value === null ? 'N/A' : number_format($value, 2).'%';
@endphp

<div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
    <div>
        <h1 class="text-2xl font-bold leading-tight text-black sm:text-3xl">GMR Submission Detail</h1>
        <p class="mt-2 text-sm leading-6 text-black">
            {{ $approval->branch?->name ?? '—' }}
            @if ($approval->reference_number) · Recommendation Memo: {{ $approval->reference_number }} @endif
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('gmr-approvals.index') }}" class="btn btn-outline btn-sm sm:btn-md gap-2">
            <span class="icon-[tabler--arrow-left] size-5"></span>
            Back to Approvals
        </a>
    </div>
</div>

<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
    <div class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <p class="text-xs font-semibold uppercase text-black">Status</p>
        <p class="mt-2">
            @if ($approval->isApproved())
                <span class="badge badge-soft badge-success">Approved</span>
            @elseif ($approval->isRejected())
                <span class="badge badge-soft badge-error">Rejected</span>
            @else
                <span class="badge badge-soft badge-info">Submitted</span>
            @endif
        </p>
    </div>
    <div class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <p class="text-xs font-semibold uppercase text-black">Submitted</p>
        <p class="mt-2 text-sm">{{ $approval->submitted_at?->format('M d, Y H:i') ?? '—' }}</p>
        <p class="text-xs text-base-content/60">{{ $approval->submittedBy?->name }}</p>
    </div>
    <div class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <p class="text-xs font-semibold uppercase text-black">Approved / Rejected</p>
        <p class="mt-2 text-sm">{{ $approval->approved_at?->format('M d, Y H:i') ?? '—' }}</p>
        <p class="text-xs text-base-content/60">{{ $approval->approvedBy?->name }}</p>
    </div>
    <div class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <p class="text-xs font-semibold uppercase text-black">Piles Included</p>
        <p class="mt-2 text-2xl font-bold font-mono">{{ $approval->piles->count() }}</p>
    </div>
    <div class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <p class="text-xs font-semibold uppercase text-black">Recommendation Memo No.</p>
        <p class="mt-2 text-sm font-mono">{{ $approval->reference_number ?? '—' }}</p>
    </div>
    <div class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
        <p class="text-xs font-semibold uppercase text-black">CO Memo No.</p>
        <p class="mt-2 text-sm font-mono">{{ $approval->co_approval_memo_no ?? '—' }}</p>
    </div>
</div>

@if ($approval->remarks)
    <div class="alert alert-soft alert-info mb-4 text-sm"><strong>Remarks:</strong> {{ $approval->remarks }}</div>
@endif
@if ($approval->rejection_reason)
    <div class="alert alert-soft alert-error mb-4 text-sm"><strong>Rejection reason:</strong> {{ $approval->rejection_reason }}</div>
@endif

@php
    $canApprove = auth()->user()?->hasRole('RMEC', 'ADMINISTRATOR') && $approval->isSubmitted();
@endphp

@if ($canApprove)
    <form method="POST" action="{{ route('gmr-approvals.approve', $approval) }}" id="approve-form">
        @csrf
        <div class="mb-5 rounded-lg border border-secondary/30 bg-secondary/5 p-4">
            <h2 class="text-sm font-semibold uppercase text-secondary">Central Office Approval</h2>
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

<section class="card border border-base-content/10 bg-base-100 p-4 text-black shadow-sm">
    <h2 class="mb-4 text-lg font-semibold text-black">Included Piles (Frozen Snapshot)</h2>
    <div class="overflow-x-auto">
        <table class="table table-sm min-w-[64rem] text-sm">
            <thead>
                <tr class="border-b border-base-content/15 text-sm font-semibold text-black">
                    <th>Pile</th>
                    <th>Variety</th>
                    <th>Quality</th>
                    <th class="text-end">Volume (kg)</th>
                    <th class="text-end">AMR</th>
                    <th class="text-end">PMR</th>
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
                        <td class="font-semibold">{{ $pileRow->pile?->pile_number ?? ($pileRow->pile?->number ?? '—') }}</td>
                        <td>{{ $pileRow->variety ?? '—' }}</td>
                        <td>{{ ! empty($pileRow->quality) ? strtoupper($pileRow->quality) : 'GQA' }}</td>
                        <td class="text-end font-mono">{{ $pileRow->volume_kg !== null ? number_format((float) $pileRow->volume_kg, 3) : 'N/A' }}</td>
                        <td class="text-end font-mono">{{ $formatPercentage($pileRow->amr !== null ? (float) $pileRow->amr : null) }}</td>
                        <td class="text-end font-mono">{{ $formatPercentage($pileRow->pmr !== null ? (float) $pileRow->pmr : null) }}</td>
                        <td class="text-end font-mono">{{ $formatPercentage($recommended) }}</td>
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
                                <span class="text-success">{{ $formatPercentage($final) }}</span>
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
</section>

@if ($canApprove)
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="button" id="btn-open-approve-confirm" class="btn btn-primary btn-sm gap-2">
                <span class="icon-[tabler--check] size-4"></span>
                Approve &amp; Lock Piles
            </button>
            <button type="button" id="btn-reject" class="btn btn-outline btn-error btn-sm gap-2">
                <span class="icon-[tabler--x] size-4"></span>
                Reject
            </button>
        </div>
    </form>

    <!-- Custom Confirmation Dialog Modal for Approval -->
    <div id="approve-confirm-modal"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-xs p-4 transition-opacity duration-200"
         role="dialog"
         aria-modal="true"
         aria-labelledby="approve-confirm-modal-title">
        <div class="w-full max-w-md transform overflow-hidden rounded-2xl bg-base-100 p-6 text-black shadow-2xl border border-base-content/15 text-left transition-all">
            <div class="flex items-start gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary ring-8 ring-primary/5">
                    <span class="icon-[tabler--shield-lock] size-6"></span>
                </div>
                <div class="flex-1">
                    <h3 id="approve-confirm-modal-title" class="text-lg font-bold text-black leading-snug">
                        Approve &amp; Lock Piles?
                    </h3>
                    <p class="mt-0.5 text-xs text-base-content/60">
                        Central Office Final GMR Approval
                    </p>
                </div>
                <button type="button" id="btn-close-approve-confirm" class="btn btn-circle btn-text btn-xs text-base-content/50 hover:text-black" aria-label="Close">
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
                        id="btn-cancel-approve-confirm"
                        class="btn btn-outline min-h-10 px-4 text-sm">
                    Cancel
                </button>
                <button type="button"
                        id="btn-submit-approve-confirm"
                        class="btn btn-primary min-h-10 px-5 text-sm gap-2">
                    <span class="icon-[tabler--check] size-4"></span>
                    Yes, Approve &amp; Lock
                </button>
            </div>
        </div>
    </div>

    <form id="reject-form" method="POST" action="{{ route('gmr-approvals.reject', $approval) }}" class="hidden mb-5 card border border-error/30 p-4">
        @csrf
        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Rejection reason *</span>
            <textarea name="rejection_reason" class="textarea textarea-bordered min-h-24 text-black" required></textarea>
        </label>
        <div class="mt-3 flex gap-2">
            <button type="submit" class="btn btn-error btn-sm">Confirm Reject</button>
            <button type="button" id="btn-cancel-reject" class="btn btn-ghost btn-sm">Cancel</button>
        </div>
    </form>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rejectBtn = document.getElementById('btn-reject');
        const rejectForm = document.getElementById('reject-form');
        const cancelRejectBtn = document.getElementById('btn-cancel-reject');

        const openApproveConfirmBtn = document.getElementById('btn-open-approve-confirm');
        const approveConfirmModal = document.getElementById('approve-confirm-modal');
        const closeApproveConfirmBtn = document.getElementById('btn-close-approve-confirm');
        const cancelApproveConfirmBtn = document.getElementById('btn-cancel-approve-confirm');
        const submitApproveConfirmBtn = document.getElementById('btn-submit-approve-confirm');
        const approveForm = document.getElementById('approve-form');

        if (rejectBtn && rejectForm) {
            rejectBtn.addEventListener('click', () => rejectForm.classList.remove('hidden'));
        }
        if (cancelRejectBtn) {
            cancelRejectBtn.addEventListener('click', () => rejectForm?.classList.add('hidden'));
        }

        function openConfirmModal() {
            if (!approveForm) return;
            if (!approveForm.checkValidity()) {
                approveForm.reportValidity();
                return;
            }
            if (approveConfirmModal) {
                approveConfirmModal.classList.remove('hidden');
                approveConfirmModal.classList.add('flex');
            }
        }

        function closeConfirmModal() {
            if (approveConfirmModal) {
                approveConfirmModal.classList.add('hidden');
                approveConfirmModal.classList.remove('flex');
            }
        }

        if (openApproveConfirmBtn) {
            openApproveConfirmBtn.addEventListener('click', openConfirmModal);
        }
        if (closeApproveConfirmBtn) {
            closeApproveConfirmBtn.addEventListener('click', closeConfirmModal);
        }
        if (cancelApproveConfirmBtn) {
            cancelApproveConfirmBtn.addEventListener('click', closeConfirmModal);
        }
        if (approveConfirmModal) {
            approveConfirmModal.addEventListener('click', function (e) {
                if (e.target === approveConfirmModal) {
                    closeConfirmModal();
                }
            });
        }
        if (submitApproveConfirmBtn && approveForm) {
            submitApproveConfirmBtn.addEventListener('click', function () {
                submitApproveConfirmBtn.disabled = true;
                submitApproveConfirmBtn.innerHTML = '<span class="loading loading-spinner loading-xs"></span> Processing...';
                approveForm.submit();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && approveConfirmModal && !approveConfirmModal.classList.contains('hidden')) {
                closeConfirmModal();
            }
        });
    });
</script>
@endsection
