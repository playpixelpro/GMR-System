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

class AmrExportService
{
    /**
     * Generate and stream an Excel (.xlsx) file for the AMR report.
     *
     * @param  Collection<int, mixed>  $recordGroups
     * @param  array{branch?: ?string, warehouse?: ?string}  $filters
     */
    public function exportExcel(Collection $recordGroups, array $filters = []): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('AMR Report');

        // Document header
        $sheet->setCellValue('A1', 'NATIONAL FOOD AUTHORITY');
        $sheet->setCellValue('A2', 'ACTUAL MILLING RECOVERY (AMR) REPORT');

        $filterText = 'Branch: '.($filters['branch'] ?? 'All Branches').' | Warehouse: '.($filters['warehouse'] ?? 'All Warehouses').' | Generated on: '.now()->format('Y-m-d H:i:s');
        $sheet->setCellValue('A3', $filterText);

        $sheet->mergeCells('A1:R1');
        $sheet->mergeCells('A2:R2');
        $sheet->mergeCells('A3:R3');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new Color('064E3B'));
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true)->setColor(new Color('4B5563'));

        $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Table headers on row 5
        $headers = [
            'A' => 'No.',
            'B' => 'Branch',
            'C' => 'Warehouse',
            'D' => 'Pile No.',
            'E' => 'Variety',
            'F' => 'Purity (%)',
            'G' => 'MC (%)',
            'H' => 'Quality',
            'I' => 'Aged (mos)',
            'J' => 'Volume (50kg bags)',
            'K' => 'Rice Miller',
            'L' => 'Trial',
            'M' => 'Palay In (kg)',
            'N' => 'Rice Rec (kg)',
            'O' => 'Rec Rate (%)',
            'P' => 'Mean (%)',
            'Q' => 'AMR (%)',
            'R' => 'Status',
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

        $sheet->getStyle('A5:R5')->applyFromArray($headerStyle);
        $sheet->getRowDimension(5)->setRowHeight(28);

        $row = 6;
        $pmrCalcService = app(PmrCalculationService::class);
        $amrCalcService = app(AmrCalculationService::class);

        foreach ($recordGroups as $index => $group) {
            $groupIndex = $index + 1;
            $records = is_array($group) || $group instanceof \ArrayAccess ? $group['records'] : $group;
            $calculation = is_array($group) || $group instanceof \ArrayAccess ? ($group['calculation'] ?? null) : null;
            if (! $calculation) {
                $calculation = $amrCalcService->calculateForGroup($records);
            }
            $trialRecords = $records->keyBy('trial_number');
            $firstRecord = $records->first();
            $pile = is_array($group) || $group instanceof \ArrayAccess ? ($group['pile'] ?? $firstRecord?->pile) : $firstRecord?->pile;
            $warehouseName = is_array($group) || $group instanceof \ArrayAccess ? ($group['warehouse_name'] ?? $pile?->warehouse?->name ?? $firstRecord?->warehouse_name ?? '—') : ($firstRecord?->warehouse_name ?? $pile?->warehouse?->name ?? '—');
            $branchName = is_array($group) || $group instanceof \ArrayAccess ? ($group['branch_name'] ?? $pile?->warehouse?->branch?->name ?? $firstRecord?->pile?->warehouse?->branch?->name ?? '—') : ($pile?->warehouse?->branch?->name ?? $firstRecord?->pile?->warehouse?->branch?->name ?? '—');
            $pileNumber = is_array($group) || $group instanceof \ArrayAccess ? ($group['pile_number'] ?? $pile?->pile_number ?? $pile?->number ?? $firstRecord?->pile_number ?? '—') : ($pile?->pile_number ?? $pile?->number ?? $firstRecord?->pile_number ?? '—');
            $variety = is_array($group) || $group instanceof \ArrayAccess ? ($group['variety'] ?? $pile?->variety ?? $firstRecord?->variety ?? '—') : ($pile?->variety ?? $firstRecord?->variety ?? '—');
            $purity = is_array($group) || $group instanceof \ArrayAccess ? ($group['purity'] ?? $pile?->purity ?? $firstRecord?->purity) : ($pile?->purity ?? $firstRecord?->purity);
            $mc = is_array($group) || $group instanceof \ArrayAccess ? ($group['mc'] ?? $pile?->mc ?? $firstRecord?->mc) : ($pile?->mc ?? $firstRecord?->mc);
            $quality = is_array($group) || $group instanceof \ArrayAccess ? ($group['quality'] ?? $pile?->quality ?? $firstRecord?->quality ?? '') : ($pile?->quality ?? $firstRecord?->quality ?? '');
            $agedMonths = is_array($group) || $group instanceof \ArrayAccess ? ($group['aged_months'] ?? $pile?->aged_months ?? $firstRecord?->aged_months ?? 0) : ($pile?->aged_months ?? $firstRecord?->aged_months ?? 0);
            $volumeKg = is_array($group) || $group instanceof \ArrayAccess ? ($group['volume_kg'] ?? $pile?->volume_kg ?? $firstRecord?->volume_kg ?? 0) : ($pile?->volume_kg ?? $firstRecord?->volume_kg ?? 0);
            $riceMillers = is_array($group) || $group instanceof \ArrayAccess ? ($group['rice_millers'] ?? $firstRecord?->rice_millers ?? '—') : ($firstRecord?->rice_millers ?? '—');
            $validRecoveries = $records->map(fn ($r) => (float) $r->milling_recovery_percentage)->filter(fn ($val) => $val > 0);
            $mean = $validRecoveries->isNotEmpty() ? $validRecoveries->avg() : null;
            $amrRateValue = $calculation->amrRate ?? $mean;
            $pmrRateValue = is_array($group) || $group instanceof \ArrayAccess ? ($group['pmr_rate'] ?? null) : null;

            $reestablishment = $pmrCalcService->evaluateReestablishment($pmrRateValue, $amrRateValue);
            $isAmrLowerThan60 = $amrRateValue !== null && $amrRateValue <= 60.0;
            $isPmrLowerThanAmr = $reestablishment['is_pmr_below_amr'];
            $isAmrDivergent = $reestablishment['is_amr_divergent_from_pmr'];

            $isMri = $calculation->isMriEstablished();
            $isLowVolEligible = ($volumeKg > 0 && (float) $volumeKg <= 50000);
            $isSingleRow = $isMri || ($records->isEmpty() && $isLowVolEligible);
            $numRows = $isSingleRow ? 1 : 3;

            if ($records->isEmpty()) {
                $statusText = $isLowVolEligible ? 'Pending MRI' : 'Pending (0/3)';
            } elseif (! $isMri && $records->count() < 3 && $amrRateValue === null) {
                $statusText = $records->count().'/3 trials';
            } elseif ($isAmrLowerThan60) {
                $statusText = 'AMR ≤ 60%';
            } elseif ($isPmrLowerThanAmr) {
                $statusText = 'PMR < AMR';
            } elseif ($isAmrDivergent) {
                $statusText = 'Divergent';
            } elseif ($calculation->isValid) {
                $statusText = $isMri ? 'Recommended (MRI)' : 'Recommended';
            } elseif ($calculation->isInvalidOutliers()) {
                $statusText = 'Invalid Outliers';
            } else {
                $statusText = 'Incomplete';
            }

            $startRow = $row;
            $endRow = $row + ($numRows - 1);

            // Merged columns A-K and P-R if multiple rows
            if ($startRow !== $endRow) {
                foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'P', 'Q', 'R'] as $mergeCol) {
                    $sheet->mergeCells("{$mergeCol}{$startRow}:{$mergeCol}{$endRow}");
                }
            }

            // Set pile-level values
            $sheet->setCellValue("A{$startRow}", $groupIndex);
            $sheet->setCellValue("B{$startRow}", $branchName);
            $sheet->setCellValue("C{$startRow}", $warehouseName);
            $sheet->setCellValue("D{$startRow}", $pileNumber);
            $sheet->setCellValue("E{$startRow}", $variety);
            $sheet->setCellValue("F{$startRow}", $purity !== null ? (float) $purity : '—');
            $sheet->setCellValue("G{$startRow}", $mc !== null ? (float) $mc : '—');
            $sheet->setCellValue("H{$startRow}", strtoupper(str_replace('_', ' ', $quality ?: '—')));
            $sheet->setCellValue("I{$startRow}", (float) $agedMonths);
            $sheet->setCellValue("J{$startRow}", round((float) $volumeKg / 50, 3));
            $sheet->setCellValue("K{$startRow}", $riceMillers);

            $sheet->setCellValue("P{$startRow}", $isMri && $amrRateValue !== null ? round($amrRateValue, 2).'%' : ($mean !== null ? round($mean, 2).'%' : '—'));
            $sheet->setCellValue("Q{$startRow}", $amrRateValue !== null ? round($amrRateValue, 2).'%' : '—');
            $sheet->setCellValue("R{$startRow}", $statusText);

            if ($isMri) {
                $rec = $records->first();
                $sheet->setCellValue("L{$startRow}", 'C.3.10 (MRI)');
                $sheet->setCellValue("M{$startRow}", 'Exempt');
                $sheet->setCellValue("N{$startRow}", 'Exempt');
                $sheet->setCellValue("O{$startRow}", $rec && $rec->milling_recovery !== null ? round((float) $rec->milling_recovery, 2).'%' : '—');
            } elseif ($isSingleRow && $records->isEmpty()) {
                $sheet->setCellValue("L{$startRow}", 'Exempt / MRI');
                $sheet->setCellValue("M{$startRow}", '—');
                $sheet->setCellValue("N{$startRow}", '—');
                $sheet->setCellValue("O{$startRow}", '—');
            } else {
                // Per trial rows
                for ($trial = 1; $trial <= 3; $trial++) {
                    $currentRow = $startRow + ($trial - 1);
                    $rec = $trialRecords->get($trial);

                    $sheet->setCellValue("L{$currentRow}", "Trial {$trial}");
                    $sheet->setCellValue("M{$currentRow}", $rec && $rec->palay_input_kg !== null ? round((float) $rec->palay_input_kg, 2) : '—');
                    $sheet->setCellValue("N{$currentRow}", $rec && $rec->rice_recovery_kg !== null ? round((float) $rec->rice_recovery_kg, 2) : '—');
                    $sheet->setCellValue("O{$currentRow}", $rec && $rec->milling_recovery_percentage > 0 ? round($rec->milling_recovery_percentage, 2).'%' : '—');
                }
            }

            // Apply formatting for the rows
            $groupRange = "A{$startRow}:R{$endRow}";
            $sheet->getStyle($groupRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$startRow}:D{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$startRow}:G{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("H{$startRow}:I{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("J{$startRow}:J{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("L{$startRow}:L{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("M{$startRow}:Q{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("R{$startRow}:R{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Borders for group
            $sheet->getStyle($groupRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
            $sheet->getStyle("A{$endRow}:R{$endRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('064E3B');

            $row += $numRows;
        }

        // Auto-fit column widths
        foreach (range('A', 'R') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $sheet->freezePane('A6');

        $writer = new Xlsx($spreadsheet);
        $filename = 'AMR_Report_'.now()->format('Y-m-d_His').'.xlsx';

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
     * Generate and download a PDF file for the AMR report.
     *
     * @param  Collection<int, mixed>  $recordGroups
     * @param  array{branch?: ?string, warehouse?: ?string}  $filters
     */
    public function exportPdf(Collection $recordGroups, array $filters = []): Response
    {
        $pdf = Pdf::loadView('reports.pdf.amr', [
            'recordGroups' => $recordGroups,
            'filterBranch' => $filters['branch'] ?? null,
            'filterWarehouse' => $filters['warehouse'] ?? null,
            'generatedAt' => now()->format('F d, Y h:i A'),
        ]);

        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption('isPhpEnabled', true);

        $filename = 'AMR_Report_'.now()->format('Y-m-d_His').'.pdf';

        return $pdf->download($filename);
    }
}
