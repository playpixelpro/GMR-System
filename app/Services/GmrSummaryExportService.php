<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GmrSummaryExportService
{
    /**
     * Generate and stream an Excel (.xlsx) file for the GMR Summary report.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{branch?: ?string, warehouse?: ?string}  $filters
     */
    public function exportExcel(Collection $rows, array $filters = []): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('GMR Summary');

        // Document header
        $sheet->setCellValue('A1', 'NATIONAL FOOD AUTHORITY');
        $sheet->setCellValue('A2', 'GUARANTEED MILLING RECOVERY (GMR) SUMMARY REPORT');

        $filterText = 'Branch: '.($filters['branch'] ?? 'All Branches').' | Warehouse: '.($filters['warehouse'] ?? 'All Warehouses').' | Generated on: '.now()->format('Y-m-d H:i:s');
        $sheet->setCellValue('A3', $filterText);

        $sheet->mergeCells('A1:L1');
        $sheet->mergeCells('A2:L2');
        $sheet->mergeCells('A3:L3');

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
            'E' => 'Volume Before Test Milling (50kg bags)',
            'F' => 'Volume After Test Milling (50kg bags)',
            'G' => 'PMR (%)',
            'H' => 'AMR (%)',
            'I' => 'EMR Range (%)',
            'J' => 'GMR (%)',
            'K' => 'Status',
            'L' => 'Approved GMR (%)',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}5", $label);
        }

        // Style header row
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

        $sheet->getStyle('A5:L5')->applyFromArray($headerStyle);
        $sheet->getRowDimension(5)->setRowHeight(28);

        // Data rows starting at row 6
        $row = 6;
        $itemNumber = 1;

        foreach ($rows as $item) {
            $sheet->setCellValue("A{$row}", $itemNumber);
            $sheet->setCellValue("B{$row}", $item['branch'] ?? '—');
            $sheet->setCellValue("C{$row}", $item['warehouse'] ?? '—');
            $sheet->setCellValue("D{$row}", $item['pile'] ?? '—');

            // Pile volume before and after test milling
            $sheet->setCellValue("E{$row}", $item['volume_bags'] !== null ? round((float) $item['volume_bags'], 3) : 'N/A');
            $sheet->setCellValue("F{$row}", $item['volume_after_test_milling_bags'] !== null ? round((float) $item['volume_after_test_milling_bags'], 3) : 'N/A');

            // PMR
            $sheet->setCellValue("G{$row}", $item['pmr'] !== null ? round((float) $item['pmr'], 2) : 'N/A');

            // AMR
            $sheet->setCellValue("H{$row}", $item['amr'] !== null ? round((float) $item['amr'], 2) : 'N/A');

            // EMR Range (numbers without % symbol, e.g. 61.50 – 65.20)
            $emrDisplay = 'N/A';
            if ($item['amr'] !== null && $item['pmr'] !== null) {
                $emrDisplay = number_format((float) $item['amr'], 2).' – '.number_format((float) $item['pmr'], 2);
            } elseif (! empty($item['emr']) && $item['emr'] !== 'N/A') {
                $emrDisplay = str_replace('%', '', (string) $item['emr']);
            }
            $sheet->setCellValue("I{$row}", $emrDisplay);

            // GMR
            $sheet->setCellValue("J{$row}", $item['gmr'] !== null ? round((float) $item['gmr'], 2) : 'N/A');

            // Status
            $sheet->setCellValue("K{$row}", $item['status'] ?? '—');

            // Approved GMR
            $approvedGmr = $item['approved_gmr'] ?? null;
            if (($item['gmr_status'] ?? null) === 'approved' && $approvedGmr !== null) {
                $sheet->setCellValue("L{$row}", round((float) $approvedGmr, 2));
            } elseif (($item['gmr_status'] ?? null) === 'submitted') {
                $sheet->setCellValue("L{$row}", 'Submitted');
            } else {
                $sheet->setCellValue("L{$row}", '—');
            }

            // Cell alignments and styles
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("K{$row}:L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Number formats (0.00) for numeric columns
            $sheet->getStyle("E{$row}:F{$row}")->getNumberFormat()->setFormatCode('#,##0.000');
            $sheet->getStyle("G{$row}:H{$row}")->getNumberFormat()->setFormatCode('0.00');
            $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode('0.00');
            if (($item['gmr_status'] ?? null) === 'approved' && $approvedGmr !== null) {
                $sheet->getStyle("L{$row}")->getNumberFormat()->setFormatCode('0.00');
            }

            // Highlight GMR
            if ($item['gmr'] !== null) {
                $sheet->getStyle("J{$row}")->getFont()->setBold(true)->setColor(new Color('064E3B'));
            }

            // Border for data row
            $sheet->getStyle("A{$row}:L{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E5E7EB');

            $row++;
            $itemNumber++;
        }

        // Bottom border for the table
        $lastRow = $row - 1;
        if ($lastRow >= 6) {
            $sheet->getStyle("A{$lastRow}:L{$lastRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('064E3B');
        }

        // Auto-fit column widths
        foreach (range('A', 'L') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $sheet->freezePane('A6');

        $writer = new Xlsx($spreadsheet);
        $filename = 'GMR_Summary_'.now()->format('Y-m-d_His').'.xlsx';

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
