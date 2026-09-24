<?php

namespace App\Services;

use App\Models\Pile;
use App\Models\PmrCalculation;
use App\Models\PmrRecord;
use Illuminate\Support\Collection;

class PmrCalculationService
{
    public const REQUIRED_TRIALS = 5;

    public const MINIMUM_VALID_TRIALS = 3;

    public const OUTLIER_TOLERANCE_PERCENT = 0.02;

    public const MAX_CV_PERCENT = 5.0;

    /**
     * Get the active NFA rule profile name.
     */
    public function getActiveProfile(): string
    {
        return config('nfa.active_profile', 'standard');
    }

    /**
     * Get the configured parameters for a given or active profile.
     *
     * @return array{name: string, required_trials: int, minimum_valid_trials: int, outlier_tolerance_percent: float, max_cv_percent: float}
     */
    public function getConfiguredProfile(?string $profile = null): array
    {
        $profileKey = $profile ?? $this->getActiveProfile();
        $profileConfig = config("nfa.profiles.{$profileKey}.pmr", []);

        return [
            'name' => config("nfa.profiles.{$profileKey}.name", 'NFA Standard Milling Recovery Profile'),
            'required_trials' => (int) ($profileConfig['required_trials'] ?? config('nfa.pmr.required_trials', self::REQUIRED_TRIALS)),
            'minimum_valid_trials' => (int) ($profileConfig['minimum_valid_trials'] ?? config('nfa.pmr.minimum_valid_trials', self::MINIMUM_VALID_TRIALS)),
            'outlier_tolerance_percent' => (float) ($profileConfig['outlier_tolerance_percent'] ?? config('nfa.pmr.outlier_tolerance_percent', self::OUTLIER_TOLERANCE_PERCENT)),
            'max_cv_percent' => (float) ($profileConfig['max_cv_percent'] ?? config('nfa.pmr.max_cv_percent', self::MAX_CV_PERCENT)),
        ];
    }

    /**
     * Get the maximum CV threshold for the given or active profile.
     */
    public function getMaxCvThreshold(?string $profile = null): float
    {
        $configured = $this->getConfiguredProfile($profile);

        return $configured['max_cv_percent'];
    }

