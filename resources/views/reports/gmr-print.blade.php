<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $config->title ?? 'REPORT ON PRE-MILLING ACTIVITY' }}</title>
    <style>
        @page {
            size: {{ $config->getCssPageSize() }};
            margin: {{ $config->getCssMargins() }};
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            color: #000;
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
            font-size: 11pt;
            line-height: 1.25;
        }

        .no-print-toolbar {
            background-color: #1e293b;
            color: #fff;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .toolbar-title {
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
        }

        .btn-primary {
            background-color: #2563eb;
            color: #fff;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
        }

        .btn-secondary {
            background-color: #475569;
            color: #fff;
        }
        .btn-secondary:hover {
            background-color: #334155;
        }

        .btn-outline {
            background-color: transparent;
            border-color: #94a3b8;
            color: #f1f5f9;
        }
        .btn-outline:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .badge-preview {
            background-color: #f59e0b;
            color: #000;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 9999px;
            text-transform: uppercase;
        }

        .sheet-container {
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        .report-sheet {
            background: #fff;
            width: 100%;
            max-width: 8.5in;
            min-height: 11in;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            padding: 0.5in;
            margin: 0 auto;
        }

        /* Report Header */
        .header-section {
            text-align: center;
            margin-bottom: 12px;
        }

        .header-title {
            font-size: 13pt;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .header-subtitle {
            font-size: 11pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 2px 0;
        }

        .header-region {
            font-size: 11pt;
            font-weight: 700;
            margin: 2px 0;
        }

        .header-branch {
            font-size: 11pt;
            font-weight: 700;
            margin: 2px 0 10px 0;
        }

        /* Report Table */
        .gmr-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            margin-bottom: 20px;
        }

        .gmr-table th,
        .gmr-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 9.5pt;
        }

        .gmr-table th {
            background-color: #fff;
            color: #000;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
        }

        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }

        /* Signatories Section */
        .signatories-section {
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .signatories-header {
            font-size: 10.5pt;
            font-weight: 800;
            text-align: center;
            margin: 10px 0 4px 0;
        }

        .signatories-subheader {
            font-size: 10pt;
            font-weight: 700;
            text-align: center;
            margin: 0 0 20px 0;
        }

        .signatories-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 30px;
            row-gap: 28px;
            margin-bottom: 24px;
        }

        .signatory-box {
            text-align: center;
            padding: 8px 10px;
        }

        .signatory-name {
            font-size: 10pt;
            font-weight: 800;
            text-transform: uppercase;
            border-bottom: 1.5px solid #000;
            display: inline-block;
            min-width: 220px;
            padding-bottom: 2px;
            margin-bottom: 3px;
            margin-top: 18px;
        }

        .signatory-position {
            font-size: 8.5pt;
            font-style: italic;
            color: #111;
        }

        .signatory-center {
            text-align: center;
            margin: 20px 0;
        }

        .reviewed-section {
            margin-top: 28px;
            text-align: center;
        }

        .reviewed-header {
            font-size: 10pt;
            font-weight: 800;
            text-align: center;
            margin-bottom: 20px;
        }

        @media print {
            body {
                background: transparent !important;
                font-size: 10pt;
            }

            .no-print,
            .no-print-toolbar {
                display: none !important;
            }

            .sheet-container {
                padding: 0 !important;
                display: block !important;
            }

            .report-sheet {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: none !important;
                width: 100% !important;
            }

            .gmr-table th,
            .gmr-table td {
                font-size: 9pt;
                padding: 3px 5px;
            }
        }
    </style>
