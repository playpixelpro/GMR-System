<?php

namespace App\Http\Controllers;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrCalculation;
use App\Models\PmrRecord;
use App\Models\Warehouse;
use App\Services\AmrCalculationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AmrRecordController extends Controller
{
    public function __construct(
        protected AmrCalculationService $calculationService,
    ) {}

    /**
     * Display the AMR report.
     */
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $filterWarehouseNames = Warehouse::query()
            ->when(
                $filters['branch_id'],
                fn ($query, $branchId) => $query->where('branch_id', $branchId),
            )
            ->when(
                $filters['warehouse_id'],
                fn ($query, $warehouseId) => $query->whereKey($warehouseId),
            )
            ->pluck('name');
        $hasFilters =
            $filters['branch_id'] !== null || $filters['warehouse_id'] !== null;

        // 1. Fetch all master piles that have AMR records, PMR records, or shared pile details
        $piles = Pile::query()
            ->with([
                'warehouse.branch',
                'amrRecords' => fn ($query) => $query->orderBy('trial_number'),
                'amrCalculation',
                'pmrCalculation',
                'pmrRecords' => fn ($query) => $query->orderBy('trial_number'),
            ])
            ->where(function ($query) {
                $query
                    ->whereHas('amrRecords')
                    ->orWhereHas('pmrRecords')
                    ->orWhereNotNull('variety');
            })
            ->when($filters['branch_id'], function ($query, $branchId): void {
                $query->where(function ($branchQuery) use ($branchId): void {
                    $branchQuery
                        ->where('branch_id', $branchId)
                        ->orWhereHas(
                            'warehouse',
                            fn ($warehouseQuery) => $warehouseQuery->where(
                                'branch_id',
                                $branchId,
                            ),
                        );
                });
            })
            ->when(
                $filters['warehouse_id'],
                fn ($query, $warehouseId) => $query->where(
                    'warehouse_id',
                    $warehouseId,
                ),
            )
            ->orderBy('branch_id')
            ->orderBy('warehouse_id')
            ->orderBy('pile_number')
            ->orderBy('number')
            ->get();

        $groups = collect();

        foreach ($piles as $pile) {
            $records = $pile->amrRecords;
            $hasRecords = $records->isNotEmpty();

            if ($hasRecords) {
                $calc = $this->calculationService->calculateForGroup(
                    $records,
                    'pile:'.$pile->id,
                );
                $validRecoveries = $records
                    ->filter(
                        fn ($r): bool => $r->included_in_computation &&
                            $r->status === 'RECOMMENDED',
                    )
                    ->filter(fn ($r) => (float) $r->palay_input_kg > 0)
                    ->map(fn ($r) => (float) $r->milling_recovery_percentage);
                $mean = $validRecoveries->isNotEmpty()
                    ? $validRecoveries->avg()
                    : null;
            } else {
                $calc = $this->calculationService->calculate([]);
                $mean = null;
            }

            // Determine PMR rate
            $pmrRate = null;
            if ($pile->pmrCalculation?->pmr_rate !== null) {
                $pmrRate = (float) $pile->pmrCalculation->pmr_rate;
            } elseif ($pile->pmrRecords->isNotEmpty()) {
                $validPmr = $pile->pmrRecords
                    ->filter(
                        fn (
                            PmrRecord $record,
                        ): bool => $record->included_in_computation &&
                            $record->status === 'RECOMMENDED',
                    )
                    ->filter(fn (PmrRecord $r) => (float) $r->palay_input_kg > 0)
                    ->map(
                        fn (
                            PmrRecord $r,
                        ) => (float) $r->recovery_rate_percentage,
                    );
                $pmrRate = $validPmr->isNotEmpty()
                    ? (float) $validPmr->avg()
                    : null;
            }

            $firstRecord = $records->first() ?? $pile->pmrRecords->first();

            $groups->push([
                'pile' => $pile,
                'records' => $records,
                'calculation' => $calc,
                'mean' => $mean,
                'pmr_rate' => $pmrRate,
                'branch_name' => $pile->warehouse?->branch?->name ?? '—',
                'warehouse_name' => $pile->warehouse?->name ??
                    ($firstRecord?->warehouse_name ?? '—'),
                'pile_number' => $pile->pile_number ??
                    ($pile->number ?? ($firstRecord?->pile_number ?? '—')),
                'variety' => $pile->variety ?? ($firstRecord?->variety ?? '—'),
                'purity' => $pile->purity ?? $firstRecord?->purity,
                'mc' => $pile->mc ?? $firstRecord?->mc,
                'quality' => $pile->quality ?? ($firstRecord?->quality ?? ''),
                'aged_months' => $pile->aged_months ?? ($firstRecord?->aged_months ?? 0),
                'volume_kg' => $pile->volume_kg ?? ($firstRecord?->volume_kg ?? 0),
                'rice_millers' => $records->first()?->rice_millers ?? '—',
            ]);
        }

        // 2. Fetch standalone AMR records without pile_id
        $standaloneRecords = AmrRecord::query()
            ->whereNull('pile_id')
            ->when(
                $hasFilters,
                fn ($query) => $query->whereIn(
                    'warehouse_name',
                    $filterWarehouseNames,
                ),
            )
            ->orderBy('warehouse_name')
            ->orderBy('pile_number')
            ->orderBy('trial_number')
            ->get()
            ->groupBy(
                fn (AmrRecord $record): string => implode('|', [
                    $record->warehouse_name,
                    $record->pile_number,
                    $record->variety,
                    $record->aged_months,
                    $record->volume_kg,
                    $record->rice_millers,
                ]),
            );

        foreach ($standaloneRecords as $key => $standaloneGroup) {
            $first = $standaloneGroup->first();
            $calc = $this->calculationService->calculateForGroup(
                $standaloneGroup,
                $key,
            );
            $validRecoveries = $standaloneGroup
                ->filter(fn ($r) => (float) $r->palay_input_kg > 0)
                ->map(fn ($r) => (float) $r->milling_recovery_percentage);
            $mean = $validRecoveries->isNotEmpty()
                ? $validRecoveries->avg()
                : null;

            $pmrRate = null;
            if ($first) {
                $matchingPmr = PmrCalculation::query()
                    ->whereHas('pile', function ($query) use ($first) {
                        $query
                            ->where('number', $first->pile_number)
                            ->whereHas(
                                'warehouse',
                                fn ($w) => $w->where(
                                    'name',
                                    $first->warehouse_name,
                                ),
                            );
                    })
                    ->latest('calculated_at')
                    ->first();

                if ($matchingPmr?->pmr_rate !== null) {
                    $pmrRate = (float) $matchingPmr->pmr_rate;
                } else {
                    $matchingRecords = PmrRecord::query()
                        ->where('warehouse_name', $first->warehouse_name)
                        ->where('pile_number', $first->pile_number)
                        ->get();

                    if ($matchingRecords->isNotEmpty()) {
                        $validRecoveriesPmr = $matchingRecords
                            ->filter(
                                fn (PmrRecord $r) => (float) $r->palay_input_kg >
                                    0,
                            )
                            ->map(
                                fn (
                                    PmrRecord $r,
                                ) => (float) $r->recovery_rate_percentage,
                            );

                        $pmrRate = $validRecoveriesPmr->isNotEmpty()
                            ? (float) $validRecoveriesPmr->avg()
                            : null;
                    }
                }
            }

            $groups->push([
                'pile' => null,
                'records' => $standaloneGroup,
                'calculation' => $calc,
                'mean' => $mean,
                'pmr_rate' => $pmrRate,
                'branch_name' => '—',
                'warehouse_name' => $first?->warehouse_name ?? '—',
                'pile_number' => $first?->pile_number ?? '—',
                'variety' => $first?->variety ?? '—',
                'purity' => $first?->purity,
                'mc' => $first?->mc,
                'quality' => $first?->quality ?? '',
                'aged_months' => $first?->aged_months ?? 0,
                'volume_kg' => $first?->volume_kg ?? 0,
                'rice_millers' => $first?->rice_millers ?? '—',
            ]);
        }

        return view('reports.amr', [
            'branches' => Branch::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'warehouses' => Warehouse::query()
                ->when(
                    $filters['branch_id'],
                    fn ($query, $branchId) => $query->where(
                        'branch_id',
                        $branchId,
                    ),
                )
                ->orderBy('name')
                ->get(['id', 'branch_id', 'name']),
            'filters' => $filters,
            'recordGroups' => $groups,
        ]);
    }

    /**
     * @return array{branch_id: ?int, warehouse_id: ?int}
     */
    private function filters(Request $request): array
    {
        return [
            'branch_id' => $request->integer('branch_id') ?: null,
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
        ];
    }
}
