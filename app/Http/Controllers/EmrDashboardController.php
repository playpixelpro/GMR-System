<?php

namespace App\Http\Controllers;

use App\Models\AmrRecord;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\Warehouse;
use App\Services\EmrExportService;
use App\Services\EmrGmrGateService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmrDashboardController extends Controller
{
    public function __construct(
        protected EmrExportService $exportService,
        protected EmrGmrGateService $gateService,
    ) {}

    /**
     * Display the Expected Milling Recovery dashboard.
     */
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $rows = $this->rows($filters);
        $paginator = $this->paginate($rows, $request);
        $user = $request->user();
        $isBranchRestricted = (bool) $user?->isBranchRestricted();

        $warehouses = Warehouse::query()
            ->when(
                $filters['branch_id'],
                fn ($query, $branchId) => $query->where('branch_id', $branchId),
            )
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name']);

        return view('reports.emr', [
            'branches' => Branch::query()
                ->when($isBranchRestricted, fn ($query) => $query->whereKey($user->branch_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'warehouses' => $warehouses,
            'filters' => $filters,
            'rows' => $paginator,
            'allRows' => $rows,
            'summary' => $this->summary($rows),
            'total_warehouses' => $warehouses->count(),
        ]);
    }

    /**
     * Export the currently filtered dashboard rows as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $this->rows($filters);
        $this->logEmrExport($request, 'csv');

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
     * Export the currently filtered dashboard rows as Excel.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $this->rows($filters);
        $filterNames = $this->filterNames($filters);
        $this->logEmrExport($request, 'excel');

        return $this->exportService->exportExcel($rows, $filterNames);
    }

    /**
     * Download the currently filtered dashboard rows as PDF.
     */
    public function exportPdf(Request $request): Response
    {
        $filters = $this->filters($request);
        $rows = $this->rows($filters);
        $filterNames = $this->filterNames($filters);
        $this->logEmrExport($request, 'pdf');

        return $this->exportService->exportPdf($rows, $filterNames);
    }

    private function logEmrExport(Request $request, string $format): void
    {
        AuditLog::create([
            'module' => 'emr',
            'action' => 'EMR_EXPORTED',
            'description' => "EMR report exported as {$format}",
            'ip_address' => $request->ip(),
            'metadata' => [
                'format' => $format,
                'branch_id' => $request->integer('branch_id') ?: null,
                'warehouse_id' => $request->integer('warehouse_id') ?: null,
            ],
        ]);
    }

    /**
     * @param  array{branch_id: ?int, warehouse_id: ?int}  $filters
     * @return array{branch: ?string, warehouse: ?string}
     */
    private function filterNames(array $filters): array
    {
        return [
            'branch' => $filters['branch_id'] ? Branch::find($filters['branch_id'])?->name : null,
            'warehouse' => $filters['warehouse_id'] ? Warehouse::find($filters['warehouse_id'])?->name : null,
        ];
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
            $gate = $this->gateService->evaluateGate($pile);

            return [
                'id' => $pile->id,
                'branch_id' => $pile->branch_id ?? $pile->warehouse?->branch_id,
                'warehouse_id' => $pile->warehouse_id,
                'branch' => $pile->branch?->name ?? ($pile->warehouse?->branch?->name ?? '—'),
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
                'amr' => $gate['amr_rate'],
                'pmr' => $gate['pmr_rate'],
                'emr_lower' => $gate['emr_lower'],
                'emr_upper' => $gate['emr_upper'],
                'emr_display' => $gate['emr_display'],
                'status' => $gate['status'],
                'can_compute' => $gate['can_compute'],
                'gate_message' => $gate['gate_message'],
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
            'warehouses' => $rows->pluck('warehouse')->unique()->filter(fn ($w): bool => ! empty($w) && $w !== '—')->count(),
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
            'incomplete' => $rows->filter(fn ($r): bool => ! in_array($r['status'], ['VALID', 'QUESTIONABLE'], true))->count(),
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
        $user = $request->user();
        $isBranchRestricted = (bool) $user?->isBranchRestricted();

        $branchId = $isBranchRestricted ? (int) $user->branch_id : ($request->integer('branch_id') ?: null);
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
