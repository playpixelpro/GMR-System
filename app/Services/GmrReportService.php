<?php

namespace App\Services;

use App\Models\AmrRecord;
use App\Models\GmrReportConfiguration;
use App\Models\GmrReportSignatory;
use App\Models\Pile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GmrReportService
{
    public function __construct(
        protected ?EmrGmrGateService $gateService = null,
    ) {
        $this->gateService = $gateService ?? app(EmrGmrGateService::class);
    }

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
                'amrRecords',
                'pmrRecords',
            ])
            ->whereIn('id', $pileIds)
            ->orderBy('branch_id')
            ->orderBy('warehouse_id')
            ->orderBy('pile_number')
            ->orderBy('number')
            ->get();

        return $piles
            ->map(fn (Pile $pile): array => $this->mapPileToRow($pile))
            ->values();
    }

    /**
     * Map a pile model to report row data.
     *
     * @return array<string, mixed>
     */
    public function mapPileToRow(Pile $pile): array
    {
        $gate = $this->gateService->evaluateGate($pile);

        $amr = $gate['can_compute'] ? $gate['amr_rate'] : null;
        $pmr = $gate['can_compute'] ? $gate['pmr_rate'] : null;
        $gmr = $gate['can_compute'] ? $gate['gmr'] : null;
        $emr = $gate['can_compute']
            ? ($amr !== null && $pmr !== null
                ? number_format((float) $amr, 2).
                    ' – '.
                    number_format((float) $pmr, 2)
                : str_replace('%', '', (string) $gate['emr_display']))
            : '—';

        return [
            'id' => $pile->id,
            'branch' => $pile->branch?->name ??
                ($pile->warehouse?->branch?->name ?? '—'),
            'warehouse' => $pile->warehouse?->name ?? '—',
            'pile' => $pile->pile_number ?? ($pile->number ?? '—'),
            'volume_bags' => $pile->volume_kg !== null
                    ? round((float) $pile->volume_kg / 50, 3)
                    : null,
            'quality' => ! empty($pile->quality)
                ? strtoupper($pile->quality)
                : 'GQA',
            'amr' => $amr,
            'pmr' => $pmr,
            'emr' => $emr,
            'gmr' => $gmr,
            'status' => $gate['status'],
            'can_compute' => $gate['can_compute'],
        ];
    }

    /**
     * Calculate rate from calculation model or individual records.
     *
     * @param  Collection<int, mixed>|EloquentCollection  $records
     */
    private function calculateRate(
        mixed $calculatedRate,
        mixed $records,
        string $recordClass,
    ): ?float {
        if ($calculatedRate !== null) {
            return (float) $calculatedRate;
        }

        $validRates = collect($records)
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
                'gmr' => 62.0,
            ],
            [
                'warehouse' => 'GID#2, MLANG BS',
                'pile' => '2',
                'volume_bags' => 12259,
                'quality' => 'GQA',
                'amr' => 62.66,
                'pmr' => 62.77,
                'emr' => '62.66-62.77',
                'gmr' => 62.7,
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
                'gmr' => 64.6,
            ],
            [
                'warehouse' => 'GID#2, MLANG BS',
                'pile' => '5',
                'volume_bags' => 9438,
                'quality' => 'GQA',
                'amr' => 62.91,
                'pmr' => 63.14,
                'emr' => '62.91-63.14',
                'gmr' => 63.0,
            ],
            [
                'warehouse' => 'GID#4, MLANG BS',
                'pile' => '1',
                'volume_bags' => 7517,
                'quality' => 'GQA',
                'amr' => 63.25,
                'pmr' => 63.64,
                'emr' => '63.25-63.64',
                'gmr' => 63.4,
            ],
            [
                'warehouse' => 'GID#4, MLANG BS',
                'pile' => '2',
                'volume_bags' => 3165,
                'quality' => 'GQA',
                'amr' => 62.92,
                'pmr' => 63.29,
                'emr' => '62.92-63.29',
                'gmr' => 63.0,
            ],
            [
                'warehouse' => 'GID#4, MLANG BS',
                'pile' => '9',
                'volume_bags' => 2165,
                'quality' => 'GQA',
                'amr' => 62.43,
                'pmr' => 63.66,
                'emr' => '62.43-63.66',
                'gmr' => 63.0,
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
        ?string $branchName = null,
    ): Response {
        $pdf = Pdf::loadView('reports.gmr-print-pdf', [
            'rows' => $rows,
            'config' => $config,
            'signatories' => $signatories,
            'branchName' => $branchName,
            'isPdf' => true,
        ]);
        $pdf->setOption('isPhpEnabled', true);

        $bounds = $config->getPaperBounds();
        $pdf->setPaper(
            [0, 0, $bounds[2], $bounds[3]],
            strtolower($config->orientation),
        );

        return $pdf->download('GMR_Report_'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Generate and stream an Excel (.xlsx) file for the GMR report.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function exportExcel(
        Collection $rows,
        GmrReportConfiguration $config,
        Collection $signatories,
        ?string $branchName = null,
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('GMR Report');

        // Document header
        $sheet->setCellValue(
            'A1',
            $config->title ?? 'REPORT ON PRE-MILLING ACTIVITY',
        );
        $sheet->setCellValue(
            'A2',
            $config->subtitle ?? 'QUALITY AND QUANTITY, AMR, PMR AND EMR/GMR',
        );
        $sheet->setCellValue('A3', $config->region_text ?? 'Region XII');
        $sheet->setCellValue(
            'A4',
            $config->branch_text ?: $branchName ?? 'North Cotabato Branch',
        );

        foreach (range(1, 4) as $hRow) {
            $sheet->mergeCells("A{$hRow}:H{$hRow}");
            $sheet
                ->getStyle("A{$hRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet
            ->getStyle('A1')
            ->getFont()
            ->setBold(true)
            ->setSize(12)
            ->setColor(new Color('064E3B'));
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(10.5);
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(10);

        // Table headers on row 6
        $headers = [
            'A' => 'Warehouse',
            'B' => 'Pile No.',
            'C' => 'Volume in Bags',
            'D' => 'Quality',
            'E' => 'PMR(%)',
            'F' => 'AMR(%)',
            'G' => 'EMR(%)',
            'H' => 'GMR(%)',
        ];
        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}6", $label);
        }

        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '064E3B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '064E3B'],
                ],
            ],
        ];
        $sheet->getStyle('A6:H6')->applyFromArray($headerStyle);
        $sheet->getRowDimension(6)->setRowHeight(24);

        // Data rows starting at row 7
        $row = 7;
        foreach ($rows as $item) {
            $sheet->setCellValue("A{$row}", $item['warehouse'] ?? '—');
            $sheet->setCellValue("B{$row}", $item['pile'] ?? '—');

            if ($item['volume_bags'] !== null) {
                $sheet->setCellValue("C{$row}", (float) $item['volume_bags']);
                $sheet
                    ->getStyle("C{$row}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
            } else {
                $sheet->setCellValue("C{$row}", '—');
            }

            $sheet->setCellValue("D{$row}", $item['quality'] ?? 'GQA');

            if ($item['pmr'] !== null) {
                $sheet->setCellValue("E{$row}", (float) $item['pmr']);
                $sheet
                    ->getStyle("E{$row}")
                    ->getNumberFormat()
                    ->setFormatCode('0.00');
            } else {
                $sheet->setCellValue("E{$row}", '—');
            }

            if ($item['amr'] !== null) {
                $sheet->setCellValue("F{$row}", (float) $item['amr']);
                $sheet
                    ->getStyle("F{$row}")
                    ->getNumberFormat()
                    ->setFormatCode('0.00');
            } else {
                $sheet->setCellValue("F{$row}", '—');
            }

            $sheet->setCellValue(
                "G{$row}",
                str_replace('%', '', (string) ($item['emr'] ?? '—')),
            );

            if ($item['gmr'] !== null) {
                $sheet->setCellValue("H{$row}", (float) $item['gmr']);
                $sheet
                    ->getStyle("H{$row}")
                    ->getNumberFormat()
                    ->setFormatCode('0.00');
            } else {
                $sheet->setCellValue("H{$row}", '—');
            }

            $sheet
                ->getStyle("A{$row}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet
                ->getStyle("B{$row}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet
                ->getStyle("C{$row}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet
                ->getStyle("D{$row}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet
                ->getStyle("E{$row}:F{$row}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet
                ->getStyle("G{$row}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet
                ->getStyle("H{$row}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet
                ->getStyle("A{$row}")
                ->getFont()
                ->setBold(true);
            $sheet
                ->getStyle("B{$row}")
                ->getFont()
                ->setBold(true);
            $sheet
                ->getStyle("D{$row}")
                ->getFont()
                ->setBold(true);
            $sheet
                ->getStyle("G{$row}")
                ->getFont()
                ->setBold(true);
            $sheet
                ->getStyle("H{$row}")
                ->getFont()
                ->setBold(true);

            $sheet
                ->getStyle("A{$row}:H{$row}")
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()
                ->setRGB('E5E7EB');
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;
        }

        // Bottom border for table
        $lastDataRow = $row - 1;
        if ($lastDataRow >= 7) {
            $sheet
                ->getStyle("A{$lastDataRow}:H{$lastDataRow}")
                ->getBorders()
                ->getBottom()
                ->setBorderStyle(Border::BORDER_MEDIUM)
                ->getColor()
                ->setRGB('064E3B');
        }

        // Signatories Section
        $currRow = $row + 2;
        $sheet->setCellValue("A{$currRow}", 'Prepared and Recommended By:');
        $sheet->mergeCells("A{$currRow}:H{$currRow}");
        $sheet
            ->getStyle("A{$currRow}")
            ->getFont()
            ->setBold(true)
            ->setSize(10);
        $sheet
            ->getStyle("A{$currRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $currRow++;

        $sheet->setCellValue(
            "A{$currRow}",
            'Regional Milling Committee (RMEC) Members:',
        );
        $sheet->mergeCells("A{$currRow}:H{$currRow}");
        $sheet
            ->getStyle("A{$currRow}")
            ->getFont()
            ->setBold(true)
            ->setSize(9.5);
        $sheet
            ->getStyle("A{$currRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $currRow += 2;

        $activeList = $signatories ?? collect();
        $members = $activeList
            ->filter(
                fn ($s) => in_array($s->role_group, ['member', 'default', null]),
            )
            ->values();
        $chairpersons = $activeList
            ->filter(fn ($s) => $s->role_group === 'chairperson')
            ->values();
        $coa = $activeList
            ->filter(fn ($s) => $s->role_group === 'coa')
            ->values();
        $reviewers = $activeList
            ->filter(fn ($s) => $s->role_group === 'reviewer')
            ->values();

        for ($i = 0; $i < $members->count(); $i += 2) {
            $m1 = $members[$i] ?? null;
            $m2 = $members[$i + 1] ?? null;

            if ($m1) {
                $sheet->setCellValue("B{$currRow}", strtoupper($m1->name));
                $sheet->mergeCells("B{$currRow}:C{$currRow}");
                $sheet
                    ->getStyle("B{$currRow}:C{$currRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet
                    ->getStyle("B{$currRow}")
                    ->getFont()
                    ->setBold(true)
                    ->setSize(9.5);
                $sheet
                    ->getStyle("B{$currRow}:C{$currRow}")
                    ->getBorders()
                    ->getBottom()
                    ->setBorderStyle(Border::BORDER_THIN);
            }
            if ($m2) {
                $sheet->setCellValue("F{$currRow}", strtoupper($m2->name));
                $sheet->mergeCells("F{$currRow}:G{$currRow}");
                $sheet
                    ->getStyle("F{$currRow}:G{$currRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet
                    ->getStyle("F{$currRow}")
                    ->getFont()
                    ->setBold(true)
                    ->setSize(9.5);
                $sheet
                    ->getStyle("F{$currRow}:G{$currRow}")
                    ->getBorders()
                    ->getBottom()
                    ->setBorderStyle(Border::BORDER_THIN);
            }
            $currRow++;

            if ($m1) {
                $sheet->setCellValue("B{$currRow}", $m1->position);
                $sheet->mergeCells("B{$currRow}:C{$currRow}");
                $sheet
                    ->getStyle("B{$currRow}:C{$currRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet
                    ->getStyle("B{$currRow}")
                    ->getFont()
                    ->setItalic(true)
                    ->setSize(8);
            }
            if ($m2) {
                $sheet->setCellValue("F{$currRow}", $m2->position);
                $sheet->mergeCells("F{$currRow}:G{$currRow}");
                $sheet
                    ->getStyle("F{$currRow}:G{$currRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet
                    ->getStyle("F{$currRow}")
                    ->getFont()
                    ->setItalic(true)
                    ->setSize(8);
            }
            $currRow += 2;
        }

        foreach ($chairpersons as $chair) {
            $sheet->setCellValue("C{$currRow}", strtoupper($chair->name));
            $sheet->mergeCells("C{$currRow}:F{$currRow}");
            $sheet
                ->getStyle("C{$currRow}:F{$currRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet
                ->getStyle("C{$currRow}")
                ->getFont()
                ->setBold(true)
                ->setSize(9.5);
            $sheet
                ->getStyle("C{$currRow}:F{$currRow}")
                ->getBorders()
                ->getBottom()
                ->setBorderStyle(Border::BORDER_THIN);
            $currRow++;

            $sheet->setCellValue("C{$currRow}", $chair->position);
            $sheet->mergeCells("C{$currRow}:F{$currRow}");
            $sheet
                ->getStyle("C{$currRow}:F{$currRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet
                ->getStyle("C{$currRow}")
                ->getFont()
                ->setItalic(true)
                ->setSize(8);
            $currRow += 2;
        }

        foreach ($coa as $coaRep) {
            $sheet->mergeCells("C{$currRow}:F{$currRow}");
            $sheet
                ->getStyle("C{$currRow}:F{$currRow}")
                ->getBorders()
                ->getBottom()
                ->setBorderStyle(Border::BORDER_THIN);
            $currRow++;

            $sheet->setCellValue("C{$currRow}", strtoupper($coaRep->name));
            $sheet->mergeCells("C{$currRow}:F{$currRow}");
            $sheet
                ->getStyle("C{$currRow}:F{$currRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet
                ->getStyle("C{$currRow}")
                ->getFont()
                ->setBold(true)
                ->setSize(9);
            $currRow++;

            if ($coaRep->position) {
                $sheet->setCellValue("C{$currRow}", $coaRep->position);
                $sheet->mergeCells("C{$currRow}:F{$currRow}");
                $sheet
                    ->getStyle("C{$currRow}:F{$currRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet
                    ->getStyle("C{$currRow}")
                    ->getFont()
                    ->setItalic(true)
                    ->setSize(8);
                $currRow++;
            }
            $currRow++;
        }

        if ($reviewers->isNotEmpty()) {
            $sheet->setCellValue("A{$currRow}", 'Reviewed by:');
            $sheet->mergeCells("A{$currRow}:H{$currRow}");
            $sheet
                ->getStyle("A{$currRow}")
                ->getFont()
                ->setBold(true)
                ->setSize(9.5);
            $sheet
                ->getStyle("A{$currRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currRow += 2;

            foreach ($reviewers as $rev) {
                $sheet->setCellValue("C{$currRow}", strtoupper($rev->name));
                $sheet->mergeCells("C{$currRow}:F{$currRow}");
                $sheet
                    ->getStyle("C{$currRow}:F{$currRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet
                    ->getStyle("C{$currRow}")
                    ->getFont()
                    ->setBold(true)
                    ->setSize(9.5);
                $sheet
                    ->getStyle("C{$currRow}:F{$currRow}")
                    ->getBorders()
                    ->getBottom()
                    ->setBorderStyle(Border::BORDER_THIN);
                $currRow++;

                $sheet->setCellValue("C{$currRow}", $rev->position);
                $sheet->mergeCells("C{$currRow}:F{$currRow}");
                $sheet
                    ->getStyle("C{$currRow}:F{$currRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet
                    ->getStyle("C{$currRow}")
                    ->getFont()
                    ->setItalic(true)
                    ->setSize(8);
                $currRow += 2;
            }
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'GMR_Report_'.now()->format('Y-m-d_His').'.xlsx';

        return response()->streamDownload(
            function () use ($writer): void {
                $writer->save('php://output');
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }
}
