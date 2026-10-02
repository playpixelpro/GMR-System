<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PmrExportService
{
    /**
     * Generate and stream an Excel (.xlsx) file for the PMR report.
     *
     * @param  Collection<int, mixed>  $recordGroups
     * @param  array{branch?: ?string, warehouse?: ?string}  $filters
     */
    public function exportExcel(Collection $recordGroups, array $filters = []): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('PMR Report');

        // Document header
        $sheet->setCellValue('A1', 'NATIONAL FOOD AUTHORITY');
        $sheet->setCellValue('A2', 'POTENTIAL MILLING RECOVERY (PMR) REPORT');

        $filterText = 'Branch: '.($filters['branch'] ?? 'All Branches').' | Warehouse: '.($filters['warehouse'] ?? 'All Warehouses').' | Generated on: '.now()->format('Y-m-d H:i:s');
        $sheet->setCellValue('A3', $filterText);

        $sheet->mergeCells('A1:O1');
        $sheet->mergeCells('A2:O2');
        $sheet->mergeCells('A3:O3');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new Color('064E3B'));
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true)->setColor(new Color('4B5563'));

        $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Table headers on row 5
        $headers = [
            'A' => 'No.',
            'B' => 'Branch',
            'C' => 'Warehouse Name',
            'D' => 'Pile Number',
            'E' => 'Variety',
            'F' => 'Purity (%)',
            'G' => 'MC (%)',
            'H' => 'Quality',
            'I' => 'Aged (mos)',
            'J' => 'Volume (50kg bags)',
            'K' => 'Trial',
            'L' => 'Recovery Rate (%)',
            'M' => 'Mean (%)',
            'N' => 'PMR (%)',
            'O' => 'Status',
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col.'5', $header);
        }

        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 9,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '064E3B'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '033426'],
                ],
            ],
        ];

        $sheet->getStyle('A5:O5')->applyFromArray($headerStyle);
        $sheet->getRowDimension(5)->setRowHeight(28);

        $row = 6;
        $pmrCalcService = app(PmrCalculationService::class);

        foreach ($recordGroups as $index => $group) {
            $groupIndex = $index + 1;
            $records = is_array($group) || $group instanceof \ArrayAccess ? $group['records'] : $group;
            $calculation = is_array($group) || $group instanceof \ArrayAccess ? ($group['calculation'] ?? null) : null;
            if (! $calculation) {
                $calculation = $pmrCalcService->calculateForGroup($records);
            }
            $trialRecords = $records->keyBy('trial_number');
            $firstRecord = $records->first();
            $pile = is_array($group) || $group instanceof \ArrayAccess ? ($group['pile'] ?? $firstRecord?->pile) : $firstRecord?->pile;
            $warehouseName = is_array($group) || $group instanceof \ArrayAccess ? ($group['warehouse_name'] ?? $pile?->warehouse?->name ?? $firstRecord?->warehouse_name ?? '—') : ($firstRecord?->warehouse_name ?? $pile?->warehouse?->name ?? '—');
            $branchName = is_array($group) || $group instanceof \ArrayAccess ? ($group['branch_name'] ?? $pile?->warehouse?->branch?->name ?? $firstRecord?->pile?->warehouse?->branch?->name ?? '—') : ($pile?->warehouse?->branch?->name ?? $firstRecord?->pile?->warehouse?->branch?->name ?? '—');
            $pileNumber = is_array($group) || $group instanceof \ArrayAccess ? ($group['pile_number'] ?? $pile?->pile_number ?? $pile?->number ?? $firstRecord?->pile_number ?? '—') : ($pile?->pile_number ?? $pile?->number ?? $firstRecord?->pile_number ?? '—');
            $variety = is_array($group) || $group instanceof \ArrayAccess ? ($group['variety'] ?? $pile?->variety ?? $firstRecord?->variety ?? '—') : ($pile?->variety ?? $firstRecord?->variety ?? '—');
            $purity = is_array($group) || $group instanceof \ArrayAccess ? ($group['purity'] ?? $pile?->purity ?? $firstRecord?->purity ?? 0) : ($pile?->purity ?? $firstRecord?->purity ?? 0);
            $mc = is_array($group) || $group instanceof \ArrayAccess ? ($group['mc'] ?? $pile?->mc ?? $firstRecord?->mc ?? 0) : ($pile?->mc ?? $firstRecord?->mc ?? 0);
            $quality = is_array($group) || $group instanceof \ArrayAccess ? ($group['quality'] ?? $pile?->quality ?? $firstRecord?->quality ?? '') : ($pile?->quality ?? $firstRecord?->quality ?? '');
            $agedMonths = is_array($group) || $group instanceof \ArrayAccess ? ($group['aged_months'] ?? $pile?->aged_months ?? $firstRecord?->aged_months ?? 0) : ($pile?->aged_months ?? $firstRecord?->aged_months ?? 0);
            $volumeKg = is_array($group) || $group instanceof \ArrayAccess ? ($group['volume_kg'] ?? $pile?->volume_kg ?? $firstRecord?->volume_kg ?? 0) : ($pile?->volume_kg ?? $firstRecord?->volume_kg ?? 0);
            $mean = is_array($group) || $group instanceof \ArrayAccess ? ($group['mean'] ?? $calculation->mean) : $calculation->mean;
            $amrRateValue = is_array($group) || $group instanceof \ArrayAccess ? ($group['amr_rate'] ?? null) : null;
            $pmrRateValue = $calculation->pmrRate ?? ($records->isNotEmpty() ? $mean : null);
            $maxTrials = max(3, $trialRecords->keys()->max() ?? 3);

            $reest = $pmrCalcService->evaluateReestablishment($pmrRateValue, $amrRateValue);

            if ($records->isEmpty()) {
                $statusText = 'Pending (0/3)';
            } elseif ($calculation->isHistoricalLegacy()) {
                $statusText = 'Historical Legacy';
            } elseif ($reest['is_pmr_below_60']) {
                $statusText = 'PMR ≤ 60%';
            } elseif ($reest['is_pmr_below_amr']) {
                $statusText = 'PMR < AMR';
            } elseif ($reest['is_amr_divergent_from_pmr']) {
                $statusText = 'Divergent';
            } elseif ($calculation->isValid) {
                $statusText = 'Recommended';
            } elseif ($calculation->isInvalidOutliers()) {
                $statusText = 'Invalid Outliers';
            } elseif ($calculation->isInvalidCv()) {
                $statusText = 'Invalid CV > 5%';
            } else {
                $statusText = 'Incomplete';
            }

            $startRow = $row;
            $endRow = $row + ($maxTrials - 1);

            // Merged columns A-J and M-O
            foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'M', 'N', 'O'] as $mergeCol) {
                if ($startRow !== $endRow) {
                    $sheet->mergeCells("{$mergeCol}{$startRow}:{$mergeCol}{$endRow}");
                }
            }

            // Set pile-level values
            $sheet->setCellValue("A{$startRow}", $groupIndex);
            $sheet->setCellValue("B{$startRow}", $branchName);
            $sheet->setCellValue("C{$startRow}", $warehouseName);
            $sheet->setCellValue("D{$startRow}", $pileNumber);
            $sheet->setCellValue("E{$startRow}", $variety);
            $sheet->setCellValue("F{$startRow}", (float) $purity);
            $sheet->setCellValue("G{$startRow}", (float) $mc);
            $sheet->setCellValue("H{$startRow}", strtoupper(str_replace('_', ' ', $quality ?: '—')));
            $sheet->setCellValue("I{$startRow}", (float) $agedMonths);
            $sheet->setCellValue("J{$startRow}", round((float) $volumeKg / 50, 3));

            $sheet->setCellValue("M{$startRow}", $mean !== null ? round($mean, 2) : '—');
            $sheet->setCellValue("N{$startRow}", $pmrRateValue !== null ? round($pmrRateValue, 2) : '—');
            $sheet->setCellValue("O{$startRow}", $statusText);

            // Per trial rows
            for ($trial = 1; $trial <= $maxTrials; $trial++) {
                $currentRow = $startRow + ($trial - 1);
                $rec = $trialRecords->get($trial);

                $sheet->setCellValue("K{$currentRow}", "Trial {$trial}");
                $sheet->setCellValue("L{$currentRow}", $rec ? round($rec->recovery_rate_percentage, 2) : '—');
            }

            // Apply formatting for the group rows
            $groupRange = "A{$startRow}:O{$endRow}";
            $sheet->getStyle($groupRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$startRow}:D{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$startRow}:G{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("H{$startRow}:I{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("J{$startRow}:J{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("K{$startRow}:K{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("L{$startRow}:N{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("O{$startRow}:O{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Explicit 0.00 number format for numeric rates (ensuring pure numbers with no % symbol)
            $sheet->getStyle("F{$startRow}:G{$endRow}")->getNumberFormat()->setFormatCode('0.00');
            $sheet->getStyle("J{$startRow}:J{$endRow}")->getNumberFormat()->setFormatCode('#,##0.000');
            $sheet->getStyle("L{$startRow}:N{$endRow}")->getNumberFormat()->setFormatCode('0.00');

            // Borders for group
            $sheet->getStyle($groupRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
            $sheet->getStyle("A{$endRow}:O{$endRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('064E3B');

            $row += $maxTrials;
        }

        // Auto-fit column widths
        foreach (range('A', 'O') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $sheet->freezePane('A6');

        $writer = new Xlsx($spreadsheet);
        $filename = 'PMR_Report_'.now()->format('Y-m-d_His').'.xlsx';

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

    /**
     * Generate and download a PDF file for the PMR report.
     *
     * @param  Collection<int, mixed>  $recordGroups
     * @param  array{branch?: ?string, warehouse?: ?string}  $filters
     */
    public function exportPdf(Collection $recordGroups, array $filters = []): Response
    {
        $pdf = Pdf::loadView('reports.pdf.pmr', [
            'recordGroups' => $recordGroups,
            'filterBranch' => $filters['branch'] ?? null,
            'filterWarehouse' => $filters['warehouse'] ?? null,
            'generatedAt' => now()->format('F d, Y h:i A'),
        ]);

        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption('isPhpEnabled', true);

        $filename = 'PMR_Report_'.now()->format('Y-m-d_His').'.pdf';

        return $pdf->download($filename);
    }
}
