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
                <div class="alert alert-soft alert-info mt-3 text-sm"><strong>Remarks:</strong> {{ $approval->remarks }}</div>
            @endif
            @if ($approval->rejection_reason)
                <div class="alert alert-soft alert-error mt-3 text-sm"><strong>Rejection reason:</strong> {{ $approval->rejection_reason }}</div>
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
                        <button type="submit" class="btn btn-primary btn-sm gap-2" onclick="return confirm('Approve this GMR report and permanently lock the {{ $approval->piles->count() }} included pile(s)? This action cannot be undone.');">
                            <span class="icon-[tabler--check] size-4"></span>
                            Approve &amp; Lock Piles
                        </button>
                        <button type="button" class="btn btn-outline btn-error btn-sm gap-2" data-reject-toggle="reject-form-{{ $approval->id }}">
                            <span class="icon-[tabler--x] size-4"></span>
                            Reject
                        </button>
                    </div>
                </form>

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

        if (event.target.matches('[role="dialog"]')) {
            event.target.classList.add('hidden');
            event.target.classList.remove('flex');
        }

        if (rejectToggle) {
            const form = document.getElementById(rejectToggle.dataset.rejectToggle);
            form?.classList.toggle('hidden');
        }
    });
</script>
@endsection
