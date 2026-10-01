@extends('layouts.app')

@section('title', 'AMR Report')

@section('content')
<div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-xl font-semibold text-base-content">AMR Report</h1>
        <p class="text-xs text-base-content/60">Actual Milling Recovery (AMR) — Commercial milling results and statistical analysis</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('amr.export.excel', request()->query()) }}" class="btn btn-outline btn-success btn-sm" title="Export AMR report to Excel (.xlsx)">
            <span class="icon-[tabler--file-spreadsheet] size-4"></span>
            Excel Export
        </a>
        <a href="{{ route('amr.export.pdf', request()->query()) }}" class="btn btn-outline btn-error btn-sm" title="Download AMR report as PDF">
            <span class="icon-[tabler--file-type-pdf] size-4"></span>
            PDF Download
        </a>
        <a href="{{ route('records.create', ['type' => 'amr']) }}"
            class="btn btn-secondary btn-sm">
            <span class="icon-[tabler--plus] size-4"></span>
            Add AMR Trial
        </a>
    </div>
</div>

<form method="GET" action="{{ route('amr.index') }}" class="card mb-4 border border-base-content/10 bg-base-100 p-4 shadow-sm">
    <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-3">
        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Branch</span>
            @php
                $isStaffUser = (bool) auth()->user()?->isBranchRestricted();
            @endphp
            <select name="branch_id" class="select select-bordered min-h-11 w-full text-base text-black @if($isStaffUser) bg-gray-100 text-gray-500 cursor-not-allowed @endif" onchange="this.form.submit()" @disabled($isStaffUser)>
                @unless($isStaffUser)
                    <option value="">All Branches</option>
                @endunless
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($filters['branch_id'] === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
            @if($isStaffUser)
                <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
            @endif
        </label>
        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Warehouse</span>
            <select name="warehouse_id" class="select select-bordered min-h-11 w-full text-base text-black" onchange="this.form.submit()">
                <option value="">All Warehouses</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected($filters['warehouse_id'] === $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex gap-2">
            <a href="{{ route('amr.index') }}" class="btn btn-outline min-h-11 px-4 text-sm">Reset Filters</a>
        </div>
    </div>
</form>

<div class="card w-full shadow-sm border border-base-content/10 bg-base-100 overflow-hidden">
  <div class="w-full overflow-x-auto">
    <table class="table table-sm w-full text-sm">
      <thead>
        <tr class="bg-base-200/60 text-base-content border-b border-base-content/15 text-xs font-semibold uppercase tracking-wider">
          <th class="w-8 text-center px-2 py-2">No.</th>
          <th class="px-2.5 py-2">Branch</th>
          <th class="px-2.5 py-2">Warehouse</th>
          <th class="text-center px-2 py-2">Pile <br>No.</th>
          <th class="px-2.5 py-2">Variety</th>
          <th class="text-center px-2.5 py-2">Purity<br> (%)</th>
          <th class="text-end px-2.5 py-2">MC (%)</th>
          <th class="text-center px-2.5 py-2">Quality</th>
          <th class="text-center px-2.5 py-2">Aged <br> (mos)</th>
          <th class="text-center px-2.5 py-2">Volume <br>(bags)</th>
          <th class="px-2.5 py-2">Rice Miller</th>
          <th class="text-center px-2.5 py-2">Trial</th>
          <th class="text-center px-2.5 py-2">Palay <br> In (kg)</th>
          <th class="text-center px-2.5 py-2">Rice <br>Recovery (kg)</th>
          <th class="text-center px-2.5 py-2">Recovery <br>(%)</th>
          <th class="text-center px-2.5 py-2">Mean (%)</th>
          <th class="text-center px-2.5 py-2">AMR (%)</th>
          <th class="text-center px-2.5 py-2">Status</th>
          <th class="text-center w-16 px-2 py-2">Actions</th>
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
          $modalId = 'amr-calc-modal-' . ($pile?->id ?? $firstRecord?->pile_id ?? 'group-' . $groupIndex);
          $hasAmrTrials = $records->isNotEmpty();
          $isMri = $calculation->isMriEstablished();
          $isLowVolEligible = ($volumeKg > 0 && (float) $volumeKg <= 50000);
          $isSingleRow = $isMri || ($records->isEmpty() && $isLowVolEligible);
          $totalRows = $isSingleRow ? 1 : 3;
        @endphp
        @for ($trial = 1; $trial <= $totalRows; $trial++)
          @php $record = $isMri ? $records->first() : $trialRecords->get($trial); @endphp
          <tr class="{{ $trial === $totalRows ? 'border-b-2 border-base-content/20' : 'border-b border-base-content/10' }} hover:bg-base-200/30">
            @if ($trial === 1)
              <td rowspan="{{ $totalRows }}" class="text-center font-medium align-middle border-e border-base-content/10 px-2 py-1">
                {{ $groupIndex }}
              </td>
              <td rowspan="{{ $totalRows }}" class="align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $branchName }}
              </td>
              <td rowspan="{{ $totalRows }}" class="align-middle font-medium border-e border-base-content/10 px-2.5 py-1">
                {{ $warehouseName }}
              </td>
              <td rowspan="{{ $totalRows }}" class="text-center align-middle border-e border-base-content/10 px-2 py-1">
                <span class="font-semibold text-base-content">{{ $pileNumber }}</span>
              </td>
              <td rowspan="{{ $totalRows }}" class="align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $variety }}
              </td>
              <td rowspan="{{ $totalRows }}" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $purity !== null ? number_format((float) $purity, 2) : '—' }}
              </td>
              <td rowspan="{{ $totalRows }}" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $mc !== null ? number_format((float) $mc, 1) : '—' }}
              </td>
              <td rowspan="{{ $totalRows }}" class="text-center align-middle border-e border-base-content/10 px-2.5 py-1">
                @if ($quality)
                  <span class="badge badge-soft badge-primary text-xs uppercase">{{ strtoupper(str_replace('_', ' ', $quality)) }}</span>
                @else
                  —
                @endif
              </td>
              <td rowspan="{{ $totalRows }}" class="text-center align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $agedMonths }}
              </td>
              <td rowspan="{{ $totalRows }}" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ number_format((float) $volumeKg / 50, 3) }}
              </td>
              <td rowspan="{{ $totalRows }}" class="align-middle border-e border-base-content/20 font-medium px-2.5 py-1">
                {{ $riceMillers }}
              </td>
            @endif

            @if ($isMri)
              <td class="text-center align-middle px-2 py-1">
                <span class="badge badge-soft badge-info text-xs font-semibold px-1.5 py-0.5" title="Milling Recovery Index deduction per NFA Guideline C.3.10">C.3.10 (MRI)</span>
              </td>
              <td class="text-center font-mono text-base-content/40 align-middle px-2.5 py-1">
                <span class="text-xs italic" title="Commercial test milling exempt for &le; 50,000 kg">Exempt</span>
              </td>
              <td class="text-center font-mono text-base-content/40 align-middle px-2.5 py-1">
                <span class="text-xs italic" title="Commercial test milling exempt for &le; 50,000 kg">Exempt</span>
              </td>
              <td class="text-end font-mono font-medium align-middle border-e border-base-content/20 px-2.5 py-1 text-primary">
                <div>{{ number_format((float) $record->milling_recovery, 2) }}%</div>
                <div class="text-[10px] text-base-content/60 font-normal">PMR {{ number_format((float) $record->pmr_rate, 2) }}% &minus; {{ number_format((float) $record->mri_rate, 2) }}%</div>
              </td>
            @elseif ($isSingleRow && ! $hasAmrTrials)
              <td class="text-center align-middle px-2 py-1">
                <span class="badge badge-soft badge-neutral text-xs">Exempt / MRI</span>
              </td>
              <td class="text-center font-mono text-base-content/40 align-middle px-2.5 py-1">—</td>
              <td class="text-center font-mono text-base-content/40 align-middle px-2.5 py-1">—</td>
              <td class="text-center font-mono text-base-content/40 align-middle border-e border-base-content/20 px-2.5 py-1">—</td>
            @else
              <td class="text-center align-middle px-2 py-1">
                <span class="badge badge-soft badge-neutral text-xs font-semibold px-1.5 py-0.5">Trial {{ $trial }}</span>
              </td>
              <td class="text-end font-mono align-middle px-2.5 py-1">
                {{ $record && $record->palay_input_kg !== null ? number_format((float) $record->palay_input_kg, 2) : '—' }}
              </td>
              <td class="text-end font-mono align-middle px-2.5 py-1">
                {{ $record && $record->rice_recovery_kg !== null ? number_format((float) $record->rice_recovery_kg, 2) : '—' }}
              </td>
              <td class="text-end font-mono font-medium align-middle border-e border-base-content/20 px-2.5 py-1 {{ $record ? 'text-primary' : '' }}">
                {{ $record && $record->milling_recovery_percentage > 0 ? number_format($record->milling_recovery_percentage, 2) . '%' : '—' }}
              </td>
            @endif

            @if ($trial === 1)
              <td rowspan="{{ $totalRows }}" class="text-end font-mono font-semibold text-primary align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $isMri ? number_format((float) $amrRateValue, 2) . '%' : ($mean !== null ? number_format($mean, 2) . '%' : '—') }}
              </td>
              <td rowspan="{{ $totalRows }}" class="text-end font-mono font-semibold align-middle border-e border-base-content/10 px-2.5 py-1">
                @if (! $hasAmrTrials)
                  <span class="text-base-content/50">—</span>
                @elseif ($calculation->isValid)
                  <a href="javascript:void(0)"
                     class="font-mono font-semibold text-primary underline decoration-primary/40 hover:decoration-primary bg-transparent hover:bg-primary/10 px-1 py-0.5 rounded transition-colors cursor-pointer inline-flex items-center gap-1"
                     data-open-modal="#{{ $modalId }}"
                     data-overlay="#{{ $modalId }}"
                     title="Click to view AMR computation breakdown">
                    {{ $calculation->getFormattedAmrRate() }}
                    <span class="icon-[tabler--info-circle] size-3.5 text-primary"></span>
                  </a>
                @elseif ($calculation->isInvalidOutliers())
                  <a href="javascript:void(0)"
                     class="badge badge-soft badge-error text-xs cursor-pointer inline-flex items-center gap-1 px-1.5 py-0.5"
                     data-open-modal="#{{ $modalId }}"
                     data-overlay="#{{ $modalId }}"
                     title="Outliers exceeded tolerance — Click for computation details">
                    <span class="icon-[tabler--alert-triangle] size-3"></span>
                    Invalid
                  </a>
                @else
                  <a href="javascript:void(0)"
                     class="badge badge-soft badge-neutral text-xs cursor-pointer inline-flex items-center gap-1 px-1.5 py-0.5"
                     data-open-modal="#{{ $modalId }}"
                     data-overlay="#{{ $modalId }}"
                     title="Calculation Incomplete — Click for computation details">
                    <span class="icon-[tabler--clock] size-3"></span>
                    Incomplete
                  </a>
                @endif
              </td>
              <td rowspan="{{ $totalRows }}" class="text-center align-middle border-e border-base-content/10 px-2.5 py-1">
                @php
                  $reestablishment = app(\App\Services\PmrCalculationService::class)->evaluateReestablishment($pmrRateValue, $amrRateValue);
                  $isAmrLowerThan60 = $amrRateValue !== null && $amrRateValue <= 60.0;
                  $isPmrLowerThanAmr = $reestablishment['is_pmr_below_amr'];
                  $isAmrDivergent = $reestablishment['is_amr_divergent_from_pmr'];
                  $requiresReestablishment = $isAmrLowerThan60 || $isPmrLowerThanAmr || $isAmrDivergent;
                @endphp
                <div class="flex flex-col items-center justify-center gap-1">
                  @if (! $hasAmrTrials)
                    <span class="badge badge-soft badge-neutral text-xs font-medium px-2 py-0.5 whitespace-nowrap">
                      <span class="icon-[tabler--clock] size-3.5 mr-1"></span>
                      {{ $isLowVolEligible ? 'Pending MRI (Exempt)' : 'Pending AMR Trials (0/3)' }}
                    </span>
                  @elseif (! $isMri && $records->count() < 3 && $amrRateValue === null)
                    <span class="badge badge-soft badge-neutral text-xs px-1.5 py-0.5">{{ $records->count() }}/3 trials</span>
                  @else
                    @if ($isAmrLowerThan60)
                      <span class="badge badge-soft badge-secondary text-xs font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="AMR ({{ number_format($amrRateValue, 2) }}%) is 60.0% or below">
                        Lower than 60%
                      </span>
                    @endif
                    @if ($isPmrLowerThanAmr)
                      <span class="badge badge-soft badge-secondary text-xs font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="PMR ({{ number_format($pmrRateValue, 2) }}%) is lower than AMR ({{ number_format($amrRateValue, 2) }}%)">
                        PMR lower than AMR
                      </span>
                    @endif
                    @if ($isAmrDivergent)
                      <span class="badge badge-soft badge-secondary text-xs font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="AMR ({{ number_format($amrRateValue, 2) }}%) is less than PMR ({{ number_format($pmrRateValue, 2) }}%) by more than 3 percentage points">
                        AMR &lt; PMR by &gt;3%
                      </span>
                    @endif
                    @if ($calculation->isValid && ! $requiresReestablishment)
                      <span class="badge badge-soft badge-primary text-xs font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="AMR ({{ number_format($amrRateValue ?? 0, 2) }}%) meets all NFA requirements">
                        OK
                      </span>
                    @endif
                  @endif

                  @if ($pile && $pile->amr_status)
                    @php
                      $status = strtolower($pile->amr_status);
                      $isLocked = $records->isNotEmpty() && $records->every('is_locked');
                      $statusLabel = match($status) {
                        'recommend', 'recommended' => ($isLocked ? 'LOCKED - RECOMMENDED' : 'RECOMMENDED'),
                        'retest' => ($isLocked ? 'LOCKED - RETEST REQUIRED' : 'RETEST REQUIRED'),
                        default => strtoupper($pile->amr_status),
                      };
                      $badgeClass = match($status) {
                        'retest' => 'badge-secondary',
                        'recommend', 'recommended' => 'badge-primary',
                        default => 'badge-neutral',
                      };
                    @endphp
                    <span class="badge badge-soft {{ $badgeClass }} text-xs font-semibold px-2 py-0.5 whitespace-nowrap">{{ $statusLabel }}</span>
                  @endif
                </div>
              </td>
              <td rowspan="{{ $totalRows }}" class="align-middle text-center px-2 py-1">
                <div class="flex items-center justify-center gap-1">
                  @if ($pile && strtolower((string)$pile->amr_status) === 'retest')
                    @if (auth()->user()?->hasRole('STAFF', 'RMEC', 'ADMINISTRATOR'))
                      <a href="{{ route('records.create', ['type' => 'amr', 'pile_id' => $pile->id, 'retest' => 1]) }}"
                         class="btn btn-secondary btn-xs inline-flex items-center gap-1 font-semibold"
                         title="Create New AMR Test Milling Data for this Pile">
                        <span class="icon-[tabler--refresh] size-3.5"></span>
                        <span>CREATE RETEST</span>
                      </a>
                    @endif
                    @if (auth()->user()?->hasRole('ADMINISTRATOR'))
                      <button type="button" class="btn btn-outline btn-warning btn-xs inline-flex items-center gap-1"
                              data-open-modal="#reset-modal-amr-{{ $pile->id }}"
                              data-overlay="#reset-modal-amr-{{ $pile->id }}"
                              title="Administrator: Reset RMEC Action">
                        <span class="icon-[tabler--rotate-clockwise] size-3.5"></span>
                        <span>Reset</span>
                      </button>
                    @endif
                  @elseif ($pile && strtolower((string)$pile->amr_status) === 'recommended')
                    @if (auth()->user()?->hasRole('ADMINISTRATOR'))
                      <button type="button" class="btn btn-outline btn-warning btn-xs inline-flex items-center gap-1"
                              data-open-modal="#reset-modal-amr-{{ $pile->id }}"
                              data-overlay="#reset-modal-amr-{{ $pile->id }}"
                              title="Administrator: Reset RMEC Action">
                        <span class="icon-[tabler--rotate-clockwise] size-3.5"></span>
                        <span>Reset</span>
                      </button>
                    @else
                      <span class="text-xs text-base-content/60 font-medium">Locked</span>
                    @endif
                  @elseif (! $hasAmrTrials && ! auth()->user()?->hasRole('VIEWER'))
                    <a href="{{ route('records.create', ['type' => 'amr', 'pile_id' => $pile?->id]) }}"
                       class="btn btn-secondary btn-xs inline-flex items-center gap-1"
                       title="Add AMR Trials for this Pile">
                      <span class="icon-[tabler--plus] size-3.5"></span>
                      <span>Add Trials</span>
                    </a>
                  @elseif (! $hasAmrTrials)
                    <span class="text-xs text-base-content/40 italic">No trials</span>
                  @else
                    @if (auth()->user()?->hasRole('STAFF') && $records->every(fn($r) => ! $r->is_locked))
                      <a href="{{ route('records.create', ['type' => 'amr', 'pile_id' => $pile?->id]) }}"
                         class="btn btn-outline btn-primary btn-xs inline-flex items-center gap-1"
                         title="Edit AMR trials for this Pile">
                        <span class="icon-[tabler--pencil] size-3.5"></span>
                        <span>Edit</span>
                      </a>
                    @endif
                    @if (auth()->user()?->hasRole('RMEC', 'ADMINISTRATOR'))
                      <div class="dropdown relative inline-flex [--placement:bottom-end]">
                        <button id="amr-actions-{{ $pile->id }}" type="button" class="dropdown-toggle btn btn-primary btn-xs inline-flex items-center gap-1 cursor-pointer" aria-haspopup="menu" aria-expanded="false" aria-label="AMR actions" title="Choose AMR action">
                          <span class="icon-[tabler--check] size-3.5"></span>
                          <span>Actions</span>
                        </button>
                        <ul class="dropdown-menu dropdown-open:opacity-100 hidden min-w-40 shadow-md p-1.5 space-y-1" role="menu" aria-labelledby="amr-actions-{{ $pile->id }}">
                          @php
                            $statusActions = $calculation->isValid
                              ? [
                                  'recommend' => ['label' => 'Recommend', 'icon' => 'icon-[tabler--thumb-up]', 'color' => 'text-primary'],
                                  'retest' => ['label' => 'Retest', 'icon' => 'icon-[tabler--refresh]', 'color' => 'text-secondary'],
                                ]
                              : ($calculation->isInvalidOutliers() || $records->count() >= 3
                                ? [
                                    'retest' => ['label' => 'Retest', 'icon' => 'icon-[tabler--refresh]', 'color' => 'text-secondary'],
                                  ]
                                : []);
                          @endphp
                          @if ($statusActions)
                            @foreach ($statusActions as $action => $opt)
                              <li>
                                <form method="POST" action="{{ route('piles.rmec-action', $pile) }}">
                                  @csrf
                                  <input type="hidden" name="form_type" value="amr">
                                  <input type="hidden" name="action" value="{{ $action }}">
                                  <button type="submit" class="dropdown-item w-full flex items-center gap-2 {{ $opt['color'] }} cursor-pointer text-xs font-semibold py-1.5">
                                    <span class="{{ $opt['icon'] }} size-4"></span>
                                    {{ $opt['label'] }}
                                  </button>
                                </form>
                              </li>
                            @endforeach
                          @else
                            <li class="dropdown-item text-xs text-base-content/60">{{ $isLowVolEligible ? 'Pending MRI establishment' : 'Complete 3 trials first' }}</li>
                          @endif
                        </ul>
                      </div>
                    @endif
                  @endif
                </div>
              </td>
            @endif
          </tr>
        @endfor
        @empty
        <tr>
          <td colspan="19" class="py-8 text-center text-base-content/60">No AMR trial records or master piles found.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- Computation Detail Modals for Each Pile --}}
