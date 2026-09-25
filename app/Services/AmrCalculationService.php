<?php

namespace App\Services;

use App\Models\AmrCalculation;
use App\Models\AmrRecord;
use App\Models\Pile;
use Illuminate\Support\Collection;

class AmrCalculationService
{
    public const REQUIRED_TRIALS = 3;

    public const MINIMUM_VALID_TRIALS = 2;

    public const OUTLIER_TOLERANCE_PERCENT = 0.02;

    /**
     * Compute AMR and outlier evaluation from trial data.
     *
     * @param  iterable<mixed>  $trials
     */
    public function calculate(iterable $trials): AmrCalculationResult
    {
        $normalizedTrials = [];

        foreach ($trials as $trial) {
            $trialNumber =
                (int) ($trial instanceof AmrRecord
                    ? $trial->trial_number
                    : (is_array($trial)
                        ? $trial['trial_number'] ?? 0
                        : $trial->trial_number ?? 0));

            $palayInput =
                (float) ($trial instanceof AmrRecord
                    ? $trial->palay_input_kg
                    : (is_array($trial)
                        ? $trial['palay_input_kg'] ??
                            ($trial['palay_input'] ?? 0)
                        : $trial->palay_input_kg ??
                            ($trial->palay_input ?? 0)));

            $riceRecovery =
                (float) ($trial instanceof AmrRecord
                    ? $trial->rice_recovery_kg
                    : (is_array($trial)
                        ? $trial['rice_recovery_kg'] ??
                            ($trial['rice_recovery'] ?? 0)
                        : $trial->rice_recovery_kg ??
                            ($trial->rice_recovery ?? 0)));

            $millingRecovery =
                $palayInput > 0
                    ? round(($riceRecovery / $palayInput) * 100, 2)
                    : 0.0;

            $normalizedTrials[$trialNumber] = [
                'trial_number' => $trialNumber,
                'palay_input_kg' => round($palayInput, 2),
                'rice_recovery_kg' => round($riceRecovery, 2),
                'milling_recovery' => $millingRecovery,
                'is_outlier' => false,
                'status' => 'PENDING',
                'record_id' => $trial instanceof AmrRecord ? $trial->id : null,
            ];
        }

        ksort($normalizedTrials);
        $trialCount = count($normalizedTrials);

        if ($trialCount < self::REQUIRED_TRIALS) {
            $snapshot = [
                'formula' => 'NFA Actual Milling Recovery (AMR)',
                'rule_version' => 'NFA-AMR-2026',
                'required_trials' => self::REQUIRED_TRIALS,
                'entered_trials' => $trialCount,
                'median' => null,
                'lower_limit' => null,
                'upper_limit' => null,
                'trials' => array_values($normalizedTrials),
                'valid_trial_count' => 0,
                'outlier_count' => 0,
                'amr_rate' => null,
                'status' => 'INCOMPLETE',
                'status_label' => "Incomplete ({$trialCount}/3 Trials)",
                'status_message' => "At least 3 actual milling trials are required to compute AMR (currently {$trialCount}/3).",
                'calculated_at' => now()->toIso8601String(),
            ];

            return new AmrCalculationResult(
                trials: array_values($normalizedTrials),
                median: null,
                lowerLimit: null,
                upperLimit: null,
                validTrialCount: 0,
                outlierCount: 0,
                amrRate: null,
                isValid: false,
                status: 'INCOMPLETE',
                statusLabel: "Incomplete ({$trialCount}/3 Trials)",
                statusMessage: "At least 3 actual milling trials are required to compute AMR (currently {$trialCount}/3).",
                snapshot: $snapshot,
            );
        }

        $recoveries = array_column($normalizedTrials, 'milling_recovery');
        sort($recoveries, SORT_NUMERIC);

        // For 3 trials, median is index 1
        $median = round((float) $recoveries[1], 2);

        // Lower & Upper Limits: ±2% using the median
        $lowerLimit = round($median * (1 - self::OUTLIER_TOLERANCE_PERCENT), 4);
        $upperLimit = round($median * (1 + self::OUTLIER_TOLERANCE_PERCENT), 4);

        $validTrials = [];
        $outlierCount = 0;
        $epsilon = 0.00001;

        foreach ($normalizedTrials as $index => $item) {
            $recovery = (float) $item['milling_recovery'];
            $isOutlier =
                $recovery < $lowerLimit - $epsilon ||
                $recovery > $upperLimit + $epsilon;

            $normalizedTrials[$index]['is_outlier'] = $isOutlier;
            $normalizedTrials[$index]['status'] = $isOutlier
                ? 'OUTLIER'
                : 'VALID';

            if ($isOutlier) {
                $outlierCount++;
            } else {
                $validTrials[] = $normalizedTrials[$index];
            }
        }

        $validCount = count($validTrials);
        $evaluatedTrialsList = array_values($normalizedTrials);

        if ($validCount < self::MINIMUM_VALID_TRIALS) {
            $status = 'INVALID_FEWER_VALID_TRIALS';
            $statusLabel = "Invalid ({$outlierCount} Outliers — Retest Required)";
            $statusMessage =
                'Fewer than '.
                self::MINIMUM_VALID_TRIALS.
                " valid trials remain ({$validCount} valid, {$outlierCount} outliers). Outliers exceed tolerance (±2%). A re-trial is required.";
            $amrRate = null;
            $isValid = false;
        } else {
            $validSum = array_sum(
                array_column($validTrials, 'milling_recovery'),
            );
            $amrRate = round($validSum / $validCount, 2);
            $isValid = true;
            $status = 'VALID';
            $statusLabel =
                $outlierCount > 0
                    ? "Recommended ({$outlierCount} Outlier Excluded)"
                    : 'Recommended (3/3 Trials Valid)';
            $statusMessage = "Recommended AMR of {$amrRate}% computed as arithmetic mean of {$validCount} valid trials.";
        }

        $snapshot = [
            'formula' => 'NFA Actual Milling Recovery (AMR)',
            'rule_version' => 'NFA-AMR-2026',
            'required_trials' => self::REQUIRED_TRIALS,
            'minimum_valid_trials' => self::MINIMUM_VALID_TRIALS,
            'outlier_rule' => '±2% from median (Lower = Median × 0.98, Upper = Median × 1.02)',
            'median' => $median,
            'lower_limit' => $lowerLimit,
            'upper_limit' => $upperLimit,
            'trials' => $evaluatedTrialsList,
            'valid_trial_count' => $validCount,
            'outlier_count' => $outlierCount,
            'amr_rate' => $amrRate,
            'is_valid' => $isValid,
            'status' => $status,
            'status_label' => $statusLabel,
            'status_message' => $statusMessage,
            'calculated_at' => now()->toIso8601String(),
        ];

        return new AmrCalculationResult(
            trials: $evaluatedTrialsList,
            median: $median,
            lowerLimit: $lowerLimit,
            upperLimit: $upperLimit,
            validTrialCount: $validCount,
            outlierCount: $outlierCount,
            amrRate: $amrRate,
            isValid: $isValid,
            status: $status,
            statusLabel: $statusLabel,
            statusMessage: $statusMessage,
            snapshot: $snapshot,
        );
    }

