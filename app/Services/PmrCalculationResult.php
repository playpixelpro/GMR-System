<?php

namespace App\Services;

use JsonSerializable;

class PmrCalculationResult implements JsonSerializable
{
    /**
     * @param  list<array{trial_number: int, palay_input_kg: float, rice_recovery_kg: float, milling_recovery: float, is_outlier: bool, status: string, record_id?: int|null}>  $trials
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public readonly array $trials,
        public readonly ?float $median,
        public readonly ?float $lowerLimit,
        public readonly ?float $upperLimit,
        public readonly int $validTrialCount,
        public readonly int $outlierCount,
        public readonly ?float $mean,
        public readonly ?float $standardDeviation,
        public readonly ?float $coefficientOfVariation,
        public readonly bool $isOutlierValid,
        public readonly bool $isCvValid,
        public readonly ?float $pmrRate,
        public readonly bool $isValid,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly string $statusMessage,
        public readonly array $snapshot,
        public readonly int $requiredTrials = 3,
        public readonly int $minimumValidTrials = 2,
        public readonly float $maxCvThreshold = 5.0,
    ) {}

    public function isApproved(): bool
    {
        return $this->isValid && $this->pmrRate !== null;
    }

    public function isIncomplete(): bool
    {
        return $this->status === 'INCOMPLETE';
    }

    public function isHistoricalLegacy(): bool
    {
        return $this->status === 'HISTORICAL_LEGACY';
    }

    public function isInvalid(): bool
    {
        return ! $this->isValid && ! $this->isIncomplete() && ! $this->isHistoricalLegacy();
    }

    public function isInvalidCv(): bool
    {
        return $this->status === 'INVALID_CV_EXCEEDED';
    }

    public function isInvalidOutliers(): bool
    {
        return $this->status === 'INVALID_FEWER_VALID_TRIALS';
    }

    public function getFormattedPmrRate(): string
    {
        if ($this->pmrRate === null) {
            return '—';
        }

        return number_format($this->pmrRate, 2).'%';
    }

    public function getFormattedMedian(): string
    {
        if ($this->median === null) {
            return '—';
        }

        return number_format($this->median, 2).'%';
    }

    public function getFormattedLowerLimit(): string
    {
        if ($this->lowerLimit === null) {
            return '—';
        }

        return number_format($this->lowerLimit, 2).'%';
    }

    public function getFormattedUpperLimit(): string
    {
        if ($this->upperLimit === null) {
            return '—';
        }

        return number_format($this->upperLimit, 2).'%';
    }

    public function getFormattedMean(): string
    {
        if ($this->mean === null) {
            return '—';
        }

        return number_format($this->mean, 2).'%';
    }

    public function getFormattedStandardDeviation(): string
    {
        if ($this->standardDeviation === null) {
            return '—';
        }

        return number_format($this->standardDeviation, 2);
    }

    public function getFormattedCv(): string
    {
        if ($this->coefficientOfVariation === null) {
            return '—';
        }

        return number_format($this->coefficientOfVariation, 2).'%';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'trials' => $this->trials,
            'median' => $this->median,
            'lower_limit' => $this->lowerLimit,
            'upper_limit' => $this->upperLimit,
            'valid_trial_count' => $this->validTrialCount,
            'outlier_count' => $this->outlierCount,
            'mean' => $this->mean,
            'standard_deviation' => $this->standardDeviation,
            'coefficient_of_variation' => $this->coefficientOfVariation,
            'is_outlier_valid' => $this->isOutlierValid,
            'is_cv_valid' => $this->isCvValid,
            'pmr_rate' => $this->pmrRate,
            'formatted_pmr_rate' => $this->getFormattedPmrRate(),
            'formatted_median' => $this->getFormattedMedian(),
            'formatted_lower_limit' => $this->getFormattedLowerLimit(),
            'formatted_upper_limit' => $this->getFormattedUpperLimit(),
            'formatted_mean' => $this->getFormattedMean(),
            'formatted_standard_deviation' => $this->getFormattedStandardDeviation(),
            'formatted_cv' => $this->getFormattedCv(),
            'is_valid' => $this->isValid,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'status_message' => $this->statusMessage,
            'snapshot' => $this->snapshot,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
