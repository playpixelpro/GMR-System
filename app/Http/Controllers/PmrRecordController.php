<?php

namespace App\Http\Controllers;

use App\Models\AmrCalculation;
use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\Warehouse;
use App\Services\PmrCalculationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PmrRecordController extends Controller
{
    public function __construct(
        protected PmrCalculationService $calculationService,
    ) {}

    /**
     * Display the PMR report.
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

        // 1. Fetch all master piles that have PMR records, AMR records, or shared pile details
        $piles = Pile::query()
            ->with([
                'warehouse.branch',
                'pmrRecords' => fn ($query) => $query->orderBy('trial_number'),
                'pmrCalculation',
                'amrCalculation',
                'amrRecords' => fn ($query) => $query->orderBy('trial_number'),
            ])
            ->where(function ($query) {
                $query
                    ->whereHas('pmrRecords')
                    ->orWhereHas('amrRecords')
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
            $records = $pile->pmrRecords;
            $hasRecords = $records->isNotEmpty();

            if ($hasRecords) {
                $calc = $this->calculationService->calculateForGroup(
                    $records,
                    'pile:'.$pile->id,
                );
                $rates = $records
                    ->filter(
                        fn (
                            PmrRecord $record,
                        ): bool => $record->included_in_computation &&
                            $record->status === 'RECOMMENDED',
                    )
                    ->map(fn (PmrRecord $r) => $r->recovery_rate_percentage)
                    ->values();
                $mean = $rates->avg() ?? 0.0;
                $stdDev = $this->sampleStandardDeviation($rates->all(), $mean);
            } else {
                $calc = $this->calculationService->calculate([]);
                $mean = null;
                $stdDev = null;
            }

            // Determine AMR rate for re-establishment comparison
            $amrRate = null;
            if ($pile->amrCalculation?->amr_rate !== null) {
                $amrRate = (float) $pile->amrCalculation->amr_rate;
            } elseif ($pile->amrRecords->isNotEmpty()) {
                $validAmrRecoveries = $pile->amrRecords
                    ->filter(
                        fn (
                            AmrRecord $record,
                        ): bool => $record->included_in_computation &&
                            $record->status === 'RECOMMENDED',
                    )
                    ->filter(fn (AmrRecord $r) => (float) $r->palay_input_kg > 0)
                    ->map(
                        fn (
                            AmrRecord $r,
                        ) => (float) $r->milling_recovery_percentage,
                    );

                $amrRate = $validAmrRecoveries->isNotEmpty()
                    ? (float) $validAmrRecoveries->avg()
                    : null;
            }

            $firstRecord = $records->first() ?? $pile->amrRecords->first();

            $groups->push([
                'pile' => $pile,
                'records' => $records,
                'calculation' => $calc,
                'mean' => $mean,
                'standard_deviation' => $stdDev,
                'amr_rate' => $amrRate,
                'branch_name' => $pile->warehouse?->branch?->name ?? 'â€”',
                'warehouse_name' => $pile->warehouse?->name ??
                    ($firstRecord?->warehouse_name ?? 'â€”'),
                'pile_number' => $pile->pile_number ??
                    ($pile->number ?? ($firstRecord?->pile_number ?? 'â€”')),
                'variety' => $pile->variety ?? ($firstRecord?->variety ?? 'â€”'),
                'purity' => $pile->purity ?? ($firstRecord?->purity ?? 0),
                'mc' => $pile->mc ?? ($firstRecord?->mc ?? 0),
                'quality' => $pile->quality ?? ($firstRecord?->quality ?? ''),
                'aged_months' => $pile->aged_months ?? ($firstRecord?->aged_months ?? 0),
                'volume_kg' => $pile->volume_kg ?? ($firstRecord?->volume_kg ?? 0),
            ]);
        }

        // 2. Also fetch any standalone PMR records without a pile_id (for historical legacy data)
        $standaloneRecords = PmrRecord::query()
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
                fn (PmrRecord $record): string => implode('|', [
                    $record->warehouse_name,
                    $record->pile_number,
                    $record->variety,
                    $record->purity,
                    $record->mc,
                    $record->quality,
                    $record->aged_months,
                    $record->volume_kg,
                ]),
            );

        foreach ($standaloneRecords as $key => $standaloneGroup) {
            $calc = $this->calculationService->calculateForGroup(
                $standaloneGroup,
                $key,
            );
            $rates = $standaloneGroup
                ->map(fn (PmrRecord $r) => $r->recovery_rate_percentage)
                ->values();
            $mean = $rates->avg() ?? 0.0;
            $stdDev = $this->sampleStandardDeviation($rates->all(), $mean);
            $firstRecord = $standaloneGroup->first();

            $amrRate = null;
            if ($firstRecord) {
                $matchingAmr = AmrCalculation::query()
                    ->whereHas('pile', function ($query) use ($firstRecord) {
                        $query
                            ->where('number', $firstRecord->pile_number)
                            ->whereHas(
                                'warehouse',
                                fn ($w) => $w->where(
                                    'name',
                                    $firstRecord->warehouse_name,
                                ),
                            );
                    })
                    ->latest('calculated_at')
                    ->first();

                if ($matchingAmr?->amr_rate !== null) {
                    $amrRate = (float) $matchingAmr->amr_rate;
                } else {
                    $matchingRecords = AmrRecord::query()
                        ->where('warehouse_name', $firstRecord->warehouse_name)
                        ->where('pile_number', $firstRecord->pile_number)
                        ->get();

                    if ($matchingRecords->isNotEmpty()) {
                        $validRecoveries = $matchingRecords
                            ->filter(
                                fn (AmrRecord $r) => (float) $r->palay_input_kg >
                                    0,
                            )
                            ->map(
                                fn (
                                    AmrRecord $r,
                                ) => (float) $r->milling_recovery_percentage,
                            );

                        $amrRate = $validRecoveries->isNotEmpty()
                            ? (float) $validRecoveries->avg()
                            : null;
                    }
                }
            }

            $groups->push([
                'pile' => null,
                'records' => $standaloneGroup,
                'calculation' => $calc,
                'mean' => $mean,
                'standard_deviation' => $stdDev,
                'amr_rate' => $amrRate,
                'branch_name' => 'â€”',
                'warehouse_name' => $firstRecord?->warehouse_name ?? 'â€”',
                'pile_number' => $firstRecord?->pile_number ?? 'â€”',
                'variety' => $firstRecord?->variety ?? 'â€”',
                'purity' => $firstRecord?->purity ?? 0,
                'mc' => $firstRecord?->mc ?? 0,
                'quality' => $firstRecord?->quality ?? '',
                'aged_months' => $firstRecord?->aged_months ?? 0,
                'volume_kg' => $firstRecord?->volume_kg ?? 0,
            ]);
        }

        return view('reports.pmr', [
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

    /**
     * Calculate sample standard deviation for a group of trial rates (fallback helper).
     *
     * @param  list<float>  $values
     */
    private function sampleStandardDeviation(array $values, float $mean): float
    {
        if (count($values) < 2) {
            return 0.0;
        }

        $sumOfSquares = array_sum(
            array_map(
                fn (float $value): float => ($value - $mean) ** 2,
                $values,
            ),
        );

        return sqrt($sumOfSquares / (count($values) - 1));
    }
}