@foreach ($recordGroups as $group)
  @php
    $groupIndex = $loop->iteration;
    $records = is_array($group) || $group instanceof \ArrayAccess ? $group['records'] : $group;
    $calculation = is_array($group) || $group instanceof \ArrayAccess ? ($group['calculation'] ?? null) : null;
    if (! $calculation) {
        $calculation = app(\App\Services\AmrCalculationService::class)->calculateForGroup($records);
    }
    $firstRecord = $records->first();
    $pile = is_array($group) || $group instanceof \ArrayAccess ? ($group['pile'] ?? $firstRecord?->pile) : $firstRecord?->pile;
    $modalId = 'amr-calc-modal-' . ($pile?->id ?? $firstRecord?->pile_id ?? 'group-' . $groupIndex);
    $warehouseName = is_array($group) || $group instanceof \ArrayAccess ? ($group['warehouse_name'] ?? $pile?->warehouse?->name ?? $firstRecord?->warehouse_name ?? '—') : ($firstRecord?->warehouse_name ?? $pile?->warehouse?->name ?? '—');
    $pileNumber = is_array($group) || $group instanceof \ArrayAccess ? ($group['pile_number'] ?? $pile?->pile_number ?? $pile?->number ?? $firstRecord?->pile_number ?? '—') : ($pile?->pile_number ?? $pile?->number ?? $firstRecord?->pile_number ?? '—');
    $variety = is_array($group) || $group instanceof \ArrayAccess ? ($group['variety'] ?? $pile?->variety ?? $firstRecord?->variety ?? '—') : ($pile?->variety ?? $firstRecord?->variety ?? '—');
    $validRecoveries = $records->map(fn($r) => (float) $r->milling_recovery_percentage)->filter(fn($val) => $val > 0);
    $mean = $validRecoveries->isNotEmpty() ? $validRecoveries->avg() : null;
    $amrRateValue = $calculation->amrRate ?? $mean;
    $pmrRateValue = is_array($group) || $group instanceof \ArrayAccess ? ($group['pmr_rate'] ?? null) : null;
    $reestablishment = app(\App\Services\PmrCalculationService::class)->evaluateReestablishment($pmrRateValue, $amrRateValue);
    $hasAmrTrials = $records->isNotEmpty();
  @endphp
  <div id="{{ $modalId }}"
       class="amr-modal fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-xs transition-opacity duration-200"
       role="dialog"
       tabindex="-1"
       aria-modal="true"
       aria-labelledby="{{ $modalId }}-title">
    <div class="relative w-full max-w-3xl rounded-xl border border-base-content/15 bg-base-100 shadow-2xl overflow-hidden max-h-[90vh] flex flex-col my-auto">

      <!-- Modal Header -->
      <div class="flex items-center justify-between border-b border-base-content/10 px-4 py-3 bg-base-200/50 shrink-0">
        <div>
          <h3 id="{{ $modalId }}-title" class="font-semibold text-base text-base-content flex items-center gap-1.5">
            <span class="icon-[tabler--calculator] size-4 text-primary"></span>
            AMR Statistical Evaluation &mdash; AMR Computation Breakdown
          </h3>
          <p class="text-xs text-base-content/60">
            {{ $warehouseName }} &bull; Pile {{ $pileNumber }} &bull; {{ $variety }}
          </p>
        </div>
        <button type="button"
                class="btn btn-ghost btn-circle btn-xs text-base-content/70 hover:text-base-content cursor-pointer"
                data-close-modal="{{ $modalId }}"
                aria-label="Close modal">
          <span class="icon-[tabler--x] size-4"></span>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-4 space-y-4 overflow-y-auto text-xs">

        {{-- Status Alert Banner --}}
        @if ($calculation->isMriEstablished())
          <x-alert-box type="info" size="sm" icon="icon-[tabler--certificate]">
            <div>
              <span class="font-bold text-sm block">NFA Guideline C.3.10 — Stockpiles &lt; 50,000 kg (&lt; 1,000 bags)</span>
              <p class="mt-0.5 text-base-content/80">
                Piles with total quantity below 50,000 kg are exempt from commercial test milling. AMR is established directly by deducting the Milling Recovery Index (MRI) of up to 3.00% from PMR (PNS/BAFS 303:2020).
              </p>
            </div>
          </x-alert-box>

          {{-- MRI Computation Breakdown Card --}}
          <div class="bg-base-200/50 rounded-xl p-4 border border-base-content/10 space-y-3">
            <h4 class="text-xs font-bold uppercase tracking-wider text-base-content/70">Milling Recovery Index (MRI) Deduction Breakdown</h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-center">
              <div class="bg-base-100 rounded-lg p-3 border border-base-content/10">
                <span class="text-xs text-base-content/60 block font-medium">1. Base PMR Rate</span>
                <span class="font-mono font-bold text-base-content text-base">{{ number_format((float) $calculation->pmrRate, 2) }}%</span>
                <span class="text-[10px] text-base-content/50 block mt-0.5">Potential Milling Recovery</span>
              </div>
              <div class="bg-base-100 rounded-lg p-3 border border-base-content/10">
                <span class="text-xs text-secondary block font-medium">2. Rate of MRI (Deduction)</span>
                <span class="font-mono font-bold text-secondary text-base">&minus;{{ number_format((float) $calculation->mriRate, 2) }}%</span>
                <span class="text-[10px] text-base-content/50 block mt-0.5">Standard Deduction (Max 3.00%)</span>
              </div>
              <div class="bg-primary/10 rounded-lg p-3 border border-primary/30">
                <span class="text-xs text-primary block font-bold">3. Established AMR</span>
                <span class="font-mono font-bold text-primary text-lg">{{ $calculation->getFormattedAmrRate() }}</span>
                <span class="text-[10px] text-primary/80 block mt-0.5">AMR = PMR &minus; MRI</span>
              </div>
            </div>
            @php
              $mriExplanation = $calculation->getMriRemarks() ?: ($records->first()?->mri_remarks ?? null);
            @endphp
            {{-- Explanation of the MRI Used (Positioned directly below the 3 cards in the highlighted area) --}}
            <div class="p-3 bg-base-100 rounded-lg border border-base-content/10 text-xs space-y-1">
              <div class="flex items-center gap-1.5 font-semibold text-base-content/80">
                <span class="icon-[tabler--file-description] size-4 text-primary shrink-0"></span>
                <span>Explanation of the MRI Used</span>
              </div>
              <p class="text-base-content/70 leading-relaxed pl-5.5 whitespace-pre-line">{{ $mriExplanation ?: 'No explanation provided.' }}</p>
            </div>
            <div class="p-2.5 bg-base-100 rounded-lg border border-base-content/10 text-xs text-base-content/70 flex items-center justify-between">
              <span>Formula: <strong>AMR (%) = PMR (%) &minus; MRI Rate (%)</strong></span>
              <span class="badge badge-soft badge-primary font-semibold">Valid C.3.10 Establishment</span>
            </div>
          </div>
        @elseif ($calculation->isValid)
          <x-alert-box type="primary" size="sm" icon="icon-[tabler--circle-check]" title="Calculation Valid:" :message="$calculation->statusMessage" />
        @elseif ($calculation->isInvalidOutliers())
          <x-alert-box type="error" size="sm" icon="icon-[tabler--alert-triangle]" title="Invalid — Outliers Detected:" :message="$calculation->statusMessage" />
        @else
          <x-alert-box type="neutral" size="sm" icon="icon-[tabler--clock]" title="Incomplete Trials:" :message="$calculation->statusMessage" />
        @endif

        @if (! $calculation->isMriEstablished())
        {{-- Statistical Summary Cards --}}
        <div>
          <h4 class="text-xs font-semibold uppercase tracking-wider text-base-content/60 mb-1.5">1. Statistical Distribution (±2% Tolerance)</h4>
          <div class="grid grid-cols-3 gap-2 text-center">
            <div class="bg-base-200/50 rounded-lg p-2 border border-base-content/10">
              <span class="text-xs text-base-content/60 block">Lower Limit (-2%)</span>
              <span class="font-mono font-semibold text-base-content text-sm">{{ $calculation->getFormattedLowerLimit() }}</span>
            </div>
            <div class="bg-base-200/50 rounded-lg p-2 border border-primary/20 bg-primary/5">
              <span class="text-xs text-primary block font-medium">Median Recovery</span>
              <span class="font-mono font-bold text-primary text-sm">{{ $calculation->getFormattedMedian() }}</span>
            </div>
            <div class="bg-base-200/50 rounded-lg p-2 border border-base-content/10">
              <span class="text-xs text-base-content/60 block">Upper Limit (+2%)</span>
              <span class="font-mono font-semibold text-base-content text-sm">{{ $calculation->getFormattedUpperLimit() }}</span>
            </div>
          </div>
          <p class="text-xs text-base-content/50 mt-1 italic text-center">
            Formula: Median = Trial 2 of 3 sorted recoveries &bull; Limits = Median &plusmn; 2%
          </p>
        </div>

        {{-- Trial Evaluation Breakdown Table --}}
        <div>
          <h4 class="text-xs font-semibold uppercase tracking-wider text-base-content/60 mb-1.5">2. Trial Evaluation &amp; Outlier Status</h4>
          <div class="overflow-x-auto border border-base-content/10 rounded-lg">
            <table class="table table-sm w-full text-sm">
              <thead class="bg-base-200/60 text-xs uppercase font-semibold text-base-content">
                <tr>
                  <th class="py-1 px-2">Trial</th>
                  <th class="text-end py-1 px-2">Palay Input (kg)</th>
                  <th class="text-end py-1 px-2">Rice Rec (kg)</th>
                  <th class="text-end py-1 px-2">Recovery %</th>
                  <th class="text-center py-1 px-2">Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($calculation->trials as $trialRow)
                  <tr class="border-b border-base-content/10">
                    <td class="font-medium py-1 px-2">Trial {{ $trialRow['trial_number'] }}</td>
                    <td class="text-end font-mono py-1 px-2">{{ $trialRow['palay_input_kg'] !== null ? number_format((float) $trialRow['palay_input_kg'], 2) : '—' }}</td>
                    <td class="text-end font-mono py-1 px-2">{{ $trialRow['rice_recovery_kg'] !== null ? number_format((float) $trialRow['rice_recovery_kg'], 2) : '—' }}</td>
                    <td class="text-end font-mono font-semibold py-1 px-2 text-primary">{{ number_format($trialRow['milling_recovery'], 2) }}%</td>
                    <td class="text-center py-1 px-2">
                      @if ($trialRow['status'] === 'VALID')
                        <span class="badge badge-soft badge-primary text-xs font-medium inline-flex items-center gap-1 py-0 px-1.5">
                          <span class="icon-[tabler--check] size-3"></span>
                          VALID
                        </span>
                      @elseif ($trialRow['status'] === 'OUTLIER')
                        <span class="badge badge-soft badge-secondary text-xs font-medium inline-flex items-center gap-1 py-0 px-1.5">
                          <span class="icon-[tabler--alert-circle] size-3"></span>
                          OUTLIER (Excluded)
                        </span>
                      @else
                        <span class="badge badge-soft badge-neutral text-xs font-medium py-0 px-1.5">PENDING</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="py-4 text-center text-base-content/60">No AMR trials entered yet for this pile.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        @endif

        {{-- Final Result & Re-establishment Checks --}}
        <div class="bg-base-200/40 p-3 rounded-lg border border-base-content/10 space-y-2">
          <h4 class="text-xs font-semibold uppercase tracking-wider text-base-content/60">3. Final AMR Establishment &amp; Re-establishment Criteria</h4>
          <div class="flex items-center justify-between text-xs">
            <div>
              <p class="text-base-content/80">
                <span class="font-medium text-base-content">Valid Trials Used:</span> {{ $calculation->validTrialCount }} of 3
                @if ($calculation->outlierCount > 0)
                  <span class="text-secondary font-medium">({{ $calculation->outlierCount }} outlier excluded)</span>
                @endif
              </p>
              <p class="text-xs text-base-content/60">
                AMR = Arithmetic mean of valid commercial trials
              </p>
            </div>
            <div class="text-end">
              <span class="text-xs text-base-content/60 block">Computed AMR</span>
              <span class="text-xl font-bold font-mono text-primary leading-tight">{{ $calculation->getFormattedAmrRate() }}</span>
            </div>
          </div>

          <div class="pt-2 border-t border-base-content/10 space-y-1.5 text-xs">
            {{-- Check 1: 60.0% Benchmark --}}
            <div class="flex items-center justify-between">
              <div>
                <span class="text-base-content/80 font-medium">Benchmark Rule (AMR &gt; 60.0%):</span>
                <p class="text-xs text-base-content/60">Flagged if AMR is 60.0% or below</p>
              </div>
              @if ($amrRateValue !== null && $amrRateValue <= 60.0)
                <span class="badge badge-soft badge-secondary text-xs font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                  AMR &le; 60.0% (Failed)
                </span>
              @elseif ($amrRateValue !== null)
                <span class="badge badge-soft badge-primary text-xs font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--circle-check] size-3.5 mr-1"></span>
                  OK (&gt; 60.0%)
                </span>
              @else
                <span class="badge badge-soft badge-neutral text-xs py-0.5 px-2">—</span>
              @endif
            </div>

            {{-- Check 2: PMR vs AMR --}}
            @if ($pmrRateValue !== null)
              <div class="flex items-center justify-between">
                <div>
                  <span class="text-base-content/80 font-medium">Recovery Relationship (PMR &ge; AMR):</span>
                  <p class="text-xs text-base-content/60">PMR: {{ number_format($pmrRateValue, 2) }}% &bull; AMR: {{ $amrRateValue !== null ? number_format($amrRateValue, 2).'%' : '—' }}</p>
                </div>
                @if ($reestablishment['is_pmr_below_amr'])
                  <span class="badge badge-soft badge-secondary text-xs font-semibold py-0.5 px-2">
                    <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                    PMR &lt; AMR (Failed)
                  </span>
                @elseif ($amrRateValue !== null)
                  <span class="badge badge-soft badge-primary text-xs font-semibold py-0.5 px-2">
                    <span class="icon-[tabler--circle-check] size-3.5 mr-1"></span>
                    OK (PMR &ge; AMR)
                  </span>
                @else
                  <span class="badge badge-soft badge-neutral text-xs py-0.5 px-2">—</span>
                @endif
              </div>

              {{-- Check 3: AMR Divergence Rule --}}
              <div class="flex items-center justify-between">
                <div>
                  <span class="text-base-content/80 font-medium">Spread Rule (AMR within 3.0% of PMR):</span>
                  @php
                    $diff = ($amrRateValue !== null) ? round($pmrRateValue - $amrRateValue, 4) : null;
                  @endphp
                  <p class="text-xs text-base-content/60">Spread (PMR - AMR): {{ $diff !== null ? number_format($diff, 2).' pts' : '—' }} (Max 3.0 pts)</p>
                </div>
                @if ($reestablishment['is_amr_divergent_from_pmr'])
                  <span class="badge badge-soft badge-secondary text-xs font-semibold py-0.5 px-2">
                    <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                    AMR &lt; PMR by &gt; 3.0% (Failed)
                  </span>
                @elseif ($amrRateValue !== null)
                  <span class="badge badge-soft badge-primary text-xs font-semibold py-0.5 px-2">
                    <span class="icon-[tabler--circle-check] size-3.5 mr-1"></span>
                    OK (Spread &le; 3.0 pts)
                  </span>
                @else
                  <span class="badge badge-soft badge-neutral text-xs py-0.5 px-2">—</span>
                @endif
              </div>
            @endif

            {{-- Summary Re-establishment Determination --}}
            <div class="pt-2 border-t border-base-content/10 flex items-center justify-between">
              <span class="font-semibold text-base-content">Re-establishment Status:</span>
              @if (! $hasAmrTrials)
                <span class="badge badge-soft badge-neutral text-xs font-bold py-1 px-2.5">
                  <span class="icon-[tabler--clock] size-3.5 mr-1"></span>
                  PENDING AMR TRIALS (0/3)
                </span>
              @elseif ($reestablishment['requires_reestablishment'] || ($amrRateValue !== null && $amrRateValue <= 60.0))
                <span class="badge badge-soft badge-secondary text-xs font-bold py-1 px-2.5">
                  <span class="icon-[tabler--refresh] size-3.5 mr-1"></span>
                  RE-ESTABLISHMENT REQUIRED
                </span>
              @elseif ($calculation->isValid)
                <span class="badge badge-soft badge-primary text-xs font-bold py-1 px-2.5">
                  <span class="icon-[tabler--check] size-3.5 mr-1"></span>
                  ESTABLISHED / PASSED
                </span>
              @else
                <span class="badge badge-soft badge-neutral text-xs py-1 px-2.5">PENDING</span>
              @endif
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="border-t border-base-content/10 px-4 py-2.5 bg-base-200/30 flex justify-end gap-2 shrink-0">
        @if (! $hasAmrTrials)
          <a href="{{ route('records.create', ['type' => 'amr', 'pile_id' => $pile?->id]) }}" class="btn btn-secondary btn-sm">
            <span class="icon-[tabler--plus] size-4"></span>
            Add AMR Trials
          </a>
        @endif
        <button type="button" class="btn btn-soft btn-secondary btn-sm cursor-pointer" data-close-modal="{{ $modalId }}">Close</button>
      </div>
    </div>
  </div>

  @if (auth()->user()?->hasRole('ADMINISTRATOR') && $pile && in_array(strtolower((string)$pile->amr_status), ['recommended', 'retest'], true))
    <div id="reset-modal-amr-{{ $pile->id }}" class="amr-modal hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs items-center justify-center p-4">
      <div class="card bg-base-100 max-w-lg w-full shadow-2xl border border-warning/30 rounded-box p-6 relative">
        <div class="flex items-center justify-between pb-3 border-b border-base-content/10 mb-4">
          <div class="flex items-center gap-2">
            <span class="icon-[tabler--alert-circle] size-6 text-warning"></span>
            <h3 class="text-base font-bold text-base-content">Reset RMEC Action — Pile {{ $pile->pile_number ?? $pile->number }}</h3>
          </div>
          <button type="button" class="btn btn-ghost btn-circle btn-xs" data-close-modal="reset-modal-amr-{{ $pile->id }}">✕</button>
        </div>
        <form method="POST" action="{{ route('piles.rmec-reset', $pile) }}">
          @csrf
          <input type="hidden" name="form_type" value="amr">
          <p class="text-sm text-base-content/80 mb-3">
            This will mark the current RMEC action (<strong class="uppercase text-primary">{{ $pile->amr_status }}</strong>) as <strong>RESET/SUPERSEDED</strong> and return the AMR test milling conduct to <strong>PENDING</strong> status. Historical test-milling data will remain preserved.
          </p>
          <div class="mb-4">
            <label class="label label-text font-semibold text-xs mb-1" for="reason-amr-{{ $pile->id }}">Reset Reason <span class="text-error">*</span></label>
            <textarea id="reason-amr-{{ $pile->id }}" name="reason" rows="3" required minlength="3" maxlength="1000" class="textarea textarea-bordered w-full text-sm" placeholder="State the reason why this RMEC action is being reset..."></textarea>
          </div>
          <div class="flex items-center justify-end gap-2">
            <button type="button" class="btn btn-ghost btn-sm" data-close-modal="reset-modal-amr-{{ $pile->id }}">Cancel</button>
            <button type="submit" class="btn btn-warning btn-sm font-semibold">Confirm Reset</button>
          </div>
        </form>
      </div>
    </div>
  @endif
