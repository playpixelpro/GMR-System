<?php

namespace App\Http\Controllers;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\Warehouse;
use App\Services\EmrGmrGateService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class GmrSummaryController extends Controller
{
    public function __construct(
        protected EmrGmrGateService $gateService,
    ) {}

    /**
     * Display the GMR summary dashboard.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $isStaff = $user && $user->hasRole('STAFF') && $user->branch_id;

        $filters = [
            'branch_id' => $isStaff ? (int) $user->branch_id : ($request->integer('branch_id') ?: null),
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
        ];
        $rows = $this->rows($filters);
        $warehouses = Warehouse::query()
            ->when(
                $filters['branch_id'],
                fn ($query, $branchId) => $query->where(
                    'branch_id',
                    $branchId,
                ),
            )
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name']);

        return view('reports.gmr-summary', [
            'branches' => Branch::query()
                ->when($isStaff, fn ($query) => $query->whereKey($user->branch_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'warehouses' => $warehouses,
            'filters' => $filters,
            'rows' => $rows,
            'summary' => $this->summary($rows),
            'total_warehouses' => $warehouses->count(),
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
                'amrRecords',
                'pmrRecords',
            ])
            ->where(function ($query): void {
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
            ->get()
            ->map(fn (Pile $pile): array => $this->mapPile($pile))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPile(Pile $pile): array
    {
        $gate = $this->gateService->evaluateGate($pile);

        if (! $gate['can_compute']) {
            return [
                'id' => $pile->id,
                'branch_id' => $pile->branch_id ?? $pile->warehouse?->branch_id,
                'branch' => $pile->branch?->name ??
                    ($pile->warehouse?->branch?->name ?? '—'),
                'warehouse' => $pile->warehouse?->name ?? '—',
                'pile' => $pile->pile_number ?? ($pile->number ?? '—'),
                'variety' => $pile->variety ?? '—',
                'age' => $pile->aged_months,
                'volume' => $pile->volume_kg,
                'volume_bags' => $pile->volume_kg !== null
                        ? round((float) $pile->volume_kg / 50, 3)
                        : null,
                'quality' => ! empty($pile->quality) ? strtoupper($pile->quality) : 'GQA',
                'amr' => null,
                'pmr' => null,
                'emr' => 'N/A',
                'gmr' => null,
                'status' => $gate['status'],
                'can_compute' => false,
                'gate_message' => $gate['gate_message'],
                'review_reasons' => [$gate['gate_message']],
            ];
        }

        $amr = $gate['amr_rate'];
        $pmr = $gate['pmr_rate'];
        $gmr = $gate['gmr'];
        $emr = $gate['emr_display'];

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
            $gmr <= 60
                ? 'Re-establish'
                : ($reviewReasons === []
                    ? 'Validated'
                    : 'Review');

        return [
            'id' => $pile->id,
            'branch_id' => $pile->branch_id ?? $pile->warehouse?->branch_id,
            'branch' => $pile->branch?->name ??
                ($pile->warehouse?->branch?->name ?? '—'),
            'warehouse' => $pile->warehouse?->name ?? '—',
            'pile' => $pile->pile_number ?? ($pile->number ?? '—'),
            'variety' => $pile->variety ?? '—',
            'age' => $pile->aged_months,
            'volume' => $pile->volume_kg,
            'volume_bags' => $pile->volume_kg !== null
                    ? round((float) $pile->volume_kg / 50, 3)
                    : null,
            'quality' => ! empty($pile->quality) ? strtoupper($pile->quality) : 'GQA',
            'amr' => $amr,
            'pmr' => $pmr,
            'emr' => $emr,
            'gmr' => $gmr,
            'status' => $status,
            'can_compute' => true,
            'gate_message' => $gate['gate_message'],
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
            ->filter(fn ($record): bool => (float) $record->palay_input_kg > 0 || $record->milling_recovery !== null)
            ->map(
                fn ($record): float => $recordClass === AmrRecord::class
                    ? (float) $record->milling_recovery_percentage
                    : (float) $record->recovery_rate_percentage,
            )
            ->filter(fn ($rate): bool => $rate > 0);

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

        $amrValues = $rows->pluck('amr')->filter(fn ($value) => $value !== null);
        $pmrValues = $rows->pluck('pmr')->filter(fn ($value) => $value !== null);
        $gmrValues = $rows->pluck('gmr')->filter(fn ($value) => $value !== null);

        $warehouseCount = $rows->pluck('warehouse')->unique()->filter(fn ($w): bool => ! empty($w) && $w !== '—')->count();

        return [
            'warehouses' => $warehouseCount,
            'total_warehouses' => $warehouseCount,
            'piles' => $rows->count(),
            'total_piles' => $rows->count(),
            'volume_bags' => $rows->sum(
                fn (array $row): float => (float) ($row['volume_bags'] ?? 0),
            ),
            'total_volume_bags' => $rows->sum(
                fn (array $row): float => (float) ($row['volume_bags'] ?? 0),
            ),
            'pmr' => $average('pmr'),
            'average_pmr' => $pmrValues->isNotEmpty()
                ? round((float) $pmrValues->avg(), 2)
                : null,
            'amr' => $average('amr'),
            'average_amr' => $amrValues->isNotEmpty()
                ? round((float) $amrValues->avg(), 2)
                : null,
            'gmr' => $average('gmr'),
            'average_gmr' => $gmrValues->isNotEmpty()
                ? round((float) $gmrValues->avg(), 2)
                : null,
            'emr_lower' => $completeRows->min('amr'),
            'emr_upper' => $completeRows->max('pmr'),
            'emr_min' => $completeRows->min('amr'),
            'emr_max' => $completeRows->max('pmr'),
        ];
    }
}
