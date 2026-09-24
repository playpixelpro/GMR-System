<?php

namespace App\Http\Controllers;

use App\Models\AmrCalculation;
use App\Models\AmrRecord;
use App\Models\PmrRecord;
use App\Services\PmrCalculationService;
use Illuminate\View\View;

class PmrRecordController extends Controller
{
    public function __construct(
        protected PmrCalculationService $calculationService,
    ) {}

    /**
     * Display the PMR report.
     */
    public function index(): View
    {
        $records = PmrRecord::query()
            ->with(['pile.warehouse.branch', 'pile.pmrCalculation', 'pile.amrCalculation', 'pile.amrRecords'])
            ->orderBy('warehouse_name')
            ->orderBy('pile_number')
            ->orderBy('trial_number')
            ->get()
            ->groupBy(
                fn (PmrRecord $record): string => $record->pile_id
                    ? 'pile:'.$record->pile_id
                    : implode('|', [
                        $record->warehouse_name,
                        $record->pile_number,
                        $record->variety,
                        $record->purity,
                        $record->mc,
                        $record->quality,
                        $record->aged_months,
                        $record->volume_bags,
                    ]),
            )
            ->map(function ($group, $key): array {
                $calc = $this->calculationService->calculateForGroup($group, $key);
                $rates = $group->map(fn (PmrRecord $r) => $r->recovery_rate_percentage)->values();
                $mean = $rates->avg() ?? 0.0;
                $stdDev = $this->sampleStandardDeviation($rates->all(), $mean);

                $firstRecord = $group->first();
                $amrRate = null;

                if ($firstRecord?->pile) {
                    if ($firstRecord->pile->amrCalculation?->amr_rate !== null) {
                        $amrRate = (float) $firstRecord->pile->amrCalculation->amr_rate;
                    } elseif ($firstRecord->pile->amrRecords->isNotEmpty()) {
                        $validAmrRecoveries = $firstRecord->pile->amrRecords
                            ->filter(fn (AmrRecord $r) => (float) $r->palay_input_kg > 0)
                            ->map(fn (AmrRecord $r) => (float) $r->milling_recovery_percentage);

                        $amrRate = $validAmrRecoveries->isNotEmpty() ? (float) $validAmrRecoveries->avg() : null;
                    }
                }

                if ($amrRate === null && $firstRecord) {
                    $matchingAmr = AmrCalculation::query()
                        ->whereHas('pile', function ($query) use ($firstRecord) {
                            $query->where('number', $firstRecord->pile_number)
                                ->whereHas('warehouse', fn ($w) => $w->where('name', $firstRecord->warehouse_name));
                        })
                        ->latest('calculated_at')
                        ->first();

                    if ($matchingAmr?->amr_rate !== null) {
                        $amrRate = (float) $matchingAmr->amr_rate;
                    } else {
                        $matchingRecords = AmrRecord::query()
                            ->where('warehouse_name', $firstRecord->warehouse_name)
                            ->where('pile_number', $firstRecord->pile_number)
                            ->get();

                        if ($matchingRecords->isNotEmpty()) {
                            $validRecoveries = $matchingRecords
                                ->filter(fn (AmrRecord $r) => (float) $r->palay_input_kg > 0)
                                ->map(fn (AmrRecord $r) => (float) $r->milling_recovery_percentage);

                            $amrRate = $validRecoveries->isNotEmpty() ? (float) $validRecoveries->avg() : null;
                        }
                    }
                }

                return [
                    'records' => $group,
                    'calculation' => $calc,
                    'mean' => $mean,
                    'standard_deviation' => $stdDev,
                    'amr_rate' => $amrRate,
                ];
            });

        return view('reports.pmr', ['recordGroups' => $records]);
    }

    /**
     * Calculate sample standard deviation for a group of trial rates (fallback helper).
     *
     * @param  list<float>  $values
     */
    private function sampleStandardDeviation(array $values, float $mean): float
    {
        if (count($values) < 2) {
            return 0.0;
        }

        $sumOfSquares = array_sum(
            array_map(
                fn (float $value): float => ($value - $mean) ** 2,
                $values,
            ),
        );

        return sqrt($sumOfSquares / (count($values) - 1));
    }
}
