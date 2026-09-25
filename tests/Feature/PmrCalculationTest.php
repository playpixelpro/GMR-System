<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrCalculation;
use App\Models\PmrRecord;
use App\Models\Warehouse;
use App\Services\PmrCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmrCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected PmrCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PmrCalculationService::class);
    }

    public function test_normal_3_trial_calculations_without_outliers(): void
    {
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 6500], // 65.00%
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6550], // 65.50%
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 6600], // 66.00%
        ];

        // Sorted: 65.00, 65.50, 66.00 => Median = 65.50%
        // Lower limit = 65.50 * 0.98 = 64.19%
        // Upper limit = 65.50 * 1.02 = 66.81%
        $result = $this->service->calculate($trials);

        $this->assertTrue($result->isValid);
        $this->assertTrue($result->isOutlierValid);
        $this->assertTrue($result->isCvValid);
        $this->assertSame(65.50, $result->median);
        $this->assertSame(64.19, $result->lowerLimit);
        $this->assertSame(66.81, $result->upperLimit);
        $this->assertSame(3, $result->validTrialCount);
        $this->assertSame(0, $result->outlierCount);
        $this->assertSame(65.50, $result->pmrRate);
        $this->assertSame('VALID', $result->status);
        $this->assertNotNull($result->standardDeviation);
        $this->assertNotNull($result->coefficientOfVariation);
        $this->assertLessThanOrEqual(5.0, $result->coefficientOfVariation);

        foreach ($result->trials as $trial) {
            $this->assertSame('VALID', $trial['status']);
            $this->assertFalse($trial['is_outlier']);
        }
    }

    public function test_outlier_identified_and_excluded_from_pmr_calculation(): void
    {
        // 3 trials: Trial 3 is an outlier at 59.00%
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 6500], // 65.00%
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6550], // 65.50%
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 5900], // 59.00% (Outlier)
        ];

        // Sorted: 59.00, 65.00, 65.50 => Median = 65.00%
        // Lower limit = 65.00 * 0.98 = 63.70%
        // Upper limit = 65.00 * 1.02 = 66.30%
        $result = $this->service->calculate($trials);

        $this->assertTrue($result->isValid);
        $this->assertTrue($result->isOutlierValid);
        $this->assertTrue($result->isCvValid);
        $this->assertSame(65.00, $result->median);
        $this->assertSame(63.70, $result->lowerLimit);
        $this->assertSame(66.30, $result->upperLimit);
        $this->assertSame(2, $result->validTrialCount);
        $this->assertSame(1, $result->outlierCount);

        // Arithmetic mean of remaining 2 valid trials: (65.00 + 65.50) / 2 = 65.25%
        $this->assertSame(65.25, $result->pmrRate);

        // Verify outlier data is preserved in trials array and flagged
        $trial3 = collect($result->trials)->firstWhere('trial_number', 3);
        $this->assertNotNull($trial3);
        $this->assertTrue($trial3['is_outlier']);
        $this->assertSame('OUTLIER', $trial3['status']);
        $this->assertSame(59.00, $trial3['milling_recovery']);
    }

    public function test_insufficient_valid_trials_when_two_outliers_occur(): void
    {
        // 3 trials: Median is 65.00%, lower limit 63.70%, upper limit 66.30%
        // Trials 1 & 3 are outliers
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 5500], // 55.00% (Outlier)
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6500], // 65.00% (Median)
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 7500], // 75.00% (Outlier)
        ];

        $result = $this->service->calculate($trials);

        $this->assertFalse($result->isValid);
        $this->assertFalse($result->isOutlierValid);
        $this->assertNull($result->pmrRate);
        $this->assertSame(1, $result->validTrialCount);
        $this->assertSame(2, $result->outlierCount);
        $this->assertSame('INVALID_FEWER_VALID_TRIALS', $result->status);
        $this->assertSame('Invalid / Requires Re-establishment', $result->statusLabel);
        $this->assertStringContainsString('Fewer than 2 valid trials remain', $result->statusMessage);
    }

    public function test_exactly_plus_minus_two_percent_boundaries_are_valid(): void
    {
        // Median is 60.00%
        // Lower limit (-2%): 60.00 * 0.98 = 58.80%
        // Upper limit (+2%): 60.00 * 1.02 = 61.20%
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 5880], // 58.80% (exactly lower boundary)
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6000], // 60.00% (median)
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 6120], // 61.20% (exactly upper boundary)
        ];

        $result = $this->service->calculate($trials);

        $this->assertTrue($result->isValid);
        $this->assertTrue($result->isOutlierValid);
        $this->assertTrue($result->isCvValid);
        $this->assertSame(60.00, $result->median);
        $this->assertSame(58.80, $result->lowerLimit);
        $this->assertSame(61.20, $result->upperLimit);
        $this->assertSame(3, $result->validTrialCount);
        $this->assertSame(0, $result->outlierCount);
        $this->assertSame(60.00, $result->pmrRate);

        // Both boundary trials must be classified as VALID
        $trial1 = collect($result->trials)->firstWhere('trial_number', 1);
        $trial3 = collect($result->trials)->firstWhere('trial_number', 3);
        $this->assertSame('VALID', $trial1['status']);
        $this->assertSame('VALID', $trial3['status']);
        $this->assertFalse($trial1['is_outlier']);
        $this->assertFalse($trial3['is_outlier']);
    }

    public function test_cv_within_threshold_passes_statistical_requirement(): void
    {
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 6400], // 64.00%
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6500], // 65.00%
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 6600], // 66.00%
        ];

        $result = $this->service->calculate($trials);

        $this->assertTrue($result->isCvValid);
        $this->assertTrue($result->isValid);
        $this->assertLessThanOrEqual(5.0, $result->coefficientOfVariation);
        $this->assertSame(65.00, $result->pmrRate);
        $this->assertSame('VALID', $result->status);
    }

    public function test_cv_above_threshold_fails_even_without_outliers(): void
    {
        // Direct CV evaluation: SD = 3.6, Mean = 60.0 => CV = (3.6 / 60.0) * 100 = 6.00% > 5.00%
        $this->assertFalse($this->service->evaluateCv(3.6, 60.0, 5.0));

        // Exactly 5.00% passes
        $this->assertTrue($this->service->evaluateCv(3.0, 60.0, 5.0));

        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 5600], // 56.00%
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6000], // 60.00%
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 6400], // 64.00%
        ];

        // Using larger outlier tolerance so trials are within boundaries, but CV is (4.0 / 60.0) * 100 = 6.67% > 5%
        $result = $this->service->calculate($trials, outlierTolerance: 0.10, maxCv: 5.0);

        $this->assertTrue($result->isOutlierValid);
        $this->assertFalse($result->isCvValid);
        $this->assertFalse($result->isValid);
        $this->assertNull($result->pmrRate);
        $this->assertSame('INVALID_CV_EXCEEDED', $result->status);
        $this->assertSame('Invalid / Requires Re-establishment', $result->statusLabel);
        $this->assertStringContainsString('exceeds the 5% maximum threshold', $result->statusMessage);
    }

    public function test_historical_legacy_data_flagged_when_more_than_three_trials(): void
    {
        // Legacy record with 5 trials from previous implementation
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 6500],
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6600],
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 6550],
            ['trial_number' => 4, 'palay_input' => 10000, 'rice_recovery' => 6580],
            ['trial_number' => 5, 'palay_input' => 10000, 'rice_recovery' => 6490],
        ];

        $result = $this->service->calculate($trials);

        $this->assertFalse($result->isValid);
        $this->assertTrue($result->isHistoricalLegacy());
        $this->assertSame('HISTORICAL_LEGACY', $result->status);
        $this->assertSame('Historical Legacy (5 Trials)', $result->statusLabel);
        $this->assertStringContainsString('re-established under current 3-trial rules', $result->statusMessage);
    }

    public function test_reestablishment_conditions_evaluation(): void
    {
        // Case 1: PMR <= 60.0%
        $eval1 = $this->service->evaluateReestablishment(pmrRate: 59.5, amrRate: 59.0);
        $this->assertTrue($eval1['requires_reestablishment']);
        $this->assertTrue($eval1['is_pmr_below_60']);
        $this->assertTrue($eval1['is_amr_below_60']);

        // Case 2: PMR < AMR
        $eval2 = $this->service->evaluateReestablishment(pmrRate: 62.0, amrRate: 64.0);
        $this->assertTrue($eval2['requires_reestablishment']);
        $this->assertTrue($eval2['is_pmr_below_amr']);
        $this->assertFalse($eval2['is_pmr_below_60']);

        // Case 3: AMR < PMR by more than 3 percentage points (PMR - AMR > 3.0)
        $eval3 = $this->service->evaluateReestablishment(pmrRate: 67.0, amrRate: 62.0);
        $this->assertTrue($eval3['requires_reestablishment']);
        $this->assertTrue($eval3['is_amr_divergent_from_pmr']);
        $this->assertSame(5.0, $eval3['spread_difference']);

        // Case 4: Compliant (PMR = 65.0%, AMR = 64.0%, spread = 1.0% <= 3.0%, both > 60%)
        $eval4 = $this->service->evaluateReestablishment(pmrRate: 65.0, amrRate: 64.0);
        $this->assertFalse($eval4['requires_reestablishment']);
        $this->assertFalse($eval4['is_pmr_below_60']);
        $this->assertFalse($eval4['is_amr_below_60']);
        $this->assertFalse($eval4['is_pmr_below_amr']);
        $this->assertFalse($eval4['is_amr_divergent_from_pmr']);
        $this->assertEmpty($eval4['flags']);
    }

    public function test_incomplete_when_fewer_than_three_trials(): void
    {
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 6500],
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6550],
        ];

        $result = $this->service->calculate($trials);

        $this->assertFalse($result->isValid);
        $this->assertTrue($result->isIncomplete());
        $this->assertNull($result->pmrRate);
        $this->assertSame('INCOMPLETE', $result->status);
        $this->assertSame('Incomplete (2/3 Trials)', $result->statusLabel);
        $this->assertStringContainsString('THREE (3) laboratory milling trials are required', $result->statusMessage);
    }

    public function test_calculate_and_store_for_pile_persists_three_trials_snapshot(): void
    {
        $branch = Branch::firstOrCreate(['name' => 'PMR Test Branch']);
        $warehouse = Warehouse::create(['name' => 'WH-PMR-A', 'branch_id' => $branch->id]);
        $pile = Pile::create(['number' => 'P-101', 'warehouse_id' => $warehouse->id]);

        $recoveryRates = [65.00, 66.00, 65.50];
        foreach ($recoveryRates as $index => $rate) {
            PmrRecord::factory()->create([
                'pile_id' => $pile->id,
                'warehouse_name' => $warehouse->name,
                'pile_number' => $pile->number,
                'trial_number' => $index + 1,
                'palay_input_kg' => 10000,
                'rice_recovery_kg' => $rate * 100,
            ]);
        }

        $result = $this->service->calculateAndStoreForPile($pile);

        $this->assertTrue($result->isValid);
        $this->assertSame(65.50, $result->pmrRate);

        // Verify pmr_calculations table persistence
        $this->assertDatabaseHas('pmr_calculations', [
            'pile_id' => $pile->id,
            'group_key' => 'pile:'.$pile->id,
            'is_valid' => true,
            'status' => 'VALID',
            'valid_trial_count' => 3,
            'outlier_count' => 0,
            'pmr_rate' => 65.50,
        ]);

        // Verify all 3 trial records were updated with audit flags
        for ($i = 1; $i <= 3; $i++) {
            $this->assertDatabaseHas('pmr_records', [
                'pile_id' => $pile->id,
                'trial_number' => $i,
                'is_outlier' => false,
            ]);
        }

        $calcRecord = PmrCalculation::where('pile_id', $pile->id)->first();
        $this->assertNotNull($calcRecord);
        $this->assertIsArray($calcRecord->snapshot);
        $this->assertSame('NFA Potential Milling Recovery (PMR)', $calcRecord->snapshot['formula']);
        $this->assertSame(3, $calcRecord->snapshot['required_trials']);
    }

    public function test_pmr_report_displays_three_trials_and_computation_modal(): void
    {
        $branch = Branch::create(['name' => 'Branch Cotabato']);
        $warehouse = Warehouse::create(['name' => 'Warehouse Alpha', 'branch_id' => $branch->id]);
        $pile = Pile::create(['number' => 'P-1', 'warehouse_id' => $warehouse->id]);

        $recoveryRates = [65.00, 66.00, 65.50];
        foreach ($recoveryRates as $index => $rate) {
            PmrRecord::factory()->create([
                'pile_id' => $pile->id,
                'warehouse_name' => $warehouse->name,
                'pile_number' => $pile->number,
                'variety' => 'PD',
                'aged_months' => 5,
                'volume_bags' => '11522.357',
                'trial_number' => $index + 1,
                'palay_input_kg' => 10000.00,
                'rice_recovery_kg' => $rate * 100,
            ]);
        }

        $response = $this->get(route('pmr.index'));

        $response->assertOk();
        $response->assertSee('data-open-modal="#pmr-calc-modal-'.$pile->id.'"', false);
        $response->assertSee('id="pmr-calc-modal-'.$pile->id.'"', false);
        $response->assertSee('PMR Computation Breakdown (3 Trials - NFA Rules)');
        $response->assertSee('Outlier Boundary Parameters (±2% of Median)');
        $response->assertSee('Statistical Quality Check (CV Rule: CV ≤ 5.00%)');
        $response->assertSee('PASSED (CV ≤ 5.00%)');
        $response->assertSee('65.50%');
        $response->assertSee('Trial 1 Recovery Rate (%)');
        $response->assertSee('Trial 3 Recovery Rate (%)');
    }
}
