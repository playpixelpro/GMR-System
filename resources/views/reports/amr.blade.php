@extends('layouts.app')

@section('title', 'AMR Report')

@section('content')
<div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-xl font-semibold text-base-content">AMR Report</h1>
        <p class="text-xs text-base-content/60">Aged Milled Rice results — one row per pile with vertical trials</p>
    </div>
    <a href="{{ route('records.create', ['type' => 'amr']) }}"
        class="btn btn-primary btn-sm">
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
          <th class="text-center px-2 py-2">Pile No.</th>
          <th class="px-2.5 py-2">Variety</th>
          <th class="text-end px-2.5 py-2">Purity</th>
          <th class="text-end px-2.5 py-2">MC</th>
          <th class="text-center px-2.5 py-2">Quality</th>
          <th class="text-center px-2 py-2">Aged (mos)</th>
          <th class="text-end px-2.5 py-2">Volume (50kg bags)</th>
          <th class="px-2.5 py-2">Rice Miller</th>
          <th class="text-center px-2 py-2">Trial</th>
          <th class="text-end px-2.5 py-2">Palay In (kg)</th>
          <th class="text-end px-2.5 py-2">Rice Rec (kg)</th>
          <th class="text-end px-2.5 py-2">Recovery (%)</th>
          <th class="text-end px-2.5 py-2">Mean (%)</th>
          <th class="text-end px-2.5 py-2">AMR (%)</th>
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
          $validRecoveries = $records->filter(fn($r) => (float) $r->palay_input_kg > 0)->map(fn($r) => $r->milling_recovery_percentage);
          $mean = $validRecoveries->isNotEmpty() ? $validRecoveries->avg() : null;
          $amrRateValue = $calculation->amrRate ?? $mean;
          $modalId = 'amr-calc-modal-' . ($firstRecord->pile_id ?? 'group-' . $groupIndex);
        @endphp
        @for ($trial = 1; $trial <= 3; $trial++)
          @php $record = $trialRecords->get($trial); @endphp
          <tr class="{{ $trial === 3 ? 'border-b-2 border-base-content/20' : 'border-b border-base-content/10' }} hover:bg-base-200/30">
            @if ($trial === 1)
              <td rowspan="3" class="text-center font-medium align-middle border-e border-base-content/10 px-2 py-1">
                {{ $groupIndex }}
              </td>
              <td rowspan="3" class="align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $firstRecord->pile?->warehouse?->branch?->name ?? '—' }}
              </td>
              <td rowspan="3" class="align-middle font-medium border-e border-base-content/10 px-2.5 py-1">
                {{ $firstRecord->warehouse_name }}
              </td>
              <td rowspan="3" class="text-center align-middle border-e border-base-content/10 px-2 py-1">
                <span class="font-semibold text-base-content">{{ $firstRecord->pile_number }}</span>
              </td>
              <td rowspan="3" class="align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $firstRecord->variety }}
              </td>
              <td rowspan="3" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $firstRecord->purity !== null ? number_format((float) $firstRecord->purity, 2) : '—' }}
              </td>
              <td rowspan="3" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ $firstRecord->mc !== null ? number_format((float) $firstRecord->mc, 1) : '—' }}
              </td>
              <td rowspan="3" class="text-center align-middle border-e border-base-content/10 px-2.5 py-1">
                @if ($firstRecord->quality)
                  <span class="badge badge-soft badge-primary text-xs uppercase">{{ strtoupper(str_replace('_', ' ', $firstRecord->quality)) }}</span>
                @else
                  —
                @endif
              </td>
              <td rowspan="3" class="text-center align-middle border-e border-base-content/10 px-2 py-1">
                {{ $firstRecord->aged_months }}
              </td>
              <td rowspan="3" class="text-end font-mono align-middle border-e border-base-content/10 px-2.5 py-1">
                {{ number_format((float) $firstRecord->volume_bags, 3) }}
              </td>
              <td rowspan="3" class="align-middle border-e border-base-content/20 font-medium px-2.5 py-1">
                {{ $firstRecord->rice_millers }}
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
                @if ($calculation->isValid)
                  <button type="button"
                          class="btn btn-link btn-xs font-mono font-semibold text-primary underline decoration-primary/40 hover:decoration-primary p-0 h-auto cursor-pointer inline-flex items-center gap-1"
                          data-open-modal="#{{ $modalId }}"
                          data-overlay="#{{ $modalId }}"
                          title="Click to view AMR computation breakdown">
                    {{ $calculation->getFormattedAmrRate() }}
                    <span class="icon-[tabler--info-circle] size-3.5 text-primary/70"></span>
                  </button>
                @elseif ($calculation->isInvalidOutliers())
                  <button type="button"
                          class="badge badge-soft badge-error text-xs cursor-pointer inline-flex items-center gap-1 px-1.5 py-0.5"
                          data-open-modal="#{{ $modalId }}"
                          data-overlay="#{{ $modalId }}"
                          title="Outliers exceeded tolerance — Click for computation details">
                    <span class="icon-[tabler--alert-triangle] size-3"></span>
                    Invalid
                  </button>
                @else
                  <button type="button"
                          class="badge badge-soft badge-neutral text-xs cursor-pointer inline-flex items-center gap-1 px-1.5 py-0.5"
                          data-open-modal="#{{ $modalId }}"
                          data-overlay="#{{ $modalId }}"
                          title="Calculation Incomplete — Click for computation details">
                    <span class="icon-[tabler--clock] size-3"></span>
                    Incomplete
                  </button>
                @endif
              </td>
              <td rowspan="3" class="text-center align-middle border-e border-base-content/10 px-2.5 py-1">
                @php
                  $isAmrLowerThan60 = $amrRateValue !== null && $amrRateValue < 60.0;
                @endphp
                <div class="flex flex-col items-center justify-center gap-1">
                  @if ($records->count() < 3 && $amrRateValue === null)
                    <span class="badge badge-soft badge-neutral text-[11px] px-1.5 py-0.5">{{ $records->count() }}/3 trials</span>
                  @elseif ($isAmrLowerThan60)
                    <span class="badge badge-soft badge-secondary text-[11px] font-medium px-1.5 py-0.5 whitespace-nowrap"
                          title="AMR ({{ number_format($amrRateValue, 2) }}%) is lower than 60%">
                      Lower than 60%
                    </span>
                  @else
                    <span class="badge badge-soft badge-primary text-[11px] font-medium px-1.5 py-0.5 whitespace-nowrap"
                          title="AMR ({{ number_format($amrRateValue ?? 0, 2) }}%) is OK">
                      OK
                    </span>
                  @endif

                  @if ($firstRecord->pile && $firstRecord->pile->amr_status)
                    @php
                      $status = strtolower($firstRecord->pile->amr_status);
                      $badgeClass = match($status) {
                        'approved' => 'badge-primary',
                        'retest' => 'badge-secondary',
                        'recommend', 'recommended' => 'badge-primary',
                        'rejected' => 'badge-secondary',
                        default => 'badge-primary',
                      };
                    @endphp
                    <span class="badge badge-soft {{ $badgeClass }} text-[10px] uppercase px-1.5 py-0.5">{{ $firstRecord->pile->amr_status }}</span>
                  @endif
                </div>
              </td>
              <td rowspan="3" class="align-middle text-center px-2 py-1">
                <div class="flex items-center justify-center gap-1">
                  <a href="{{ route('records.create', ['type' => 'amr']) }}" class="btn btn-circle btn-text btn-xs" aria-label="Edit trial" title="Edit trial">
                    <span class="icon-[tabler--pencil] size-4"></span>
                  </a>
                  @if ($firstRecord->pile && ! $firstRecord->pile->amr_status)
                    <div class="dropdown relative inline-flex [--placement:bottom-end]">
                      <button id="amr-actions-{{ $firstRecord->pile->id }}" type="button" class="dropdown-toggle btn btn-circle btn-text btn-xs" aria-haspopup="menu" aria-expanded="false" aria-label="Pile actions" title="Update status">
                        <span class="icon-[tabler--dots-vertical] size-4"></span>
                      </button>
                      <ul class="dropdown-menu dropdown-open:opacity-100 hidden min-w-36 shadow-md" role="menu" aria-labelledby="amr-actions-{{ $firstRecord->pile->id }}">
                        @php
                          $statusActions = [
                            'recommend' => ['label' => 'Recommend', 'icon' => 'icon-[tabler--thumb-up]', 'color' => 'text-primary'],
                            'retest' => ['label' => 'Retest', 'icon' => 'icon-[tabler--refresh]', 'color' => 'text-error'],
                            'approved' => ['label' => 'Approve', 'icon' => 'icon-[tabler--check]', 'color' => 'text-primary'],
                          ];
                        @endphp
                        @foreach ($statusActions as $action => $opt)
                          <li>
                            <form method="POST" action="{{ route('piles.status', $firstRecord->pile) }}">
                              @csrf
                              <input type="hidden" name="form_type" value="amr">
                              <button type="submit" name="action" value="{{ $action }}" class="dropdown-item w-full {{ $opt['color'] }}">
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
          <td colspan="19" class="py-8 text-center text-base-content/60">No AMR trial records have been entered yet.</td>
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
    $modalId = 'amr-calc-modal-' . ($firstRecord->pile_id ?? 'group-' . $groupIndex);
  @endphp
  <div id="{{ $modalId }}"
       class="amr-modal fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-xs transition-opacity duration-200"
       role="dialog"
       tabindex="-1"
       aria-modal="true"
       aria-labelledby="{{ $modalId }}-title">
    <div class="relative w-full max-w-lg rounded-xl border border-base-content/15 bg-base-100 shadow-2xl overflow-hidden max-h-[90vh] flex flex-col my-auto"
         onclick="event.stopPropagation()">
      
      <!-- Modal Header -->
      <div class="flex items-center justify-between border-b border-base-content/10 px-4 py-3 bg-base-200/50 shrink-0">
        <div>
          <h3 id="{{ $modalId }}-title" class="text-sm font-semibold text-base-content flex items-center gap-2">
            <span class="icon-[tabler--calculator] size-4.5 text-primary"></span>
            AMR Computation Breakdown
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

      <!-- Modal Body (Scrollable if viewport is small) -->
      <div class="p-4 space-y-3.5 overflow-y-auto flex-1 text-xs">
        {{-- Status Summary Alert --}}
        @if ($calculation->isValid)
          <div class="alert alert-soft alert-primary flex items-center gap-2.5 p-2.5 rounded-lg text-xs">
            <span class="icon-[tabler--circle-check] size-4.5 text-primary shrink-0"></span>
            <div>
              <p class="font-semibold text-primary">Approved AMR: {{ $calculation->getFormattedAmrRate() }}</p>
              <p class="text-base-content/70 text-[11px] mt-0.5">{{ $calculation->statusMessage }}</p>
            </div>
          </div>
        @elseif ($calculation->isInvalidOutliers())
          <div class="alert alert-soft alert-error flex items-center gap-2.5 p-2.5 rounded-lg text-xs">
            <span class="icon-[tabler--alert-triangle] size-4.5 text-error shrink-0"></span>
            <div>
              <p class="font-semibold text-error">Calculation Invalid: Retest Required</p>
              <p class="text-base-content/70 text-[11px] mt-0.5">{{ $calculation->statusMessage }}</p>
            </div>
          </div>
        @else
          <div class="alert alert-soft alert-error flex items-center gap-2.5 p-2.5 rounded-lg text-xs">
            <span class="icon-[tabler--clock] size-4.5 text-error shrink-0"></span>
            <div>
              <p class="font-semibold text-error">Calculation Incomplete</p>
              <p class="text-base-content/70 text-[11px] mt-0.5">{{ $calculation->statusMessage }}</p>
            </div>
          </div>
        @endif

        {{-- Statistical Boundary Cards --}}
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
          <h4 class="text-[11px] font-semibold uppercase tracking-wider text-base-content/60 mb-1.5">2. Trial Evaluation & Outlier Status</h4>
          <div class="overflow-x-auto border border-base-content/10 rounded-lg">
            <table class="table table-xs w-full text-xs">
              <thead class="bg-base-200/60 text-[10px] uppercase font-semibold text-base-content">
                <tr>
                  <th class="py-1 px-2">Trial</th>
                  <th class="text-end py-1 px-2">Palay In (kg)</th>
                  <th class="text-end py-1 px-2">Rice Rec (kg)</th>
                  <th class="text-end py-1 px-2">Recovery</th>
                  <th class="text-center py-1 px-2">Evaluation</th>
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
                        <span class="badge badge-soft badge-error text-[10px] font-medium inline-flex items-center gap-1 py-0 px-1.5">
                          <span class="icon-[tabler--alert-circle] size-3"></span>
                          OUTLIER
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
        </div>

        {{-- Final AMR Calculation --}}
        <div class="bg-base-200/40 p-3 rounded-lg border border-base-content/10 space-y-2">
          <h4 class="text-[11px] font-semibold uppercase tracking-wider text-base-content/60">3. Final AMR Calculation</h4>
          <div class="flex items-center justify-between text-xs">
            <div>
              <p class="text-base-content/80">
                <span class="font-medium text-base-content">Valid Trials:</span> {{ $calculation->validTrialCount }} of 3 required
              </p>
              <p class="text-[11px] text-base-content/60">
                Arithmetic mean of remaining valid trials
              </p>
            </div>
            <div class="text-end">
              <span class="text-[10px] text-base-content/60 block">Computed AMR</span>
              <span class="text-xl font-bold font-mono text-primary leading-tight">{{ $calculation->getFormattedAmrRate() }}</span>
            </div>
          </div>

          <div class="pt-2 border-t border-base-content/10 flex items-center justify-between text-[11px]">
            <span class="text-base-content/70">60% Benchmark Check:</span>
            @if ($amrRateValue !== null && $amrRateValue >= 60.0)
              <span class="badge badge-soft badge-primary text-[10px] font-semibold py-0.5 px-2">
                <span class="icon-[tabler--circle-check] size-3.5 mr-1"></span>
                OK (≥ 60%)
              </span>
            @elseif ($amrRateValue !== null)
              <span class="badge badge-soft badge-secondary text-[10px] font-semibold py-0.5 px-2">
                <span class="icon-[tabler--alert-circle] size-3.5 mr-1"></span>
                Lower than 60%
              </span>
            @else
              <span class="badge badge-soft badge-neutral text-[10px] py-0.5 px-2">—</span>
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
            const modal = document.getElementById(modalId);
            closeModal(modal);
            return;
        }

        // Backdrop click (click outside modal content on the backdrop)
        if (e.target.classList.contains('amr-modal')) {
            closeModal(e.target);
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const openModalEl = document.querySelector('.amr-modal:not(.hidden)');
            if (openModalEl) {
                closeModal(openModalEl);
            }
        }
    });
})();
</script>
@endsection