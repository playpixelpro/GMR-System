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
    public function calculate(iterable $trials, ?Pile $pile = null): AmrCalculationResult
    {
        $normalizedTrials = [];

        foreach ($trials as $trial) {
            $trialNumber =
                (int) ($trial instanceof AmrRecord
                    ? $trial->trial_number
                    : (is_array($trial)
                        ? $trial['trial_number'] ?? ($trial['no_of_trial'] ?? 0)
                        : $trial->trial_number ?? ($trial->no_of_trial ?? 0)));

            $palayInput =
                $trial instanceof AmrRecord
                    ? ($trial->palay_input_kg !== null
                        ? (float) $trial->palay_input_kg
                        : null)
                    : (is_array($trial)
                        ? (isset($trial['palay_input_kg']) &&
                        $trial['palay_input_kg'] !== '' &&
                        $trial['palay_input_kg'] !== null
                            ? (float) $trial['palay_input_kg']
                            : (isset($trial['palay_input']) &&
                            $trial['palay_input'] !== '' &&
                            $trial['palay_input'] !== null
                                ? (float) $trial['palay_input']
                                : null))
                        : (isset($trial->palay_input_kg) &&
                        $trial->palay_input_kg !== null
                            ? (float) $trial->palay_input_kg
                            : null));

            $riceRecovery =
                $trial instanceof AmrRecord
                    ? ($trial->rice_recovery_kg !== null
                        ? (float) $trial->rice_recovery_kg
                        : null)
                    : (is_array($trial)
                        ? (isset($trial['rice_recovery_kg']) &&
                        $trial['rice_recovery_kg'] !== '' &&
                        $trial['rice_recovery_kg'] !== null
                            ? (float) $trial['rice_recovery_kg']
                            : (isset($trial['rice_recovery']) &&
                            $trial['rice_recovery'] !== '' &&
                            $trial['rice_recovery'] !== null
                                ? (float) $trial['rice_recovery']
                                : null))
                        : (isset($trial->rice_recovery_kg) &&
                        $trial->rice_recovery_kg !== null
                            ? (float) $trial->rice_recovery_kg
                            : null));

            $pmrRate =
                $trial instanceof AmrRecord
                    ? ($trial->pmr_rate !== null ? (float) $trial->pmr_rate : null)
                    : (is_array($trial)
                        ? (isset($trial['pmr_rate']) && $trial['pmr_rate'] !== '' && $trial['pmr_rate'] !== null ? (float) $trial['pmr_rate'] : null)
                        : (isset($trial->pmr_rate) && $trial->pmr_rate !== null ? (float) $trial->pmr_rate : null));

            $mriRate =
                $trial instanceof AmrRecord
                    ? ($trial->mri_rate !== null ? (float) $trial->mri_rate : null)
                    : (is_array($trial)
                        ? (isset($trial['mri_rate']) && $trial['mri_rate'] !== '' && $trial['mri_rate'] !== null ? (float) $trial['mri_rate'] : null)
                        : (isset($trial->mri_rate) && $trial->mri_rate !== null ? (float) $trial->mri_rate : null));

            $establishmentType =
                $trial instanceof AmrRecord
                    ? $trial->establishment_type
                    : (is_array($trial) ? ($trial['establishment_type'] ?? null) : ($trial->establishment_type ?? null));

            $mriRemarks =
                $trial instanceof AmrRecord
                    ? $trial->mri_remarks
                    : (is_array($trial) ? ($trial['mri_remarks'] ?? null) : ($trial->mri_remarks ?? null));

            $providedRecovery =
                $trial instanceof AmrRecord
                    ? ($trial->milling_recovery !== null
                        ? (float) $trial->milling_recovery
                        : null)
                    : (is_array($trial)
                        ? (isset($trial['recovery_rate']) &&
                        $trial['recovery_rate'] !== '' &&
                        $trial['recovery_rate'] !== null
                            ? (float) $trial['recovery_rate']
                            : (isset($trial['milling_recovery']) &&
                            $trial['milling_recovery'] !== '' &&
                            $trial['milling_recovery'] !== null
                                ? (float) $trial['milling_recovery']
                                : null))
                        : (isset($trial->milling_recovery) &&
                        $trial->milling_recovery !== null
                            ? (float) $trial->milling_recovery
                            : (isset($trial->recovery_rate) &&
                            $trial->recovery_rate !== null
                                ? (float) $trial->recovery_rate
                                : null)));

            if ($pmrRate !== null && $mriRate !== null) {
                $millingRecovery = round($pmrRate - $mriRate, 2);
            } elseif (
                $palayInput !== null &&
                $riceRecovery !== null &&
                $palayInput > 0
            ) {
                $millingRecovery = round(
                    ($riceRecovery / $palayInput) * 100,
                    2,
                );
            } elseif ($providedRecovery !== null) {
                $millingRecovery = round($providedRecovery, 2);
            } else {
                $millingRecovery = 0.0;
            }

            $normalizedTrials[$trialNumber] = [
                'trial_number' => $trialNumber,
                'palay_input_kg' => $palayInput !== null ? round($palayInput, 2) : null,
                'rice_recovery_kg' => $riceRecovery !== null ? round($riceRecovery, 2) : null,
                'pmr_rate' => $pmrRate,
                'mri_rate' => $mriRate,
                'mri_remarks' => $mriRemarks,
                'establishment_type' => $establishmentType,
                'milling_recovery' => $millingRecovery,
                'is_outlier' => false,
                'status' => 'PENDING',
                'record_id' => $trial instanceof AmrRecord ? $trial->id : null,
            ];
        }

        ksort($normalizedTrials);
        $trialCount = count($normalizedTrials);

        $pileVolume = $pile?->volume_kg;
        if ($pileVolume === null) {
            foreach ($trials as $t) {
                if ($t instanceof AmrRecord) {
                    $pileVolume = $t->pile?->volume_kg ?? $t->volume_kg;
                } elseif (is_array($t) && isset($t['volume_kg'])) {
                    $pileVolume = $t['volume_kg'];
                } elseif (is_array($t) && isset($t['volume'])) {
                    $pileVolume = $t['volume'];
                } elseif (is_object($t) && isset($t->volume_kg)) {
                    $pileVolume = $t->volume_kg;
                }
                if ($pileVolume !== null) {
                    break;
                }
            }
        }
        $isLowVolume = $pileVolume !== null && (float) $pileVolume < 50000;

        if ($trialCount === 0) {
            $snapshot = [
                'formula' => $isLowVolume ? 'NFA Guideline C.3.10 (Milling Recovery Index)' : 'NFA Actual Milling Recovery (AMR)',
                'rule_version' => 'NFA-AMR-2026',
                'is_mri_established' => false,
                'required_trials' => $isLowVolume ? 1 : self::REQUIRED_TRIALS,
                'entered_trials' => 0,
                'median' => null,
                'lower_limit' => null,
                'upper_limit' => null,
                'trials' => [],
                'valid_trial_count' => 0,
                'outlier_count' => 0,
                'amr_rate' => null,
                'status' => 'INCOMPLETE',
                'status_label' => $isLowVolume ? 'Pending AMR Establishment' : 'Incomplete (0/3 Trials)',
                'status_message' => $isLowVolume
                    ? 'No AMR establishment record found for this pile.'
                    : 'At least 3 actual milling trials are required to compute AMR.',
                'calculated_at' => now()->toIso8601String(),
            ];

            return new AmrCalculationResult(
                trials: [],
                median: null,
                lowerLimit: null,
                upperLimit: null,
                validTrialCount: 0,
                outlierCount: 0,
                amrRate: null,
                isValid: false,
                status: 'INCOMPLETE',
                statusLabel: $isLowVolume ? 'Pending AMR Establishment' : 'Incomplete (0/3 Trials)',
                statusMessage: $isLowVolume
                    ? 'No AMR establishment record found for this pile.'
                    : 'At least 3 actual milling trials are required to compute AMR.',
                snapshot: $snapshot,
            );
        }

        $firstNormalized = reset($normalizedTrials);
        $hasMriExplicit = ($firstNormalized['establishment_type'] ?? null) === 'mri'
            || (($firstNormalized['pmr_rate'] ?? null) !== null && ($firstNormalized['mri_rate'] ?? null) !== null);

        // C.3.10: Piles < 50,000 kg (< 1,000 bags) established via MRI or single trial without palay input
        if ($isLowVolume && ($hasMriExplicit || ($trialCount === 1 && empty($firstNormalized['palay_input_kg'])))) {
            $validRecoveries = array_column($normalizedTrials, 'milling_recovery');
            $amrRate = round(array_sum($validRecoveries) / count($validRecoveries), 2);
            $recordedPmr = $firstNormalized['pmr_rate'] ?? null;
            $recordedMri = $firstNormalized['mri_rate'] ?? null;

            foreach ($normalizedTrials as $index => $item) {
                $normalizedTrials[$index]['is_outlier'] = false;
                $normalizedTrials[$index]['status'] = 'VALID';
            }

            $evaluatedTrialsList = array_values($normalizedTrials);
            $statusLabel = 'Established via MRI (C.3.10)';
            $statusMessage = "AMR of {$amrRate}% established via Milling Recovery Index (MRI) per NFA Guideline C.3.10 (< 1,000 bags).";

            $snapshot = [
                'formula' => 'NFA Guideline C.3.10 (Milling Recovery Index)',
                'rule_version' => 'NFA-AMR-2026-C310',
                'is_mri_established' => true,
                'stockpile_exemption' => 'Quantity < 1,000 bags (< 50,000 kg)',
                'required_trials' => 1,
                'entered_trials' => $trialCount,
                'pmr_rate' => $recordedPmr,
                'mri_rate' => $recordedMri,
                'mri_remarks' => $firstNormalized['mri_remarks'] ?? null,
                'median' => $amrRate,
                'lower_limit' => null,
                'upper_limit' => null,
                'trials' => $evaluatedTrialsList,
                'valid_trial_count' => $trialCount,
                'outlier_count' => 0,
                'amr_rate' => $amrRate,
                'is_valid' => true,
                'status' => 'VALID',
                'status_label' => $statusLabel,
                'status_message' => $statusMessage,
                'calculated_at' => now()->toIso8601String(),
            ];

            return new AmrCalculationResult(
                trials: $evaluatedTrialsList,
                median: $amrRate,
                lowerLimit: null,
                upperLimit: null,
                validTrialCount: $trialCount,
                outlierCount: 0,
                amrRate: $amrRate,
                isValid: true,
                status: 'VALID',
                statusLabel: $statusLabel,
                statusMessage: $statusMessage,
                snapshot: $snapshot,
            );
        }

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
                "Retest required: {$outlierCount} outliers detected; only {$validCount} valid trial remains. ".
                'Outliers exceed the ±2% tolerance.';
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
        $recommended = $pile->amrRecords->filter(
            fn (AmrRecord $r): bool => $r->included_in_computation && $r->status === 'RECOMMENDED',
        );
        $result = $this->calculate($recommended->isNotEmpty() ? $recommended : $pile->amrRecords, $pile);

        // Update trial records with individual audit flags
        foreach ($result->trials as $trialData) {
            if (! empty($trialData['record_id'])) {
                $updateTrialData = [
                    'milling_recovery' => $trialData['milling_recovery'],
                    'is_outlier' => $trialData['is_outlier'],
                ];
                if (isset($trialData['pmr_rate'])) {
                    $updateTrialData['pmr_rate'] = $trialData['pmr_rate'];
                }
                if (isset($trialData['mri_rate'])) {
                    $updateTrialData['mri_rate'] = $trialData['mri_rate'];
                }
                AmrRecord::where('id', $trialData['record_id'])->update($updateTrialData);
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
                        'pmr_rate' => $t['pmr_rate'] ?? null,
                        'mri_rate' => $t['mri_rate'] ?? null,
                        'establishment_type' => $t['establishment_type'] ?? null,
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