    /**
     * Compute, persist calculation snapshot, and update records for a given pile.
     */
    public function calculateAndStoreForPile(Pile $pile): AmrCalculationResult
    {
        $pile->loadMissing('amrRecords');
        $result = $this->calculate(
            $pile->amrRecords->filter(
                fn (
                    AmrRecord $record,
                ): bool => $record->included_in_computation &&
                    $record->status === 'RECOMMENDED',
            ),
        );

        // Update trial records with individual audit flags
        foreach ($result->trials as $trialData) {
            if (! empty($trialData['record_id'])) {
                AmrRecord::where('id', $trialData['record_id'])->update([
                    'milling_recovery' => $trialData['milling_recovery'],
                    'is_outlier' => $trialData['is_outlier'],
                ]);
            }
        }

        // Store snapshot in amr_calculations
        AmrCalculation::updateOrCreate(
            ['pile_id' => $pile->id],
            [
                'group_key' => 'pile:'.$pile->id,
                'trial_inputs' => array_map(
                    fn ($t) => [
                        'trial_number' => $t['trial_number'],
                        'palay_input_kg' => $t['palay_input_kg'],
                        'rice_recovery_kg' => $t['rice_recovery_kg'],
                    ],
                    $result->trials,
                ),
                'trial_recoveries' => array_column(
                    $result->trials,
                    'milling_recovery',
                    'trial_number',
                ),
                'median' => $result->median,
                'lower_limit' => $result->lowerLimit,
                'upper_limit' => $result->upperLimit,
                'outlier_results' => $result->trials,
                'valid_trial_count' => $result->validTrialCount,
                'outlier_count' => $result->outlierCount,
                'is_valid' => $result->isValid,
                'status' => $result->status,
                'status_message' => $result->statusMessage,
                'amr_rate' => $result->amrRate,
                'snapshot' => $result->snapshot,
                'calculated_at' => now(),
            ],
        );

        return $result;
    }

    /**
     * Compute and optionally persist calculation for any group of records (including legacy).
     *
     * @param  Collection<int, AmrRecord>|array<int, mixed>  $records
     */
    public function calculateForGroup(
        iterable $records,
        ?string $groupKey = null,
    ): AmrCalculationResult {
        $collection =
            $records instanceof Collection ? $records : collect($records);
        $firstRecord = $collection->first();

        if ($firstRecord instanceof AmrRecord && $firstRecord->pile) {
            return $this->calculateAndStoreForPile($firstRecord->pile);
        }

        $result = $this->calculate($collection);

        if ($groupKey !== null) {
            AmrCalculation::updateOrCreate(
                ['group_key' => $groupKey],
                [
                    'pile_id' => null,
                    'trial_inputs' => array_map(
                        fn ($t) => [
                            'trial_number' => $t['trial_number'],
                            'palay_input_kg' => $t['palay_input_kg'],
                            'rice_recovery_kg' => $t['rice_recovery_kg'],
                        ],
                        $result->trials,
                    ),
                    'trial_recoveries' => array_column(
                        $result->trials,
                        'milling_recovery',
                        'trial_number',
                    ),
                    'median' => $result->median,
                    'lower_limit' => $result->lowerLimit,
                    'upper_limit' => $result->upperLimit,
                    'outlier_results' => $result->trials,
                    'valid_trial_count' => $result->validTrialCount,
                    'outlier_count' => $result->outlierCount,
                    'is_valid' => $result->isValid,
                    'status' => $result->status,
                    'status_message' => $result->statusMessage,
                    'amr_rate' => $result->amrRate,
                    'snapshot' => $result->snapshot,
                    'calculated_at' => now(),
                ],
            );
        }

        return $result;
    }
}
