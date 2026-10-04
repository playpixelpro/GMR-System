@extends('layouts.app')

@section('title', 'PMR Report')

@section('content')
<div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-xl font-semibold text-base-content">PMR Report</h1>
        <p class="text-xs text-base-content/60">Potential Milling Recovery (PMR) — 3 laboratory test milling trials under NFA recovery standards</p>
        <p class="mt-1 text-xs font-medium text-base-content/70">Note: Volume values shown are before test milling.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <div class="dropdown relative inline-flex [--placement:bottom-end]">
            <button id="pmr-columns-dropdown-btn" type="button" class="dropdown-toggle btn btn-outline btn-neutral btn-sm" aria-haspopup="menu" aria-expanded="false" title="Show or hide table columns">
                <span class="icon-[tabler--columns] size-4"></span>
                Columns
                <span class="icon-[tabler--chevron-down] size-3.5 ms-1"></span>
            </button>
            <div class="dropdown-menu dropdown-open:opacity-100 hidden min-w-64 max-h-96 overflow-y-auto p-3 shadow-xl bg-base-100 border border-base-content/15 rounded-box z-50" role="menu" aria-labelledby="pmr-columns-dropdown-btn">
                <div class="flex items-center justify-between pb-2 mb-2 border-b border-base-content/10">
                    <span class="text-xs font-bold uppercase tracking-wider text-base-content/70">Toggle Columns</span>
                    <button type="button" id="pmr-reset-columns-btn" class="text-xs text-primary hover:underline font-semibold">Reset to Default</button>
                </div>
                <div class="space-y-1.5" id="pmr-column-checkboxes">
                    @foreach (\App\Models\ReportColumnSetting::availableColumns('pmr') as $colKey => $colMeta)
                        <label class="flex items-center gap-2.5 px-1 py-1 rounded hover:bg-base-200/50 cursor-pointer text-xs">
                            <input type="checkbox"
                                   class="checkbox checkbox-primary checkbox-xs col-toggle-cb"
                                   data-target-col="{{ $colKey }}"
                                   {{ in_array($colKey, $visibleColumns ?? []) ? 'checked' : '' }}>
                            <span class="text-base-content font-medium">{{ $colMeta['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="pt-2 mt-2 border-t border-base-content/10 flex justify-between items-center text-[11px] text-base-content/60">
                    <span>Saved in browser</span>
                    @if (auth()->user()?->hasRole('ADMINISTRATOR', 'RMEC'))
                        <a href="{{ route('settings.reports.columns') }}" class="text-primary hover:underline font-medium inline-flex items-center gap-0.5">
                            <span class="icon-[tabler--settings] size-3"></span>
                            Defaults
                        </a>
                    @endif
                </div>
            </div>
        </div>
        <a href="{{ route('pmr.export.excel', request()->query()) }}" class="btn btn-outline btn-success btn-sm" title="Export PMR report to Excel (.xlsx)">
            <span class="icon-[tabler--file-spreadsheet] size-4"></span>
            Excel Export
        </a>
        <a href="{{ route('pmr.export.pdf', request()->query()) }}" class="btn btn-outline btn-error btn-sm" title="Download PMR report as PDF">
            <span class="icon-[tabler--file-type-pdf] size-4"></span>
            PDF Download
        </a>
        <a href="{{ route('records.create', ['type' => 'pmr']) }}"
            class="btn btn-secondary btn-sm">
            <span class="icon-[tabler--plus] size-4"></span>
            Add PMR Trial
        </a>
    </div>
</div>

<form method="GET" action="{{ route('pmr.index') }}" class="card mb-4 border border-base-content/10 bg-base-100 p-4 shadow-sm">
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
            <a href="{{ route('pmr.index') }}" class="btn btn-outline min-h-11 px-4 text-sm">Reset Filters</a>
        </div>
    </div>
</form>

<div class="card w-full shadow-sm border border-base-content/10 bg-base-100 overflow-hidden">
  <div class="w-full overflow-x-auto">
    <table class="table table-sm w-full text-sm">
      <thead>
        <tr class="bg-base-200/60 text-base-content border-b border-base-content/15 text-xs font-semibold uppercase tracking-wider">
          <th data-col="no" class="w-8 text-center px-2 py-2">NO.</th>
          <th data-col="branch" class="px-2.5 py-2">BRANCH</th>
          <th data-col="warehouse" class="px-2.5 py-2">WAREHOUSE NAME</th>
          <th data-col="pile_number" class="text-center px-2 py-2">PILE <br> NUMBER</th>
          <th data-col="variety" class="px-2.5 py-2">VARIETY</th>
          <th data-col="purity" class="text-end px-2.5 py-2">PURITY</th>
          <th data-col="mc" class="text-end px-2.5 py-2">MC</th>
          <th data-col="quality" class="text-center px-2.5 py-2">QUALITY <br> (CONDITION)</th>
          <th data-col="aged_months" class="text-center px-2.5 py-2">AGED <br>( in months)</th>
          <th data-col="volume_bags" class="text-center px-2.5 py-2">VOLUME <br> (bags)</th>
          <th data-col="trial" class="text-center px-2.5 py-2">NO. OF <br> TRIAL</th>
          <th data-col="recovery_rate" class="text-center px-2.0 py-2">RECOVERY<br> RATE (%)</th>
          <th data-col="mean" class="text-center px-2.5 py-2">MEAN (%)</th>
          <th data-col="pmr_rate" class="text-center px-2.5 py-2">PMR <br> (%)</th>
          <th data-col="status" class="text-center px-2.5 py-2">STATUS</th>
          <th data-col="actions" class="text-center w-24 px-2 py-2">ACTIONS</th>
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
          $modalId = 'pmr-calc-modal-' . ($pile?->id ?? $firstRecord?->pile_id ?? 'group-' . $groupIndex);
          $hasPmrTrials = $records->isNotEmpty();
        @endphp
        @for ($trial = 1; $trial <= $maxTrials; $trial++)
          @php $record = $trialRecords->get($trial); @endphp
          <tr class="{{ $trial === $maxTrials ? 'border-b-2 border-base-content/20' : 'border-b border-base-content/10' }} hover:bg-base-200/30">
            @if ($trial === 1)
              <td data-col="no" rowspan="{{ $maxTrials }}" class="text-center font-medium align-middle border-e border-base-content/10 px-2 py-1">
                {{ $groupIndex }}
              </td>
              <td data-col="branch" rowspan="{{ $maxTrials }}" class="align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $branchName }}
              </td>
              <td data-col="warehouse" rowspan="{{ $maxTrials }}" class="align-middle font-medium border-e border-base-content/10 px-2.5 py-1">
                {{ $warehouseName }}
              </td>
              <td data-col="pile_number" rowspan="{{ $maxTrials }}" class="text-center align-middle border-e border-base-content/10 px-2 py-1">
                <span class="font-semibold text-base-content">{{ $pileNumber }}</span>
              </td>
              <td data-col="variety" rowspan="{{ $maxTrials }}" class="align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $variety }}
              </td>
              <td data-col="purity" rowspan="{{ $maxTrials }}" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ number_format((float) $purity, 2) }}
              </td>
              <td data-col="mc" rowspan="{{ $maxTrials }}" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ number_format((float) $mc, 1) }}
              </td>
              <td data-col="quality" rowspan="{{ $maxTrials }}" class="text-center align-middle border-e border-base-content/10 px-2.5 py-1">
                <span class="badge badge-soft badge-primary text-xs uppercase">{{ strtoupper(str_replace('_', ' ', $quality ?? '')) }}</span>
              </td>
              <td data-col="aged_months" rowspan="{{ $maxTrials }}" class="text-center align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $agedMonths }}
              </td>
              <td data-col="volume_bags" rowspan="{{ $maxTrials }}" class="text-end font-mono align-middle border-e border-base-content/20 px-2.5 py-1">
                {{ number_format((float) $volumeKg / 50, 3) }}
              </td>
            @endif

            <td data-col="trial" class="text-center align-middle px-2 py-1 border-e border-base-content/10">
              <span class="font-semibold text-base-content">{{ $trial }}</span>
            </td>
            <td data-col="recovery_rate" class="text-end font-mono align-middle border-e border-base-content/20 px-2.5 py-1 {{ $record ? 'text-primary font-medium' : '' }}">
              <span class="sr-only">Trial {{ $trial }} Recovery Rate (%)</span>
              {{ $record ? number_format($record->recovery_rate_percentage, 2) : '—' }}
            </td>

            @if ($trial === 1)
              <td data-col="mean" rowspan="{{ $maxTrials }}" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                <div class="flex flex-col items-end">
                  <span class="font-medium text-base-content">{{ $mean !== null ? number_format($mean, 2) : '—' }}</span>
                  <span class="text-xs text-base-content/60">{{ $stdDev !== null ? 's = '.number_format($stdDev, 2) : '—' }}</span>
                </div>
              </td>
              <td data-col="pmr_rate" rowspan="{{ $maxTrials }}" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                <div class="flex flex-col items-end gap-0.5">
                  @if (! $hasPmrTrials)
                    <span class="font-medium text-base-content/50" title="No PMR trials recorded yet">—</span>
                    <span class="text-xs text-base-content/50">0/3 trials</span>
                  @elseif ($calculation->isHistoricalLegacy())
                    <span class="font-bold text-base-content/60 line-through" title="Historical legacy PMR ({{ count($calculation->trials) }} trials). Re-establishment required.">
                      {{ $mean !== null ? number_format($mean, 2).'%' : '—' }}
                    </span>
                    <span class="badge badge-soft badge-neutral text-xs px-1 py-0 uppercase">Historical</span>
                  @elseif ($calculation->isValid)
                    <span class="font-bold text-primary text-sm" title="Recommended PMR of {{ $calculation->getFormattedPmrRate() }} computed from {{ $calculation->validTrialCount }} valid trials (CV: {{ $calculation->getFormattedCv() }})">
                      {{ $calculation->getFormattedPmrRate() }}
                    </span>
                    <span class="text-xs text-primary/80 font-mono">CV {{ $calculation->getFormattedCv() }}</span>
                  @elseif ($calculation->isIncomplete())
                    <span class="font-medium text-base-content/50" title="{{ $calculation->statusMessage }}">—</span>
                    <span class="text-xs text-base-content/50">{{ count($calculation->trials) }}/3 trials</span>
                  @else
                    <span class="font-medium text-secondary" title="{{ $calculation->statusMessage }}">Invalid</span>
                    <span class="text-xs text-secondary font-mono">{{ $calculation->isInvalidCv() ? 'CV > 5%' : 'Outliers' }}</span>
                  @endif

                  {{-- Trigger for computation breakdown modal --}}
                  <a href="javascript:void(0)"
                     class="inline-flex items-center gap-1 text-xs text-primary bg-transparent hover:bg-primary/10 rounded px-1.5 py-0.5 mt-0.5 font-normal cursor-pointer"
                     data-open-modal="#{{ $modalId }}"
                     data-overlay="#{{ $modalId }}"
                     aria-haspopup="dialog"
                     aria-expanded="false"
                     aria-controls="{{ $modalId }}"
                     title="View 3-trial statistical breakdown">
                    <span class="icon-[tabler--calculator] size-3"></span>
                    <span>Breakdown</span>
                  </a>
                </div>
              </td>
              <td data-col="status" rowspan="{{ $maxTrials }}" class="text-center align-middle border-e border-base-content/10 px-2 py-1">
                <div class="flex flex-col items-center gap-1">
                  @if (! $hasPmrTrials)
                    <span class="badge badge-soft badge-neutral text-xs font-medium px-2 py-0.5 whitespace-nowrap"
                          title="No PMR trials recorded yet. 3 laboratory test milling trials required.">
                      <span class="icon-[tabler--clock] size-3.5 mr-1"></span>
                      Pending PMR Trials (0/3)
                    </span>
                  @elseif ($calculation->isHistoricalLegacy())
                    <span class="badge badge-soft badge-neutral text-xs font-medium px-1.5 py-0.5 whitespace-nowrap"
                          title="Recorded with {{ count($calculation->trials) }} trials. Must be re-established under current 3-trial guidelines.">
                      Historical Legacy
                    </span>
                  @else
                    @php
                      $reest = app(\App\Services\PmrCalculationService::class)->evaluateReestablishment($pmrRateValue, $amrRateValue);
                      $isPmrOk = ! $reest['requires_reestablishment'] && $calculation->isValid;
                    @endphp

                    @if ($reest['is_pmr_below_60'])
                      <span class="badge badge-soft badge-secondary text-xs font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="PMR ({{ number_format($pmrRateValue, 2) }}%) is 60.0% or below. Requires re-establishment.">
                        Lower than 60%
                      </span>
                    @endif
                    @if ($reest['is_pmr_below_amr'])
                      <span class="badge badge-soft badge-secondary text-xs font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="PMR ({{ number_format($pmrRateValue, 2) }}%) is lower than AMR ({{ number_format($amrRateValue, 2) }}%). Requires re-establishment.">
                        PMR lower than AMR
                      </span>
                    @endif
                    @if ($reest['is_amr_divergent_from_pmr'])
                      <span class="badge badge-soft badge-secondary text-xs font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="AMR is less than PMR by more than 3 percentage points (difference: {{ number_format($reest['difference'], 2) }}%). Requires re-establishment.">
                        AMR &lt; PMR by &gt;3%
                      </span>
                    @endif
                    @if ($calculation->isInvalid() && ! $calculation->isHistoricalLegacy())
                      <span class="badge badge-soft badge-secondary text-xs font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="{{ $calculation->statusMessage }}">
                        {{ $calculation->isInvalidCv() ? 'CV > 5%' : 'Outlier Failure' }}
                      </span>
                    @endif
                    @if ($isPmrOk)
                      <span class="badge badge-soft badge-primary text-xs font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="PMR ({{ number_format($pmrRateValue, 2) }}%) meets all NFA requirements">
                        OK
                      </span>
                    @endif
                  @endif

                  @if ($pile && $pile->pmr_status)
                    @php
                      $status = strtolower($pile->pmr_status);
                      $isLocked = $records->isNotEmpty() && $records->every('is_locked');
                      $statusLabel = match($status) {
                        'recommend', 'recommended' => ($isLocked ? 'LOCKED - RECOMMENDED' : 'RECOMMENDED'),
                        'retest' => ($isLocked ? 'LOCKED - RETEST REQUIRED' : 'RETEST REQUIRED'),
                        default => strtoupper($pile->pmr_status),
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
              <td data-col="actions" rowspan="{{ $maxTrials }}" class="align-middle text-center px-2 py-1">
                <div class="flex items-center justify-center gap-1">
                  @if ($pile && strtolower((string)$pile->pmr_status) === 'retest')
                    @if (auth()->user()?->hasRole('STAFF', 'RMEC', 'ADMINISTRATOR'))
                      <a href="{{ route('records.create', ['type' => 'pmr', 'pile_id' => $pile->id, 'retest' => 1]) }}"
                         class="btn btn-secondary btn-xs inline-flex items-center gap-1 font-semibold"
                         title="Create New PMR Laboratory Test Milling Data for this Pile">
                        <span class="icon-[tabler--refresh] size-3.5"></span>
                        <span>CREATE RETEST</span>
                      </a>
                    @endif
                    @if (auth()->user()?->hasRole('ADMINISTRATOR'))
                      <button type="button" class="btn btn-outline btn-warning btn-xs inline-flex items-center gap-1"
                              data-open-modal="#reset-modal-pmr-{{ $pile->id }}"
                              data-overlay="#reset-modal-pmr-{{ $pile->id }}"
                              title="Administrator: Reset RMEC Action">
                        <span class="icon-[tabler--rotate-clockwise] size-3.5"></span>
                        <span>Reset</span>
                      </button>
                    @endif
                  @elseif ($pile && strtolower((string)$pile->pmr_status) === 'recommended')
                    @if (auth()->user()?->hasRole('ADMINISTRATOR'))
                      <button type="button" class="btn btn-outline btn-warning btn-xs inline-flex items-center gap-1"
                              data-open-modal="#reset-modal-pmr-{{ $pile->id }}"
                              data-overlay="#reset-modal-pmr-{{ $pile->id }}"
                              title="Administrator: Reset RMEC Action">
                        <span class="icon-[tabler--rotate-clockwise] size-3.5"></span>
                        <span>Reset</span>
                      </button>
                    @else
                      <span class="text-xs text-base-content/60 font-medium">Locked</span>
                    @endif
                  @elseif (! $hasPmrTrials && ! auth()->user()?->hasRole('VIEWER'))
                    <a href="{{ route('records.create', ['type' => 'pmr', 'pile_id' => $pile?->id]) }}"
                       class="btn btn-secondary btn-xs inline-flex items-center gap-1"
                       title="Add PMR Trials for this Pile">
                      <span class="icon-[tabler--plus] size-3.5"></span>
                      <span>Add Trials</span>
                    </a>
                  @elseif (! $hasPmrTrials)
                    <span class="text-xs text-base-content/40 italic">No trials</span>
                  @else
                    @if (auth()->user()?->hasRole('STAFF') && $records->every(fn($r) => ! $r->is_locked))
                      <a href="{{ route('records.create', ['type' => 'pmr', 'pile_id' => $pile?->id]) }}"
                         class="btn btn-outline btn-primary btn-xs inline-flex items-center gap-1"
                         title="Edit PMR trials for this Pile">
                        <span class="icon-[tabler--pencil] size-3.5"></span>
                        <span>Edit</span>
                      </a>
                    @endif
                    @if (auth()->user()?->hasRole('RMEC', 'ADMINISTRATOR'))
                      @php
                        $reestablishment = app(\App\Services\PmrCalculationService::class)->evaluateReestablishment($pmrRateValue, $amrRateValue);
                        $pmrCanRecommend = $calculation->isValid && ! $reestablishment['requires_reestablishment'];
                        $pmrNeedsRetest = $calculation->isInvalid() || $calculation->isHistoricalLegacy() || ($calculation->isValid && $reestablishment['requires_reestablishment']);
                        $statusActions = $pmrCanRecommend
                          ? [
                              'recommend' => ['label' => 'Recommend', 'icon' => 'icon-[tabler--thumb-up]', 'color' => 'text-primary'],
                              'retest' => ['label' => 'Retest', 'icon' => 'icon-[tabler--refresh]', 'color' => 'text-secondary'],
                            ]
                          : ($pmrNeedsRetest || $records->count() >= 3
                            ? [
                                'retest' => ['label' => 'Retest', 'icon' => 'icon-[tabler--refresh]', 'color' => 'text-secondary'],
                              ]
                            : []);
                      @endphp
                      <div class="dropdown relative inline-flex [--placement:bottom-end]">
                        <button id="pmr-actions-{{ $pile->id }}" type="button" class="dropdown-toggle btn btn-primary btn-xs inline-flex items-center gap-1 cursor-pointer" aria-haspopup="menu" aria-expanded="false" aria-label="PMR actions" title="Choose PMR action">
                          <span class="icon-[tabler--check] size-3.5"></span>
                          <span>Actions</span>
                        </button>
                        <ul class="dropdown-menu dropdown-open:opacity-100 hidden min-w-40 shadow-md p-1.5 space-y-1" role="menu" aria-labelledby="pmr-actions-{{ $pile->id }}">
                          @if ($statusActions)
                            @foreach ($statusActions as $action => $opt)
                              <li>
                                <form method="POST" action="{{ route('piles.rmec-action', $pile) }}">
                                  @csrf
                                  <input type="hidden" name="form_type" value="pmr">
                                  <input type="hidden" name="action" value="{{ $action }}">
                                  <button type="submit" class="dropdown-item w-full flex items-center gap-2 {{ $opt['color'] }} cursor-pointer text-xs font-semibold py-1.5">
                                    <span class="{{ $opt['icon'] }} size-4"></span>
                                    {{ $opt['label'] }}
                                  </button>
                                </form>
                              </li>
                            @endforeach
                          @else
                            <li class="dropdown-item text-xs text-base-content/60">Complete 3 trials first</li>
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
          <td colspan="16" class="py-8 text-center text-base-content/60">No PMR trial records or master piles found.</td>
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
        $calculation = app(\App\Services\PmrCalculationService::class)->calculateForGroup($records);
    }
    $firstRecord = $records->first();
    $pile = is_array($group) || $group instanceof \ArrayAccess ? ($group['pile'] ?? $firstRecord?->pile) : $firstRecord?->pile;
    $modalId = 'pmr-calc-modal-' . ($pile?->id ?? $firstRecord?->pile_id ?? 'group-' . $groupIndex);
    $warehouseName = is_array($group) || $group instanceof \ArrayAccess ? ($group['warehouse_name'] ?? $pile?->warehouse?->name ?? $firstRecord?->warehouse_name ?? '—') : ($firstRecord?->warehouse_name ?? $pile?->warehouse?->name ?? '—');
    $pileNumber = is_array($group) || $group instanceof \ArrayAccess ? ($group['pile_number'] ?? $pile?->pile_number ?? $pile?->number ?? $firstRecord?->pile_number ?? '—') : ($pile?->pile_number ?? $pile?->number ?? $firstRecord?->pile_number ?? '—');
    $variety = is_array($group) || $group instanceof \ArrayAccess ? ($group['variety'] ?? $pile?->variety ?? $firstRecord?->variety ?? '—') : ($pile?->variety ?? $firstRecord?->variety ?? '—');
    $amrRateValue = is_array($group) || $group instanceof \ArrayAccess ? ($group['amr_rate'] ?? null) : null;
    $pmrRateValue = $calculation->pmrRate ?? ($records->isNotEmpty() ? $mean : null);
    $reestablishment = app(\App\Services\PmrCalculationService::class)->evaluateReestablishment($pmrRateValue, $amrRateValue);
    $hasPmrTrials = $records->isNotEmpty();
  @endphp
  <div id="{{ $modalId }}"
       class="pmr-modal fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-xs transition-opacity duration-200"
       role="dialog"
       tabindex="-1"
       aria-modal="true"
       aria-labelledby="{{ $modalId }}-title">
    <div class="relative w-full max-w-3xl rounded-xl border border-base-content/15 bg-base-100 shadow-2xl overflow-hidden max-h-[90vh] flex flex-col my-auto">

      <!-- Modal Header -->
      <div class="flex items-center justify-between border-b border-base-content/10 px-4 py-3 bg-base-200/50 shrink-0">
        <div>
          <h3 id="{{ $modalId }}-title" class="text-sm font-semibold text-base-content flex items-center gap-2">
            <span class="icon-[tabler--calculator] size-4.5 text-primary"></span>
            PMR Computation Breakdown (3 Trials - NFA Rules)
          </h3>
          <p class="text-xs text-base-content/60 mt-0.5">
            Warehouse: <span class="font-medium text-base-content">{{ $warehouseName }}</span> •
            Pile: <span class="font-medium text-base-content">{{ $pileNumber }}</span> •
            Variety: <span class="font-medium text-base-content">{{ $variety }}</span>
          </p>
          @if ($pile?->pmr_status)
            <p class="text-xs text-base-content/60 mt-1">
              Pile status: <span class="font-semibold uppercase text-secondary">{{ $pile->pmr_status }}</span>
            </p>
          @endif
        </div>
        <button type="button"
                class="btn btn-circle btn-text btn-xs text-base-content/70 hover:text-base-content cursor-pointer"
                aria-label="Close modal"
                data-close-modal="{{ $modalId }}">
          <span class="icon-[tabler--x] size-4"></span>
        </button>
      </div>

      <!-- Modal Body (Scrollable) -->
      <div class="p-4 space-y-3.5 overflow-y-auto flex-1 text-xs">
        {{-- Status Summary Alert --}}
        @if (! $hasPmrTrials)
          <x-alert-box type="neutral" size="sm" icon="icon-[tabler--clock]" title="Pending PMR Trials (0/3)">
            <p class="text-xs mt-0.5 opacity-80">No laboratory milling trials have been entered yet for this pile. Exactly 3 laboratory milling trials are required to establish the PMR.</p>
          </x-alert-box>
        @elseif ($calculation->isHistoricalLegacy())
          <x-alert-box type="neutral" size="sm" icon="icon-[tabler--history]" title="Historical Legacy Record ({{ count($calculation->trials) }} Trials)">
            <p class="text-xs mt-0.5 opacity-80">This PMR was recorded under a legacy requirement. Under current NFA guidelines, PMR requires 3 laboratory milling trials and must be re-established.</p>
          </x-alert-box>
        @elseif ($calculation->isValid)
          <x-alert-box type="primary" size="sm" icon="icon-[tabler--circle-check]" title="Recommended PMR: {{ $calculation->getFormattedPmrRate() }}" :message="$calculation->statusMessage" />
        @elseif ($calculation->isInvalid())
          <x-alert-box type="error" size="sm" icon="icon-[tabler--alert-triangle]" title="Retest Required: {{ $calculation->outlierCount }} Outliers / Rule Failure" :message="$calculation->statusMessage" />
        @else
          <x-alert-box type="neutral" size="sm" icon="icon-[tabler--clock]" title="Calculation Incomplete" :message="$calculation->statusMessage" />
        @endif

        {{-- Statistical Boundary Cards (±2% of Median) --}}
        <div>
          <h4 class="text-xs font-semibold uppercase tracking-wider text-base-content/60 mb-1.5">1. Outlier Boundary Parameters (±2% of Median)</h4>
          <div class="grid grid-cols-3 gap-2">
            <div class="bg-base-200/50 p-2 rounded-lg border border-base-content/10 text-center">
              <span class="text-xs text-base-content/60 block">Median Recovery</span>
              <span class="text-sm font-mono font-bold text-base-content mt-0.5 block">{{ $calculation->getFormattedMedian() }}</span>
              <span class="text-xs text-base-content/50 block">Middle trial rate</span>
            </div>
            <div class="bg-base-200/50 p-2 rounded-lg border border-base-content/10 text-center">
              <span class="text-xs text-base-content/60 block">Lower Limit (-2%)</span>
              <span class="text-sm font-mono font-bold text-base-content mt-0.5 block">{{ $calculation->getFormattedLowerLimit() }}</span>
              <span class="text-xs text-base-content/50 block">Median &times; 0.98</span>
            </div>
            <div class="bg-base-200/50 p-2 rounded-lg border border-base-content/10 text-center">
              <span class="text-xs text-base-content/60 block">Upper Limit (+2%)</span>
              <span class="text-sm font-mono font-bold text-base-content mt-0.5 block">{{ $calculation->getFormattedUpperLimit() }}</span>
              <span class="text-xs text-base-content/50 block">Median &times; 1.02</span>
            </div>
          </div>
        </div>

        {{-- Trials Analysis Table --}}
        <div>
          <h4 class="text-xs font-semibold uppercase tracking-wider text-base-content/60 mb-1.5">2. Laboratory Trial Evaluation &amp; Outlier Status</h4>
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
                    <td class="text-end font-mono py-1 px-2">{{ $trialRow['palay_input_kg'] !== null ? number_format($trialRow['palay_input_kg'], 2) : '—' }}</td>
                    <td class="text-end font-mono py-1 px-2">{{ $trialRow['rice_recovery_kg'] !== null ? number_format($trialRow['rice_recovery_kg'], 2) : '—' }}</td>
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
                    <td colspan="5" class="py-4 text-center text-base-content/60">No laboratory test milling trials entered yet for this pile.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          <p class="text-xs text-base-content/60 mt-1">
            * PMR uses 3 laboratory milling trials. Outliers falling outside the ±2% limits are excluded from the PMR calculation, but preserved in the record.
          </p>
        </div>

        {{-- Statistical Quality Check (Coefficient of Variation) --}}
        <div>
          <h4 class="text-xs font-semibold uppercase tracking-wider text-base-content/60 mb-1.5">3. Statistical Quality Check (CV Rule: CV ≤ 5.00%)</h4>
          <div class="bg-base-200/40 p-2.5 rounded-lg border border-base-content/10 space-y-2">
            <div class="grid grid-cols-3 gap-2 text-center">
              <div class="bg-base-100 p-2 rounded border border-base-content/10">
                <span class="text-xs text-base-content/60 block">Valid Mean</span>
                <span class="text-sm font-mono font-bold text-base-content mt-0.5 block">{{ $calculation->getFormattedMean() }}</span>
                <span class="text-xs text-base-content/50 block">Arithmetic mean</span>
              </div>
              <div class="bg-base-100 p-2 rounded border border-base-content/10">
                <span class="text-xs text-base-content/60 block">Std Dev (s)</span>
                <span class="text-sm font-mono font-bold text-base-content mt-0.5 block">{{ $calculation->getFormattedStandardDeviation() }}</span>
                <span class="text-xs text-base-content/50 block">Sample SD (N-1)</span>
              </div>
              <div class="bg-base-100 p-2 rounded border border-base-content/10">
                <span class="text-xs text-base-content/60 block">Coeff. of Variation</span>
                <span class="text-sm font-mono font-bold mt-0.5 block {{ $calculation->isCvValid ? 'text-primary' : ($calculation->coefficientOfVariation !== null ? 'text-secondary' : 'text-base-content') }}">
                  {{ $calculation->getFormattedCv() }}
                </span>
                <span class="text-xs text-base-content/50 block">(s / Mean) &times; 100</span>
              </div>
            </div>

            <div class="flex items-center justify-between text-xs pt-1 border-t border-base-content/10">
              <span class="text-base-content/70">NFA Requirement: <strong class="text-base-content">CV ≤ 5.00%</strong></span>
              @if ($calculation->isCvValid)
                <span class="badge badge-soft badge-primary text-xs font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--circle-check] size-3.5 mr-1"></span>
                  PASSED (CV ≤ 5.00%)
                </span>
              @elseif ($calculation->coefficientOfVariation !== null)
                <span class="badge badge-soft badge-secondary text-xs font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                  FAILED (CV &gt; 5.00%)
                </span>
              @else
                <span class="badge badge-soft badge-neutral text-xs py-0.5 px-2">INSUFFICIENT DATA</span>
              @endif
            </div>
          </div>
        </div>

        {{-- Final PMR Calculation Result & Re-establishment Checks --}}
        <div class="bg-base-200/40 p-3 rounded-lg border border-base-content/10 space-y-2.5">
          <h4 class="text-xs font-semibold uppercase tracking-wider text-base-content/60">4. Final PMR Establishment &amp; Re-establishment Criteria</h4>
          <div class="flex items-center justify-between text-xs">
            <div>
              <p class="text-base-content/80">
                <span class="font-medium text-base-content">Valid Trials:</span> {{ $calculation->validTrialCount }} of {{ $calculation->requiredTrials }} required
                @if ($calculation->outlierCount > 0)
                  <span class="text-secondary font-medium">({{ $calculation->outlierCount }} outlier excluded)</span>
                @endif
              </p>
              <p class="text-xs text-base-content/60">
                PMR = Arithmetic mean of valid laboratory milling results
              </p>
            </div>
            <div class="text-end">
              <span class="text-xs text-base-content/60 block">Computed PMR</span>
              <span class="text-xl font-bold font-mono text-primary leading-tight">{{ $calculation->getFormattedPmrRate() }}</span>
            </div>
          </div>

          <div class="pt-2 border-t border-base-content/10 space-y-2 text-xs">
            {{-- Check 1: 60.0% Benchmark --}}
            <div class="flex items-center justify-between">
              <div>
                <span class="text-base-content/80 font-medium">Benchmark Rule (PMR &amp; AMR &gt; 60.0%):</span>
                <p class="text-xs text-base-content/60">Flagged if PMR or AMR is 60.0% or below</p>
              </div>
              @if ($reestablishment['is_pmr_below_60'])
                <span class="badge badge-soft badge-secondary text-xs font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                  PMR &le; 60.0% (Failed)
                </span>
              @elseif ($pmrRateValue !== null)
                <span class="badge badge-soft badge-primary text-xs font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--circle-check] size-3.5 mr-1"></span>
                  OK (&gt; 60.0%)
                </span>
              @else
                <span class="badge badge-soft badge-neutral text-xs py-0.5 px-2">—</span>
              @endif
            </div>

            {{-- Check 2: PMR vs AMR --}}
            @if ($amrRateValue !== null)
              <div class="flex items-center justify-between">
                <div>
                  <span class="text-base-content/80 font-medium">Recovery Relationship (PMR &ge; AMR):</span>
                  <p class="text-xs text-base-content/60">AMR: {{ number_format($amrRateValue, 2) }}% • PMR: {{ $pmrRateValue !== null ? number_format($pmrRateValue, 2).'%' : '—' }}</p>
                </div>
                @if ($reestablishment['is_pmr_below_amr'])
                  <span class="badge badge-soft badge-secondary text-xs font-semibold py-0.5 px-2">
                    <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                    PMR &lt; AMR (Failed)
                  </span>
                @elseif ($pmrRateValue !== null)
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
                    $diff = ($pmrRateValue !== null) ? round($pmrRateValue - $amrRateValue, 4) : null;
                  @endphp
                  <p class="text-xs text-base-content/60">Spread (PMR - AMR): {{ $diff !== null ? number_format($diff, 2).' pts' : '—' }} (Max 3.0 pts)</p>
                </div>
                @if ($reestablishment['is_amr_divergent_from_pmr'])
                  <span class="badge badge-soft badge-secondary text-xs font-semibold py-0.5 px-2">
                    <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                    AMR &lt; PMR by &gt; 3.0% (Failed)
                  </span>
                @elseif ($pmrRateValue !== null)
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
              @if (! $hasPmrTrials)
                <span class="badge badge-soft badge-neutral text-xs font-bold py-1 px-2.5">
                  <span class="icon-[tabler--clock] size-3.5 mr-1"></span>
                  PENDING PMR TRIALS (0/3)
                </span>
              @elseif ($reestablishment['requires_reestablishment'])
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
        @if (! $hasPmrTrials)
          <a href="{{ route('records.create', ['type' => 'pmr', 'pile_id' => $pile?->id]) }}" class="btn btn-secondary btn-sm">
            <span class="icon-[tabler--plus] size-4"></span>
            Add PMR Trials
          </a>
        @endif
        <button type="button" class="btn btn-soft btn-secondary btn-sm cursor-pointer" data-close-modal="{{ $modalId }}">Close</button>
      </div>
    </div>
  </div>

  @if (auth()->user()?->hasRole('ADMINISTRATOR') && $pile && in_array(strtolower((string)$pile->pmr_status), ['recommended', 'retest'], true))
    <div id="reset-modal-pmr-{{ $pile->id }}" class="pmr-modal hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs items-center justify-center p-4">
      <div class="card bg-base-100 max-w-lg w-full shadow-2xl border border-warning/30 rounded-box p-6 relative">
        <div class="flex items-center justify-between pb-3 border-b border-base-content/10 mb-4">
          <div class="flex items-center gap-2">
            <span class="icon-[tabler--alert-circle] size-6 text-warning"></span>
            <h3 class="text-base font-bold text-base-content">Reset RMEC Action — Pile {{ $pile->pile_number ?? $pile->number }}</h3>
          </div>
          <button type="button" class="btn btn-ghost btn-circle btn-xs" data-close-modal="reset-modal-pmr-{{ $pile->id }}">✕</button>
        </div>
        <form method="POST" action="{{ route('piles.rmec-reset', $pile) }}">
          @csrf
          <input type="hidden" name="form_type" value="pmr">
          <p class="text-sm text-base-content/80 mb-3">
            This will mark the current RMEC action (<strong class="uppercase text-primary">{{ $pile->pmr_status }}</strong>) as <strong>RESET/SUPERSEDED</strong> and return the PMR laboratory test milling conduct to <strong>PENDING</strong> status. Historical test-milling data will remain preserved.
          </p>
          <div class="mb-4">
            <label class="label label-text font-semibold text-xs mb-1" for="reason-pmr-{{ $pile->id }}">Reset Reason <span class="text-error">*</span></label>
            <textarea id="reason-pmr-{{ $pile->id }}" name="reason" rows="3" required minlength="3" maxlength="1000" class="textarea textarea-bordered w-full text-sm" placeholder="State the reason why this RMEC action is being reset..."></textarea>
          </div>
          <div class="flex items-center justify-end gap-2">
            <button type="button" class="btn btn-ghost btn-sm" data-close-modal="reset-modal-pmr-{{ $pile->id }}">Cancel</button>
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
        if (!document.querySelector('.pmr-modal:not(.hidden)')) {
            document.body.classList.remove('overflow-hidden');
        }
    }

    document.addEventListener('click', function (e) {
        // Open trigger
        const openTrigger = e.target.closest('[data-open-modal], [data-overlay]');
        if (openTrigger) {
            const selector = openTrigger.getAttribute('data-open-modal') || openTrigger.getAttribute('data-overlay');
            if (selector && (selector.startsWith('#pmr-calc-modal-') || selector.startsWith('#reset-modal-pmr-'))) {
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
            const modal = modalId ? document.getElementById(modalId) : closeTrigger.closest('.pmr-modal');
            closeModal(modal);
            return;
        }

        // Backdrop click (click outside modal content on the backdrop)
        if (e.target.classList.contains('pmr-modal')) {
            closeModal(e.target);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.pmr-modal:not(.hidden)');
            openModals.forEach((modal) => closeModal(modal));
        }
    });
})();

