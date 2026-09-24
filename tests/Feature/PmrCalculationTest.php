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

    public function test_normal_5_trial_calculations_without_outliers(): void
    {
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 6500], // 65.00%
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6600], // 66.00%
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 6550], // 65.50%
            ['trial_number' => 4, 'palay_input' => 10000, 'rice_recovery' => 6580], // 65.80%
            ['trial_number' => 5, 'palay_input' => 10000, 'rice_recovery' => 6490], // 64.90%
        ];

        // Sorted: 64.90, 65.00, 65.50, 65.80, 66.00 => Median = 65.50%
        // Lower limit = 65.50 * 0.98 = 64.19%
        // Upper limit = 65.50 * 1.02 = 66.81%
        $result = $this->service->calculate($trials);

        $this->assertTrue($result->isValid);
        $this->assertTrue($result->isOutlierValid);
        $this->assertTrue($result->isCvValid);
        $this->assertSame(65.50, $result->median);
        $this->assertSame(64.19, $result->lowerLimit);
        $this->assertSame(66.81, $result->upperLimit);
        $this->assertSame(5, $result->validTrialCount);
        $this->assertSame(0, $result->outlierCount);
        $this->assertSame(65.44, $result->pmrRate);
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
        // Trial 5 has 59.00% which is far below 65.20 * 0.98 = 63.896%
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 6500], // 65.00%
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6550], // 65.50%
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 6520], // 65.20%
            ['trial_number' => 4, 'palay_input' => 10000, 'rice_recovery' => 6580], // 65.80%
            ['trial_number' => 5, 'palay_input' => 10000, 'rice_recovery' => 5900], // 59.00% (Outlier)
        ];

        // Sorted: 59.00, 65.00, 65.20, 65.50, 65.80 => Median = 65.20%
        // Lower limit = 65.20 * 0.98 = 63.896%
        // Upper limit = 65.20 * 1.02 = 66.504%
        $result = $this->service->calculate($trials);

        $this->assertTrue($result->isValid);
        $this->assertTrue($result->isOutlierValid);
        $this->assertTrue($result->isCvValid);
        $this->assertSame(65.20, $result->median);
        $this->assertSame(63.896, $result->lowerLimit);
        $this->assertSame(66.504, $result->upperLimit);
        $this->assertSame(4, $result->validTrialCount);
        $this->assertSame(1, $result->outlierCount);

        // Arithmetic mean of remaining 4 valid trials: (65.00 + 65.50 + 65.20 + 65.80) / 4 = 65.375 => 65.38%
        $this->assertSame(65.38, $result->pmrRate);

        // Verify outlier data is preserved but marked as OUTLIER
        $trial5 = collect($result->trials)->firstWhere('trial_number', 5);
        $this->assertNotNull($trial5);
        $this->assertTrue($trial5['is_outlier']);
        $this->assertSame('OUTLIER', $trial5['status']);
        $this->assertSame(59.00, $trial5['milling_recovery']);
    }

    public function test_two_outliers_identified_and_three_valid_trials_remain(): void
    {
        // Median is 65.00%. Lower: 63.70%, Upper: 66.30%
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 6000], // 60.00% (Low Outlier)
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6500], // 65.00% (Valid)
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 6520], // 65.20% (Valid)
            ['trial_number' => 4, 'palay_input' => 10000, 'rice_recovery' => 6480], // 64.80% (Valid)
            ['trial_number' => 5, 'palay_input' => 10000, 'rice_recovery' => 7000], // 70.00% (High Outlier)
        ];

        // Sorted: 60.00, 64.80, 65.00, 65.20, 70.00 => Median = 65.00%
        // Limits: [63.70, 66.30]
        $result = $this->service->calculate($trials);

        $this->assertTrue($result->isValid);
        $this->assertTrue($result->isOutlierValid);
        $this->assertSame(3, $result->validTrialCount);
        $this->assertSame(2, $result->outlierCount);
        // Valid trials: 64.80, 65.00, 65.20 => Mean = 65.00%
        $this->assertSame(65.00, $result->pmrRate);
    }

    public function test_exactly_plus_minus_two_percent_boundaries_are_valid(): void
    {
        // Median is 60.00%
        // Lower limit (-2%): 60.00 * 0.98 = 58.80%
        // Upper limit (+2%): 60.00 * 1.02 = 61.20%
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 5880], // 58.80% (exactly lower boundary)
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 5950], // 59.50%
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 6000], // 60.00% (median)
            ['trial_number' => 4, 'palay_input' => 10000, 'rice_recovery' => 6050], // 60.50%
            ['trial_number' => 5, 'palay_input' => 10000, 'rice_recovery' => 6120], // 61.20% (exactly upper boundary)
        ];

        $result = $this->service->calculate($trials);

        $this->assertTrue($result->isValid);
        $this->assertTrue($result->isOutlierValid);
        $this->assertTrue($result->isCvValid);
        $this->assertSame(60.00, $result->median);
        $this->assertSame(58.80, $result->lowerLimit);
        $this->assertSame(61.20, $result->upperLimit);
        $this->assertSame(5, $result->validTrialCount);
        $this->assertSame(0, $result->outlierCount);
        $this->assertSame(60.00, $result->pmrRate);

        // Both boundary trials must be classified as VALID
        $trial1 = collect($result->trials)->firstWhere('trial_number', 1);
        $trial5 = collect($result->trials)->firstWhere('trial_number', 5);
        $this->assertSame('VALID', $trial1['status']);
        $this->assertSame('VALID', $trial5['status']);
        $this->assertFalse($trial1['is_outlier']);
        $this->assertFalse($trial5['is_outlier']);
    }

    public function test_insufficient_valid_trials_when_three_outliers_occur(): void
    {
        // Median is 65.00%. Lower: 63.70%, Upper: 66.30%
        // 3 outliers: 55.00, 56.00, 75.00
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 5500], // 55.00% (Outlier)
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 5600], // 56.00% (Outlier)
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 6500], // 65.00% (Median)
            ['trial_number' => 4, 'palay_input' => 10000, 'rice_recovery' => 6520], // 65.20% (Valid)
            ['trial_number' => 5, 'palay_input' => 10000, 'rice_recovery' => 7500], // 75.00% (Outlier)
        ];

        $result = $this->service->calculate($trials);

        $this->assertFalse($result->isValid);
        $this->assertFalse($result->isOutlierValid);
        $this->assertNull($result->pmrRate);
        $this->assertSame(2, $result->validTrialCount);
        $this->assertSame(3, $result->outlierCount);
        $this->assertSame('INVALID_FEWER_VALID_TRIALS', $result->status);
        $this->assertSame('Invalid / Requires Re-establishment', $result->statusLabel);
        $this->assertStringContainsString('Fewer than 3 valid trials remain', $result->statusMessage);
    }

    public function test_cv_within_threshold_passes_statistical_requirement(): void
    {
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 6400], // 64.00%
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6450], // 64.50%
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 6500], // 65.00%
            ['trial_number' => 4, 'palay_input' => 10000, 'rice_recovery' => 6550], // 65.50%
            ['trial_number' => 5, 'palay_input' => 10000, 'rice_recovery' => 6600], // 66.00%
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
        // When outlier tolerance is large or trials vary within ±2%, but CV exceeds threshold
        // Direct CV evaluation: SD = 3.6, Mean = 60.0 => CV = (3.6 / 60.0) * 100 = 6.00% > 5.00%
        $this->assertFalse($this->service->evaluateCv(3.6, 60.0, 5.0));

        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 5600], // 56.00%
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 5800], // 58.00%
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 6000], // 60.00%
            ['trial_number' => 4, 'palay_input' => 10000, 'rice_recovery' => 6200], // 62.00%
            ['trial_number' => 5, 'palay_input' => 10000, 'rice_recovery' => 6400], // 64.00%
        ];

        $result = $this->service->calculate($trials, outlierTolerance: 0.10, maxCv: 5.0);

        $this->assertTrue($result->isOutlierValid); // 0 outliers with wider tolerance
        $this->assertFalse($result->isCvValid);    // CV > 5.0%
        $this->assertFalse($result->isValid);      // Overall invalid
        $this->assertNull($result->pmrRate);       // No approved PMR produced
        $this->assertSame('INVALID_CV_EXCEEDED', $result->status);
        $this->assertSame('Invalid / Requires Re-establishment', $result->statusLabel);
        $this->assertStringContainsString('exceeds the 5% maximum threshold', $result->statusMessage);
    }

    public function test_configurable_cv_threshold_by_nfa_rule_profile(): void
    {
        // Under standard profile: threshold is 5.0%
        $this->assertSame(5.00, $this->service->getMaxCvThreshold('standard'));

        // Under strict profile: threshold is 3.0%
        $this->assertSame(3.00, $this->service->getMaxCvThreshold('strict'));

        // Under field profile: threshold is 7.0%
        $this->assertSame(7.00, $this->service->getMaxCvThreshold('field'));

        // A CV of 4.0% passes standard (<=5%) but fails strict (<=3%)
        $this->assertTrue($this->service->evaluateCv(2.4, 60.0, 5.00));
        $this->assertFalse($this->service->evaluateCv(2.4, 60.0, 3.00));
    }

    public function test_incomplete_when_fewer_than_five_trials(): void
    {
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 6500],
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6550],
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 6520],
        ];

        $result = $this->service->calculate($trials);

        $this->assertFalse($result->isValid);
        $this->assertTrue($result->isIncomplete());
        $this->assertNull($result->pmrRate);
        $this->assertSame('INCOMPLETE', $result->status);
        $this->assertSame('Incomplete (3/5 Trials)', $result->statusLabel);
        $this->assertStringContainsString('FIVE (5) laboratory milling trials are required', $result->statusMessage);
    }

    public function test_calculate_and_store_for_pile_persists_five_trials_snapshot(): void
    {
        $branch = Branch::firstOrCreate(['name' => 'PMR Test Branch']);
        $warehouse = Warehouse::create(['name' => 'WH-PMR-A', 'branch_id' => $branch->id]);
        $pile = Pile::create(['number' => 'P-101', 'warehouse_id' => $warehouse->id]);

        $recoveryRates = [65.00, 66.00, 65.50, 65.80, 64.90];
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
        $this->assertSame(65.44, $result->pmrRate);

        // Verify pmr_calculations table persistence
        $this->assertDatabaseHas('pmr_calculations', [
            'pile_id' => $pile->id,
            'group_key' => 'pile:'.$pile->id,
            'is_valid' => true,
            'status' => 'VALID',
            'valid_trial_count' => 5,
            'outlier_count' => 0,
            'pmr_rate' => 65.44,
        ]);

        // Verify all 5 trial records were updated with audit flags
        for ($i = 1; $i <= 5; $i++) {
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
        $this->assertSame(5, $calcRecord->snapshot['required_trials']);
    }

    public function test_pmr_report_displays_five_trials_and_computation_modal(): void
    {
        $branch = Branch::create(['name' => 'Branch Cotabato']);
        $warehouse = Warehouse::create(['name' => 'Warehouse Alpha', 'branch_id' => $branch->id]);
        $pile = Pile::create(['number' => 'P-1', 'warehouse_id' => $warehouse->id]);

        $recoveryRates = [65.00, 66.00, 65.50, 65.80, 64.90];
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
        $response->assertSee('PMR Computation Breakdown (NFA Rules)');
        $response->assertSee('Outlier Boundary Parameters (±2% of Median)');
        $response->assertSee('Statistical Quality Check (CV Rule: CV ≤ 5%)');
        $response->assertSee('PASSED (CV ≤ 5%)');
        $response->assertSee('65.44%');
        $response->assertSee('Trial 1 Recovery Rate (%)');
        $response->assertSee('Trial 5 Recovery Rate (%)');
    }
}
