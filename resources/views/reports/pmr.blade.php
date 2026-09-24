@extends('layouts.app')

@section('title', 'PMR Report')

@section('content')
<div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-xl font-semibold text-base-content">PMR Report</h1>
        <p class="text-xs text-base-content/60">Performance Milling Recovery (PMR) — laboratory test milling results under NFA recovery standards</p>
    </div>
    <a href="{{ route('records.create', ['type' => 'pmr']) }}"
        class="btn btn-secondary btn-sm">
        <span class="icon-[tabler--plus] size-4"></span>
        Add PMR Trial
    </a>
</div>

<div class="card w-full shadow-sm border border-base-content/10 bg-base-100 overflow-hidden">
  <div class="w-full overflow-x-auto">
    <table class="table table-xs w-full text-xs">
      <thead>
        <tr class="bg-base-200/60 text-base-content border-b border-base-content/15 text-[11px] font-semibold uppercase tracking-wider">
          <th class="w-8 text-center px-2 py-2">NO.</th>
          <th class="px-2.5 py-2">BRANCH</th>
          <th class="px-2.5 py-2">WAREHOUSE NAME</th>
          <th class="text-center px-2 py-2">PILE NUMBER</th>
          <th class="px-2.5 py-2">VARIETY</th>
          <th class="text-end px-2.5 py-2">PURITY</th>
          <th class="text-end px-2.5 py-2">MC</th>
          <th class="text-center px-2.5 py-2">QUALITY (CONDITION)</th>
          <th class="text-center px-2.5 py-2">AGED ( in months)</th>
          <th class="text-end px-2.5 py-2">VOLUME (in bags of 50kg)</th>
          <th class="text-center px-2.5 py-2">NO. OF TRIAL</th>
          <th class="text-end px-2.5 py-2">RECOVERY RATE (%)</th>
          <th class="text-end px-2.5 py-2">MEAN (%)</th>
          <th class="text-end px-2.5 py-2">PMR (%)</th>
          <th class="text-center px-2.5 py-2">STATUS</th>
          <th class="text-center w-20 px-2 py-2">ACTIONS</th>
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
          $mean = is_array($group) || $group instanceof \ArrayAccess ? ($group['mean'] ?? $calculation->mean) : $calculation->mean;
          $stdDev = is_array($group) || $group instanceof \ArrayAccess ? ($group['standard_deviation'] ?? $calculation->standardDeviation) : $calculation->standardDeviation;
          $amrRateValue = is_array($group) || $group instanceof \ArrayAccess ? ($group['amr_rate'] ?? null) : null;
          $pmrRateValue = $calculation->pmrRate ?? ($records->isNotEmpty() ? $mean : null);
          $maxTrials = max(5, $trialRecords->keys()->max() ?? 5);
          $modalId = 'pmr-calc-modal-' . ($firstRecord->pile_id ?? 'group-' . $groupIndex);
        @endphp
        @for ($trial = 1; $trial <= $maxTrials; $trial++)
          @php $record = $trialRecords->get($trial); @endphp
          <tr class="{{ $trial === $maxTrials ? 'border-b-2 border-base-content/20' : 'border-b border-base-content/10' }} hover:bg-base-200/30">
            @if ($trial === 1)
              <td rowspan="{{ $maxTrials }}" class="text-center font-medium align-middle border-e border-base-content/10 px-2 py-1">
                {{ $groupIndex }}
              </td>
              <td rowspan="{{ $maxTrials }}" class="align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $firstRecord->pile?->warehouse?->branch?->name ?? '—' }}
              </td>
              <td rowspan="{{ $maxTrials }}" class="align-middle font-medium border-e border-base-content/10 px-2.5 py-1">
                {{ $firstRecord->warehouse_name }}
              </td>
              <td rowspan="{{ $maxTrials }}" class="text-center align-middle border-e border-base-content/10 px-2 py-1">
                <span class="font-semibold text-base-content">{{ $firstRecord->pile_number }}</span>
              </td>
              <td rowspan="{{ $maxTrials }}" class="align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $firstRecord->variety }}
              </td>
              <td rowspan="{{ $maxTrials }}" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ number_format((float) $firstRecord->purity, 2) }}
              </td>
              <td rowspan="{{ $maxTrials }}" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ number_format((float) $firstRecord->mc, 1) }}
              </td>
              <td rowspan="{{ $maxTrials }}" class="text-center align-middle border-e border-base-content/10 px-2.5 py-1">
                <span class="badge badge-soft badge-primary text-xs uppercase">{{ strtoupper(str_replace('_', ' ', $firstRecord->quality ?? '')) }}</span>
              </td>
              <td rowspan="{{ $maxTrials }}" class="text-center align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $firstRecord->aged_months }}
              </td>
              <td rowspan="{{ $maxTrials }}" class="text-end font-mono align-middle border-e border-base-content/20 px-2.5 py-1">
                {{ number_format((float) $firstRecord->volume_bags, 3) }}
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
              <td rowspan="{{ $maxTrials }}" class="text-end font-mono font-semibold text-primary align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $mean !== null ? number_format($mean, 2) : '—' }}
              </td>
              <td rowspan="{{ $maxTrials }}" class="text-end font-mono font-bold align-middle border-e border-base-content/10 px-2.5 py-1">
                @if ($calculation->isValid)
                  <button type="button"
                          class="btn btn-text btn-xs font-mono font-bold text-primary hover:underline cursor-pointer inline-flex items-center gap-1 px-1"
                          data-open-modal="#{{ $modalId }}"
                          title="Click to view PMR calculation breakdown">
                    <span>{{ $calculation->getFormattedPmrRate() }}</span>
                    <span class="icon-[tabler--info-circle] size-3.5 text-primary"></span>
                  </button>
                @elseif ($calculation->isInvalid())
                  <button type="button"
                          class="badge badge-soft badge-secondary text-[11px] font-semibold cursor-pointer inline-flex items-center gap-1"
                          data-open-modal="#{{ $modalId }}"
                          title="Calculation Invalid: click to see details">
                    <span class="icon-[tabler--alert-triangle] size-3"></span>
                    Invalid
                  </button>
                @else
                  <button type="button"
                          class="btn btn-text btn-xs font-mono text-base-content/60 cursor-pointer inline-flex items-center gap-1 px-1"
                          data-open-modal="#{{ $modalId }}"
                          title="Click to view PMR calculation breakdown">
                    <span>{{ $calculation->getFormattedPmrRate() }}</span>
                    <span class="icon-[tabler--info-circle] size-3.5 opacity-60"></span>
                  </button>
                @endif
                <span class="sr-only">Std Dev: {{ number_format($stdDev ?? 0, 2) }}</span>
              </td>
              <td rowspan="{{ $maxTrials }}" class="text-center align-middle border-e border-base-content/10 px-2.5 py-1">
                @php
                  $isPmrLowerThan60 = $pmrRateValue !== null && $pmrRateValue < 60.0;
                  $isPmrLowerThanAmr = $pmrRateValue !== null && $amrRateValue !== null && $pmrRateValue < $amrRateValue;
                  $isPmrOk = $pmrRateValue !== null && ! $isPmrLowerThan60 && ! $isPmrLowerThanAmr;
                @endphp
                <div class="flex flex-col items-center justify-center gap-1">
                  @if ($records->count() < $calculation->requiredTrials && $pmrRateValue === null)
                    <span class="badge badge-soft badge-neutral text-[11px] px-1.5 py-0.5">{{ $records->count() }}/{{ $calculation->requiredTrials }} trials</span>
                  @else
                    @if ($isPmrLowerThanAmr)
                      <span class="badge badge-soft badge-secondary text-[11px] font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="PMR ({{ number_format($pmrRateValue, 2) }}%) is lower than AMR ({{ number_format($amrRateValue, 2) }}%)">
                        PMR lower than AMR
                      </span>
                    @endif
                    @if ($isPmrLowerThan60)
                      <span class="badge badge-soft badge-secondary text-[11px] font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="PMR ({{ number_format($pmrRateValue, 2) }}%) is lower than 60%">
                        Lower than 60%
                      </span>
                    @endif
                    @if ($isPmrOk)
                      <span class="badge badge-soft badge-primary text-[11px] font-medium px-1.5 py-0.5 whitespace-nowrap"
                            title="PMR ({{ number_format($pmrRateValue, 2) }}%) meets requirements">
                        OK
                      </span>
                    @endif
                  @endif

                  @if ($firstRecord->pile && $firstRecord->pile->pmr_status)
                    @php
                      $status = strtolower($firstRecord->pile->pmr_status);
                      $badgeClass = match($status) {
                        'approved' => 'badge-primary',
                        'retest' => 'badge-secondary',
                        'recommend', 'recommended' => 'badge-primary',
                        'rejected' => 'badge-secondary',
                        default => 'badge-primary',
                      };
                    @endphp
                    <span class="badge badge-soft {{ $badgeClass }} text-[10px] uppercase px-1.5 py-0.5">{{ $firstRecord->pile->pmr_status }}</span>
                  @endif
                </div>
              </td>
              <td rowspan="{{ $maxTrials }}" class="align-middle text-center px-2 py-1">
                <div class="flex items-center justify-center gap-1">
                  <a href="{{ route('records.create', ['type' => 'pmr']) }}" class="btn btn-circle btn-text btn-xs" aria-label="Edit trial" title="Edit trial">
                    <span class="icon-[tabler--pencil] size-4"></span>
                  </a>
                  @if ($firstRecord->pile && ! $firstRecord->pile->pmr_status)
                    <div class="dropdown relative inline-flex [--placement:bottom-end]">
                      <button id="pmr-actions-{{ $firstRecord->pile->id }}" type="button" class="dropdown-toggle btn btn-circle btn-text btn-xs cursor-pointer" aria-haspopup="menu" aria-expanded="false" aria-label="Pile actions" title="Update status">
                        <span class="icon-[tabler--dots-vertical] size-4"></span>
                      </button>
                      <ul class="dropdown-menu dropdown-open:opacity-100 hidden min-w-36 shadow-md" role="menu" aria-labelledby="pmr-actions-{{ $firstRecord->pile->id }}">
                        @php
                          $statusActions = [
                            'recommend' => ['label' => 'Recommend', 'icon' => 'icon-[tabler--thumb-up]', 'color' => 'text-primary'],
                            'retest' => ['label' => 'Retest', 'icon' => 'icon-[tabler--refresh]', 'color' => 'text-secondary'],
                            'approved' => ['label' => 'Approve', 'icon' => 'icon-[tabler--check]', 'color' => 'text-primary'],
                          ];
                        @endphp
                        @foreach ($statusActions as $action => $opt)
                          <li>
                            <form method="POST" action="{{ route('piles.status', $firstRecord->pile) }}">
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
          <td colspan="16" class="py-8 text-center text-base-content/60">No PMR trial records have been entered yet.</td>
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
    $modalId = 'pmr-calc-modal-' . ($firstRecord->pile_id ?? 'group-' . $groupIndex);
  @endphp
  <div id="{{ $modalId }}"
       class="pmr-modal fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-xs transition-opacity duration-200"
       role="dialog"
       tabindex="-1"
       aria-modal="true"
       aria-labelledby="{{ $modalId }}-title">
    <div class="relative w-full max-w-xl rounded-xl border border-base-content/15 bg-base-100 shadow-2xl overflow-hidden max-h-[90vh] flex flex-col my-auto"
         onclick="event.stopPropagation()">
      
      <!-- Modal Header -->
      <div class="flex items-center justify-between border-b border-base-content/10 px-4 py-3 bg-base-200/50 shrink-0">
        <div>
          <h3 id="{{ $modalId }}-title" class="text-sm font-semibold text-base-content flex items-center gap-2">
            <span class="icon-[tabler--calculator] size-4.5 text-primary"></span>
            PMR Computation Breakdown (NFA Rules)
          </h3>
          <p class="text-[11px] text-base-content/60 mt-0.5">
            Warehouse: <span class="font-medium text-base-content">{{ $firstRecord->warehouse_name }}</span> •
            Pile: <span class="font-medium text-base-content">{{ $firstRecord->pile_number }}</span> •
            Variety: <span class="font-medium text-base-content">{{ $firstRecord->variety }}</span>
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
        @if ($calculation->isValid)
          <div class="alert alert-soft alert-primary flex items-center gap-2.5 p-2.5 rounded-lg text-xs">
            <span class="icon-[tabler--circle-check] size-4.5 text-primary shrink-0"></span>
            <div>
              <p class="font-semibold text-primary">Approved PMR: {{ $calculation->getFormattedPmrRate() }}</p>
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
              <span class="text-[9px] text-base-content/50 block">Median × 0.98</span>
            </div>
            <div class="bg-base-200/50 p-2 rounded-lg border border-base-content/10 text-center">
              <span class="text-[10px] text-base-content/60 block">Upper Limit (+2%)</span>
              <span class="text-sm font-mono font-bold text-base-content mt-0.5 block">{{ $calculation->getFormattedUpperLimit() }}</span>
              <span class="text-[9px] text-base-content/50 block">Median × 1.02</span>
            </div>
          </div>
        </div>

        {{-- Trials Analysis Table --}}
        <div>
          <h4 class="text-[11px] font-semibold uppercase tracking-wider text-base-content/60 mb-1.5">2. Laboratory Trial Evaluation & Outlier Status</h4>
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
                @foreach ($calculation->trials as $trialRow)
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
                @endforeach
              </tbody>
            </table>
          </div>
          <p class="text-[10px] text-base-content/60 mt-1">
            * Trials falling outside the ±2% limits are flagged as OUTLIER and excluded from PMR calculation. Original data is kept intact.
          </p>
        </div>

        {{-- Statistical Quality Check (Coefficient of Variation) --}}
        <div>
          <h4 class="text-[11px] font-semibold uppercase tracking-wider text-base-content/60 mb-1.5">3. Statistical Quality Check (CV Rule: CV ≤ 5%)</h4>
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
                <span class="text-[9px] text-base-content/50 block">(s / Mean) × 100</span>
              </div>
            </div>

            <div class="flex items-center justify-between text-[11px] pt-1 border-t border-base-content/10">
              <span class="text-base-content/70">NFA Requirement: <strong class="text-base-content">CV ≤ 5.00%</strong></span>
              @if ($calculation->isCvValid)
                <span class="badge badge-soft badge-primary text-[10px] font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--circle-check] size-3.5 mr-1"></span>
                  PASSED (CV ≤ 5%)
                </span>
              @elseif ($calculation->coefficientOfVariation !== null)
                <span class="badge badge-soft badge-secondary text-[10px] font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                  FAILED (CV > 5%)
                </span>
              @else
                <span class="badge badge-soft badge-neutral text-[10px] py-0.5 px-2">INSUFFICIENT DATA</span>
              @endif
            </div>
          </div>
        </div>

        {{-- Final PMR Calculation Result --}}
        <div class="bg-base-200/40 p-3 rounded-lg border border-base-content/10 space-y-2">
          <h4 class="text-[11px] font-semibold uppercase tracking-wider text-base-content/60">4. Final PMR Establishment</h4>
          <div class="flex items-center justify-between text-xs">
            <div>
              <p class="text-base-content/80">
                <span class="font-medium text-base-content">Valid Trials:</span> {{ $calculation->validTrialCount }} of {{ $calculation->requiredTrials }} required
                @if ($calculation->outlierCount > 0)
                  <span class="text-secondary font-medium">({{ $calculation->outlierCount }} outlier excluded)</span>
                @endif
              </p>
              <p class="text-[11px] text-base-content/60">
                PMR = Arithmetic mean of remaining valid trials
              </p>
            </div>
            <div class="text-end">
              <span class="text-[10px] text-base-content/60 block">Computed PMR</span>
              <span class="text-xl font-bold font-mono text-primary leading-tight">{{ $calculation->getFormattedPmrRate() }}</span>
            </div>
          </div>

          <div class="pt-2 border-t border-base-content/10 space-y-1.5 text-[11px]">
            <div class="flex items-center justify-between">
              <span class="text-base-content/70">60% Benchmark Check:</span>
              @if ($pmrRateValue !== null && $pmrRateValue >= 60.0)
                <span class="badge badge-soft badge-primary text-[10px] font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--circle-check] size-3.5 mr-1"></span>
                  OK (≥ 60%)
                </span>
              @elseif ($pmrRateValue !== null)
                <span class="badge badge-soft badge-secondary text-[10px] font-semibold py-0.5 px-2">
                  <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                  Lower than 60%
                </span>
              @else
                <span class="badge badge-soft badge-neutral text-[10px] py-0.5 px-2">—</span>
              @endif
            </div>

            @if ($amrRateValue !== null)
              <div class="flex items-center justify-between">
                <span class="text-base-content/70">AMR Comparison (AMR: <strong class="text-base-content">{{ number_format($amrRateValue, 2) }}%</strong>):</span>
                @if ($pmrRateValue !== null && $pmrRateValue >= $amrRateValue)
                  <span class="badge badge-soft badge-primary text-[10px] font-semibold py-0.5 px-2">
                    <span class="icon-[tabler--circle-check] size-3.5 mr-1"></span>
                    OK (PMR ≥ AMR)
                  </span>
                @elseif ($pmrRateValue !== null)
                  <span class="badge badge-soft badge-secondary text-[10px] font-semibold py-0.5 px-2">
                    <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                    PMR lower than AMR
                  </span>
                @else
                  <span class="badge badge-soft badge-neutral text-[10px] py-0.5 px-2">—</span>
                @endif
              </div>
            @endif
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="border-t border-base-content/10 px-4 py-2.5 bg-base-200/30 flex justify-end shrink-0">
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
            const modal = document.getElementById(modalId);
            closeModal(modal);
            return;
        }

        // Backdrop click (click outside modal content on the backdrop)
        if (e.target.classList.contains('pmr-modal')) {
            closeModal(e.target);
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const openModalEl = document.querySelector('.pmr-modal:not(.hidden)');
            if (openModalEl) {
                closeModal(openModalEl);
            }
        }
    });
})();
</script>
@endsection