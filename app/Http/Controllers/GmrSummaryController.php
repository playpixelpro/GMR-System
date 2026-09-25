<?php

namespace App\Http\Controllers;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class GmrSummaryController extends Controller
{
    /**
     * Display the GMR summary dashboard.
     */
    public function index(Request $request): View
    {
        $filters = [
            'branch_id' => $request->integer('branch_id') ?: null,
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
        ];
        $rows = $this->rows($filters);

        return view('reports.gmr-summary', [
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
            'rows' => $rows,
            'summary' => $this->summary($rows),
        ]);
    }

    /**
     * @param  array{branch_id: ?int, warehouse_id: ?int}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(array $filters): Collection
    {
        return Pile::query()
            ->with([
                'branch:id,name',
                'warehouse:id,branch_id,name',
                'warehouse.branch:id,name',
                'amrCalculation',
                'pmrCalculation',
                'amrRecords:id,pile_id,palay_input_kg,rice_recovery_kg,milling_recovery',
                'pmrRecords:id,pile_id,palay_input_kg,rice_recovery_kg,milling_recovery',
            ])
            ->where(function ($query): void {
                $query
                    ->whereHas('amrRecords')
                    ->orWhereHas('pmrRecords')
                    ->whereNotNull('variety')
                    ->whereHas(
                        'amrRecords',
                        fn ($query) => $query
                            ->where('status', 'RECOMMENDED')
                            ->where('included_in_computation', true),
                    )
                    ->whereHas(
                        'pmrRecords',
                        fn ($query) => $query
                            ->where('status', 'RECOMMENDED')
                            ->where('included_in_computation', true),
                    );
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
            ->get()
            ->map(fn (Pile $pile): array => $this->mapPile($pile))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPile(Pile $pile): array
    {
        $amr = $this->rate(
            $pile->amrCalculation?->amr_rate,
            $pile->amrRecords,
            AmrRecord::class,
        );
        $pmr = $this->rate(
            $pile->pmrCalculation?->pmr_rate,
            $pile->pmrRecords,
            PmrRecord::class,
        );
        $gmr =
            $amr !== null && $pmr !== null ? round(($amr + $pmr) / 2, 2) : null;
        $reviewReasons = [];

        if ($amr !== null && $amr <= 60) {
            $reviewReasons[] = 'AMR is 60% or lower';
        }

        if ($gmr !== null && $gmr <= 60) {
            array_unshift($reviewReasons, 'GMR is 60% or lower');
        }

        if ($amr !== null && $pmr !== null && $pmr < $amr) {
            $reviewReasons[] = 'PMR is below AMR';
        }

        if ($amr !== null && $pmr !== null && $pmr > $amr + 3) {
            $reviewReasons[] = 'PMR is more than 3 points above AMR';
        }

        $status =
            $gmr === null
                ? 'Incomplete'
                : ($gmr <= 60
                    ? 'Re-establish'
                    : ($reviewReasons === []
                        ? 'Validated'
                        : 'Review'));

        return [
            'id' => $pile->id,
            'branch' => $pile->branch?->name ??
                ($pile->warehouse?->branch?->name ?? '—'),
            'warehouse' => $pile->warehouse?->name ?? '—',
            'pile' => $pile->pile_number ?? ($pile->number ?? '—'),
            'volume_bags' => $pile->volume_kg !== null
                    ? (float) $pile->volume_kg / 50
                    : null,
            'amr' => $amr,
            'pmr' => $pmr,
            'gmr' => $gmr,
            'status' => $status,
            'review' => $reviewReasons !== [],
            'review_reasons' => $reviewReasons,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $records
     */
    private function rate(
        mixed $calculatedRate,
        Collection $records,
        string $recordClass,
    ): ?float {
        if ($calculatedRate !== null) {
            return (float) $calculatedRate;
        }

        $validRates = $records
            ->filter(
                fn ($record): bool => $record->status === 'RECOMMENDED' &&
                    $record->included_in_computation,
            )
            ->filter(fn ($record): bool => (float) $record->palay_input_kg > 0)
            ->map(
                fn ($record): float => $recordClass === AmrRecord::class
                    ? (float) $record->milling_recovery_percentage
                    : (float) $record->recovery_rate_percentage,
            );

        return $validRates->isNotEmpty()
            ? round((float) $validRates->avg(), 2)
            : null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summary(Collection $rows): array
    {
        $completeRows = $rows->filter(
            fn (array $row): bool => $row['amr'] !== null &&
                $row['pmr'] !== null,
        );
        $average = static fn (string $key): ?float => $completeRows
            ->pluck($key)
            ->avg();

        return [
            'piles' => $rows->count(),
            'volume_bags' => $rows->sum(
                fn (array $row): float => (float) ($row['volume_bags'] ?? 0),
            ),
            'pmr' => $average('pmr'),
            'amr' => $average('amr'),
            'gmr' => $average('gmr'),
            'emr_lower' => $completeRows->min('amr'),
            'emr_upper' => $completeRows->max('pmr'),
        ];
    }
}
