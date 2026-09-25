<?php

namespace App\Services;

use App\Models\Pile;
use App\Models\PmrCalculation;
use App\Models\PmrRecord;
use Illuminate\Support\Collection;

class PmrCalculationService
{
    public const REQUIRED_TRIALS = 3;

    public const MINIMUM_VALID_TRIALS = 2;

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
     * Compute PMR, outlier evaluation, and CV validation from 3 laboratory milling trials.
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

            $palayInput = $trial instanceof PmrRecord
                ? ($trial->palay_input_kg !== null ? (float) $trial->palay_input_kg : null)
                : (is_array($trial)
                    ? (isset($trial['palay_input_kg']) && $trial['palay_input_kg'] !== '' && $trial['palay_input_kg'] !== null ? (float) $trial['palay_input_kg'] : (isset($trial['palay_input']) && $trial['palay_input'] !== '' && $trial['palay_input'] !== null ? (float) $trial['palay_input'] : null))
                    : (isset($trial->palay_input_kg) && $trial->palay_input_kg !== null ? (float) $trial->palay_input_kg : null));

            $riceRecovery = $trial instanceof PmrRecord
                ? ($trial->rice_recovery_kg !== null ? (float) $trial->rice_recovery_kg : null)
                : (is_array($trial)
                    ? (isset($trial['rice_recovery_kg']) && $trial['rice_recovery_kg'] !== '' && $trial['rice_recovery_kg'] !== null ? (float) $trial['rice_recovery_kg'] : (isset($trial['rice_recovery']) && $trial['rice_recovery'] !== '' && $trial['rice_recovery'] !== null ? (float) $trial['rice_recovery'] : null))
                    : (isset($trial->rice_recovery_kg) && $trial->rice_recovery_kg !== null ? (float) $trial->rice_recovery_kg : null));

            $providedRecovery = $trial instanceof PmrRecord
                ? ($trial->milling_recovery !== null ? (float) $trial->milling_recovery : null)
                : (is_array($trial)
                    ? (isset($trial['recovery_rate']) && $trial['recovery_rate'] !== '' && $trial['recovery_rate'] !== null ? (float) $trial['recovery_rate'] : (isset($trial['milling_recovery']) && $trial['milling_recovery'] !== '' && $trial['milling_recovery'] !== null ? (float) $trial['milling_recovery'] : null))
                    : (isset($trial->milling_recovery) && $trial->milling_recovery !== null ? (float) $trial->milling_recovery : (isset($trial->recovery_rate) && $trial->recovery_rate !== null ? (float) $trial->recovery_rate : null)));

            // Priority:
            // 1. If both Palay Input and Rice Output are available and Palay Input > 0:
            //    Milling Recovery (%) = (Rice Output / Palay Input) * 100
            // 2. Else: use directly provided Recovery Rate
            if ($palayInput !== null && $riceRecovery !== null && $palayInput > 0) {
                $millingRecovery = round(($riceRecovery / $palayInput) * 100, 2);
            } elseif ($providedRecovery !== null) {
                $millingRecovery = round($providedRecovery, 2);
            } else {
                $millingRecovery = 0.0;
            }

