<?php

namespace App\Http\Controllers;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmrDashboardController extends Controller
{
    /**
     * Display the Expected Milling Recovery dashboard.
     */
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $rows = $this->rows($filters);
        $paginator = $this->paginate($rows, $request);
        $warehouses = Warehouse::query()
            ->when(
                $filters['branch_id'],
                fn ($query, $branchId) => $query->where('branch_id', $branchId),
            )
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name']);

        return view('reports.emr', [
            'branches' => Branch::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'warehouses' => $warehouses,
            'filters' => $filters,
            'rows' => $paginator,
            'allRows' => $rows,
            'summary' => $this->summary($rows),
        ]);
    }

    /**
     * Export the currently filtered dashboard rows as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $this->rows($filters);

        return response()->streamDownload(
            function () use ($rows): void {
                $handle = fopen('php://output', 'wb');
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, [
                    'Branch',
                    'Warehouse',
                    'Pile Number',
                    'Variety',
                    'Age',
                    'Volume (net kg)',
                    'Volume (50kg bags)',
                    'Purity',
                    'Quality',
                    'AMR',
                    'PMR',
                    'EMR Display',
                    'Validation Status',
                ]);

                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row['branch'],
                        $row['warehouse'],
                        $row['pile'],
                        $row['variety'],
                        $row['age'],
                        $row['volume'],
                        $row['volume_bags'],
                        $row['purity'],
                        $row['quality'],
                        $row['amr'],
                        $row['pmr'],
                        $row['emr_display'],
                        $row['status'],
                    ]);
                }

                fclose($handle);
            },
            'expected-milling-recovery.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ],
        );
    }

    /**
     * @param  array{branch_id: ?int, warehouse_id: ?int}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(array $filters): Collection
    {
        $piles = Pile::query()
            ->with([
                'branch:id,name',
                'warehouse:id,branch_id,name',
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
            ->when(
                $filters['branch_id'],
                fn ($query, $branchId) => $query->where(function (
                    $branchQuery,
                ) use ($branchId): void {
                    $branchQuery
                        ->where('branch_id', $branchId)
                        ->orWhereHas(
                            'warehouse',
                            fn ($warehouseQuery) => $warehouseQuery->where(
                                'branch_id',
                                $branchId,
                            ),
                        );
                }),
            )
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

        $rows = $piles->map(function (Pile $pile): array {
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
            $hasBothRates = $amr !== null && $pmr !== null;

            return [
                'id' => $pile->id,
                'branch_id' => $pile->branch_id ?? $pile->warehouse?->branch_id,
                'warehouse_id' => $pile->warehouse_id,
                'branch' => $pile->branch?->name ?? '—',
                'warehouse' => $pile->warehouse?->name ?? '—',
                'pile' => $pile->pile_number ?? ($pile->number ?? '—'),
                'variety' => $pile->variety ?? '—',
                'age' => $pile->aged_months,
                'volume' => $pile->volume_kg,
                'volume_bags' => $pile->volume_kg !== null
                        ? (float) $pile->volume_kg / 50
                        : null,
                'purity' => $pile->purity,
                'quality' => $pile->quality ?? '—',
                'amr' => $amr,
                'pmr' => $pmr,
                'emr_lower' => $hasBothRates ? $amr : null,
                'emr_upper' => $hasBothRates ? $pmr : null,
                'emr_display' => $hasBothRates
                    ? $this->range($amr, $pmr)
                    : 'N/A',
                'status' => ! $hasBothRates
                    ? 'INCOMPLETE'
                    : ($pmr >= $amr
                        ? 'VALID'
                        : 'QUESTIONABLE'),
            ];
        });

        return $rows->values();
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
     * @return array<string, int|float|string|null>
     */
    private function summary(Collection $rows): array
    {
        $completeRows = $rows->filter(
            fn (array $row): bool => $row['amr'] !== null &&
                $row['pmr'] !== null,
        );
        $average = static fn (string $key): ?float => $rows
            ->pluck($key)
            ->filter(fn ($value): bool => $value !== null)
            ->avg();

        return [
            'piles' => $rows->count(),
            'volume' => $rows->sum(
                fn (array $row): float => (float) $row['volume'],
            ),
            'volume_bags' => $rows->sum(
                fn (array $row): float => (float) ($row['volume_bags'] ?? 0),
            ),
            'purity' => $average('purity'),
            'amr' => $average('amr'),
            'pmr' => $average('pmr'),
            'emr_lower' => $completeRows->min('amr'),
            'emr_upper' => $completeRows->max('pmr'),
            'valid' => $rows->where('status', 'VALID')->count(),
            'questionable' => $rows->where('status', 'QUESTIONABLE')->count(),
            'incomplete' => $rows->where('status', 'INCOMPLETE')->count(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function paginate(
        Collection $rows,
        Request $request,
    ): LengthAwarePaginator {
        $perPage = 25;
        $page = max(1, $request->integer('page', 1));
        $items = $rows->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $rows->count(),
            $perPage,
            $page,
            ['path' => URL::current(), 'query' => $request->except('page')],
        );
    }

    /**
     * @return array{branch_id: ?int, warehouse_id: ?int}
     */
    private function filters(Request $request): array
    {
        $branchId = $request->integer('branch_id') ?: null;
        $warehouseId = $request->integer('warehouse_id') ?: null;

        if ($warehouseId !== null) {
            $warehouse = Warehouse::query()->find($warehouseId);

            if (
                $warehouse === null ||
                ($branchId !== null && $warehouse->branch_id !== $branchId)
            ) {
                $warehouseId = null;
            }
        }

        return [
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
        ];
    }

    private function range(?float $lower, ?float $upper): string
    {
        return number_format($lower, 2).
            '% – '.
            number_format($upper, 2).
            '%';
    }
}
