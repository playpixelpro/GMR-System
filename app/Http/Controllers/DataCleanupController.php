<?php

namespace App\Http\Controllers;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\Warehouse;
use App\Services\DataCleanupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Administrator-only data cleanup (Settings → Data Cleanup).
 *
 * Deletions are strictly bottom-up: laboratory test trials first, then the
 * pile itself, then the warehouse — a parent can never be deleted while it
 * still owns children. Piles whose GMR reached the Central Office
 * (submitted or approved) and their test data are never deletable.
 */
class DataCleanupController extends Controller
{
    public const TABS = ['warehouses', 'piles', 'test-milling'];

    public function __construct(private readonly DataCleanupService $cleanup) {}

    /**
     * Show the cleanup page (warehouses, piles, laboratory test data tabs).
     */
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $tab = in_array($request->query('tab'), self::TABS, true)
            ? $request->query('tab')
            : 'warehouses';

        $branchId = $request->integer('branch_id') ?: null;
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $search = trim((string) $request->query('q', ''));

        $branches = Branch::query()->orderBy('name')->get(['id', 'name']);

        $warehouseOptions = Warehouse::query()
            ->with('branch:id,name')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name']);

        $warehouseRows = Warehouse::query()
            ->with('branch:id,name')
            ->withCount('piles')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $pilesQuery = Pile::query()
            ->with(['warehouse:id,branch_id,name'])
            ->withCount(['amrRecords', 'pmrRecords', 'millings'])
            ->when($branchId, fn ($q) => $q->where(
                fn ($qq) => $qq->where('branch_id', $branchId)
                    ->orWhereHas('warehouse', fn ($w) => $w->where('branch_id', $branchId)),
            ))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($search !== '', fn ($q) => $q->where(
                fn ($qq) => $qq->where('pile_number', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%"),
            ));

        if ($tab === 'test-milling') {
            $pilesQuery->where(fn ($q) => $q->has('amrRecords')->orWhereHas('pmrRecords'));
        }

        $piles = $pilesQuery
            ->orderBy('warehouse_id')
            ->orderBy('pile_number')
            ->orderBy('number')
            ->paginate(50)
            ->withQueryString();

        if ($tab === 'test-milling') {
            $piles->getCollection()->load([
                'amrRecords' => fn ($q) => $q->orderBy('trial_number'),
                'pmrRecords' => fn ($q) => $q->orderBy('trial_number'),
            ]);
        }

        return view('settings.data-cleanup', [
            'tab' => $tab,
            'branches' => $branches,
            'warehouseOptions' => $warehouseOptions,
            'warehouseRows' => $warehouseRows,
            'piles' => $piles,
            'filters' => [
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'q' => $search,
            ],
        ]);
    }

    /**
     * Delete a warehouse once all of its piles are gone.
     */
    public function destroyWarehouse(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ($warehouse->piles()->exists()) {
            return back()->withErrors([
                'data_cleanup' => "Warehouse '{$warehouse->name}' still contains piles. Delete all of its piles first.",
            ]);
        }

        $name = $warehouse->name;
        $this->cleanup->deleteWarehouse($warehouse);

        return back()->with('status', "Warehouse '{$name}' deleted.");
    }

    /**
     * Delete a pile once its laboratory test data is gone and its GMR
     * never reached the Central Office.
     */
    public function destroyPile(Request $request, Pile $pile): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $blockers = $this->pileDeletionBlockers($pile);
        if ($blockers !== []) {
            return back()->withErrors(['data_cleanup' => implode(' ', $blockers)]);
        }

        $label = $pile->pile_number ?? $pile->number;
        $this->cleanup->deletePile($pile);

