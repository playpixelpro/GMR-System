<?php

namespace App\Services;

use App\Models\AmrRecord;
use App\Models\Pile;
use App\Models\PmrRecord;

class EmrGmrGateService
{
    /**
     * Determine if AMR is eligible for EMR/GMR computation.
     *
     * Verification Requirements:
     * - Valid current AMR Test Milling Data exists.
     * - Required AMR Test Milling Data is complete (3 trials).
     * - The current AMR Test Milling Data is LOCKED/FINALIZED.
     * - The current AMR Test Milling Data has a final RMEC action of RECOMMEND.
     *
     * @return array{
     *     eligible: bool,
     *     status: string,
     *     rate: ?float,
     *     conduct_number: ?int,
     *     message: string
     * }
     */
    public function evaluateAmr(Pile $pile): array
    {
        $pile->loadMissing('amrRecords');
        $records = $pile->amrRecords;

        if ($records->isEmpty()) {
            return [
                'eligible' => false,
                'status' => 'MISSING',
                'rate' => null,
                'conduct_number' => null,
                'message' => 'AMR Test Milling Data missing',
            ];
        }

        $latestConduct = $records->max('conduct_number') ?? 1;
        $conductRecords = $records->where('conduct_number', $latestConduct);

        if ($conductRecords->count() < 3) {
            return [
                'eligible' => false,
                'status' => 'INCOMPLETE',
                'rate' => null,
                'conduct_number' => $latestConduct,
                'message' => 'AMR Test Milling Data incomplete ('.$conductRecords->count().'/3 trials)',
            ];
        }

        $isLocked = $conductRecords->every(fn (AmrRecord $r): bool => (bool) $r->is_locked);
        $isRetest = $conductRecords->contains(fn (AmrRecord $r): bool => strtoupper((string) $r->status) === 'RETEST');
        $isRecommended = $conductRecords->every(fn (AmrRecord $r): bool => strtoupper((string) $r->status) === 'RECOMMENDED')
            && strtoupper((string) $conductRecords->first()->status) === 'RECOMMENDED';

        if ($isRetest) {
            return [
                'eligible' => false,
                'status' => 'RETEST',
                'rate' => null,
                'conduct_number' => $latestConduct,
                'message' => 'AMR Retest Required',
            ];
        }

        if (! $isLocked || ! $isRecommended) {
            return [
                'eligible' => false,
                'status' => 'PENDING',
                'rate' => null,
                'conduct_number' => $latestConduct,
                'message' => 'AMR Pending RMEC Action',
            ];
        }

        // Current conduct is LOCKED and RECOMMENDED
        $validTrials = $conductRecords->filter(
            fn (AmrRecord $r): bool => ! $r->is_outlier && (float) $r->milling_recovery_percentage > 0
        );

        if ($validTrials->isEmpty()) {
            $validTrials = $conductRecords->filter(
                fn (AmrRecord $r): bool => (float) $r->milling_recovery_percentage > 0
            );
        }

        $rate = $validTrials->isNotEmpty()
            ? round((float) $validTrials->avg(fn (AmrRecord $r): float => (float) $r->milling_recovery_percentage), 2)
            : null;

        return [
            'eligible' => $rate !== null,
            'status' => 'RECOMMENDED',
            'rate' => $rate,
            'conduct_number' => $latestConduct,
            'message' => 'AMR Recommended',
        ];
    }

    /**
     * Determine if PMR is eligible for EMR/GMR computation.
     *
     * Verification Requirements:
     * - Valid current PMR Laboratory Test Milling Data exists.
     * - Required PMR Laboratory Test Milling Data is complete (3 trials).
     * - The current PMR Laboratory Test Milling Data is LOCKED/FINALIZED.
     * - The current PMR Laboratory Test Milling Data has a final RMEC action of RECOMMEND.
     *
     * @return array{
     *     eligible: bool,
     *     status: string,
     *     rate: ?float,
     *     conduct_number: ?int,
     *     message: string
     * }
     */
    public function evaluatePmr(Pile $pile): array
    {
        $pile->loadMissing('pmrRecords');
        $records = $pile->pmrRecords;

        if ($records->isEmpty()) {
            return [
                'eligible' => false,
                'status' => 'MISSING',
                'rate' => null,
                'conduct_number' => null,
                'message' => 'PMR Laboratory Test Milling Data missing',
            ];
        }

        $latestConduct = $records->max('conduct_number') ?? 1;
        $conductRecords = $records->where('conduct_number', $latestConduct);

        if ($conductRecords->count() < 3) {
            return [
                'eligible' => false,
                'status' => 'INCOMPLETE',
                'rate' => null,
                'conduct_number' => $latestConduct,
                'message' => 'PMR Laboratory Test Milling Data incomplete ('.$conductRecords->count().'/3 trials)',
            ];
        }

        $isLocked = $conductRecords->every(fn (PmrRecord $r): bool => (bool) $r->is_locked);
        $isRetest = $conductRecords->contains(fn (PmrRecord $r): bool => strtoupper((string) $r->status) === 'RETEST');
        $isRecommended = $conductRecords->every(fn (PmrRecord $r): bool => strtoupper((string) $r->status) === 'RECOMMENDED')
            && strtoupper((string) $conductRecords->first()->status) === 'RECOMMENDED';

        if ($isRetest) {
            return [
                'eligible' => false,
                'status' => 'RETEST',
                'rate' => null,
                'conduct_number' => $latestConduct,
                'message' => 'PMR Retest Required',
            ];
        }

        if (! $isLocked || ! $isRecommended) {
            return [
                'eligible' => false,
                'status' => 'PENDING',
                'rate' => null,
                'conduct_number' => $latestConduct,
                'message' => 'PMR Pending RMEC Action',
            ];
        }

        // Current conduct is LOCKED and RECOMMENDED
        $validTrials = $conductRecords->filter(
            fn (PmrRecord $r): bool => ! $r->is_outlier && (float) $r->recovery_rate_percentage > 0
        );

        if ($validTrials->isEmpty()) {
            $validTrials = $conductRecords->filter(
                fn (PmrRecord $r): bool => (float) $r->recovery_rate_percentage > 0
            );
        }

        $rate = $validTrials->isNotEmpty()
            ? round((float) $validTrials->avg(fn (PmrRecord $r): float => (float) $r->recovery_rate_percentage), 2)
            : null;

        return [
            'eligible' => $rate !== null,
            'status' => 'RECOMMENDED',
            'rate' => $rate,
            'conduct_number' => $latestConduct,
            'message' => 'PMR Recommended',
        ];
    }

