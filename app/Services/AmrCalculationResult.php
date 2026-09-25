<?php

namespace App\Services;

use JsonSerializable;

class AmrCalculationResult implements JsonSerializable
{
    /**
     * @param  list<array{trial_number: int, palay_input_kg: float, rice_recovery_kg: float, milling_recovery: float, is_outlier: bool, status: string}>  $trials
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public readonly array $trials,
        public readonly ?float $median,
        public readonly ?float $lowerLimit,
        public readonly ?float $upperLimit,
        public readonly int $validTrialCount,
        public readonly int $outlierCount,
        public readonly ?float $amrRate,
        public readonly bool $isValid,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly string $statusMessage,
        public readonly array $snapshot,
    ) {}

    public function isApproved(): bool
    {
        return $this->isValid && $this->amrRate !== null;
    }

    public function isIncomplete(): bool
    {
        return $this->status === 'INCOMPLETE';
    }

    public function isInvalidOutliers(): bool
    {
        return $this->status === 'INVALID_FEWER_VALID_TRIALS';
    }

    public function getFormattedAmrRate(): string
    {
        if ($this->amrRate === null) {
            return '—';
        }

        return number_format($this->amrRate, 2).'%';
    }

    public function getFormattedMedian(): string
    {
        if ($this->median === null) {
            return '—';
        }

        return number_format($this->median, 2).'%';
    }

    public function getFormattedMean(): string
    {
        return $this->getFormattedMedian();
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
            'amr_rate' => $this->amrRate,
            'formatted_amr_rate' => $this->getFormattedAmrRate(),
            'formatted_median' => $this->getFormattedMedian(),
            'formatted_lower_limit' => $this->getFormattedLowerLimit(),
            'formatted_upper_limit' => $this->getFormattedUpperLimit(),
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
