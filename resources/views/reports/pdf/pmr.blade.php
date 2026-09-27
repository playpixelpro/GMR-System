<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PMR Report</title>
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
        .group-separator {
            border-bottom: 1.5px solid #064e3b !important;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .text-primary { color: #047857; }
        .text-error { color: #dc2626; }
        .badge {
            display: inline-block;
            padding: 1px 4px;
            font-size: 7px;
            font-weight: bold;
            border-radius: 3px;
        }
        .badge-success { background-color: #d1fae5; color: #065f46; }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        .badge-neutral { background-color: #e5e7eb; color: #374151; }
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
        <h2>Performance Milling Recovery (PMR) Report</h2>
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
                <th style="width: 80px;">Branch</th>
                <th style="width: 90px;">Warehouse</th>
                <th style="width: 50px;">Pile No.</th>
                <th style="width: 80px;">Variety</th>
                <th style="width: 45px;">Purity (%)</th>
                <th style="width: 40px;">MC (%)</th>
                <th style="width: 60px;">Quality</th>
                <th style="width: 40px;">Aged (mos)</th>
                <th style="width: 65px;">Volume (bags)</th>
                <th style="width: 40px;">Trial</th>
                <th style="width: 55px;">Recovery Rate (%)</th>
                <th style="width: 55px;">Mean (%)</th>
                <th style="width: 55px;">PMR (%)</th>
                <th style="width: 70px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recordGroups as $group)
                @php
                    $groupIndex = $loop->iteration;
                    $records = is_array($group) || $group instanceof \ArrayAccess ? $group['records'] : $group;
                    $calculation = is_array($group) || $group instanceof \ArrayAccess ? ($group['calculation'] ?? null) : null;
                    if (! $calculation) {
                        $calculation = app(\App\Services\PmrCalculationService::class)->calculateForGroup($records);
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
                    $stdDev = is_array($group) || $group instanceof \ArrayAccess ? ($group['standard_deviation'] ?? $calculation->standardDeviation) : $calculation->standardDeviation;
                    $amrRateValue = is_array($group) || $group instanceof \ArrayAccess ? ($group['amr_rate'] ?? null) : null;
                    $pmrRateValue = $calculation->pmrRate ?? ($records->isNotEmpty() ? $mean : null);
                    $maxTrials = max(3, $trialRecords->keys()->max() ?? 3);

                    $reest = app(\App\Services\PmrCalculationService::class)->evaluateReestablishment($pmrRateValue, $amrRateValue);

                    if ($records->isEmpty()) {
                        $statusBadge = 'badge-neutral';
                        $statusText = 'Pending (0/3)';
                    } elseif ($calculation->isHistoricalLegacy()) {
                        $statusBadge = 'badge-neutral';
                        $statusText = 'Historical Legacy';
                    } elseif ($reest['is_pmr_below_60']) {
                        $statusBadge = 'badge-danger';
                        $statusText = 'PMR ≤ 60%';
                    } elseif ($reest['is_pmr_below_amr']) {
                        $statusBadge = 'badge-warning';
                        $statusText = 'PMR < AMR';
                    } elseif ($reest['is_amr_divergent_from_pmr']) {
                        $statusBadge = 'badge-warning';
                        $statusText = 'Divergent';
                    } elseif ($calculation->isValid) {
                        $statusBadge = 'badge-success';
                        $statusText = 'Recommended';
                    } elseif ($calculation->isInvalidOutliers()) {
                        $statusBadge = 'badge-danger';
                        $statusText = 'Invalid Outliers';
                    } elseif ($calculation->isInvalidCv()) {
                        $statusBadge = 'badge-danger';
                        $statusText = 'Invalid CV > 5%';
                    } else {
                        $statusBadge = 'badge-neutral';
                        $statusText = 'Incomplete';
                    }
                @endphp

                @for ($trial = 1; $trial <= $maxTrials; $trial++)
                    @php
                        $record = $trialRecords->get($trial);
                        $isLastTrial = ($trial === $maxTrials);
                    @endphp
                    <tr class="{{ $isLastTrial ? 'group-separator' : '' }}">
                        @if ($trial === 1)
                            <td rowspan="{{ $maxTrials }}" class="text-center font-bold">{{ $groupIndex }}</td>
                            <td rowspan="{{ $maxTrials }}" class="text-left">{{ $branchName }}</td>
                            <td rowspan="{{ $maxTrials }}" class="text-left font-bold">{{ $warehouseName }}</td>
                            <td rowspan="{{ $maxTrials }}" class="text-center font-bold">{{ $pileNumber }}</td>
                            <td rowspan="{{ $maxTrials }}" class="text-left">{{ $variety }}</td>
                            <td rowspan="{{ $maxTrials }}" class="text-right">{{ number_format((float) $purity, 2) }}</td>
                            <td rowspan="{{ $maxTrials }}" class="text-right">{{ number_format((float) $mc, 1) }}</td>
                            <td rowspan="{{ $maxTrials }}" class="text-center">{{ strtoupper(str_replace('_', ' ', $quality ?: '—')) }}</td>
                            <td rowspan="{{ $maxTrials }}" class="text-center">{{ $agedMonths }}</td>
                            <td rowspan="{{ $maxTrials }}" class="text-right font-bold">{{ number_format((float) $volumeKg / 50, 3) }}</td>
                        @endif

                        <td class="text-center">{{ $trial }}</td>
                        <td class="text-right font-bold text-primary">{{ $record ? number_format($record->recovery_rate_percentage, 2) . '%' : '—' }}</td>

                        @if ($trial === 1)
                            <td rowspan="{{ $maxTrials }}" class="text-right font-bold">{{ $mean !== null ? number_format($mean, 2) . '%' : '—' }}</td>
                            <td rowspan="{{ $maxTrials }}" class="text-right font-bold text-primary">{{ $pmrRateValue !== null ? number_format($pmrRateValue, 2) . '%' : '—' }}</td>
                            <td rowspan="{{ $maxTrials }}" class="text-center">
                                <span class="badge {{ $statusBadge }}">{{ $statusText }}</span>
                            </td>
                        @endif
                    </tr>
                @endfor
            @empty
                <tr>
                    <td colspan="15" class="text-center" style="padding: 20px;">No PMR records found matching the criteria.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">NFA GMR System &bull; Confidential</div>
        <div class="footer-center">Performance Milling Recovery (PMR) Report</div>
        <div class="footer-right">Page <script type="text/php">echo $pdf->get_page_number();</script> of <script type="text/php">echo $pdf->get_page_count();</script></div>
    </div>
</body>
</html>
