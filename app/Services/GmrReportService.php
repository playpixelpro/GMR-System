<?php

namespace App\Services;

use App\Models\AmrRecord;
use App\Models\GmrReportConfiguration;
use App\Models\GmrReportSignatory;
use App\Models\Pile;
use App\Models\PmrRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class GmrReportService
{
    /**
     * Get the active report configuration.
     */
    public function getConfiguration(): GmrReportConfiguration
    {
        return GmrReportConfiguration::current();
    }

    /**
     * Get active signatories ordered by display_order.
     */
    public function getActiveSignatories(): Collection
    {
        return GmrReportSignatory::query()->active()->get();
    }

    /**
     * Retrieve and build row data for specific pile IDs.
     *
     * @param  array<int, int|string>  $pileIds
     * @return Collection<int, array<string, mixed>>
     */
    public function getRowsForPiles(array $pileIds): Collection
    {
        if (empty($pileIds)) {
            return collect();
        }

        $piles = Pile::query()
            ->with([
                'branch:id,name',
                'warehouse:id,branch_id,name',
                'warehouse.branch:id,name',
                'amrCalculation',
                'pmrCalculation',
                'amrRecords:id,pile_id,palay_input_kg,rice_recovery_kg,milling_recovery,status,included_in_computation',
                'pmrRecords:id,pile_id,palay_input_kg,rice_recovery_kg,milling_recovery,status,included_in_computation',
            ])
            ->whereIn('id', $pileIds)
            ->orderBy('branch_id')
            ->orderBy('warehouse_id')
            ->orderBy('pile_number')
            ->orderBy('number')
            ->get();

        return $piles->map(fn (Pile $pile): array => $this->mapPileToRow($pile))->values();
    }

    /**
     * Map a pile model to report row data.
     *
     * @return array<string, mixed>
     */
    public function mapPileToRow(Pile $pile): array
    {
        $amr = $this->calculateRate(
            $pile->amrCalculation?->amr_rate,
            $pile->amrRecords,
            AmrRecord::class,
        );
        $pmr = $this->calculateRate(
            $pile->pmrCalculation?->pmr_rate,
            $pile->pmrRecords,
            PmrRecord::class,
        );
        $gmr = ($amr !== null && $pmr !== null) ? round(($amr + $pmr) / 2, 2) : null;

        $emr = ($amr !== null && $pmr !== null)
            ? number_format($amr, 2).'-'.number_format($pmr, 2)
            : 'N/A';

        return [
            'id' => $pile->id,
            'branch' => $pile->branch?->name ?? ($pile->warehouse?->branch?->name ?? '—'),
            'warehouse' => $pile->warehouse?->name ?? '—',
            'pile' => $pile->pile_number ?? ($pile->number ?? '—'),
            'volume_bags' => $pile->volume_kg !== null ? round((float) $pile->volume_kg / 50, 3) : null,
            'quality' => ! empty($pile->quality) ? strtoupper($pile->quality) : 'GQA',
            'amr' => $amr,
            'pmr' => $pmr,
            'emr' => $emr,
            'gmr' => $gmr,
        ];
    }

    /**
     * Calculate rate from calculation model or individual records.
     *
     * @param  Collection<int, mixed>|EloquentCollection  $records
     */
    private function calculateRate(mixed $calculatedRate, mixed $records, string $recordClass): ?float
    {
        if ($calculatedRate !== null) {
            return (float) $calculatedRate;
        }

        $validRates = collect($records)
            ->filter(fn ($record): bool => $record->status === 'RECOMMENDED' && $record->included_in_computation)
            ->map(fn ($record): float => $recordClass === AmrRecord::class
                ? (float) $record->milling_recovery_percentage
                : (float) $record->recovery_rate_percentage)
            ->filter(fn ($rate): bool => $rate > 0);

        return $validRates->isNotEmpty() ? round((float) $validRates->avg(), 2) : null;
    }

    /**
     * Get sample rows matching the reference report for preview when no records exist.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getSampleRows(): Collection
    {
        return collect([
            [
                'warehouse' => 'GID#2, MLANG BS',
                'pile' => '1',
                'volume_bags' => 11522,
                'quality' => 'GQA',
                'amr' => 61.53,
                'pmr' => 62.22,
                'emr' => '61.53-62.22',
                'gmr' => 62.00,
            ],
            [
                'warehouse' => 'GID#2, MLANG BS',
                'pile' => '2',
                'volume_bags' => 12259,
                'quality' => 'GQA',
                'amr' => 62.66,
                'pmr' => 62.77,
                'emr' => '62.66-62.77',
                'gmr' => 62.70,
            ],
            [
                'warehouse' => 'GID#2, MLANG BS',
                'pile' => '3',
                'volume_bags' => 6624,
                'quality' => 'GQA',
                'amr' => 63.56,
                'pmr' => 63.59,
                'emr' => '63.56-63.59',
                'gmr' => 63.56,
            ],
            [
                'warehouse' => 'GID#2, MLANG BS',
                'pile' => '4',
                'volume_bags' => 4871,
                'quality' => 'GQA',
                'amr' => 64.41,
                'pmr' => 64.78,
                'emr' => '64.41-64.78',
                'gmr' => 64.60,
            ],
            [
                'warehouse' => 'GID#2, MLANG BS',
                'pile' => '5',
                'volume_bags' => 9438,
                'quality' => 'GQA',
                'amr' => 62.91,
                'pmr' => 63.14,
                'emr' => '62.91-63.14',
                'gmr' => 63.00,
            ],
            [
                'warehouse' => 'GID#4, MLANG BS',
                'pile' => '1',
                'volume_bags' => 7517,
                'quality' => 'GQA',
                'amr' => 63.25,
                'pmr' => 63.64,
                'emr' => '63.25-63.64',
                'gmr' => 63.40,
            ],
            [
                'warehouse' => 'GID#4, MLANG BS',
                'pile' => '2',
                'volume_bags' => 3165,
                'quality' => 'GQA',
                'amr' => 62.92,
                'pmr' => 63.29,
                'emr' => '62.92-63.29',
                'gmr' => 63.00,
            ],
            [
                'warehouse' => 'GID#4, MLANG BS',
                'pile' => '9',
                'volume_bags' => 2165,
                'quality' => 'GQA',
                'amr' => 62.43,
                'pmr' => 63.66,
                'emr' => '62.43-63.66',
                'gmr' => 63.00,
            ],
        ]);
    }

    /**
     * Generate PDF response using the print view template and active configuration.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function generatePdf(
        Collection $rows,
        GmrReportConfiguration $config,
        Collection $signatories,
        ?string $branchName = null
    ): Response {
        $pdf = Pdf::loadView('reports.gmr-print-pdf', [
            'rows' => $rows,
            'config' => $config,
            'signatories' => $signatories,
            'branchName' => $branchName,
            'isPdf' => true,
        ]);

        $bounds = $config->getPaperBounds();
        $pdf->setPaper([0, 0, $bounds[2], $bounds[3]], strtolower($config->orientation));

        return $pdf->download('GMR_Report_'.now()->format('Y-m-d').'.pdf');
    }
}