// PMR Column Visibility Manager
(function () {
    const STORAGE_KEY = 'nfa_pmr_visible_columns';
    const serverDefaults = @json($visibleColumns ?? \App\Models\ReportColumnSetting::forReport('pmr'));
    const emptyCell = document.querySelector('tbody tr td[colspan]');

    function getVisibleColumns() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored) {
                const parsed = JSON.parse(stored);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    return parsed;
                }
            }
        } catch (e) {
            console.error('Failed to read localStorage column settings', e);
        }
        return serverDefaults;
    }

    function setVisibleColumns(cols) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(cols));
        } catch (e) {
            console.error('Failed to save column settings to localStorage', e);
        }
    }

    function applyVisibility(visibleCols) {
        const allColKeys = Array.from(document.querySelectorAll('.col-toggle-cb')).map(cb => cb.dataset.targetCol);
        
        allColKeys.forEach(colKey => {
            const isVisible = visibleCols.includes(colKey);
            const cells = document.querySelectorAll(`[data-col="${colKey}"]`);
            cells.forEach(el => {
                if (isVisible) {
                    el.classList.remove('hidden');
                } else {
                    el.classList.add('hidden');
                }
            });

            // Update checkbox state
            const cb = document.querySelector(`.col-toggle-cb[data-target-col="${colKey}"]`);
            if (cb) {
                cb.checked = isVisible;
            }
        });

        if (emptyCell) {
            emptyCell.setAttribute('colspan', visibleCols.length.toString());
        }
    }

    // Initialize visibility on page load
    const currentVisible = getVisibleColumns();
    applyVisibility(currentVisible);

    // Event listener for checkbox changes
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('col-toggle-cb')) {
            const checkboxes = document.querySelectorAll('.col-toggle-cb');
            const activeCols = [];
            checkboxes.forEach(cb => {
                if (cb.checked) {
                    activeCols.push(cb.dataset.targetCol);
                }
            });

            // Prevent hiding all columns
            if (activeCols.length === 0) {
                e.target.checked = true;
                return;
            }

            setVisibleColumns(activeCols);
            applyVisibility(activeCols);
        }
    });

    // Reset button
    const resetBtn = document.getElementById('pmr-reset-columns-btn');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            localStorage.removeItem(STORAGE_KEY);
            applyVisibility(serverDefaults);
        });
    }
})();
</script>
@endsection
