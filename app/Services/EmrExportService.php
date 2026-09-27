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

class EmrExportService
{
    /**
     * Generate and stream an Excel (.xlsx) file for the EMR dashboard.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{branch?: ?string, warehouse?: ?string}  $filters
     */
    public function exportExcel(Collection $rows, array $filters = []): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('EMR Report');

        // Document header
        $sheet->setCellValue('A1', 'NATIONAL FOOD AUTHORITY');
        $sheet->setCellValue('A2', 'EXPECTED MILLING RECOVERY (EMR) REPORT');

        $filterText = 'Branch: '.($filters['branch'] ?? 'All Branches').' | Warehouse: '.($filters['warehouse'] ?? 'All Warehouses').' | Generated on: '.now()->format('Y-m-d H:i:s');
        $sheet->setCellValue('A3', $filterText);

        $sheet->mergeCells('A1:M1');
        $sheet->mergeCells('A2:M2');
        $sheet->mergeCells('A3:M3');

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
            'F' => 'Aged (mos)',
            'G' => 'Volume (net kg)',
            'H' => 'Volume (50kg bags)',
            'I' => 'Purity (%)',
            'J' => 'Quality',
            'K' => 'AMR (%)',
            'L' => 'PMR (%)',
            'M' => 'EMR (%)',
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

        $sheet->getStyle('A5:M5')->applyFromArray($headerStyle);
        $sheet->getRowDimension(5)->setRowHeight(28);

        $row = 6;
        foreach ($rows as $index => $data) {
            $no = $index + 1;
            $sheet->setCellValue("A{$row}", $no);
            $sheet->setCellValue("B{$row}", $data['branch'] ?? '—');
            $sheet->setCellValue("C{$row}", $data['warehouse'] ?? '—');
            $sheet->setCellValue("D{$row}", $data['pile'] ?? '—');
            $sheet->setCellValue("E{$row}", $data['variety'] ?? '—');
            $sheet->setCellValue("F{$row}", $data['age'] ?? '—');
            $sheet->setCellValue("G{$row}", $data['volume'] ?? '—');
            $sheet->setCellValue("H{$row}", $data['volume_bags'] !== null ? round((float) $data['volume_bags'], 3) : '—');
            $sheet->setCellValue("I{$row}", $data['purity'] !== null ? round((float) $data['purity'], 2) : '—');
            $sheet->setCellValue("J{$row}", strtoupper(str_replace('_', ' ', $data['quality'] ?? '—')));
            $sheet->setCellValue("K{$row}", $data['amr'] !== null ? round((float) $data['amr'], 2).'%' : '—');
            $sheet->setCellValue("L{$row}", $data['pmr'] !== null ? round((float) $data['pmr'], 2).'%' : '—');
            $sheet->setCellValue("M{$row}", $data['emr_display'] ?? '—');

            $sheet->getStyle("A{$row}:M{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
            $sheet->getStyle("A{$row}:A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("K{$row}:M{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $row++;
        }

        // Auto-fit column widths
        foreach (range('A', 'M') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $sheet->freezePane('A6');

        $writer = new Xlsx($spreadsheet);
        $filename = 'Expected_Milling_Recovery_Report_'.now()->format('Y-m-d_His').'.xlsx';

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
     * Generate and download a PDF file for the EMR dashboard.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{branch?: ?string, warehouse?: ?string}  $filters
     */
    public function exportPdf(Collection $rows, array $filters = []): Response
    {
        $pdf = Pdf::loadView('reports.pdf.emr', [
            'rows' => $rows,
            'filterBranch' => $filters['branch'] ?? null,
            'filterWarehouse' => $filters['warehouse'] ?? null,
            'generatedAt' => now()->format('F d, Y h:i A'),
        ]);

        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption('isPhpEnabled', true);

        $filename = 'Expected_Milling_Recovery_Report_'.now()->format('Y-m-d_His').'.pdf';

        return $pdf->download($filename);
    }
}