</head>
<body>
    @if (!isset($isPdf) || !$isPdf)
    <div class="no-print-toolbar no-print">
        <div class="toolbar-title">
            <span>{{ $config->title }}</span>
            @if (isset($isPreview) && $isPreview)
                <span class="badge-preview">Preview Mode</span>
            @endif
        </div>
        <div class="toolbar-actions">
            <span style="font-size: 12px; color: #cbd5e1; margin-right: 8px;">
                Paper: <strong>{{ $config->paper_size }} ({{ ucfirst($config->orientation) }})</strong> | Margins: <strong>{{ $config->getCssMargins() }}</strong>
            </span>
            <button type="button" onclick="window.print()" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Print Report
            </button>
            @if (isset($printUrlWithPdf))
            <a href="{{ $printUrlWithPdf }}" class="btn btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Download PDF
            </a>
            @endif
            @if (isset($isPreview) && $isPreview)
            <a href="{{ route('gmr.config.edit') }}" class="btn btn-outline">
                Back to Configuration
            </a>
            @else
            <a href="{{ route('gmr.summary') }}" class="btn btn-outline">
                Back to Dashboard
            </a>
            @endif
        </div>
    </div>
    @endif

    <div class="sheet-container">
        <div class="report-sheet">
            <!-- Header Section -->
            <div class="header-section">
                <h1 class="header-title">{{ $config->title }}</h1>
                <div class="header-subtitle">{{ $config->subtitle }}</div>
                <div class="header-region">{{ $config->region_text }}</div>
                <div class="header-branch">{{ $config->branch_text ?: ($branchName ?? 'North Cotabato Branch') }}</div>
            </div>

            <!-- Table Section -->
            <table class="gmr-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Warehouse</th>
                        <th style="width: 8%;">Pile No.</th>
                        <th style="width: 14%;">Volume in Bags</th>
                        <th style="width: 9%;">Quality</th>
                        <th style="width: 11%;">AMR</th>
                        <th style="width: 11%;">PMR</th>
                        <th style="width: 14%;">EMR</th>
                        <th style="width: 8%;">GMR</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalBags = 0;
                    @endphp
                    @forelse ($rows as $row)
                        @php
                            if (isset($row['volume_bags']) && is_numeric($row['volume_bags'])) {
                                $totalBags += (float) $row['volume_bags'];
                            }
                        @endphp
                        <tr>
                            <td class="text-left font-bold">{{ $row['warehouse'] }}</td>
                            <td class="text-center font-bold">{{ $row['pile'] }}</td>
                            <td class="text-right">
                                {{ $row['volume_bags'] !== null ? number_format((float) $row['volume_bags']) : '—' }}
                            </td>
                            <td class="text-center font-bold">{{ $row['quality'] ?? 'GQA' }}</td>
                            <td class="text-center">
                                {{ $row['amr'] !== null ? number_format((float) $row['amr'], 2) : '—' }}
                            </td>
                            <td class="text-center">
                                {{ $row['pmr'] !== null ? number_format((float) $row['pmr'], 2) : '—' }}
                            </td>
                            <td class="text-center font-bold">
                                {{ $row['emr'] ?? '—' }}
                            </td>
                            <td class="text-center font-bold">
                                {{ $row['gmr'] !== null ? number_format((float) $row['gmr'], 2) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center" style="padding: 20px;">No GMR records selected.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Signatories Section -->
            @php
                $activeList = $signatories ?? collect();
                $members = $activeList->filter(fn ($s) => in_array($s->role_group, ['member', 'default', null]));
                $chairpersons = $activeList->filter(fn ($s) => $s->role_group === 'chairperson');
                $coa = $activeList->filter(fn ($s) => $s->role_group === 'coa');
                $reviewers = $activeList->filter(fn ($s) => $s->role_group === 'reviewer');
            @endphp

            <div class="signatories-section">
                <div class="signatories-header">Prepared and Recommended By:</div>
                <div class="signatories-subheader">Regional Milling Committee (RMEC) Members:</div>

                <!-- 2-column grid for RMEC members -->
                @if ($members->isNotEmpty())
                    <div class="signatories-grid">
                        @foreach ($members as $member)
                            <div class="signatory-box">
                                <div class="signatory-name">{{ $member->name }}</div>
                                <div class="signatory-position">{{ $member->position }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- Chairperson -->
                @foreach ($chairpersons as $chair)
                    <div class="signatory-center" style="margin-top: 14px;">
                        <div class="signatory-name">{{ $chair->name }}</div>
                        <div class="signatory-position">{{ $chair->position }}</div>
                    </div>
                @endforeach

                <!-- COA Representative -->
                @foreach ($coa as $coaRep)
                    <div class="signatory-center" style="margin-top: 16px;">
                        <div style="width: 220px; border-bottom: 1.5px solid #000; margin: 0 auto 4px auto; height: 16px;"></div>
                        <div class="signatory-position" style="font-weight: 700; font-style: normal;">{{ $coaRep->name }}</div>
                    </div>
                @endforeach

                <!-- Reviewed By Section -->
                @if ($reviewers->isNotEmpty())
                    <div class="reviewed-section">
                        <div class="reviewed-header">Reviewed by:</div>
                        @foreach ($reviewers as $reviewer)
                            <div class="signatory-center">
                                <div class="signatory-name">{{ $reviewer->name }}</div>
                                <div class="signatory-position">{{ $reviewer->position }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
