<?php

namespace App\Http\Controllers;

use App\Models\PmrRecord;
use Illuminate\View\View;

class PmrRecordController extends Controller
{
    /**
     * Display the PMR report.
     */
    public function index(): View
    {
        $records = PmrRecord::query()
            ->with('pile.warehouse.branch')
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
            ->map(function ($records): array {
                $rates = $records
                    ->map(
                        fn (
                            PmrRecord $record,
                        ): float => $record->recovery_rate_percentage,
                    )
                    ->values();
                $mean = $rates->avg();

                return [
                    'records' => $records,
                    'mean' => $mean,
                    'standard_deviation' => $this->sampleStandardDeviation(
                        $rates->all(),
                        $mean,
                    ),
                ];
            });

        return view('reports.pmr', ['recordGroups' => $records]);
    }

    /**
     * Calculate sample standard deviation for a group of trial rates.
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
