<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $config->title ?? 'REPORT ON PRE-MILLING ACTIVITY' }}</title>
    <style>
        @page {
            margin: {{ $config->getCssMargins() }};
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
            font-size: 9pt;
            line-height: 1.2;
        }

        .header-section {
            text-align: center;
            margin-bottom: 12px;
        }

        .header-title {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }

        .header-subtitle {
            font-size: 9.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 2px 0;
        }

        .header-region {
            font-size: 9.5pt;
            font-weight: bold;
            margin: 2px 0;
        }

        .header-branch {
            font-size: 9.5pt;
            font-weight: bold;
            margin: 2px 0 10px 0;
        }

        .gmr-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .gmr-table th,
        .gmr-table td {
            border: 1px solid #000;
            padding: 3px 5px;
            font-size: 8pt;
        }

        .gmr-table th {
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            background-color: #fff;
        }

        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }

        .signatories-section {
            margin-top: 15px;
            page-break-inside: avoid;
        }

        .signatories-header {
            font-size: 9.5pt;
            font-weight: bold;
            text-align: center;
            margin: 4px 0 2px 0;
        }

        .signatories-subheader {
            font-size: 9pt;
            font-weight: bold;
            text-align: center;
            margin: 0 0 10px 0;
        }

        .signatories-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            margin-bottom: 10px;
        }

        .signatories-table td {
            border: none;
            padding: 6px 10px;
            text-align: center;
            vertical-align: top;
            width: 50%;
        }

        .signatory-name {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 180px;
            padding-bottom: 2px;
            margin-bottom: 2px;
        }

        .signatory-position {
            font-size: 7.5pt;
            font-style: italic;
        }

        .signatory-center {
            text-align: center;
            margin: 8px 0;
        }

        .reviewed-section {
            margin-top: 14px;
            text-align: center;
        }

        .reviewed-header {
            font-size: 9pt;
            font-weight: bold;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-title">{{ $config->title }}</div>
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
            @forelse ($rows as $row)
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
                    <td colspan="8" class="text-center">No GMR records selected.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Signatories Section -->
    @php
        $activeList = $signatories ?? collect();
        $members = $activeList->filter(fn ($s) => in_array($s->role_group, ['member', 'default', null]))->values();
        $chairpersons = $activeList->filter(fn ($s) => $s->role_group === 'chairperson');
        $coa = $activeList->filter(fn ($s) => $s->role_group === 'coa');
        $reviewers = $activeList->filter(fn ($s) => $s->role_group === 'reviewer');
    @endphp

    <div class="signatories-section">
        <div class="signatories-header">Prepared and Recommended By:</div>
        <div class="signatories-subheader">Regional Milling Committee (RMEC) Members:</div>

        @if ($members->isNotEmpty())
            <table class="signatories-table">
                @for ($i = 0; $i < $members->count(); $i += 2)
                    <tr>
                        <td>
                            @if (isset($members[$i]))
                                <div class="signatory-name">{{ $members[$i]->name }}</div>
                                <div class="signatory-position">{{ $members[$i]->position }}</div>
                            @endif
                        </td>
                        <td>
                            @if (isset($members[$i + 1]))
                                <div class="signatory-name">{{ $members[$i + 1]->name }}</div>
                                <div class="signatory-position">{{ $members[$i + 1]->position }}</div>
                            @endif
                        </td>
                    </tr>
                @endfor
            </table>
        @endif

        @foreach ($chairpersons as $chair)
            <div class="signatory-center" style="margin-top: 10px;">
                <div class="signatory-name">{{ $chair->name }}</div>
                <div class="signatory-position">{{ $chair->position }}</div>
            </div>
        @endforeach

        @foreach ($coa as $coaRep)
            <div class="signatory-center" style="margin-top: 12px;">
                <div style="width: 180px; border-bottom: 1px solid #000; margin: 0 auto 3px auto; height: 12px;"></div>
                <div class="signatory-position" style="font-weight: bold; font-style: normal;">{{ $coaRep->name }}</div>
            </div>
        @endforeach

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
</body>
</html>
