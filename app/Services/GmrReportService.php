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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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

    public function formatBranchName(?string $branchName): string
    {
        $branchName = trim((string) $branchName);

        if ($branchName === '') {
            return 'North Cotabato Branch';
        }

        return str_ends_with(strtolower($branchName), ' branch')
            ? $branchName
            : "{$branchName} Branch";
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
            'volume_before_test_milling_bags' => $pile->volume_kg !== null
                    ? round((float) $pile->volume_kg / 50, 3)
                    : null,
            'volume_after_test_milling_bags' => Pile::calculateVolumeAfterTestMillingBags(
                $pile->volume_kg,
                $pile->test_milling_volume_kg,
            ),
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
                'volume_before_test_milling_bags' => 11522,
                'volume_after_test_milling_bags' => null,
                'quality' => 'GQA',
                'amr' => 61.53,
                'pmr' => 62.22,
                'emr' => '61.53-62.22',
                'gmr' => 62.0,
            ],
            [
                'warehouse' => 'GID#2, MLANG BS',
                'pile' => '2',
                'volume_before_test_milling_bags' => 12259,
                'volume_after_test_milling_bags' => null,
                'quality' => 'GQA',
                'amr' => 62.66,
                'pmr' => 62.77,
                'emr' => '62.66-62.77',
                'gmr' => 62.7,
            ],
            [
                'warehouse' => 'GID#2, MLANG BS',
                'pile' => '3',
                'volume_before_test_milling_bags' => 6624,
                'volume_after_test_milling_bags' => null,
                'quality' => 'GQA',
                'amr' => 63.56,
                'pmr' => 63.59,
                'emr' => '63.56-63.59',
                'gmr' => 63.56,
            ],
            [
                'warehouse' => 'GID#2, MLANG BS',
                'pile' => '4',
                'volume_before_test_milling_bags' => 4871,
                'volume_after_test_milling_bags' => null,
                'quality' => 'GQA',
                'amr' => 64.41,
                'pmr' => 64.78,
                'emr' => '64.41-64.78',
                'gmr' => 64.6,
            ],
            [
                'warehouse' => 'GID#2, MLANG BS',
                'pile' => '5',
                'volume_before_test_milling_bags' => 9438,
                'volume_after_test_milling_bags' => null,
                'quality' => 'GQA',
                'amr' => 62.91,
                'pmr' => 63.14,
                'emr' => '62.91-63.14',
                'gmr' => 63.0,
            ],
            [
                'warehouse' => 'GID#4, MLANG BS',
                'pile' => '1',
                'volume_before_test_milling_bags' => 7517,
                'volume_after_test_milling_bags' => null,
                'quality' => 'GQA',
                'amr' => 63.25,
                'pmr' => 63.64,
                'emr' => '63.25-63.64',
                'gmr' => 63.4,
            ],
            [
                'warehouse' => 'GID#4, MLANG BS',
                'pile' => '2',
                'volume_before_test_milling_bags' => 3165,
                'volume_after_test_milling_bags' => null,
                'quality' => 'GQA',
                'amr' => 62.92,
                'pmr' => 63.29,
                'emr' => '62.92-63.29',
                'gmr' => 63.0,
            ],
            [
                'warehouse' => 'GID#4, MLANG BS',
                'pile' => '9',
                'volume_before_test_milling_bags' => 2165,
                'volume_after_test_milling_bags' => null,
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
        $branchName = $this->formatBranchName(
            $config->branch_text ?: ($branchName ?? 'North Cotabato'),
        );

        $pdf = Pdf::loadView('reports.gmr-print-pdf', [
            'rows' => $rows,
            'config' => $config,
            'signatories' => $signatories,
            'branchName' => $branchName,
            'visibleColumns' => $config->getVisibleReportColumns(),
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
        $reportColumns = GmrReportConfiguration::availableReportColumns();
        $visibleColumns = $config->getVisibleReportColumns();
        $columnCount = count($visibleColumns);
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('GMR Report');

        $mergeAcrossRow = function (int $rowNumber, int $startIndex, int $endIndex) use ($sheet): string {
            $startColumn = Coordinate::stringFromColumnIndex($startIndex);
            $endColumn = Coordinate::stringFromColumnIndex($endIndex);
            $range = "{$startColumn}{$rowNumber}:{$endColumn}{$rowNumber}";

            if ($startIndex < $endIndex) {
                $sheet->mergeCells($range);
            }

            return $range;
        };

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
            $this->formatBranchName(
                $config->branch_text ?: ($branchName ?? 'North Cotabato'),
            ),
        );

        foreach (range(1, 4) as $hRow) {
            $mergeAcrossRow($hRow, 1, $columnCount);
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
        foreach ($visibleColumns as $index => $columnKey) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue(
                "{$column}6",
                $reportColumns[$columnKey]['label'],
            );
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
        $sheet->getStyle("A6:{$lastColumn}6")->applyFromArray($headerStyle);
        $sheet->getRowDimension(6)->setRowHeight(24);

        // Data rows starting at row 7
        $row = 7;
        foreach ($rows as $item) {
            foreach ($visibleColumns as $index => $columnKey) {
                $column = $reportColumns[$columnKey];
                $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
                $value = $item[$column['field']] ?? null;

                $cellValue = match ($column['type']) {
                    'emr' => str_replace('%', '', (string) ($value ?? '—')),
                    'bags', 'percentage' => $value !== null
                        ? (float) $value
                        : '—',
                    default => $value ?? '—',
                };

                $sheet->setCellValue("{$columnLetter}{$row}", $cellValue);

                if ($value !== null && $column['type'] === 'bags') {
                    $sheet
                        ->getStyle("{$columnLetter}{$row}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                } elseif ($value !== null && $column['type'] === 'percentage') {
                    $sheet
                        ->getStyle("{$columnLetter}{$row}")
                        ->getNumberFormat()
                        ->setFormatCode('0.00');
                }

                $alignment = match ($column['alignment']) {
                    'left' => Alignment::HORIZONTAL_LEFT,
                    'right' => Alignment::HORIZONTAL_RIGHT,
                    default => Alignment::HORIZONTAL_CENTER,
                };
                $sheet
                    ->getStyle("{$columnLetter}{$row}")
                    ->getAlignment()
                    ->setHorizontal($alignment);

                if ($column['bold']) {
                    $sheet
                        ->getStyle("{$columnLetter}{$row}")
                        ->getFont()
                        ->setBold(true);
                }
            }

            $sheet
                ->getStyle("A{$row}:{$lastColumn}{$row}")
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
                ->getStyle("A{$lastDataRow}:{$lastColumn}{$lastDataRow}")
                ->getBorders()
                ->getBottom()
                ->setBorderStyle(Border::BORDER_MEDIUM)
                ->getColor()
                ->setRGB('064E3B');
        }

        // Signatories Section
        $currRow = $row + 2;
        $sheet->setCellValue("A{$currRow}", 'Prepared and Recommended By:');
        $sectionRange = $mergeAcrossRow($currRow, 1, $columnCount);
        $sheet
            ->getStyle($sectionRange)
            ->getFont()
            ->setBold(true)
            ->setSize(10);
        $sheet
            ->getStyle($sectionRange)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $currRow++;

        $sheet->setCellValue(
            "A{$currRow}",
            'Regional Milling Committee (RMEC) Members:',
        );
        $sectionRange = $mergeAcrossRow($currRow, 1, $columnCount);
        $sheet
            ->getStyle($sectionRange)
            ->getFont()
            ->setBold(true)
            ->setSize(9.5);
        $sheet
            ->getStyle($sectionRange)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $currRow += 2;

        $writeSignatory = function (
            object $signatory,
            int $rowNumber,
            int $startIndex,
            int $endIndex,
            float $nameFontSize = 9.5,
        ) use ($sheet, $mergeAcrossRow): void {
            $nameRange = $mergeAcrossRow($rowNumber, $startIndex, $endIndex);
            $startColumn = Coordinate::stringFromColumnIndex($startIndex);
            $nameCell = "{$startColumn}{$rowNumber}";
            $sheet->setCellValue($nameCell, strtoupper($signatory->name));
            $sheet
                ->getStyle($nameRange)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet
                ->getStyle($nameCell)
                ->getFont()
                ->setBold(true)
                ->setSize($nameFontSize);
            $sheet
                ->getStyle($nameRange)
                ->getBorders()
                ->getBottom()
                ->setBorderStyle(Border::BORDER_THIN);

            $positionRow = $rowNumber + 1;
            $positionRange = $mergeAcrossRow(
                $positionRow,
                $startIndex,
                $endIndex,
            );
            $sheet->setCellValue(
                "{$startColumn}{$positionRow}",
                $signatory->position,
            );
            $sheet
                ->getStyle($positionRange)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet
                ->getStyle("{$startColumn}{$positionRow}")
                ->getFont()
                ->setItalic(true)
                ->setSize(8);
        };

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

        if ($columnCount === 1) {
            foreach ($members as $member) {
                $writeSignatory($member, $currRow, 1, $columnCount);
                $currRow += 3;
            }
        } else {
            $leftEndIndex = intdiv($columnCount, 2);
            $rightStartIndex = $leftEndIndex + 1;

            for ($i = 0; $i < $members->count(); $i += 2) {
                $firstMember = $members[$i] ?? null;
                $secondMember = $members[$i + 1] ?? null;

                if ($firstMember) {
                    $writeSignatory($firstMember, $currRow, 1, $leftEndIndex);
                }
                if ($secondMember) {
                    $writeSignatory(
                        $secondMember,
                        $currRow,
                        $rightStartIndex,
                        $columnCount,
                    );
                }

                $currRow += 3;
            }
        }

        foreach ($chairpersons as $chair) {
            $writeSignatory($chair, $currRow, 1, $columnCount);
            $currRow += 3;
        }

        foreach ($coa as $coaRep) {
            $signatureRange = $mergeAcrossRow($currRow, 1, $columnCount);
            $sheet
                ->getStyle($signatureRange)
                ->getBorders()
                ->getBottom()
                ->setBorderStyle(Border::BORDER_THIN);
            $currRow++;

            $writeSignatory($coaRep, $currRow, 1, $columnCount, 9);
            $currRow += 3;
        }

        if ($reviewers->isNotEmpty()) {
            $sheet->setCellValue("A{$currRow}", 'Reviewed by:');
            $sectionRange = $mergeAcrossRow($currRow, 1, $columnCount);
            $sheet
                ->getStyle($sectionRange)
                ->getFont()
                ->setBold(true)
                ->setSize(9.5);
            $sheet
                ->getStyle($sectionRange)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currRow += 2;

            foreach ($reviewers as $rev) {
                $writeSignatory($rev, $currRow, 1, $columnCount);
                $currRow += 3;
            }
        }

        foreach (range(1, $columnCount) as $columnIndex) {
            $col = Coordinate::stringFromColumnIndex($columnIndex);
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
