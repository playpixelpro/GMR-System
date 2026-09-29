<!DOCTYPE html>
<html lang="en" data-theme="light" class="light" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <title>AMR Report</title>
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
            padding: 3px 2px;
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
        <h2>Actual Milling Recovery (AMR) Report</h2>
        <div class="meta">
            Branch: <strong>{{ $filterBranch ?? 'All Branches' }}</strong> &nbsp;|&nbsp;
            Warehouse: <strong>{{ $filterWarehouse ?? 'All Warehouses' }}</strong> &nbsp;|&nbsp;
            Generated on: <strong>{{ $generatedAt }}</strong>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 20px;">No.</th>
                <th style="width: 55px;">Branch</th>
                <th style="width: 65px;">Warehouse</th>
                <th style="width: 38px;">Pile No.</th>
                <th style="width: 55px;">Variety</th>
                <th style="width: 35px;">Purity (%)</th>
                <th style="width: 32px;">MC (%)</th>
                <th style="width: 45px;">Quality</th>
                <th style="width: 30px;">Aged (mos)</th>
                <th style="width: 45px;">Volume (bags)</th>
                <th style="width: 60px;">Rice Miller</th>
                <th style="width: 32px;">Trial</th>
                <th style="width: 45px;">Palay In (kg)</th>
                <th style="width: 45px;">Rice Rec (kg)</th>
                <th style="width: 40px;">Rec Rate (%)</th>
                <th style="width: 40px;">Mean (%)</th>
                <th style="width: 42px;">AMR (%)</th>
                <th style="width: 55px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recordGroups as $group)
                @php
                    $groupIndex = $loop->iteration;
                    $records = is_array($group) || $group instanceof \ArrayAccess ? $group['records'] : $group;
                    $calculation = is_array($group) || $group instanceof \ArrayAccess ? ($group['calculation'] ?? null) : null;
                    if (! $calculation) {
                        $calculation = app(\App\Services\AmrCalculationService::class)->calculateForGroup($records);
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
                    $validRecoveries = $records->map(fn($r) => (float) $r->milling_recovery_percentage)->filter(fn($val) => $val > 0);
                    $mean = $validRecoveries->isNotEmpty() ? $validRecoveries->avg() : null;
                    $amrRateValue = $calculation->amrRate ?? $mean;
                    $pmrRateValue = is_array($group) || $group instanceof \ArrayAccess ? ($group['pmr_rate'] ?? null) : null;

                    $reestablishment = app(\App\Services\PmrCalculationService::class)->evaluateReestablishment($pmrRateValue, $amrRateValue);
                    $isAmrLowerThan60 = $amrRateValue !== null && $amrRateValue <= 60.0;
                    $isPmrLowerThanAmr = $reestablishment['is_pmr_below_amr'];
                    $isAmrDivergent = $reestablishment['is_amr_divergent_from_pmr'];

                    if ($records->isEmpty()) {
                        $statusBadge = 'badge-neutral';
                        $statusText = 'Pending (0/3)';
                    } elseif ($records->count() < 3 && $amrRateValue === null) {
                        $statusBadge = 'badge-neutral';
                        $statusText = $records->count() . '/3 trials';
                    } elseif ($isAmrLowerThan60) {
                        $statusBadge = 'badge-danger';
                        $statusText = 'AMR ≤ 60%';
                    } elseif ($isPmrLowerThanAmr) {
                        $statusBadge = 'badge-warning';
                        $statusText = 'PMR < AMR';
                    } elseif ($isAmrDivergent) {
                        $statusBadge = 'badge-warning';
                        $statusText = 'Divergent';
                    } elseif ($calculation->isValid) {
                        $statusBadge = 'badge-success';
                        $statusText = 'Recommended';
                    } elseif ($calculation->isInvalidOutliers()) {
                        $statusBadge = 'badge-danger';
                        $statusText = 'Invalid Outliers';
                    } else {
                        $statusBadge = 'badge-neutral';
                        $statusText = 'Incomplete';
                    }
                @endphp

                @for ($trial = 1; $trial <= 3; $trial++)
                    @php
                        $record = $trialRecords->get($trial);
                        $isLastTrial = ($trial === 3);
                    @endphp
                    <tr class="{{ $isLastTrial ? 'group-separator' : '' }}">
                        @if ($trial === 1)
                            <td rowspan="3" class="text-center font-bold">{{ $groupIndex }}</td>
                            <td rowspan="3" class="text-left">{{ $branchName }}</td>
                            <td rowspan="3" class="text-left font-bold">{{ $warehouseName }}</td>
                            <td rowspan="3" class="text-center font-bold">{{ $pileNumber }}</td>
                            <td rowspan="3" class="text-left">{{ $variety }}</td>
                            <td rowspan="3" class="text-right">{{ $purity !== null ? number_format((float) $purity, 2) : '—' }}</td>
                            <td rowspan="3" class="text-right">{{ $mc !== null ? number_format((float) $mc, 1) : '—' }}</td>
                            <td rowspan="3" class="text-center">{{ strtoupper(str_replace('_', ' ', $quality ?: '—')) }}</td>
                            <td rowspan="3" class="text-center">{{ $agedMonths }}</td>
                            <td rowspan="3" class="text-right font-bold">{{ number_format((float) $volumeKg / 50, 3) }}</td>
                            <td rowspan="3" class="text-left">{{ $riceMillers }}</td>
                        @endif

                        <td class="text-center">Trial {{ $trial }}</td>
                        <td class="text-right">{{ $record && $record->palay_input_kg !== null ? number_format((float) $record->palay_input_kg, 2) : '—' }}</td>
                        <td class="text-right">{{ $record && $record->rice_recovery_kg !== null ? number_format((float) $record->rice_recovery_kg, 2) : '—' }}</td>
                        <td class="text-right font-bold text-primary">{{ $record && $record->milling_recovery_percentage > 0 ? number_format($record->milling_recovery_percentage, 2) . '%' : '—' }}</td>

                        @if ($trial === 1)
                            <td rowspan="3" class="text-right font-bold">{{ $mean !== null ? number_format($mean, 2) . '%' : '—' }}</td>
                            <td rowspan="3" class="text-right font-bold text-primary">{{ $amrRateValue !== null ? number_format($amrRateValue, 2) . '%' : '—' }}</td>
                            <td rowspan="3" class="text-center">
                                <span class="badge {{ $statusBadge }}">{{ $statusText }}</span>
                            </td>
                        @endif
                    </tr>
                @endfor
            @empty
                <tr>
                    <td colspan="18" class="text-center" style="padding: 20px;">No AMR records found matching the criteria.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="footer-left">NFA GMR System &bull; Confidential</div>
        <div class="footer-center">Actual Milling Recovery (AMR) Report</div>
        <div class="footer-right">Page <script type="text/php">echo $pdf->get_page_number();</script> of <script type="text/php">echo $pdf->get_page_count();</script></div>
    </div>
</body>
</html>
