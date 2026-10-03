<!DOCTYPE html>
<html lang="en" data-theme="light" class="light" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <title>EMR Report</title>
    <style>
        @page {
            margin: 12mm 8mm 15mm 8mm;
            size: a4 landscape;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8px;
            color: #1a202c;
            line-height: 1.2;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 12px;
            border-bottom: 1.5px solid #047857;
            padding-bottom: 8px;
        }
        .header h1 {
            font-size: 13px;
            font-weight: bold;
            margin: 0 0 2px 0;
            color: #064e3b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header h2 {
            font-size: 11px;
            font-weight: bold;
            margin: 0 0 4px 0;
            color: #111827;
        }
        .header .meta {
            font-size: 8px;
            color: #4b5563;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            border: 0.5px solid #9ca3af;
            padding: 3.5px 3px;
            word-wrap: break-word;
        }
        th {
            background-color: #064e3b;
            color: #ffffff;
            font-weight: bold;
            font-size: 7.5px;
            text-align: center;
            text-transform: uppercase;
        }
        tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .text-primary { color: #047857; }
        .footer {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            height: 8mm;
            border-top: 0.5px solid #d1d5db;
            padding-top: 3px;
            font-size: 7.5px;
            color: #6b7280;
            display: table;
            width: 100%;
        }
        .footer-left { display: table-cell; text-align: left; width: 33%; }
        .footer-center { display: table-cell; text-align: center; width: 34%; }
        .footer-right { display: table-cell; text-align: right; width: 33%; }
    </style>
</head>
<body>
    <div class="header">
        <h1>National Food Authority</h1>
        <h2>Expected Milling Recovery (EMR) Report</h2>
        <p style="font-size: 7px; margin: 0 0 3px;">Note: Volume values shown are before test milling.</p>
        <div class="meta">
            Branch: <strong>{{ $filterBranch ?? 'All Branches' }}</strong> &nbsp;|&nbsp;
            Warehouse: <strong>{{ $filterWarehouse ?? 'All Warehouses' }}</strong> &nbsp;|&nbsp;
            Generated on: <strong>{{ $generatedAt }}</strong>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 25px;">No.</th>
                <th style="width: 90px;">Branch</th>
                <th style="width: 100px;">Warehouse</th>
                <th style="width: 55px;">Pile No.</th>
                <th style="width: 80px;">Variety</th>
                <th style="width: 45px;">Aged (mos)</th>
                <th style="width: 55px;">Volume (kg)</th>
                <th style="width: 55px;">Volume (bags)</th>
                <th style="width: 50px;">Purity (%)</th>
                <th style="width: 60px;">Quality</th>
                <th style="width: 55px;">AMR (%)</th>
                <th style="width: 55px;">PMR (%)</th>
                <th style="width: 60px;">EMR (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $data)
                <tr>
                    <td class="text-center font-bold">{{ $loop->iteration }}</td>
                    <td class="text-left">{{ $data['branch'] ?? '—' }}</td>
                    <td class="text-left font-bold">{{ $data['warehouse'] ?? '—' }}</td>
                    <td class="text-center font-bold">{{ $data['pile'] ?? '—' }}</td>
                    <td class="text-left">{{ $data['variety'] ?? '—' }}</td>
                    <td class="text-center">{{ $data['age'] ?? '—' }}</td>
                    <td class="text-right">{{ $data['volume'] ?? '—' }}</td>
                    <td class="text-right">{{ $data['volume_bags'] !== null ? number_format((float) $data['volume_bags'], 3) : '—' }}</td>
                    <td class="text-right">{{ $data['purity'] !== null ? number_format((float) $data['purity'], 2) : '—' }}</td>
                    <td class="text-center">{{ strtoupper(str_replace('_', ' ', $data['quality'] ?? '—')) }}</td>
                    <td class="text-right font-bold text-primary">{{ $data['amr'] !== null ? number_format((float) $data['amr'], 2) . '%' : '—' }}</td>
                    <td class="text-right font-bold text-primary">{{ $data['pmr'] !== null ? number_format((float) $data['pmr'], 2) . '%' : '—' }}</td>
                    <td class="text-center font-bold text-primary">{{ $data['emr_display'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="text-center" style="padding: 20px;">No EMR records found matching the criteria.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">NFA GMR System &bull; Confidential</div>
        <div class="footer-center">Expected Milling Recovery (EMR) Report</div>
        <div class="footer-right"></div>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
                $font = $fontMetrics->get_font('helvetica', 'normal');
                $size = 7.5;
                $text = "Page " . $pageNumber . " of " . $pageCount;
                $textWidth = $fontMetrics->get_text_width($text, $font, $size);
                $rightMargin = 8 * (72 / 25.4);
                $x = $canvas->get_width() - $rightMargin - $textWidth;
                $y = $canvas->get_height() - 38.3;
                $canvas->text($x, $y, $text, $font, $size, [0.42, 0.45, 0.50]);
            });
        }
    </script>
</body>
</html>