    /**
     * Compute PMR, outlier evaluation, and CV validation from 5 laboratory milling trials.
     *
     * @param  iterable<mixed>  $trials
     */
    public function calculate(
        iterable $trials,
        ?float $outlierTolerance = null,
        ?float $maxCv = null,
        ?string $profile = null,
    ): PmrCalculationResult {
        $profileConfig = $this->getConfiguredProfile($profile);
        $tolerance = $outlierTolerance ?? $profileConfig['outlier_tolerance_percent'];
        $maxCvThreshold = $maxCv ?? $profileConfig['max_cv_percent'];
        $requiredTrials = $profileConfig['required_trials'];
        $minimumValidTrials = $profileConfig['minimum_valid_trials'];

        $normalizedTrials = [];

        foreach ($trials as $trial) {
            $trialNumber = (int) ($trial instanceof PmrRecord
                ? $trial->trial_number
                : (is_array($trial) ? ($trial['trial_number'] ?? $trial['no_of_trial'] ?? 0) : ($trial->trial_number ?? $trial->no_of_trial ?? 0)));

            $palayInput = (float) ($trial instanceof PmrRecord
                ? $trial->palay_input_kg
                : (is_array($trial) ? ($trial['palay_input_kg'] ?? $trial['palay_input'] ?? 0) : ($trial->palay_input_kg ?? $trial->palay_input ?? 0)));

            $riceRecovery = (float) ($trial instanceof PmrRecord
                ? $trial->rice_recovery_kg
                : (is_array($trial) ? ($trial['rice_recovery_kg'] ?? $trial['rice_recovery'] ?? 0) : ($trial->rice_recovery_kg ?? $trial->rice_recovery ?? 0)));

            // Requirement 2: Milling Recovery (%) = (Rice Recovery / Palay Input) × 100
            $millingRecovery = $palayInput > 0
                ? round(($riceRecovery / $palayInput) * 100, 2)
                : 0.0;

            $normalizedTrials[$trialNumber] = [
                'trial_number' => $trialNumber,
                'palay_input_kg' => round($palayInput, 2),
                'rice_recovery_kg' => round($riceRecovery, 2),
                'milling_recovery' => $millingRecovery,
                'is_outlier' => false,
                'status' => 'PENDING',
                'record_id' => $trial instanceof PmrRecord ? $trial->id : null,
            ];
        }

        ksort($normalizedTrials);
        $trialCount = count($normalizedTrials);

        // Requirement 1: PMR must use 5 laboratory milling trials
        if ($trialCount < $requiredTrials) {
            $snapshot = [
                'formula' => 'NFA Potential Milling Recovery (PMR)',
                'rule_version' => 'NFA-PMR-2026',
                'profile_name' => $profileConfig['name'],
                'required_trials' => $requiredTrials,
                'entered_trials' => $trialCount,
                'median' => null,
                'lower_limit' => null,
                'upper_limit' => null,
                'trials' => array_values($normalizedTrials),
                'valid_trial_count' => 0,
                'outlier_count' => 0,
                'mean' => null,
                'standard_deviation' => null,
                'coefficient_of_variation' => null,
                'is_outlier_valid' => false,
                'is_cv_valid' => false,
                'pmr_rate' => null,
                'status' => 'INCOMPLETE',
                'status_label' => "Incomplete ({$trialCount}/{$requiredTrials} Trials)",
                'status_message' => "FIVE (5) laboratory milling trials are required to compute PMR (currently {$trialCount}/{$requiredTrials}).",
                'calculated_at' => now()->toIso8601String(),
            ];

            return new PmrCalculationResult(
                trials: array_values($normalizedTrials),
                median: null,
                lowerLimit: null,
                upperLimit: null,
                validTrialCount: 0,
                outlierCount: 0,
                mean: null,
                standardDeviation: null,
                coefficientOfVariation: null,
                isOutlierValid: false,
                isCvValid: false,
                pmrRate: null,
                isValid: false,
                status: 'INCOMPLETE',
                statusLabel: "Incomplete ({$trialCount}/{$requiredTrials} Trials)",
                statusMessage: "FIVE (5) laboratory milling trials are required to compute PMR (currently {$trialCount}/{$requiredTrials}).",
                snapshot: $snapshot,
                requiredTrials: $requiredTrials,
                minimumValidTrials: $minimumValidTrials,
                maxCvThreshold: $maxCvThreshold,
            );
        }

        // Take the 5 trials for calculation
        $calculationTrials = array_slice($normalizedTrials, 0, $requiredTrials, true);
        $recoveries = array_column($calculationTrials, 'milling_recovery');
        sort($recoveries, SORT_NUMERIC);

        // Requirement 3: Calculate the median of the 5 trial recovery results
        // For 5 trials, median is index 2 (the 3rd element)
        $medianIndex = (int) floor(count($recoveries) / 2);
        $median = round((float) $recoveries[$medianIndex], 2);

        // Requirement 4: Apply the ±2% outlier rule based on the median
        // Lower Limit = Median × 0.98, Upper Limit = Median × 1.02
        $lowerLimit = round($median * (1 - $tolerance), 4);
        $upperLimit = round($median * (1 + $tolerance), 4);

        // Requirement 5: Mark each trial as VALID or OUTLIER. Never delete original trial data.
        $validTrialCount = 0;
        $outlierCount = 0;

        foreach ($calculationTrials as $num => $t) {
            $rec = (float) $t['milling_recovery'];
            // Inclusive boundary check: lowerLimit <= rec <= upperLimit is valid
            $isOutlier = ($rec < $lowerLimit || $rec > $upperLimit);

            $calculationTrials[$num]['is_outlier'] = $isOutlier;
            $calculationTrials[$num]['status'] = $isOutlier ? 'OUTLIER' : 'VALID';

            if ($isOutlier) {
                $outlierCount++;
            } else {
                $validTrialCount++;
            }
        }

        // Outlier validation: At least minimumValidTrials (default 3) must remain
        $isOutlierValid = ($validTrialCount >= $minimumValidTrials);

        // Requirement 6 & 7: Exclude outliers; PMR = arithmetic mean of remaining valid trials
        $validTrials = array_filter($calculationTrials, fn ($t) => ! $t['is_outlier']);
        $validRecoveries = array_column($validTrials, 'milling_recovery');

        $mean = $validTrialCount > 0
            ? round(array_sum($validRecoveries) / $validTrialCount, 2)
            : null;

        // Requirement 8: Calculate Sample Standard Deviation (N-1)
        $standardDeviation = $this->calculateSampleStandardDeviation($validRecoveries, $mean);

        // Requirement 8: Coefficient of Variation: CV = (SD / Mean) × 100
        $cv = ($standardDeviation !== null && $mean !== null && $mean > 0)
            ? round(($standardDeviation / $mean) * 100, 2)
            : ($validTrialCount === 1 ? 0.0 : null);

        // Requirement 9: Evaluate CV against configured threshold (default <= 5%)
        $isCvValid = $this->evaluateCv($standardDeviation, $mean, $maxCvThreshold);

        // Overall validity: Both outlier validation and CV validation must pass
        $isValid = $isOutlierValid && $isCvValid;

        if (! $isOutlierValid) {
            $status = 'INVALID_FEWER_VALID_TRIALS';
            $statusLabel = 'Invalid / Requires Re-establishment';
            $statusMessage = "Fewer than {$minimumValidTrials} valid trials remain after outlier exclusion ({$validTrialCount}/{$requiredTrials} valid). Requires re-establishment.";
            $pmrRate = null;
        } elseif (! $isCvValid) {
            $status = 'INVALID_CV_EXCEEDED';
            $statusLabel = 'Invalid / Requires Re-establishment';
            $formattedCv = number_format($cv, 2);
            $statusMessage = "Coefficient of Variation (CV) of {$formattedCv}% exceeds the {$maxCvThreshold}% maximum threshold. Requires re-establishment.";
            $pmrRate = null;
        } else {
            $status = 'VALID';
            $statusLabel = "Approved ({$validTrialCount}/{$requiredTrials} Trials Valid)";
            $formattedCv = number_format($cv ?? 0, 2);
            $statusMessage = "Approved PMR of {$mean}% computed as arithmetic mean of {$validTrialCount} valid trials (CV: {$formattedCv}%).";
            $pmrRate = $mean;
        }

        // Put evaluated trials back into normalizedTrials
        foreach ($calculationTrials as $num => $t) {
            $normalizedTrials[$num] = $t;
        }

        // Requirement 10 & 12: Store calculation details & snapshot for auditability
        $snapshot = [
            'formula' => 'NFA Potential Milling Recovery (PMR)',
            'rule_version' => 'NFA-PMR-2026',
            'profile_name' => $profileConfig['name'],
            'required_trials' => $requiredTrials,
            'minimum_valid_trials' => $minimumValidTrials,
            'max_cv_percent' => $maxCvThreshold,
            'outlier_rule' => '±2% from median (Lower = Median × 0.98, Upper = Median × 1.02)',
            'cv_rule' => "CV = (Sample Standard Deviation / Mean) × 100 ≤ {$maxCvThreshold}%",
            'median' => $median,
            'lower_limit' => $lowerLimit,
            'upper_limit' => $upperLimit,
            'trials' => array_values($normalizedTrials),
            'valid_trial_count' => $validTrialCount,
            'outlier_count' => $outlierCount,
            'mean' => $mean,
            'standard_deviation' => $standardDeviation,
            'coefficient_of_variation' => $cv,
            'is_outlier_valid' => $isOutlierValid,
            'is_cv_valid' => $isCvValid,
            'is_valid' => $isValid,
            'pmr_rate' => $pmrRate,
            'status' => $status,
            'status_label' => $statusLabel,
            'status_message' => $statusMessage,
            'calculated_at' => now()->toIso8601String(),
        ];

        return new PmrCalculationResult(
            trials: array_values($normalizedTrials),
            median: $median,
            lowerLimit: $lowerLimit,
            upperLimit: $upperLimit,
            validTrialCount: $validTrialCount,
            outlierCount: $outlierCount,
            mean: $mean,
            standardDeviation: $standardDeviation,
            coefficientOfVariation: $cv,
            isOutlierValid: $isOutlierValid,
            isCvValid: $isCvValid,
            pmrRate: $pmrRate,
            isValid: $isValid,
            status: $status,
            statusLabel: $statusLabel,
            statusMessage: $statusMessage,
            snapshot: $snapshot,
            requiredTrials: $requiredTrials,
            minimumValidTrials: $minimumValidTrials,
            maxCvThreshold: $maxCvThreshold,
        );
    }