    /**
     * Evaluate EMR/GMR Gate for a Pile.
     *
     * Business Rule:
     * The existence of AMR Test Milling Data and PMR Laboratory Test Milling Data
     * alone is NOT sufficient to compute EMR/GMR.
     *
     * Only when BOTH AMR AND PMR are LOCKED and have final RMEC RECOMMEND
     * may the existing EMR and GMR formulas execute.
     *
     * @return array{
     *     can_compute: bool,
     *     amr_rate: ?float,
     *     pmr_rate: ?float,
     *     emr_lower: ?float,
     *     emr_upper: ?float,
     *     emr_display: string,
     *     gmr: ?float,
     *     status: string,
     *     gate_message: string,
     *     amr_eval: array{eligible: bool, status: string, rate: ?float, conduct_number: ?int, message: string},
     *     pmr_eval: array{eligible: bool, status: string, rate: ?float, conduct_number: ?int, message: string}
     * }
     */
    public function evaluateGate(Pile $pile): array
    {
        $amrEval = $this->evaluateAmr($pile);
        $pmrEval = $this->evaluatePmr($pile);

        if ($amrEval['eligible'] && $pmrEval['eligible']) {
            $amrRate = $amrEval['rate'];
            $pmrRate = $pmrEval['rate'];
            $emrLower = $amrRate;
            $emrUpper = $pmrRate;
            $emrDisplay = number_format((float) $emrLower, 2).'% – '.number_format((float) $emrUpper, 2).'%';
            $gmr = round(((float) $amrRate + (float) $pmrRate) / 2, 2);

            $status = $pmrRate >= $amrRate ? 'VALID' : 'QUESTIONABLE';

            return [
                'can_compute' => true,
                'amr_rate' => $amrRate,
                'pmr_rate' => $pmrRate,
                'emr_lower' => $emrLower,
                'emr_upper' => $emrUpper,
                'emr_display' => $emrDisplay,
                'gmr' => $gmr,
                'status' => $status,
                'gate_message' => 'Computed (AMR & PMR Recommended)',
                'amr_eval' => $amrEval,
                'pmr_eval' => $pmrEval,
            ];
        }

        $blockedStatus = $this->determineBlockedStatus($amrEval, $pmrEval);

        return [
            'can_compute' => false,
            'amr_rate' => null,
            'pmr_rate' => null,
            'emr_lower' => null,
            'emr_upper' => null,
            'emr_display' => 'N/A',
            'gmr' => null,
            'status' => $blockedStatus,
            'gate_message' => $blockedStatus,
            'amr_eval' => $amrEval,
            'pmr_eval' => $pmrEval,
        ];
    }

    /**
     * Determine appropriate blocked status message when computation cannot proceed.
     */
    public function determineBlockedStatus(array $amrEval, array $pmrEval): string
    {
        // 1. Retest required takes precedence
        if ($amrEval['status'] === 'RETEST' || $pmrEval['status'] === 'RETEST') {
            return 'EMR/GMR Blocked - Retest Required';
        }

        // 2. AMR is recommended, PMR is not recommended
        if ($amrEval['eligible'] && ! $pmrEval['eligible']) {
            return 'EMR/GMR Pending - PMR Recommendation Required';
        }

        // 3. PMR is recommended, AMR is not recommended
        if ($pmrEval['eligible'] && ! $amrEval['eligible']) {
            return 'EMR/GMR Pending - AMR Recommendation Required';
        }

        // 4. Neither has RMEC RECOMMEND
        return 'EMR/GMR Pending - RMEC Action Required';
    }
}
