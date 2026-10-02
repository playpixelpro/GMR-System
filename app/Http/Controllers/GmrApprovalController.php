<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\GmrApproval;
use App\Models\GmrApprovalPile;
use App\Models\Pile;
use App\Services\EmrGmrGateService;
use App\Services\GmrReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GmrApprovalController extends Controller
{
    public function __construct(
        protected GmrReportService $reportService,
        protected EmrGmrGateService $gateService,
    ) {}

    /**
     * List GMR submissions sent to the Central Office.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $isStaff = $user && $user->hasRole('STAFF') && $user->branch_id;

        $branchId = $isStaff
            ? (int) $user->branch_id
            : ($request->integer('branch_id') ?: null);

        $status = $request->input('status');

        $approvals = GmrApproval::query()
            ->with(['branch:id,name', 'piles.pile:id,pile_number,number,branch_id,warehouse_id', 'piles.pile.warehouse:id,branch_id,name', 'submittedBy:id,name', 'approvedBy:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $branches = $isStaff
            ? Branch::whereKey($user->branch_id)->orderBy('name')->get(['id', 'name'])
            : Branch::orderBy('name')->get(['id', 'name']);

        return view('gmr-approvals.index', [
            'approvals' => $approvals,
            'branches' => $branches,
            'filters' => [
                'branch_id' => $branchId,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Submit selected GMR records to the Central Office for approval.
     *
     * Reuses the same selection mechanism (selected_piles[]) as the print form.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'selected_piles' => ['required', 'array', 'min:1'],
            'selected_piles.*' => ['required', 'integer', 'exists:piles,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'reference_number' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'reference_number.required' => 'The Recommendation Memo No. is required when submitting to the Central Office.',
        ]);

        $branch = Branch::findOrFail($validated['branch_id']);

        // Filter pile IDs to only those belonging to the selected branch.
        $allowedPileIds = Pile::whereIn('id', $validated['selected_piles'])
            ->where(function ($query) use ($branch): void {
                $query
                    ->where('branch_id', $branch->id)
                    ->orWhereHas(
                        'warehouse',
                        fn ($warehouseQuery) => $warehouseQuery->where('branch_id', $branch->id),
                    );
            })
            ->pluck('id')
            ->all();

        if (empty($allowedPileIds)) {
            return redirect()
                ->route('gmr.summary')
                ->with('error', 'None of the selected records belong to the chosen branch.');
        }

        $piles = Pile::with(['branch:id,name', 'warehouse:id,branch_id,name', 'amrCalculation', 'pmrCalculation', 'amrRecords', 'pmrRecords'])
            ->whereIn('id', $allowedPileIds)
            ->get();

        // Build the frozen snapshot rows and reject ineligible piles.
        $snapshotRows = [];
        $ineligible = [];

        foreach ($piles as $pile) {
            if ($pile->isGmrLocked()) {
                $ineligible[] = $pile->pile_number ?? ($pile->number ?? '#'.$pile->id).' (already approved)';

                continue;
            }

            if ($pile->isGmrSubmitted()) {
                $ineligible[] = $pile->pile_number ?? ($pile->number ?? '#'.$pile->id).' (already submitted)';

                continue;
            }

            $gate = $this->gateService->evaluateGate($pile);

            if (! $gate['can_compute']) {
                $ineligible[] = $pile->pile_number ?? ($pile->number ?? '#'.$pile->id).' (GMR not computed)';

                continue;
            }

            $snapshotRows[] = [
                'pile' => $pile,
                'amr' => $gate['amr_rate'],
                'pmr' => $gate['pmr_rate'],
                'emr_display' => $gate['emr_display'],
                'gmr' => $gate['gmr'],
                'volume_kg' => $pile->volume_kg,
                'volume_bags' => $pile->volume_kg !== null ? round((float) $pile->volume_kg / 50, 3) : null,
                'quality' => $pile->quality,
                'variety' => $pile->variety,
            ];
        }

        if (empty($snapshotRows)) {
            return redirect()
                ->route('gmr.summary')
                ->with('error', 'No eligible GMR records were found in the selection. Ineligible: '.implode(', ', $ineligible));
        }

        $config = $this->reportService->getConfiguration();
        $signatories = $this->reportService->getActiveSignatories();

        $reportSnapshot = [
            'title' => $config->title,
            'subtitle' => $config->subtitle,
            'region_text' => $config->region_text,
            'branch_text' => $config->branch_text ?: $branch->name,
            'signatories' => $signatories->map(fn ($s) => [
                'name' => $s->name,
                'position' => $s->position,
                'role_group' => $s->role_group,
            ])->values()->all(),
        ];

        DB::transaction(function () use ($validated, $branch, $snapshotRows, $reportSnapshot, $request, $ineligible): void {
            $approval = GmrApproval::create([
                'branch_id' => $branch->id,
                'reference_number' => $validated['reference_number'] ?? null,
                'status' => 'submitted',
                'submitted_by' => $request->user()?->getKey(),
                'submitted_at' => now(),
                'remarks' => $validated['remarks'] ?? null,
                'report_snapshot' => $reportSnapshot,
            ]);

            foreach ($snapshotRows as $row) {
                /** @var Pile $pile */
                $pile = $row['pile'];

                $approvalPile = GmrApprovalPile::create([
                    'gmr_approval_id' => $approval->id,
                    'pile_id' => $pile->id,
                    'amr' => $row['amr'],
                    'pmr' => $row['pmr'],
                    'emr_display' => $row['emr_display'],
                    'gmr' => $row['gmr'],
                    'volume_kg' => $row['volume_kg'],
                    'volume_bags' => $row['volume_bags'],
                    'quality' => $row['quality'],
                    'variety' => $row['variety'],
                ]);

                $pile->update([
                    'gmr_status' => 'submitted',
                    'gmr_approval_pile_id' => $approvalPile->id,
                ]);
            }

            AuditLog::create([
                'module' => 'gmr',
                'action' => 'GMR_SUBMITTED',
                'description' => 'GMR report submitted to Central Office',
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'ip_address' => $request->ip(),
                'user_id' => $request->user()?->getKey(),
                'role' => $request->user()?->role,
                'auditable_type' => GmrApproval::class,
                'auditable_id' => $approval->id,
                'metadata' => [
                    'gmr_approval_id' => $approval->id,
                    'pile_count' => count($snapshotRows),
                    'pile_ids' => array_map(fn ($r) => $r['pile']->id, $snapshotRows),
                    'ineligible' => $ineligible,
                ],
            ]);
        });

        $message = count($snapshotRows).' GMR record(s) submitted to the Central Office for approval.';
        if (! empty($ineligible)) {
            $message .= ' Skipped: '.implode(', ', $ineligible).'.';
        }

        return redirect()
            ->route('gmr-approvals.index')
            ->with('status', $message);
    }

    /**
     * Show a single GMR submission with its frozen pile snapshots.
     */
    public function show(GmrApproval $approval): View
    {
        $approval->load(['branch:id,name', 'piles.pile:id,pile_number,number,branch_id,warehouse_id', 'piles.pile.warehouse:id,branch_id,name', 'submittedBy:id,name', 'approvedBy:id,name']);

        return view('gmr-approvals.show', [
            'approval' => $approval,
        ]);
    }

    /**
     * Approve a submitted GMR report — records the Central-Office approved
     * GMR per pile and permanently locks each pile.
     *
     * The CO Approval Memorandum No. is required. The per-pile Central-Office
     * Approved GMR is optional; when provided it takes precedence over the
     * system-recommended GMR as the official Final GMR. The recommended GMR
     * is never overwritten.
     */
    public function approve(Request $request, GmrApproval $approval): RedirectResponse
    {
        if (! $approval->isSubmitted()) {
            return redirect()
                ->route('gmr-approvals.index')
                ->with('error', 'Only submitted reports can be approved.');
        }

        $approval->load(['piles.pile']);

        $coGmrRules = ['nullable', 'numeric', 'min:0', 'max:100'];

        $validated = $request->validate(
            array_merge([
                'co_approval_memo_no' => ['required', 'string', 'max:255'],
                'remarks' => ['nullable', 'string', 'max:2000'],
                'co_approved_gmr' => ['array'],
            ], collect($approval->piles)->mapWithKeys(fn (GmrApprovalPile $p) => [
                "co_approved_gmr.{$p->id}" => $coGmrRules,
            ])->all()),
            [
                'co_approval_memo_no.required' => 'The CO Approval Memorandum No. is required.',
                'co_approved_gmr.*.numeric' => 'The Central Office Approved GMR must be a numeric percentage.',
                'co_approved_gmr.*.min' => 'The Central Office Approved GMR must be at least 0.',
                'co_approved_gmr.*.max' => 'The Central Office Approved GMR must not exceed 100.',
            ]
        );

        DB::transaction(function () use ($approval, $validated, $request): void {
            $approval->update([
                'status' => 'approved',
                'co_approval_memo_no' => $validated['co_approval_memo_no'],
                'approved_by' => $request->user()?->getKey(),
                'approved_at' => now(),
                'remarks' => $validated['remarks'] ?? $approval->remarks,
            ]);

            $perPileAudit = [];

            $approval->piles->each(function (GmrApprovalPile $approvalPile) use ($validated, &$perPileAudit): void {
                $coGmr = $validated['co_approved_gmr'][$approvalPile->id] ?? null;

                $approvalPile->update([
                    'co_approved_gmr' => $coGmr !== null && $coGmr !== '' ? $coGmr : null,
                ]);

                $pile = $approvalPile->pile;

                if ($pile) {
                    $pile->update([
                        'gmr_status' => 'approved',
                        'gmr_approval_pile_id' => $approvalPile->id,
                        'gmr_locked_at' => now(),
                    ]);
                }

                $perPileAudit[] = [
                    'pile_id' => $approvalPile->pile_id,
                    'recommended_gmr' => $approvalPile->gmr !== null ? (float) $approvalPile->gmr : null,
                    'co_approved_gmr' => $approvalPile->co_approved_gmr !== null ? (float) $approvalPile->co_approved_gmr : null,
                    'final_gmr' => $approvalPile->finalGmr(),
                    'final_gmr_source' => $approvalPile->finalGmrSource(),
                ];
            });

            AuditLog::create([
                'module' => 'gmr',
                'action' => 'GMR_APPROVED',
                'description' => 'GMR report approved by Central Office — piles locked',
                'branch_id' => $approval->branch_id,
                'branch_name' => $approval->branch?->name,
                'ip_address' => $request->ip(),
                'user_id' => $request->user()?->getKey(),
                'role' => $request->user()?->role,
                'auditable_type' => GmrApproval::class,
                'auditable_id' => $approval->id,
                'metadata' => [
                    'gmr_approval_id' => $approval->id,
                    'co_approval_memo_no' => $validated['co_approval_memo_no'],
                    'approved_by' => $request->user()?->getKey(),
                    'approved_at' => now()->toIso8601String(),
                    'remarks' => $validated['remarks'] ?? null,
                    'per_pile' => $perPileAudit,
                ],
            ]);
        });

        return redirect()
            ->route('gmr-approvals.index')
            ->with('status', 'GMR report approved under CO Memorandum No. '.$validated['co_approval_memo_no'].'. '.count($approval->piles).' pile(s) permanently locked.');
    }

    /**
     * Reject a submitted GMR report — releases the piles for re-submission.
     */
    public function reject(Request $request, GmrApproval $approval): RedirectResponse
    {
        if (! $approval->isSubmitted()) {
            return redirect()
                ->route('gmr-approvals.index')
                ->with('error', 'Only submitted reports can be rejected.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        DB::transaction(function () use ($approval, $validated, $request): void {
            $approval->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'approved_by' => $request->user()?->getKey(),
                'approved_at' => now(),
            ]);

            $approval->piles->load('pile')->each(function (GmrApprovalPile $approvalPile): void {
                $pile = $approvalPile->pile;

                if ($pile) {
                    $pile->update([
                        'gmr_status' => null,
                        'gmr_approval_pile_id' => null,
                        'gmr_locked_at' => null,
                    ]);
                }
            });

            AuditLog::create([
                'module' => 'gmr',
                'action' => 'GMR_REJECTED',
                'description' => 'GMR report rejected by Central Office — piles released',
                'branch_id' => $approval->branch_id,
                'branch_name' => $approval->branch?->name,
                'ip_address' => $request->ip(),
                'user_id' => $request->user()?->getKey(),
                'role' => $request->user()?->role,
                'auditable_type' => GmrApproval::class,
                'auditable_id' => $approval->id,
                'metadata' => [
                    'gmr_approval_id' => $approval->id,
                    'pile_ids' => $approval->piles->pluck('pile_id')->all(),
                    'rejection_reason' => $validated['rejection_reason'],
                ],
            ]);
        });

        return redirect()
            ->route('gmr-approvals.index')
            ->with('status', 'GMR report rejected. Piles released for re-submission.');
    }
}