    /**
     * Calculate sample standard deviation (s = sqrt(sum(x - mean)^2 / (n - 1))).
     *
     * @param  list<float>  $values
     */
    public function calculateSampleStandardDeviation(array $values, ?float $mean = null): ?float
    {
        $n = count($values);
        if ($n < 2) {
            return null;
        }

        $avg = $mean ?? (array_sum($values) / $n);
        $sumOfSquares = 0.0;

        foreach ($values as $val) {
            $sumOfSquares += (($val - $avg) ** 2);
        }

        return round(sqrt($sumOfSquares / ($n - 1)), 4);
    }

    /**
     * Calculate Coefficient of Variation: CV = (SD / Mean) * 100.
     */
    public function calculateCoefficientOfVariation(?float $standardDeviation, ?float $mean): ?float
    {
        if ($standardDeviation === null || $mean === null || $mean <= 0.0) {
            return null;
        }

        return round(($standardDeviation / $mean) * 100, 2);
    }

    /**
     * Evaluate whether CV passes statistical requirement (CV <= threshold).
     */
    public function evaluateCv(?float $standardDeviation, ?float $mean, ?float $maxCv = null): bool
    {
        if ($standardDeviation === null || $mean === null || $mean <= 0.0) {
            return false;
        }

        $threshold = $maxCv ?? $this->getMaxCvThreshold();
        $cv = ($standardDeviation / $mean) * 100;

        return round($cv, 4) <= $threshold;
    }

