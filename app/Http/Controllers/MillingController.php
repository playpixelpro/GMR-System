<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Miller;
use App\Models\Milling;
use App\Models\MillingProgress;
use App\Models\Pile;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MillingController extends Controller
{
    /**
     * List rice-milling assignments.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $isStaff = $user && $user->hasRole('STAFF') && $user->branch_id;

        $branchId = $isStaff
            ? (int) $user->branch_id
            : ($request->integer('branch_id') ?: null);

        $warehouseId = $request->integer('warehouse_id') ?: null;

        // If both branch and warehouse are selected, verify warehouse belongs to branch
        if ($branchId && $warehouseId) {
            $belongs = Warehouse::query()->where('id', $warehouseId)->where('branch_id', $branchId)->exists();
            if (! $belongs) {
                $warehouseId = null;
            }
        }

        $status = $request->input('status');
        $dateFrom = $request->input('date_from') ?: $request->input('from') ?: $request->input('date');
        $dateTo = $request->input('date_to') ?: $request->input('to') ?: $request->input('date');

        $millings = Milling::query()
            ->with(['branch:id,name', 'pile:id,pile_number,number,branch_id,warehouse_id,variety,quality,volume_kg', 'pile.warehouse:id,branch_id,name', 'assignedBy:id,name'])
            ->withSum('progress', 'palay_input_kg')
            ->withSum('progress', 'milled_rice_kg')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->whereHas('pile', fn ($pq) => $pq->where('warehouse_id', $warehouseId)))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($dateFrom || $dateTo, function ($q) use ($dateFrom, $dateTo) {
                $q->where(function ($sub) use ($dateFrom, $dateTo) {
                    $sub->where(function ($assignedQ) use ($dateFrom, $dateTo) {
                        if ($dateFrom) {
                            $assignedQ->where(function ($w) use ($dateFrom) {
                                $w->whereDate('assigned_at', '>=', $dateFrom)
                                    ->orWhere(fn ($sq) => $sq->whereNull('assigned_at')->whereDate('created_at', '>=', $dateFrom));
                            });
                        }
                        if ($dateTo) {
                            $assignedQ->where(function ($w) use ($dateTo) {
                                $w->whereDate('assigned_at', '<=', $dateTo)
                                    ->orWhere(fn ($sq) => $sq->whereNull('assigned_at')->whereDate('created_at', '<=', $dateTo));
                            });
                        }
                    })->orWhereHas('progress', function ($progQ) use ($dateFrom, $dateTo) {
                        if ($dateFrom) {
                            $progQ->whereDate('progress_date', '>=', $dateFrom);
                        }
                        if ($dateTo) {
                            $progQ->whereDate('progress_date', '<=', $dateTo);
                        }
                    });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $branches = $this->branchesForUser($user, $isStaff);

        $warehouses = Warehouse::query()
            ->with('branch:id,name')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name']);

        // Available approved-GMR piles for the Assign Milling modal.
        $availablePiles = $this->availablePiles($isStaff ? (int) $user->branch_id : null);

        // Warehouses for the Assign Milling modal, scoped like the branch list.
        $assignWarehouses = Warehouse::query()
            ->when($isStaff, fn ($q) => $q->where('branch_id', (int) $user->branch_id))
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name']);

        return view('millings.index', [
            'millings' => $millings,
            'branches' => $branches,
            'warehouses' => $warehouses,
            'availablePiles' => $availablePiles,
            'assignWarehouses' => $assignWarehouses,
            'filters' => [
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Form to assign a new rice milling to an approved-GMR pile.
     */
    public function create(Request $request): View
    {
        $user = $request->user();
        $isStaff = $user && $user->hasRole('STAFF') && $user->branch_id;

        $branchId = $isStaff ? (int) $user->branch_id : ($request->integer('branch_id') ?: null);

        $piles = $this->availablePiles($branchId);
        $branches = $this->branchesForUser($user, $isStaff);

        return view('millings.create', [
            'piles' => $piles,
            'branches' => $branches,
            'selected_branch_id' => $branchId,
        ]);
    }

    /**
     * Branches selectable by the current user (staff are scoped to their own).
     */
    private function branchesForUser($user, bool $isStaff)
    {
        return $isStaff
            ? Branch::whereKey($user->branch_id)->orderBy('name')->get(['id', 'name'])
            : Branch::orderBy('name')->get(['id', 'name']);
    }

    /**
     * Approved-GMR piles available for a new milling assignment, with warehouse
     * details so the assigner can distinguish piles by warehouse, not just number.
     */
    private function availablePiles(?int $branchId)
    {
        return Pile::query()
            ->with(['branch:id,name', 'warehouse:id,branch_id,name', 'gmrApprovalPile:id,gmr,co_approved_gmr'])
            ->where('gmr_status', 'approved')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDoesntHave('millings', fn ($q) => $q->whereIn('status', ['assigned', 'ongoing']))
            ->orderBy('pile_number')
            ->orderBy('number')
            ->get();
    }

    /**
     * Store a new rice-milling assignment (one pile per milling).
     */
    public function store(Request $request): RedirectResponse
    {
        if (! $request->has('lot_number') && $request->has('lot_no')) {
            $request->merge(['lot_number' => $request->input('lot_no')]);
        }

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'pile_id' => ['required', 'integer', 'exists:piles,id'],
            'miller' => ['required', 'string', 'max:191'],
            'reference_number' => ['required', 'string', 'max:255'],
            'lot_number' => ['required', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'miller.required' => 'The Miller / Rice Mill field is required.',
            'reference_number.required' => 'The Project / Memo Reference No. is required (bidding project ref. for contracted millers, or memo no. for NFA-owned rice mills).',
            'lot_number.required' => 'The Lot No. field is required.',
        ]);

        $pile = Pile::with([
            'branch:id,name',
            'warehouse:id,branch_id,name',
            'gmrApprovalPile:id,gmr,co_approved_gmr',
        ])->findOrFail($validated['pile_id']);

        // Only approved-GMR piles may be milled.
        if (! $pile->canBeMilled()) {
            return back()
                ->withInput()
                ->withErrors(['pile_id' => 'Only piles with an approved GMR may be assigned to a rice milling.']);
        }

        // Ensure the pile belongs to the selected branch.
        $pileBranchId = $pile->branch_id ?? $pile->warehouse?->branch_id;
        if ((int) $pileBranchId !== (int) $validated['branch_id']) {
            return back()
                ->withInput()
                ->withErrors(['pile_id' => 'The selected pile does not belong to the chosen branch.']);
        }

        // Prevent assigning to a pile that already has an active milling.
        if ($pile->millings()->whereIn('status', ['assigned', 'ongoing'])->exists()) {
            return back()
                ->withInput()
                ->withErrors(['pile_id' => 'This pile already has an active rice milling.']);
        }

        $targetVolumeKg = $pile->volume_kg !== null ? (float) $pile->volume_kg : 0;
        $targetVolumeBags = $targetVolumeKg > 0 ? round($targetVolumeKg / 50, 3) : null;

        // Freeze the official Final GMR at assignment time. The Final GMR is the
        // Central Office Approved GMR when present, otherwise the Recommended GMR.
        // This is the immutable official basis for the pile's milling/recovery.
        $finalGmr = $pile->finalGmr();
        $finalGmrSource = $pile->finalGmrIsCoApproved() ? 'co_approved' : 'recommended';

        // Link the free-text miller name to its master profile when known.
        $millerName = $validated['miller'] ?? null;
        $millerId = filled($millerName)
            ? Miller::where('name', $millerName)->value('id')
            : null;

        $milling = DB::transaction(function () use ($validated, $pile, $targetVolumeKg, $targetVolumeBags, $finalGmr, $finalGmrSource, $request, $millerId): Milling {
            $milling = Milling::create([
                'branch_id' => $validated['branch_id'],
                'pile_id' => $pile->id,
                'miller' => $validated['miller'] ?? null,
                'miller_id' => $millerId,
                'reference_number' => $validated['reference_number'] ?? null,
                'lot_number' => $validated['lot_number'] ?? null,
                'status' => 'assigned',
                'target_volume_kg' => $targetVolumeKg > 0 ? $targetVolumeKg : null,
                'target_volume_bags' => $targetVolumeBags,
                'final_gmr' => $finalGmr,
                'final_gmr_source' => $finalGmr !== null ? $finalGmrSource : null,
                'assigned_by' => $request->user()?->getKey(),
                'assigned_at' => now(),
                'remarks' => $validated['remarks'] ?? null,
            ]);

            AuditLog::create([
                'module' => 'milling',
                'action' => 'MILLING_ASSIGNED',
                'description' => 'Rice milling assigned to pile',
                'branch_id' => $validated['branch_id'],
                'branch_name' => $pile->branch?->name,
                'ip_address' => $request->ip(),
                'user_id' => $request->user()?->getKey(),
                'role' => $request->user()?->role,
                'pile_id' => $pile->id,
                'auditable_type' => Milling::class,
                'auditable_id' => $milling->id,
                'metadata' => [
                    'milling_id' => $milling->id,
                    'pile_id' => $pile->id,
                    'miller' => $validated['miller'] ?? null,
                    'reference_number' => $validated['reference_number'] ?? null,
                    'lot_number' => $validated['lot_number'] ?? null,
                    'target_volume_kg' => $targetVolumeKg > 0 ? $targetVolumeKg : null,
                    'final_gmr' => $finalGmr,
                    'final_gmr_source' => $finalGmr !== null ? $finalGmrSource : null,
                ],
            ]);

            return $milling;
        });

        return redirect()
            ->route('millings.show', $milling)
            ->with('status', 'Rice milling assigned to pile '.($pile->pile_number ?? $pile->number ?? '#'.$pile->id).'.');
    }

    /**
     * Show a milling assignment with its progress entries and cumulative accomplishment.
     */
    public function show(Milling $milling): View
    {
        $milling->load([
            'branch:id,name',
            'pile:id,pile_number,number,branch_id,warehouse_id,variety,quality,volume_kg',
            'pile.warehouse:id,branch_id,name',
            'assignedBy:id,name',
            'progress' => fn ($q) => $q->orderByDesc('progress_date')->orderByDesc('id'),
            'progress.recordedBy:id,name',
        ]);

        $cumulativeMilled = $milling->cumulativeMilledKg();
        $cumulativePalay = $milling->cumulativePalayKg();
        $progressPct = $milling->progressPercentage();

        return view('millings.show', [
            'milling' => $milling,
            'cumulativeMilled' => $cumulativeMilled,
            'cumulativePalay' => $cumulativePalay,
            'progressPct' => $progressPct,
        ]);
    }

    /**
     * Update the status of a milling assignment.
     */
    public function update(Request $request, Milling $milling): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:assigned,ongoing,completed,cancelled'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $previousStatus = $milling->status;

        DB::transaction(function () use ($milling, $validated, $previousStatus, $request): void {
            $updateData = ['status' => $validated['status']];

            if ($validated['status'] === 'ongoing' && $milling->started_at === null) {
                $updateData['started_at'] = now();
            }

            if ($validated['status'] === 'completed' && $milling->completed_at === null) {
                $updateData['completed_at'] = now();
            }

            if (array_key_exists('remarks', $validated)) {
                $updateData['remarks'] = $validated['remarks'];
            }

            $milling->update($updateData);

            AuditLog::create([
                'module' => 'milling',
                'action' => 'MILLING_STATUS_CHANGED',
                'description' => "Milling status changed from {$previousStatus} to {$validated['status']}",
                'branch_id' => $milling->branch_id,
                'branch_name' => $milling->branch?->name,
                'ip_address' => $request->ip(),
                'user_id' => $request->user()?->getKey(),
                'role' => $request->user()?->role,
                'pile_id' => $milling->pile_id,
                'auditable_type' => Milling::class,
                'auditable_id' => $milling->id,
                'metadata' => [
                    'milling_id' => $milling->id,
                    'previous_status' => $previousStatus,
                    'new_status' => $validated['status'],
                ],
            ]);
        });

        return redirect()
            ->route('millings.show', $milling)
            ->with('status', "Milling status updated to {$validated['status']}.");
    }

    /**
     * Log a milling accomplishment/progress entry for a pile.
     */
    public function storeProgress(Request $request, Milling $milling): RedirectResponse
    {
        if (! $request->has('batch_number') && $request->has('batch_no')) {
            $request->merge(['batch_number' => $request->input('batch_no')]);
        }

        $validated = $request->validate([
            'progress_date' => ['required', 'date', 'before_or_equal:today'],
            'batch_number' => ['required', 'string', 'max:100'],
            'palay_input_kg' => ['required', 'numeric', 'min:0'],
            'milled_rice_kg' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'batch_number.required' => 'The Batch No. is required.',
            'palay_input_kg.required' => 'The Palay Input (kg) is required.',
            'milled_rice_kg.required' => 'The Milled Rice (kg) is required.',
        ]);

        $palay = isset($validated['palay_input_kg']) ? (float) $validated['palay_input_kg'] : null;
        $milled = isset($validated['milled_rice_kg']) ? (float) $validated['milled_rice_kg'] : null;

        $recovery = ($palay !== null && $milled !== null && $palay > 0)
            ? round(($milled / $palay) * 100, 2)
            : null;

        DB::transaction(function () use ($milling, $validated, $palay, $milled, $recovery, $request): void {
            $progress = MillingProgress::create([
                'milling_id' => $milling->id,
                'pile_id' => $milling->pile_id,
                'progress_date' => $validated['progress_date'],
                'batch_number' => $validated['batch_number'],
                'palay_input_kg' => $palay,
                'milled_rice_kg' => $milled,
                'recovery_percentage' => $recovery,
                'remarks' => $validated['remarks'] ?? null,
                'recorded_by' => $request->user()?->getKey(),
            ]);

            // Auto-start the milling on its first progress entry.
            if ($milling->status === 'assigned') {
                $milling->update([
                    'status' => 'ongoing',
                    'started_at' => $milling->started_at ?? now(),
                ]);
            }

            // Auto-complete when cumulative milled rice meets the target.
            $target = (float) $milling->target_volume_kg;
            if ($target > 0 && $milling->fresh()->cumulativeMilledKg() >= $target) {
                $milling->update([
                    'status' => 'completed',
                    'completed_at' => $milling->completed_at ?? now(),
                ]);
            }

            AuditLog::create([
                'module' => 'milling',
                'action' => 'MILLING_PROGRESS_RECORDED',
                'description' => 'Milling accomplishment recorded',
                'branch_id' => $milling->branch_id,
                'branch_name' => $milling->branch?->name,
                'ip_address' => $request->ip(),
                'user_id' => $request->user()?->getKey(),
                'role' => $request->user()?->role,
                'pile_id' => $milling->pile_id,
                'auditable_type' => MillingProgress::class,
                'auditable_id' => $progress->id,
                'metadata' => [
                    'milling_id' => $milling->id,
                    'progress_id' => $progress->id,
                    'progress_date' => $validated['progress_date'],
                    'batch_number' => $validated['batch_number'],
                    'palay_input_kg' => $palay,
                    'milled_rice_kg' => $milled,
                    'recovery_percentage' => $recovery,
                ],
            ]);
        });

        return redirect()
            ->route('millings.show', $milling)
            ->with('status', 'Milling accomplishment recorded.');
    }

    /**
     * Delete a milling progress entry (RMEC/Admin only).
     */
    public function destroyProgress(Request $request, MillingProgress $progress): RedirectResponse
    {
        $milling = $progress->milling;

        DB::transaction(function () use ($progress, $request): void {
            $progress->delete();

            AuditLog::create([
                'module' => 'milling',
                'action' => 'MILLING_PROGRESS_DELETED',
                'description' => 'Milling accomplishment entry deleted',
                'branch_id' => $progress->milling?->branch_id,
                'ip_address' => $request->ip(),
                'user_id' => $request->user()?->getKey(),
                'role' => $request->user()?->role,
                'pile_id' => $progress->pile_id,
                'metadata' => [
                    'milling_id' => $progress->milling_id,
                    'progress_date' => $progress->progress_date?->format('Y-m-d'),
                ],
            ]);
        });

        return redirect()
            ->route('millings.show', $milling)
            ->with('status', 'Progress entry deleted.');
    }
}