@endforeach

<script>
(function () {
    function openModal(modal) {
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        if (!document.querySelector('.amr-modal:not(.hidden)')) {
            document.body.classList.remove('overflow-hidden');
        }
    }

    document.addEventListener('click', function (e) {
        // Open trigger
        const openTrigger = e.target.closest('[data-open-modal], [data-overlay]');
        if (openTrigger) {
            const selector = openTrigger.getAttribute('data-open-modal') || openTrigger.getAttribute('data-overlay');
            if (selector && (selector.startsWith('#amr-calc-modal-') || selector.startsWith('#reset-modal-amr-'))) {
                e.preventDefault();
                e.stopPropagation();
                const modal = document.querySelector(selector);
                openModal(modal);
                return;
            }
        }

        // Close trigger
        const closeTrigger = e.target.closest('[data-close-modal]');
        if (closeTrigger) {
            e.preventDefault();
            e.stopPropagation();
            const modalId = closeTrigger.getAttribute('data-close-modal');
            const modal = modalId ? document.getElementById(modalId) : closeTrigger.closest('.amr-modal');
            closeModal(modal);
            return;
        }

        // Backdrop click
        if (e.target.classList.contains('amr-modal')) {
            closeModal(e.target);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.amr-modal:not(.hidden)');
            openModals.forEach((modal) => closeModal(modal));
        }
    });
})();
</script>
@endsection