        return back()->with('status', "Pile {$label} and all of its data deleted.");
    }

    /**
     * Delete every AMR laboratory trial of a pile.
     */
    public function destroyAmrData(Request $request, Pile $pile): RedirectResponse
    {
        return $this->destroyTrialSet($request, $pile, 'amr');
    }

    /**
     * Delete every PMR laboratory trial of a pile.
     */
    public function destroyPmrData(Request $request, Pile $pile): RedirectResponse
    {
        return $this->destroyTrialSet($request, $pile, 'pmr');
    }

    /**
     * Delete a single AMR/PMR laboratory trial (admin bypass of the normal
     * 24-hour / lock restrictions, but never for Central-Office piles).
     */
    public function destroyTrial(Request $request, string $formType, int $record): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if (! in_array($formType, ['amr', 'pmr'], true)) {
            abort(404);
        }

        $model = $formType === 'amr' ? AmrRecord::class : PmrRecord::class;
        $trial = $model::findOrFail($record);
        $pile = $trial->pile;

        if ($pile === null) {
            return back()->withErrors([
                'data_cleanup' => 'This trial is not linked to a pile and cannot be cleaned up here.',
            ]);
        }

        if ($frozen = $this->frozenReason($pile)) {
            return back()->withErrors(['data_cleanup' => $frozen]);
        }

        if ($formType === 'amr') {
            $this->cleanup->deleteAmrTrial($trial);
        } else {
            $this->cleanup->deletePmrTrial($trial);
        }

        return back()->with('status', strtoupper($formType)." trial {$trial->trial_number} for pile ".($pile->pile_number ?? $pile->number).' deleted.');
    }

    /**
     * Shared guards for a bulk AMR/PMR deletion of one pile.
     */
    private function destroyTrialSet(Request $request, Pile $pile, string $formType): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ($frozen = $this->frozenReason($pile)) {
            return back()->withErrors(['data_cleanup' => $frozen]);
        }

        $hasTrials = $formType === 'amr'
            ? $pile->amrRecords()->exists()
            : $pile->pmrRecords()->exists();

        if (! $hasTrials) {
            return back()->withErrors([
                'data_cleanup' => 'Pile '.($pile->pile_number ?? $pile->number).' has no '.strtoupper($formType).' trials to delete.',
            ]);
        }

        if ($formType === 'amr') {
            $this->cleanup->deleteAllAmrTrials($pile);
        } else {
            $this->cleanup->deleteAllPmrTrials($pile);
        }

        return back()->with(
            'status',
            'All '.strtoupper($formType).' trials for pile '.($pile->pile_number ?? $pile->number).' deleted.',
        );
    }

    /**
     * Reasons why a pile cannot be deleted yet (empty array = deletable).
     *
     * @return list<string>
     */
    public function pileDeletionBlockers(Pile $pile): array
    {
        $blockers = [];

        if ($frozen = $this->frozenReason($pile)) {
            $blockers[] = $frozen;
        }

        $amrCount = $pile->getAttribute('amr_records_count') ?? $pile->amrRecords()->count();
        $pmrCount = $pile->getAttribute('pmr_records_count') ?? $pile->pmrRecords()->count();
        $millingCount = $pile->getAttribute('millings_count') ?? $pile->millings()->count();

        if ($amrCount > 0) {
            $blockers[] = "It still has {$amrCount} AMR trial(s) — delete them first.";
        }
        if ($pmrCount > 0) {
            $blockers[] = "It still has {$pmrCount} PMR trial(s) — delete them first.";
        }
        if ($millingCount > 0) {
            $blockers[] = 'It has rice-milling assignments.';
        }

        return $blockers;
    }

    /**
     * Why the laboratory data of a pile is frozen (null = not frozen).
     */
    public function frozenReason(Pile $pile): ?string
    {
        return match ($pile->gmr_status) {
            'submitted' => 'Its GMR is submitted to the Central Office, so its data is frozen.',
            'approved' => 'Its GMR is approved by the Central Office, so its data is frozen.',
            default => null,
        };
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->hasRole('ADMINISTRATOR'),
            403,
            'Only administrators can manage data cleanup.',
        );
    }
}