    /**
     * Compute, persist calculation snapshot, and update records for a given pile.
     */
    public function calculateAndStoreForPile(Pile $pile, ?string $profile = null): PmrCalculationResult
    {
        $pile->loadMissing('pmrRecords');
        $result = $this->calculate($pile->pmrRecords, null, null, $profile);

        // Update trial records with individual audit flags
        foreach ($result->trials as $trialData) {
            if (! empty($trialData['record_id'])) {
                PmrRecord::where('id', $trialData['record_id'])->update([
                    'milling_recovery' => $trialData['milling_recovery'],
                    'is_outlier' => $trialData['is_outlier'],
                ]);
            }
        }

        // Store snapshot in pmr_calculations table for auditability
        PmrCalculation::updateOrCreate(
            ['pile_id' => $pile->id],
            [
                'group_key' => 'pile:'.$pile->id,
                'trial_inputs' => array_map(fn ($t) => [
                    'trial_number' => $t['trial_number'],
                    'palay_input_kg' => $t['palay_input_kg'],
                    'rice_recovery_kg' => $t['rice_recovery_kg'],
                ], $result->trials),
                'trial_recoveries' => array_column($result->trials, 'milling_recovery', 'trial_number'),
                'median' => $result->median,
                'lower_limit' => $result->lowerLimit,
                'upper_limit' => $result->upperLimit,
                'outlier_results' => $result->trials,
                'valid_trial_count' => $result->validTrialCount,
                'outlier_count' => $result->outlierCount,
                'standard_deviation' => $result->standardDeviation,
                'coefficient_of_variation' => $result->coefficientOfVariation,
                'is_outlier_valid' => $result->isOutlierValid,
                'is_cv_valid' => $result->isCvValid,
                'is_valid' => $result->isValid,
                'status' => $result->status,
                'status_message' => $result->statusMessage,
                'pmr_rate' => $result->pmrRate,
                'snapshot' => $result->snapshot,
                'calculated_at' => now(),
            ],
        );

        if ($result->isInvalid() && $pile->pmr_status === null) {
            $pile->update(['pmr_status' => 'retest']);
        }

        return $result;
    }

    /**
     * Compute and optionally persist calculation for any group of records (including legacy).
     *
     * @param  Collection<int, PmrRecord>|array<int, mixed>  $records
     */
    public function calculateForGroup(iterable $records, ?string $groupKey = null, ?string $profile = null): PmrCalculationResult
    {
        $collection = $records instanceof Collection ? $records : collect($records);
        $firstRecord = $collection->first();

        if ($firstRecord instanceof PmrRecord && $firstRecord->pile) {
            return $this->calculateAndStoreForPile($firstRecord->pile, $profile);
        }

        $result = $this->calculate($collection, null, null, $profile);

        if ($groupKey !== null) {
            PmrCalculation::updateOrCreate(
                ['group_key' => $groupKey],
                [
                    'pile_id' => null,
                    'trial_inputs' => array_map(fn ($t) => [
                        'trial_number' => $t['trial_number'],
                        'palay_input_kg' => $t['palay_input_kg'],
                        'rice_recovery_kg' => $t['rice_recovery_kg'],
                    ], $result->trials),
                    'trial_recoveries' => array_column($result->trials, 'milling_recovery', 'trial_number'),
                    'median' => $result->median,
                    'lower_limit' => $result->lowerLimit,
                    'upper_limit' => $result->upperLimit,
                    'outlier_results' => $result->trials,
                    'valid_trial_count' => $result->validTrialCount,
                    'outlier_count' => $result->outlierCount,
                    'standard_deviation' => $result->standardDeviation,
                    'coefficient_of_variation' => $result->coefficientOfVariation,
                    'is_outlier_valid' => $result->isOutlierValid,
                    'is_cv_valid' => $result->isCvValid,
                    'is_valid' => $result->isValid,
                    'status' => $result->status,
                    'status_message' => $result->statusMessage,
                    'pmr_rate' => $result->pmrRate,
                    'snapshot' => $result->snapshot,
                    'calculated_at' => now(),
                ],
            );
        }

        return $result;
    }
}
