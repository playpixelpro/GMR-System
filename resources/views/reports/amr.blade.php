@extends('layouts.app')

@section('title', 'AMR Report')

@section('content')
<div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-xl font-semibold text-base-content">AMR Report</h1>
        <p class="text-xs text-base-content/60">Actual Milling Recovery (AMR) — Commercial milling results and statistical analysis</p>
    </div>
    <a href="{{ route('records.create', ['type' => 'amr']) }}"
        class="btn btn-secondary btn-sm">
        <span class="icon-[tabler--plus] size-4"></span>
        Add AMR Trial
    </a>
</div>

<div class="card w-full shadow-sm border border-base-content/10 bg-base-100 overflow-hidden">
  <div class="w-full overflow-x-auto">
    <table class="table table-xs w-full text-xs">
      <thead>
        <tr class="bg-base-200/60 text-base-content border-b border-base-content/15 text-[11px] font-semibold uppercase tracking-wider">
          <th class="w-8 text-center px-2 py-2">No.</th>
          <th class="px-2.5 py-2">Branch</th>
          <th class="px-2.5 py-2">Warehouse</th>
          <th class="text-center px-2 py-2">Pile <br>No.</th>
          <th class="px-2.5 py-2">Variety</th>
          <th class="text-center px-2.5 py-2">Purity<br> (%)</th>
          <th class="text-end px-2.5 py-2">MC (%)</th>
          <th class="text-center px-2.5 py-2">Quality</th>
          <th class="text-center px-2.5 py-2">Aged <br> (mos)</th>
          <th class="text-center px-2.5 py-2">Volume <br>(50kg bags)</th>
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
          $volumeBags = is_array($group) || $group instanceof \ArrayAccess ? ($group['volume_bags'] ?? $pile?->volume_bags ?? $firstRecord?->volume_bags ?? 0) : ($pile?->volume_bags ?? $firstRecord?->volume_bags ?? 0);
          $riceMillers = is_array($group) || $group instanceof \ArrayAccess ? ($group['rice_millers'] ?? $firstRecord?->rice_millers ?? '—') : ($firstRecord?->rice_millers ?? '—');
          $validRecoveries = $records->filter(fn($r) => (float) $r->palay_input_kg > 0)->map(fn($r) => $r->milling_recovery_percentage);
          $mean = $validRecoveries->isNotEmpty() ? $validRecoveries->avg() : null;
          $amrRateValue = $calculation->amrRate ?? $mean;
          $pmrRateValue = is_array($group) || $group instanceof \ArrayAccess ? ($group['pmr_rate'] ?? null) : null;
          $modalId = 'amr-calc-modal-' . ($pile?->id ?? $firstRecord?->pile_id ?? 'group-' . $groupIndex);
          $hasAmrTrials = $records->isNotEmpty();
        @endphp
        @for ($trial = 1; $trial <= 3; $trial++)
          @php $record = $trialRecords->get($trial); @endphp
          <tr class="{{ $trial === 3 ? 'border-b-2 border-base-content/20' : 'border-b border-base-content/10' }} hover:bg-base-200/30">
            @if ($trial === 1)
              <td rowspan="3" class="text-center font-medium align-middle border-e border-base-content/10 px-2 py-1">
                {{ $groupIndex }}
              </td>
              <td rowspan="3" class="align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $branchName }}
              </td>
              <td rowspan="3" class="align-middle font-medium border-e border-base-content/10 px-2.5 py-1">
                {{ $warehouseName }}
              </td>
              <td rowspan="3" class="text-center align-middle border-e border-base-content/10 px-2 py-1">
                <span class="font-semibold text-base-content">{{ $pileNumber }}</span>
              </td>
              <td rowspan="3" class="align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $variety }}
              </td>
              <td rowspan="3" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $purity !== null ? number_format((float) $purity, 2) : '—' }}
              </td>
              <td rowspan="3" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $mc !== null ? number_format((float) $mc, 1) : '—' }}
              </td>
              <td rowspan="3" class="text-center align-middle border-e border-base-content/10 px-2.5 py-1">
                @if ($quality)
                  <span class="badge badge-soft badge-primary text-xs uppercase">{{ strtoupper(str_replace('_', ' ', $quality)) }}</span>
                @else
                  —
                @endif
              </td>
              <td rowspan="3" class="text-center align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $agedMonths }}
              </td>
              <td rowspan="3" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ number_format((float) $volumeBags, 3) }}
              </td>
              <td rowspan="3" class="align-middle border-e border-base-content/20 font-medium px-2.5 py-1">
                {{ $riceMillers }}
              </td>
            @endif

            <td class="text-center align-middle px-2 py-1">
              <span class="badge badge-soft badge-neutral text-[11px] font-semibold px-1.5 py-0.5">Trial {{ $trial }}</span>
            </td>
            <td class="text-end font-mono align-middle px-2.5 py-1">
              {{ $record ? number_format((float) $record->palay_input_kg, 2) : '—' }}
            </td>
            <td class="text-end font-mono align-middle px-2.5 py-1">
              {{ $record ? number_format((float) $record->rice_recovery_kg, 2) : '—' }}
            </td>
            <td class="text-end font-mono font-medium align-middle border-e border-base-content/20 px-2.5 py-1 {{ $record ? 'text-primary' : '' }}">
              {{ $record ? number_format($record->milling_recovery_percentage, 2) . '%' : '—' }}
            </td>

            @if ($trial === 1)
              <td rowspan="3" class="text-end font-mono font-semibold text-primary align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $mean !== null ? number_format($mean, 2) . '%' : '—' }}
              </td>
              <td rowspan="3" class="text-end font-mono font-semibold align-middle border-e border-base-content/10 px-2.5 py-1">
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
              <td rowspan="3" class="text-center align-middle border-e border-base-content/10 px-2.5 py-1">
                @php
                  $reestablishment = app(\App\Services\PmrCalculationService::class)->evaluateReestablishment($pmrRateValue, $amrRateValue);
                  $isAmrLowerThan60 = $amrRateValue !== null && $amrRateValue <= 60.0;
                  $isPmrLowerThanAmr = $reestablishment['is_pmr_below_amr'];
                  $isAmrDivergent = $reestablishment['is_amr_divergent_from_pmr'];
                  $requiresReestablishment = $isAmrLowerThan60 || $isPmrLowerThanAmr || $isAmrDivergent;
                @endphp
                <div class="flex flex-col items-center justify-center gap-1">
                  @if (! $hasAmrTrials)
                    <span class="badge badge-soft badge-neutral text-[11px] font-medium px-2 py-0.5 whitespace-nowrap">
                      <span class="icon-[tabler--clock] size-3.5 mr-1"></span>
                      Pending AMR Trials (0/3)
                    </span>
                  @elseif ($records->count() < 3 && $amrRateValue === null)
                    <span class="badge badge-soft badge-neutral text-[11px] px-1.5 py-0.5">{{ $records->count() }}/3 trials</span>
                  @else
                    @if ($isAmrLowerThan60)
                      <span class="badge badge-soft badge-secondary text-[11px] font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="AMR ({{ number_format($amrRateValue, 2) }}%) is 60.0% or below">
                        Lower than 60%
                      </span>
                    @endif
                    @if ($isPmrLowerThanAmr)
                      <span class="badge badge-soft badge-secondary text-[11px] font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="PMR ({{ number_format($pmrRateValue, 2) }}%) is lower than AMR ({{ number_format($amrRateValue, 2) }}%)">
                        PMR lower than AMR
                      </span>
                    @endif
                    @if ($isAmrDivergent)
                      <span class="badge badge-soft badge-secondary text-[11px] font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="AMR ({{ number_format($amrRateValue, 2) }}%) is less than PMR ({{ number_format($pmrRateValue, 2) }}%) by more than 3 percentage points">
                        AMR &lt; PMR by &gt;3%
                      </span>
                    @endif
                    @if ($calculation->isValid && ! $requiresReestablishment)
                      <span class="badge badge-soft badge-primary text-[11px] font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="AMR ({{ number_format($amrRateValue ?? 0, 2) }}%) meets all NFA requirements">
                        OK
                      </span>
                    @endif
                  @endif

                  @if ($pile && $pile->amr_status)
                    @php
                      $status = strtolower($pile->amr_status);
                      $badgeClass = match($status) {
                        'approved' => 'badge-primary',
                        'retest' => 'badge-secondary',
                        'recommend', 'recommended' => 'badge-primary',
                        'rejected' => 'badge-secondary',
                        default => 'badge-primary',
                      };
                    @endphp
                    <span class="badge badge-soft {{ $badgeClass }} text-[10px] uppercase px-1.5 py-0.5">{{ $pile->amr_status }}</span>
                  @endif
                </div>
              </td>
              <td rowspan="3" class="align-middle text-center px-2 py-1">
                <div class="flex items-center justify-center gap-1">
                  @if (! $hasAmrTrials)
                    <a href="{{ route('records.create', ['type' => 'amr', 'pile_id' => $pile?->id]) }}"
                       class="btn btn-secondary btn-xs inline-flex items-center gap-1"
                       title="Add AMR Trials for this Pile">
                      <span class="icon-[tabler--plus] size-3.5"></span>
                      <span>Add Trials</span>
                    </a>
                  @else
                    <a href="{{ route('records.create', ['type' => 'amr', 'pile_id' => $pile?->id]) }}" class="btn btn-circle btn-text btn-xs" aria-label="Edit trial" title="Edit trial">
                      <span class="icon-[tabler--pencil] size-4"></span>
                    </a>
                  @endif

                  @if ($pile && ! $pile->amr_status && $hasAmrTrials)
                    <div class="dropdown relative inline-flex [--placement:bottom-end]">
                      <button id="amr-actions-{{ $pile->id }}" type="button" class="dropdown-toggle btn btn-circle btn-text btn-xs cursor-pointer" aria-haspopup="menu" aria-expanded="false" aria-label="Pile actions" title="Update status">
                        <span class="icon-[tabler--dots-vertical] size-4"></span>
                      </button>
                      <ul class="dropdown-menu dropdown-open:opacity-100 hidden min-w-36 shadow-md" role="menu" aria-labelledby="amr-actions-{{ $pile->id }}">
                        @php
                          $statusActions = [
                            'recommend' => ['label' => 'Recommend', 'icon' => 'icon-[tabler--thumb-up]', 'color' => 'text-primary'],
                            'retest' => ['label' => 'Retest', 'icon' => 'icon-[tabler--refresh]', 'color' => 'text-secondary'],
                            'approved' => ['label' => 'Approve', 'icon' => 'icon-[tabler--check]', 'color' => 'text-primary'],
                          ];
                        @endphp
                        @foreach ($statusActions as $action => $opt)
                          <li>
                            <form method="POST" action="{{ route('piles.status', $pile) }}">
                              @csrf
                              <input type="hidden" name="form_type" value="amr">
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
    $validRecoveries = $records->filter(fn($r) => (float) $r->palay_input_kg > 0)->map(fn($r) => $r->milling_recovery_percentage);
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
    <div class="relative w-full max-w-lg rounded-xl border border-base-content/15 bg-base-100 shadow-2xl overflow-hidden max-h-[90vh] flex flex-col my-auto">

      <!-- Modal Header -->
      <div class="flex items-center justify-between border-b border-base-content/10 px-4 py-3 bg-base-200/50 shrink-0">
        <div>
          <h3 id="{{ $modalId }}-title" class="text-sm font-semibold text-base-content flex items-center gap-2">
            <span class="icon-[tabler--calculator] size-4.5 text-primary"></span>
            AMR Computation Breakdown
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

      <!-- Modal Body (Scrollable if viewport is small) -->
      <div class="p-4 space-y-3.5 overflow-y-auto flex-1 text-xs">
        {{-- Status Summary Alert --}}
        @if (! $hasAmrTrials)
          <div class="alert alert-soft alert-neutral flex items-center gap-2.5 p-2.5 rounded-lg text-xs">
            <span class="icon-[tabler--clock] size-4.5 text-base-content/60 shrink-0"></span>
            <div>
              <p class="font-semibold text-base-content">Pending AMR Trials (0/3)</p>
              <p class="text-base-content/70 text-[11px] mt-0.5">No AMR commercial test milling trials have been entered yet for this pile.</p>
            </div>
          </div>
        @elseif ($calculation->isValid)
          <div class="alert alert-soft alert-primary flex items-center gap-2.5 p-2.5 rounded-lg text-xs">
            <span class="icon-[tabler--circle-check] size-4.5 text-primary shrink-0"></span>
            <div>
              <p class="font-semibold text-primary">Approved AMR: {{ $calculation->getFormattedAmrRate() }}</p>
              <p class="text-base-content/70 text-[11px] mt-0.5">{{ $calculation->statusMessage }}</p>
            </div>
          </div>
        @elseif ($calculation->isInvalidOutliers())
          <div class="alert alert-soft alert-secondary flex items-center gap-2.5 p-2.5 rounded-lg text-xs">
            <span class="icon-[tabler--alert-triangle] size-4.5 text-secondary shrink-0"></span>
            <div>
              <p class="font-semibold text-secondary">Calculation Invalid: Retest Required</p>
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

        {{-- Statistical Boundary Cards --}}
        <div>
          <h4 class="text-[11px] font-semibold uppercase tracking-wider text-base-content/60 mb-1.5">1. Outlier Boundary Parameters (&plusmn;2.00% of Median)</h4>
          <div class="grid grid-cols-3 gap-2">
            <div class="bg-base-200/50 p-2 rounded-lg border border-base-content/10 text-center">
              <span class="text-[10px] text-base-content/60 block">Lower Limit (-2%)</span>
              <span class="text-sm font-mono font-bold text-base-content mt-0.5 block">{{ $calculation->getFormattedLowerLimit() }}</span>
              <span class="text-[9px] text-base-content/50 block">Median &times; 0.98</span>
            </div>
            <div class="bg-base-200/50 p-2 rounded-lg border border-base-content/10 text-center">
              <span class="text-[10px] text-base-content/60 block">Median Recovery</span>
              <span class="text-sm font-mono font-bold text-base-content mt-0.5 block">{{ $calculation->getFormattedMedian() }}</span>
              <span class="text-[9px] text-base-content/50 block">Middle Trial</span>
            </div>
            <div class="bg-base-200/50 p-2 rounded-lg border border-base-content/10 text-center">
              <span class="text-[10px] text-base-content/60 block">Upper Limit (+2%)</span>
              <span class="text-sm font-mono font-bold text-base-content mt-0.5 block">{{ $calculation->getFormattedUpperLimit() }}</span>
              <span class="text-[9px] text-base-content/50 block">Median &times; 1.02</span>
            </div>
          </div>
        </div>

        {{-- Trials Analysis Table --}}
        <div>
          <h4 class="text-[11px] font-semibold uppercase tracking-wider text-base-content/60 mb-1.5">2. Trial Evaluation &amp; Outlier Status</h4>
          <div class="overflow-x-auto border border-base-content/10 rounded-lg">
            <table class="table table-xs w-full text-xs">
              <thead class="bg-base-200/60 text-[10px] uppercase font-semibold text-base-content">
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
                    <td class="text-end font-mono py-1 px-2">{{ number_format($trialRow['palay_input_kg'], 2) }}</td>
                    <td class="text-end font-mono py-1 px-2">{{ number_format($trialRow['rice_recovery_kg'], 2) }}</td>
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
                    <td colspan="5" class="py-4 text-center text-base-content/60">No AMR trials entered yet for this pile.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        {{-- Final Result & Re-establishment Checks --}}
        <div class="bg-base-200/40 p-3 rounded-lg border border-base-content/10 space-y-2">
          <h4 class="text-[11px] font-semibold uppercase tracking-wider text-base-content/60">3. Final AMR Establishment &amp; Re-establishment Criteria</h4>
          <div class="flex items-center justify-between text-xs">
            <div>
              <p class="text-base-content/80">
                <span class="font-medium text-base-content">Valid Trials Used:</span> {{ $calculation->validTrialCount }} of 3
                @if ($calculation->outlierCount > 0)
                  <span class="text-secondary font-medium">({{ $calculation->outlierCount }} outlier excluded)</span>
                @endif
              </p>
              <p class="text-[11px] text-base-content/60">
                AMR = Arithmetic mean of valid commercial trials
              </p>
            </div>
            <div class="text-end">
              <span class="text-[10px] text-base-content/60 block">Computed AMR</span>
              <span class="text-xl font-bold font-mono text-primary leading-tight">{{ $calculation->getFormattedAmrRate() }}</span>
            </div>
          </div>

          <div class="pt-2 border-t border-base-content/10 space-y-1.5 text-[11px]">
            {{-- Check 1: 60.0% Benchmark --}}
            <div class="flex items-center justify-between">
              <div>
                <span class="text-base-content/80 font-medium">Benchmark Rule (AMR &gt; 60.0%):</span>
                <p class="text-[10px] text-base-content/60">Flagged if AMR is 60.0% or below</p>
              </div>
              @if ($amrRateValue !== null && $amrRateValue <= 60.0)
                <span class="badge badge-soft badge-secondary text-[10px] font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                  AMR &le; 60.0% (Failed)
                </span>
              @elseif ($amrRateValue !== null)
                <span class="badge badge-soft badge-primary text-[10px] font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--circle-check] size-3.5 mr-1"></span>
                  OK (&gt; 60.0%)
                </span>
              @else
                <span class="badge badge-soft badge-neutral text-[10px] py-0.5 px-2">—</span>
              @endif
            </div>

            {{-- Check 2: PMR vs AMR --}}
            @if ($pmrRateValue !== null)
              <div class="flex items-center justify-between">
                <div>
                  <span class="text-base-content/80 font-medium">Recovery Relationship (PMR &ge; AMR):</span>
                  <p class="text-[10px] text-base-content/60">PMR: {{ number_format($pmrRateValue, 2) }}% • AMR: {{ $amrRateValue !== null ? number_format($amrRateValue, 2).'%' : '—' }}</p>
                </div>
                @if ($reestablishment['is_pmr_below_amr'])
                  <span class="badge badge-soft badge-secondary text-[10px] font-semibold py-0.5 px-2">
                    <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                    PMR &lt; AMR (Failed)
                  </span>
                @elseif ($amrRateValue !== null)
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
                    $diff = ($amrRateValue !== null) ? round($pmrRateValue - $amrRateValue, 4) : null;
                  @endphp
                  <p class="text-[10px] text-base-content/60">Spread (PMR - AMR): {{ $diff !== null ? number_format($diff, 2).' pts' : '—' }} (Max 3.0 pts)</p>
                </div>
                @if ($reestablishment['is_amr_divergent_from_pmr'])
                  <span class="badge badge-soft badge-secondary text-[10px] font-semibold py-0.5 px-2">
                    <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                    AMR &lt; PMR by &gt; 3.0% (Failed)
                  </span>
                @elseif ($amrRateValue !== null)
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
              @if (! $hasAmrTrials)
                <span class="badge badge-soft badge-neutral text-[11px] font-bold py-1 px-2.5">
                  <span class="icon-[tabler--clock] size-3.5 mr-1"></span>
                  PENDING AMR TRIALS (0/3)
                </span>
              @elseif ($reestablishment['requires_reestablishment'] || ($amrRateValue !== null && $amrRateValue <= 60.0))
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
            if (selector && selector.startsWith('#amr-calc-modal-')) {
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