            $normalizedTrials[$trialNumber] = [
                'trial_number' => $trialNumber,
                'palay_input_kg' => $palayInput !== null ? round($palayInput, 2) : null,
                'rice_recovery_kg' => $riceRecovery !== null ? round($riceRecovery, 2) : null,
                'milling_recovery' => $millingRecovery,
                'is_outlier' => false,
                'status' => 'PENDING',
                'record_id' => $trial instanceof PmrRecord ? $trial->id : null,
            ];
        }

        ksort($normalizedTrials);
        $trialCount = count($normalizedTrials);

        // When fewer than required trials (incomplete)
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
                'status_message' => "THREE (3) laboratory milling trials are required to compute PMR (currently {$trialCount}/{$requiredTrials}).",
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
                statusMessage: "THREE (3) laboratory milling trials are required to compute PMR (currently {$trialCount}/{$requiredTrials}).",
                snapshot: $snapshot,
                requiredTrials: $requiredTrials,
                minimumValidTrials: $minimumValidTrials,
                maxCvThreshold: $maxCvThreshold,
            );
        }

        // Rule 15: Handle legacy >3 trial records as historical data
        if ($trialCount > $requiredTrials) {
            $snapshot = [
                'formula' => 'NFA Potential Milling Recovery (PMR)',
                'rule_version' => 'NFA-PMR-HISTORICAL-LEGACY',
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
                'status' => 'HISTORICAL_LEGACY',
                'status_label' => "Historical Legacy ({$trialCount} Trials)",
                'status_message' => "Historical {$trialCount}-trial PMR record preserved. Under current NFA guidelines, PMR must be re-established under current 3-trial rules (laboratory milling trials).",
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
                status: 'HISTORICAL_LEGACY',
                statusLabel: "Historical Legacy ({$trialCount} Trials)",
                statusMessage: "Historical {$trialCount}-trial PMR record preserved. Under current NFA guidelines, PMR must be re-established under current 3-trial rules (laboratory milling trials).",
                snapshot: $snapshot,
                requiredTrials: $requiredTrials,
                minimumValidTrials: $minimumValidTrials,
                maxCvThreshold: $maxCvThreshold,
            );
        }

        // Take the 3 trials for calculation
        $calculationTrials = array_slice($normalizedTrials, 0, $requiredTrials, true);
        $recoveries = array_column($calculationTrials, 'milling_recovery');
        sort($recoveries, SORT_NUMERIC);

        // Calculate the median of the 3 trial recovery results (index 1 in sorted 3-element list)
        $medianIndex = (int) floor(count($recoveries) / 2);
        $median = round((float) $recoveries[$medianIndex], 2);

        // Apply the ±2% outlier rule based on the median
        // Lower Limit = Median * (1 - tolerance), Upper Limit = Median * (1 + tolerance)
        $lowerLimit = round($median * (1 - $tolerance), 4);
        $upperLimit = round($median * (1 + $tolerance), 4);

        // Mark each trial as VALID or OUTLIER. Never delete original trial data.
        $validTrialCount = 0;
        $outlierCount = 0;
        $epsilon = 0.00001;

        foreach ($calculationTrials as $num => $t) {
            $rec = (float) $t['milling_recovery'];
            // Inclusive boundary check: lowerLimit <= rec <= upperLimit is valid
            $isOutlier = ($rec < ($lowerLimit - $epsilon)) || ($rec > ($upperLimit + $epsilon));

            $calculationTrials[$num]['is_outlier'] = $isOutlier;
            $calculationTrials[$num]['status'] = $isOutlier ? 'OUTLIER' : 'VALID';

            if ($isOutlier) {
                $outlierCount++;
            } else {
                $validTrialCount++;
            }
        }

        // Outlier validation: At least minimumValidTrials (default 2) must remain
        $isOutlierValid = ($validTrialCount >= $minimumValidTrials);

        // Exclude outliers; PMR = arithmetic mean of remaining valid trials
        $validTrials = array_filter($calculationTrials, fn ($t) => ! $t['is_outlier']);
        $validRecoveries = array_column($validTrials, 'milling_recovery');

        $mean = $validTrialCount > 0
            ? round(array_sum($validRecoveries) / $validTrialCount, 2)
            : null;

        // Sample Standard Deviation (N-1)
        $standardDeviation = $this->calculateSampleStandardDeviation($validRecoveries, $mean);

        // Coefficient of Variation: CV = (SD / Mean) * 100
        $cv = ($standardDeviation !== null && $mean !== null && $mean > 0)
            ? round(($standardDeviation / $mean) * 100, 2)
            : ($validTrialCount === 1 ? 0.0 : null);

        // Evaluate CV against configured threshold (threshold: <= 5.00%)
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
            $formattedCv = number_format($cv ?? 0, 2);
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

        // Store calculation details & snapshot for auditability
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
     * The current CV requirement is <= 5%. CV of exactly 5.00% passes; CV above 5.00% fails.
     */
    public function evaluateCv(?float $standardDeviation, ?float $mean, ?float $maxCv = null): bool
    {
        if ($standardDeviation === null || $mean === null || $mean <= 0.0) {
            return false;
        }

        $threshold = $maxCv ?? $this->getMaxCvThreshold();
        $cv = round(($standardDeviation / $mean) * 100, 2);

        return $cv <= $threshold;
    }

    /**
     * Evaluate re-establishment flags based on the latest NFA guidelines.
     *
     * A PMR and/or AMR must be flagged for re-establishment when:
     * - PMR and/or AMR is 60.0% or below (<= 60.0%);
     * - PMR is less than AMR (PMR < AMR); or
     * - AMR is less than PMR by more than 3 percentage points (AMR < PMR - 3.0 percentage points, or PMR - AMR > 3.0).
     *
     * @return array{
     *     requires_reestablishment: bool,
     *     is_pmr_below_60: bool,
     *     is_amr_below_60: bool,
     *     is_pmr_below_amr: bool,
     *     is_amr_divergent_from_pmr: bool,
     *     flags: list<string>,
     *     reasons: list<string>,
     *     pmr_rate: ?float,
     *     amr_rate: ?float,
     *     difference: ?float,
     *     spread_difference: ?float
     * }
     */
    public function evaluateReestablishment(?float $pmrRate, ?float $amrRate): array
    {
        $isPmrBelow60 = $pmrRate !== null && $pmrRate <= 60.0;
        $isAmrBelow60 = $amrRate !== null && $amrRate <= 60.0;
        $isPmrBelowAmr = $pmrRate !== null && $amrRate !== null && $pmrRate < $amrRate;

        // Difference: PMR - AMR (how much PMR exceeds AMR)
        $difference = ($pmrRate !== null && $amrRate !== null)
            ? round($pmrRate - $amrRate, 4)
            : null;

        // AMR is less than PMR by more than 3 percentage points => PMR - AMR > 3.0
        $isAmrDivergent = $difference !== null && $difference > 3.00;

        $flags = [];
        $reasons = [];

        if ($isPmrBelow60) {
            $flags[] = 'PMR_BELOW_60';
            $reasons[] = 'PMR ('.number_format($pmrRate, 2).'%) is 60.0% or below.';
        }

        if ($isAmrBelow60) {
            $flags[] = 'AMR_BELOW_60';
            $reasons[] = 'AMR ('.number_format($amrRate, 2).'%) is 60.0% or below.';
        }

        if ($isPmrBelowAmr) {
            $flags[] = 'PMR_BELOW_AMR';
            $reasons[] = 'PMR ('.number_format($pmrRate, 2).'%) is less than AMR ('.number_format($amrRate, 2).'%).';
        }

        if ($isAmrDivergent) {
            $flags[] = 'AMR_DIVERGENT';
            $formattedDiff = number_format($difference, 2);
            $reasons[] = 'AMR is less than PMR by more than 3 percentage points (difference: '.$formattedDiff.' points).';
        }

        $requires = $isPmrBelow60 || $isAmrBelow60 || $isPmrBelowAmr || $isAmrDivergent;

        return [
            'requires_reestablishment' => $requires,
            'is_pmr_below_60' => $isPmrBelow60,
            'is_amr_below_60' => $isAmrBelow60,
            'is_pmr_below_amr' => $isPmrBelowAmr,
            'is_amr_divergent_from_pmr' => $isAmrDivergent,
            'flags' => $flags,
            'reasons' => $reasons,
            'pmr_rate' => $pmrRate,
            'amr_rate' => $amrRate,
            'difference' => $difference,
            'spread_difference' => $difference,
        ];
    }

    /**
     * Evaluate re-establishment flags for a master pile.
     *
     * @return array{
     *     requires_reestablishment: bool,
     *     is_pmr_below_60: bool,
     *     is_amr_below_60: bool,
     *     is_pmr_below_amr: bool,
     *     is_amr_divergent_from_pmr: bool,
     *     flags: list<string>,
     *     reasons: list<string>,
     *     pmr_rate: ?float,
     *     amr_rate: ?float,
     *     difference: ?float,
     *     spread_difference: ?float
     * }
     */
    public function evaluateReestablishmentForPile(Pile $pile): array
    {
        $pile->loadMissing(['pmrCalculation', 'amrCalculation', 'pmrRecords', 'amrRecords']);

        $pmrRate = $pile->pmrCalculation?->pmr_rate;
        if ($pmrRate === null && $pile->pmrRecords->isNotEmpty()) {
            $validPmr = $pile->pmrRecords->filter(fn ($r) => (float) $r->recovery_rate_percentage > 0)
                ->map(fn ($r) => (float) $r->recovery_rate_percentage);
            $pmrRate = $validPmr->isNotEmpty() ? (float) $validPmr->avg() : null;
        }

        $amrRate = $pile->amrCalculation?->amr_rate;
        if ($amrRate === null && $pile->amrRecords->isNotEmpty()) {
            $validAmr = $pile->amrRecords->filter(fn ($r) => (float) $r->palay_input_kg > 0)
                ->map(fn ($r) => (float) $r->milling_recovery_percentage);
            $amrRate = $validAmr->isNotEmpty() ? (float) $validAmr->avg() : null;
        }

        return $this->evaluateReestablishment(
            $pmrRate !== null ? (float) $pmrRate : null,
            $amrRate !== null ? (float) $amrRate : null,
        );
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
