@extends('layouts.app')

@section('title', 'PMR Report')

@section('content')
<div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-xl font-semibold text-base-content">PMR Report</h1>
        <p class="text-xs text-base-content/60">Performance Milling Recovery (PMR) — 3 laboratory test milling trials under NFA recovery standards</p>
    </div>
    <a href="{{ route('records.create', ['type' => 'pmr']) }}"
        class="btn btn-secondary btn-sm">
        <span class="icon-[tabler--plus] size-4"></span>
        Add PMR Trial
    </a>
</div>

<form method="GET" action="{{ route('pmr.index') }}" class="card mb-4 border border-base-content/10 bg-base-100 p-4 shadow-sm">
    <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-3">
        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Branch</span>
            <select name="branch_id" class="select select-bordered min-h-11 w-full text-base text-black" onchange="this.form.submit()">
                <option value="">All Branches</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($filters['branch_id'] === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
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
    <table class="table table-xs w-full text-xs">
      <thead>
        <tr class="bg-base-200/60 text-base-content border-b border-base-content/15 text-[11px] font-semibold uppercase tracking-wider">
          <th class="w-8 text-center px-2 py-2">NO.</th>
          <th class="px-2.5 py-2">BRANCH</th>
          <th class="px-2.5 py-2">WAREHOUSE NAME</th>
          <th class="text-center px-2 py-2">PILE <br> NUMBER</th>
          <th class="px-2.5 py-2">VARIETY</th>
          <th class="text-end px-2.5 py-2">PURITY</th>
          <th class="text-end px-2.5 py-2">MC</th>
          <th class="text-center px-2.5 py-2">QUALITY <br> (CONDITION)</th>
          <th class="text-center px-2.5 py-2">AGED <br>( in months)</th>
          <th class="text-center px-2.5 py-2">VOLUME <br> (50kg bags)</th>
          <th class="text-center px-2.5 py-2">NO. OF <br> TRIAL</th>
          <th class="text-center px-2.0 py-2">RECOVERY<br> RATE (%)</th>
          <th class="text-center px-2.5 py-2">MEAN (%)</th>
          <th class="text-center px-2.5 py-2">PMR <br> (%)</th>
          <th class="text-center px-2.5 py-2">STATUS</th>
          <th class="text-center w-24 px-2 py-2">ACTIONS</th>
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
              <td rowspan="{{ $maxTrials }}" class="text-center font-medium align-middle border-e border-base-content/10 px-2 py-1">
                {{ $groupIndex }}
              </td>
              <td rowspan="{{ $maxTrials }}" class="align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $branchName }}
              </td>
              <td rowspan="{{ $maxTrials }}" class="align-middle font-medium border-e border-base-content/10 px-2.5 py-1">
                {{ $warehouseName }}
              </td>
              <td rowspan="{{ $maxTrials }}" class="text-center align-middle border-e border-base-content/10 px-2 py-1">
                <span class="font-semibold text-base-content">{{ $pileNumber }}</span>
              </td>
              <td rowspan="{{ $maxTrials }}" class="align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $variety }}
              </td>
              <td rowspan="{{ $maxTrials }}" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ number_format((float) $purity, 2) }}
              </td>
              <td rowspan="{{ $maxTrials }}" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ number_format((float) $mc, 1) }}
              </td>
              <td rowspan="{{ $maxTrials }}" class="text-center align-middle border-e border-base-content/10 px-2.5 py-1">
                <span class="badge badge-soft badge-primary text-xs uppercase">{{ strtoupper(str_replace('_', ' ', $quality ?? '')) }}</span>
              </td>
              <td rowspan="{{ $maxTrials }}" class="text-center align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $agedMonths }}
              </td>
              <td rowspan="{{ $maxTrials }}" class="text-end font-mono align-middle border-e border-base-content/20 px-2.5 py-1">
                {{ number_format((float) $volumeKg / 50, 3) }}
              </td>
            @endif

            <td class="text-center align-middle px-2 py-1 border-e border-base-content/10">
              <span class="font-semibold text-base-content">{{ $trial }}</span>
            </td>
            <td class="text-end font-mono align-middle border-e border-base-content/20 px-2.5 py-1 {{ $record ? 'text-primary font-medium' : '' }}">
              <span class="sr-only">Trial {{ $trial }} Recovery Rate (%)</span>
              {{ $record ? number_format($record->recovery_rate_percentage, 2) : '—' }}
            </td>

            @if ($trial === 1)
              <td rowspan="{{ $maxTrials }}" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                <div class="flex flex-col items-end">
                  <span class="font-medium text-base-content">{{ $mean !== null ? number_format($mean, 2) : '—' }}</span>
                  <span class="text-[10px] text-base-content/60">{{ $stdDev !== null ? 's = '.number_format($stdDev, 2) : '—' }}</span>
                </div>
              </td>
              <td rowspan="{{ $maxTrials }}" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                <div class="flex flex-col items-end gap-0.5">
                  @if (! $hasPmrTrials)
                    <span class="font-medium text-base-content/50" title="No PMR trials recorded yet">—</span>
                    <span class="text-[10px] text-base-content/50">0/3 trials</span>
                  @elseif ($calculation->isHistoricalLegacy())
                    <span class="font-bold text-base-content/60 line-through" title="Historical legacy PMR ({{ count($calculation->trials) }} trials). Re-establishment required.">
                      {{ $mean !== null ? number_format($mean, 2).'%' : '—' }}
                    </span>
                    <span class="badge badge-soft badge-neutral text-[10px] px-1 py-0 uppercase">Historical</span>
                  @elseif ($calculation->isValid)
                    <span class="font-bold text-primary text-sm" title="Recommended PMR of {{ $calculation->getFormattedPmrRate() }} computed from {{ $calculation->validTrialCount }} valid trials (CV: {{ $calculation->getFormattedCv() }})">
                      {{ $calculation->getFormattedPmrRate() }}
                    </span>
                    <span class="text-[10px] text-primary/80 font-mono">CV {{ $calculation->getFormattedCv() }}</span>
                  @elseif ($calculation->isIncomplete())
                    <span class="font-medium text-base-content/50" title="{{ $calculation->statusMessage }}">—</span>
                    <span class="text-[10px] text-base-content/50">{{ count($calculation->trials) }}/3 trials</span>
                  @else
                    <span class="font-medium text-secondary" title="{{ $calculation->statusMessage }}">Invalid</span>
                    <span class="text-[10px] text-secondary font-mono">{{ $calculation->isInvalidCv() ? 'CV > 5%' : 'Outliers' }}</span>
                  @endif

                  {{-- Trigger for computation breakdown modal --}}
                  <a href="javascript:void(0)"
                     class="inline-flex items-center gap-1 text-[10px] text-primary bg-transparent hover:bg-primary/10 rounded px-1.5 py-0.5 mt-0.5 font-normal cursor-pointer"
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
              <td rowspan="{{ $maxTrials }}" class="text-center align-middle border-e border-base-content/10 px-2 py-1">
                <div class="flex flex-col items-center gap-1">
                  @if (! $hasPmrTrials)
                    <span class="badge badge-soft badge-neutral text-[11px] font-medium px-2 py-0.5 whitespace-nowrap"
                          title="No PMR trials recorded yet. 3 laboratory test milling trials required.">
                      <span class="icon-[tabler--clock] size-3.5 mr-1"></span>
                      Pending PMR Trials (0/3)
                    </span>
                  @elseif ($calculation->isHistoricalLegacy())
                    <span class="badge badge-soft badge-neutral text-[11px] font-medium px-1.5 py-0.5 whitespace-nowrap"
                          title="Recorded with {{ count($calculation->trials) }} trials. Must be re-established under current 3-trial guidelines.">
                      Historical Legacy
                    </span>
                  @else
                    @php
                      $reest = app(\App\Services\PmrCalculationService::class)->evaluateReestablishment($pmrRateValue, $amrRateValue);
                      $isPmrOk = ! $reest['requires_reestablishment'] && $calculation->isValid;
                    @endphp

                    @if ($reest['is_pmr_below_60'])
                      <span class="badge badge-soft badge-secondary text-[11px] font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="PMR ({{ number_format($pmrRateValue, 2) }}%) is 60.0% or below. Requires re-establishment.">
                        Lower than 60%
                      </span>
                    @endif
                    @if ($reest['is_pmr_below_amr'])
                      <span class="badge badge-soft badge-secondary text-[11px] font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="PMR ({{ number_format($pmrRateValue, 2) }}%) is lower than AMR ({{ number_format($amrRateValue, 2) }}%). Requires re-establishment.">
                        PMR lower than AMR
                      </span>
                    @endif
                    @if ($reest['is_amr_divergent_from_pmr'])
                      <span class="badge badge-soft badge-secondary text-[11px] font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="AMR is less than PMR by more than 3 percentage points (difference: {{ number_format($reest['difference'], 2) }}%). Requires re-establishment.">
                        AMR &lt; PMR by &gt;3%
                      </span>
                    @endif
                    @if ($calculation->isInvalid() && ! $calculation->isHistoricalLegacy())
                      <span class="badge badge-soft badge-secondary text-[11px] font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="{{ $calculation->statusMessage }}">
                        {{ $calculation->isInvalidCv() ? 'CV > 5%' : 'Outlier Failure' }}
                      </span>
                    @endif
                    @if ($isPmrOk)
                      <span class="badge badge-soft badge-primary text-[11px] font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="PMR ({{ number_format($pmrRateValue, 2) }}%) meets all NFA requirements">
                        OK
                      </span>
                    @endif
                  @endif

                  @if ($pile && $pile->pmr_status)
                    @php
                      $status = strtolower($pile->pmr_status);
                      $badgeClass = match($status) {

                        'retest' => 'badge-secondary',
                        'recommend', 'recommended' => 'badge-primary',
                        'rejected' => 'badge-secondary',
                        default => 'badge-primary',
                      };
                    @endphp
                    <span class="badge badge-soft {{ $badgeClass }} text-[10px] uppercase px-1.5 py-0.5">{{ $pile->pmr_status }}</span>
                  @endif
                </div>
              </td>
              <td rowspan="{{ $maxTrials }}" class="align-middle text-center px-2 py-1">
                <div class="flex items-center justify-center gap-1">
                  @if (! $hasPmrTrials)
                    <a href="{{ route('records.create', ['type' => 'pmr', 'pile_id' => $pile?->id]) }}"
                       class="btn btn-secondary btn-xs inline-flex items-center gap-1"
                       title="Add PMR Trials for this Pile">
                      <span class="icon-[tabler--plus] size-3.5"></span>
                      <span>Add Trials</span>
                    </a>
                  @else
                    <a href="{{ route('records.create', ['type' => 'pmr', 'pile_id' => $pile?->id]) }}"
                       class="btn btn-circle btn-text btn-xs"
                       aria-label="Edit trial"
                       title="Edit trial">
                      <span class="icon-[tabler--pencil] size-4"></span>
                    </a>
                  @endif

                  @if ($pile && in_array($pile->pmr_status, [null, 'pending'], true) && $hasPmrTrials)
                    <div class="dropdown relative inline-flex [--placement:bottom-end]">
                      <button id="pmr-actions-{{ $pile->id }}" type="button" class="dropdown-toggle btn btn-circle btn-text btn-xs cursor-pointer" aria-haspopup="menu" aria-expanded="false" aria-label="Pile actions" title="Update status">
                        <span class="icon-[tabler--dots-vertical] size-4"></span>
                      </button>
                      <ul class="dropdown-menu dropdown-open:opacity-100 hidden min-w-36 shadow-md" role="menu" aria-labelledby="pmr-actions-{{ $pile->id }}">
                        @php
                          $statusActions = $pile->pmr_status === 'pending'
                            ? [
                                'recommend' => ['label' => 'Recommend', 'icon' => 'icon-[tabler--thumb-up]', 'color' => 'text-primary'],
                                'retest' => ['label' => 'Retest', 'icon' => 'icon-[tabler--refresh]', 'color' => 'text-secondary'],
                              ]
                            : [
                                'confirm' => ['label' => 'Confirm', 'icon' => 'icon-[tabler--check]', 'color' => 'text-primary'],
                              ];
                        @endphp
                        @foreach ($statusActions as $action => $opt)
                          <li>
                            <form method="POST" action="{{ route('piles.status', $pile) }}">
                              @csrf
                              <input type="hidden" name="form_type" value="pmr">
                              <button type="submit" name="action" value="{{ $action }}" class="dropdown-item w-full {{ $opt['color'] }} cursor-pointer">
                                <span class="{{ $opt['icon'] }} size-4"></span>
                                {{ $opt['label'] }}
                              </button>
                            </form>
                          </li>
                        @endforeach
                      </ul>
                    </div>
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
    <div class="relative w-full max-w-xl rounded-xl border border-base-content/15 bg-base-100 shadow-2xl overflow-hidden max-h-[90vh] flex flex-col my-auto">

      <!-- Modal Header -->
      <div class="flex items-center justify-between border-b border-base-content/10 px-4 py-3 bg-base-200/50 shrink-0">
        <div>
          <h3 id="{{ $modalId }}-title" class="text-sm font-semibold text-base-content flex items-center gap-2">
            <span class="icon-[tabler--calculator] size-4.5 text-primary"></span>
            PMR Computation Breakdown (3 Trials - NFA Rules)
          </h3>
          <p class="text-[11px] text-base-content/60 mt-0.5">
            Warehouse: <span class="font-medium text-base-content">{{ $warehouseName }}</span> •
            Pile: <span class="font-medium text-base-content">{{ $pileNumber }}</span> •
            Variety: <span class="font-medium text-base-content">{{ $variety }}</span>
          </p>
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
          <div class="alert alert-soft alert-neutral flex items-center gap-2.5 p-2.5 rounded-lg text-xs">
            <span class="icon-[tabler--clock] size-4.5 text-base-content/60 shrink-0"></span>
            <div>
              <p class="font-semibold text-base-content">Pending PMR Trials (0/3)</p>
              <p class="text-base-content/70 text-[11px] mt-0.5">No laboratory milling trials have been entered yet for this pile. Exactly 3 laboratory milling trials are required to establish the PMR.</p>
            </div>
          </div>
        @elseif ($calculation->isHistoricalLegacy())
          <div class="alert alert-soft alert-neutral flex items-center gap-2.5 p-2.5 rounded-lg text-xs">
            <span class="icon-[tabler--history] size-4.5 text-base-content/70 shrink-0"></span>
            <div>
              <p class="font-semibold text-base-content">Historical Legacy Record ({{ count($calculation->trials) }} Trials)</p>
              <p class="text-base-content/70 text-[11px] mt-0.5">This PMR was recorded under a legacy requirement. Under current NFA guidelines, PMR requires 3 laboratory milling trials and must be re-established.</p>
            </div>
          </div>
        @elseif ($calculation->isValid)
          <div class="alert alert-soft alert-primary flex items-center gap-2.5 p-2.5 rounded-lg text-xs">
            <span class="icon-[tabler--circle-check] size-4.5 text-primary shrink-0"></span>
            <div>
              <p class="font-semibold text-primary">Recommended PMR: {{ $calculation->getFormattedPmrRate() }}</p>
              <p class="text-base-content/70 text-[11px] mt-0.5">{{ $calculation->statusMessage }}</p>
            </div>
          </div>
        @elseif ($calculation->isInvalid())
          <div class="alert alert-soft alert-secondary flex items-center gap-2.5 p-2.5 rounded-lg text-xs">
            <span class="icon-[tabler--alert-triangle] size-4.5 text-secondary shrink-0"></span>
            <div>
              <p class="font-semibold text-secondary">Calculation Invalid: Retest / Re-establishment Required</p>
              <p class="text-base-content/70 text-[11px] mt-0.5">{{ $calculation->statusMessage }}</p>
            </div>
          </div>
        @else
          <div class="alert alert-soft alert-neutral flex items-center gap-2.5 p-2.5 rounded-lg text-xs">
            <span class="icon-[tabler--clock] size-4.5 text-base-content/60 shrink-0"></span>
            <div>
              <p class="font-semibold text-base-content">Calculation Incomplete</p>
              <p class="text-base-content/70 text-[11px] mt-0.5">{{ $calculation->statusMessage }}</p>
            </div>
          </div>
        @endif

        {{-- Statistical Boundary Cards (±2% of Median) --}}
        <div>
          <h4 class="text-[11px] font-semibold uppercase tracking-wider text-base-content/60 mb-1.5">1. Outlier Boundary Parameters (±2% of Median)</h4>
          <div class="grid grid-cols-3 gap-2">
            <div class="bg-base-200/50 p-2 rounded-lg border border-base-content/10 text-center">
              <span class="text-[10px] text-base-content/60 block">Median Recovery</span>
              <span class="text-sm font-mono font-bold text-base-content mt-0.5 block">{{ $calculation->getFormattedMedian() }}</span>
              <span class="text-[9px] text-base-content/50 block">Middle trial rate</span>
            </div>
            <div class="bg-base-200/50 p-2 rounded-lg border border-base-content/10 text-center">
              <span class="text-[10px] text-base-content/60 block">Lower Limit (-2%)</span>
              <span class="text-sm font-mono font-bold text-base-content mt-0.5 block">{{ $calculation->getFormattedLowerLimit() }}</span>
              <span class="text-[9px] text-base-content/50 block">Median Ã— 0.98</span>
            </div>
            <div class="bg-base-200/50 p-2 rounded-lg border border-base-content/10 text-center">
              <span class="text-[10px] text-base-content/60 block">Upper Limit (+2%)</span>
              <span class="text-sm font-mono font-bold text-base-content mt-0.5 block">{{ $calculation->getFormattedUpperLimit() }}</span>
              <span class="text-[9px] text-base-content/50 block">Median Ã— 1.02</span>
            </div>
          </div>
        </div>

        {{-- Trials Analysis Table --}}
        <div>
          <h4 class="text-[11px] font-semibold uppercase tracking-wider text-base-content/60 mb-1.5">2. Laboratory Trial Evaluation &amp; Outlier Status</h4>
          <div class="overflow-x-auto border border-base-content/10 rounded-lg">
            <table class="table table-xs w-full text-xs">
              <thead class="bg-base-200/60 text-[10px] uppercase font-semibold text-base-content">
                <tr>
                  <th class="py-1 px-2">Trial</th>
                  <th class="text-end py-1 px-2">Palay Input (g/kg)</th>
                  <th class="text-end py-1 px-2">Rice Rec (g/kg)</th>
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
                        <span class="badge badge-soft badge-primary text-[10px] font-medium inline-flex items-center gap-1 py-0 px-1.5">
                          <span class="icon-[tabler--check] size-3"></span>
                          VALID
                        </span>
                      @elseif ($trialRow['status'] === 'OUTLIER')
                        <span class="badge badge-soft badge-secondary text-[10px] font-medium inline-flex items-center gap-1 py-0 px-1.5">
                          <span class="icon-[tabler--alert-circle] size-3"></span>
                          OUTLIER (Excluded)
                        </span>
                      @else
                        <span class="badge badge-soft badge-neutral text-[10px] font-medium py-0 px-1.5">PENDING</span>
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
          <p class="text-[10px] text-base-content/60 mt-1">
            * PMR uses 3 laboratory milling trials. Outliers falling outside the ±2% limits are excluded from the PMR calculation, but preserved in the record.
          </p>
        </div>

        {{-- Statistical Quality Check (Coefficient of Variation) --}}
        <div>
          <h4 class="text-[11px] font-semibold uppercase tracking-wider text-base-content/60 mb-1.5">3. Statistical Quality Check (CV Rule: CV ≤ 5.00%)</h4>
          <div class="bg-base-200/40 p-2.5 rounded-lg border border-base-content/10 space-y-2">
            <div class="grid grid-cols-3 gap-2 text-center">
              <div class="bg-base-100 p-2 rounded border border-base-content/10">
                <span class="text-[10px] text-base-content/60 block">Valid Mean</span>
                <span class="text-sm font-mono font-bold text-base-content mt-0.5 block">{{ $calculation->getFormattedMean() }}</span>
                <span class="text-[9px] text-base-content/50 block">Arithmetic mean</span>
              </div>
              <div class="bg-base-100 p-2 rounded border border-base-content/10">
                <span class="text-[10px] text-base-content/60 block">Std Dev (s)</span>
                <span class="text-sm font-mono font-bold text-base-content mt-0.5 block">{{ $calculation->getFormattedStandardDeviation() }}</span>
                <span class="text-[9px] text-base-content/50 block">Sample SD (N-1)</span>
              </div>
              <div class="bg-base-100 p-2 rounded border border-base-content/10">
                <span class="text-[10px] text-base-content/60 block">Coeff. of Variation</span>
                <span class="text-sm font-mono font-bold mt-0.5 block {{ $calculation->isCvValid ? 'text-primary' : ($calculation->coefficientOfVariation !== null ? 'text-secondary' : 'text-base-content') }}">
                  {{ $calculation->getFormattedCv() }}
                </span>
                <span class="text-[9px] text-base-content/50 block">(s / Mean) Ã— 100</span>
              </div>
            </div>

            <div class="flex items-center justify-between text-[11px] pt-1 border-t border-base-content/10">
              <span class="text-base-content/70">NFA Requirement: <strong class="text-base-content">CV ≤ 5.00%</strong></span>
              @if ($calculation->isCvValid)
                <span class="badge badge-soft badge-primary text-[10px] font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--circle-check] size-3.5 mr-1"></span>
                  PASSED (CV ≤ 5.00%)
                </span>
              @elseif ($calculation->coefficientOfVariation !== null)
                <span class="badge badge-soft badge-secondary text-[10px] font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                  FAILED (CV &gt; 5.00%)
                </span>
              @else
                <span class="badge badge-soft badge-neutral text-[10px] py-0.5 px-2">INSUFFICIENT DATA</span>
              @endif
            </div>
          </div>
        </div>

        {{-- Final PMR Calculation Result & Re-establishment Checks --}}
        <div class="bg-base-200/40 p-3 rounded-lg border border-base-content/10 space-y-2.5">
          <h4 class="text-[11px] font-semibold uppercase tracking-wider text-base-content/60">4. Final PMR Establishment &amp; Re-establishment Criteria</h4>
          <div class="flex items-center justify-between text-xs">
            <div>
              <p class="text-base-content/80">
                <span class="font-medium text-base-content">Valid Trials:</span> {{ $calculation->validTrialCount }} of {{ $calculation->requiredTrials }} required
                @if ($calculation->outlierCount > 0)
                  <span class="text-secondary font-medium">({{ $calculation->outlierCount }} outlier excluded)</span>
                @endif
              </p>
              <p class="text-[11px] text-base-content/60">
                PMR = Arithmetic mean of valid laboratory milling results
              </p>
            </div>
            <div class="text-end">
              <span class="text-[10px] text-base-content/60 block">Computed PMR</span>
              <span class="text-xl font-bold font-mono text-primary leading-tight">{{ $calculation->getFormattedPmrRate() }}</span>
            </div>
          </div>

          <div class="pt-2 border-t border-base-content/10 space-y-2 text-[11px]">
            {{-- Check 1: 60.0% Benchmark --}}
            <div class="flex items-center justify-between">
              <div>
                <span class="text-base-content/80 font-medium">Benchmark Rule (PMR &amp; AMR &gt; 60.0%):</span>
                <p class="text-[10px] text-base-content/60">Flagged if PMR or AMR is 60.0% or below</p>
              </div>
              @if ($reestablishment['is_pmr_below_60'])
                <span class="badge badge-soft badge-secondary text-[10px] font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                  PMR &le; 60.0% (Failed)
                </span>
              @elseif ($pmrRateValue !== null)
                <span class="badge badge-soft badge-primary text-[10px] font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--circle-check] size-3.5 mr-1"></span>
                  OK (&gt; 60.0%)
                </span>
              @else
                <span class="badge badge-soft badge-neutral text-[10px] py-0.5 px-2">—</span>
              @endif
            </div>

            {{-- Check 2: PMR vs AMR --}}
            @if ($amrRateValue !== null)
              <div class="flex items-center justify-between">
                <div>
                  <span class="text-base-content/80 font-medium">Recovery Relationship (PMR &ge; AMR):</span>
                  <p class="text-[10px] text-base-content/60">AMR: {{ number_format($amrRateValue, 2) }}% • PMR: {{ $pmrRateValue !== null ? number_format($pmrRateValue, 2).'%' : '—' }}</p>
                </div>
                @if ($reestablishment['is_pmr_below_amr'])
                  <span class="badge badge-soft badge-secondary text-[10px] font-semibold py-0.5 px-2">
                    <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                    PMR &lt; AMR (Failed)
                  </span>
                @elseif ($pmrRateValue !== null)
                  <span class="badge badge-soft badge-primary text-[10px] font-semibold py-0.5 px-2">
                    <span class="icon-[tabler--circle-check] size-3.5 mr-1"></span>
                    OK (PMR &ge; AMR)
                  </span>
                @else
                  <span class="badge badge-soft badge-neutral text-[10px] py-0.5 px-2">—</span>
                @endif
              </div>

              {{-- Check 3: AMR Divergence Rule --}}
              <div class="flex items-center justify-between">
                <div>
                  <span class="text-base-content/80 font-medium">Spread Rule (AMR within 3.0% of PMR):</span>
                  @php
                    $diff = ($pmrRateValue !== null) ? round($pmrRateValue - $amrRateValue, 4) : null;
                  @endphp
                  <p class="text-[10px] text-base-content/60">Spread (PMR - AMR): {{ $diff !== null ? number_format($diff, 2).' pts' : '—' }} (Max 3.0 pts)</p>
                </div>
                @if ($reestablishment['is_amr_divergent_from_pmr'])
                  <span class="badge badge-soft badge-secondary text-[10px] font-semibold py-0.5 px-2">
                    <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                    AMR &lt; PMR by &gt; 3.0% (Failed)
                  </span>
                @elseif ($pmrRateValue !== null)
                  <span class="badge badge-soft badge-primary text-[10px] font-semibold py-0.5 px-2">
                    <span class="icon-[tabler--circle-check] size-3.5 mr-1"></span>
                    OK (Spread &le; 3.0 pts)
                  </span>
                @else
                  <span class="badge badge-soft badge-neutral text-[10px] py-0.5 px-2">—</span>
                @endif
              </div>
            @endif

            {{-- Summary Re-establishment Determination --}}
            <div class="pt-2 border-t border-base-content/10 flex items-center justify-between">
              <span class="font-semibold text-base-content">Re-establishment Status:</span>
              @if (! $hasPmrTrials)
                <span class="badge badge-soft badge-neutral text-[11px] font-bold py-1 px-2.5">
                  <span class="icon-[tabler--clock] size-3.5 mr-1"></span>
                  PENDING PMR TRIALS (0/3)
                </span>
              @elseif ($reestablishment['requires_reestablishment'])
                <span class="badge badge-soft badge-secondary text-[11px] font-bold py-1 px-2.5">
                  <span class="icon-[tabler--refresh] size-3.5 mr-1"></span>
                  RE-ESTABLISHMENT REQUIRED
                </span>
              @elseif ($calculation->isValid)
                <span class="badge badge-soft badge-primary text-[11px] font-bold py-1 px-2.5">
                  <span class="icon-[tabler--check] size-3.5 mr-1"></span>
                  ESTABLISHED / PASSED
                </span>
              @else
                <span class="badge badge-soft badge-neutral text-[11px] py-1 px-2.5">PENDING</span>
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
            if (selector && selector.startsWith('#pmr-calc-modal-')) {
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
</script>
@endsection
