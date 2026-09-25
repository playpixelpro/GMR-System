<?php

namespace Tests\Feature;

use App\Models\AmrCalculation;
use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\Warehouse;
use App\Services\AmrCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmrCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected AmrCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AmrCalculationService::class);
    }

    public function test_normal_results_without_outliers(): void
    {
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 6240], // 62.40%
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6200], // 62.00%
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 6180], // 61.80%
        ];

        $result = $this->service->calculate($trials);

        $this->assertTrue($result->isValid);
        $this->assertSame(62.00, $result->median);
        $this->assertSame(60.76, $result->lowerLimit);
        $this->assertSame(63.24, $result->upperLimit);
        $this->assertSame(3, $result->validTrialCount);
        $this->assertSame(0, $result->outlierCount);
        $this->assertSame(62.07, $result->amrRate);
        $this->assertSame('VALID', $result->status);

        foreach ($result->trials as $trial) {
            $this->assertSame('VALID', $trial['status']);
            $this->assertFalse($trial['is_outlier']);
        }
    }

    public function test_outlier_identified_and_excluded_from_amr_calculation(): void
    {
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 6200], // 62.00%
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6250], // 62.50%
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 5800], // 58.00% (Outlier)
        ];

        $result = $this->service->calculate($trials);

        $this->assertTrue($result->isValid);
        $this->assertSame(62.00, $result->median);
        $this->assertSame(60.76, $result->lowerLimit);
        $this->assertSame(63.24, $result->upperLimit);
        $this->assertSame(2, $result->validTrialCount);
        $this->assertSame(1, $result->outlierCount);

        // Arithmetic mean of remaining valid trials: (62.00 + 62.50) / 2 = 62.25
        $this->assertSame(62.25, $result->amrRate);

        // Verify outlier data is preserved but marked as OUTLIER
        $trial3 = collect($result->trials)->firstWhere('trial_number', 3);
        $this->assertNotNull($trial3);
        $this->assertTrue($trial3['is_outlier']);
        $this->assertSame('OUTLIER', $trial3['status']);
        $this->assertSame(58.00, $trial3['milling_recovery']);
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
        $this->assertSame(60.00, $result->median);
        $this->assertSame(58.80, $result->lowerLimit);
        $this->assertSame(61.20, $result->upperLimit);
        $this->assertSame(3, $result->validTrialCount);
        $this->assertSame(0, $result->outlierCount);
        $this->assertSame(60.00, $result->amrRate);

        // Both boundary trials must be classified as VALID
        foreach ($result->trials as $trial) {
            $this->assertSame('VALID', $trial['status']);
            $this->assertFalse($trial['is_outlier']);
        }
    }

    public function test_fewer_than_required_valid_trials_marks_calculation_invalid(): void
    {
        // 2 outliers, only 1 valid trial remains
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 5000], // 50.00% (outlier)
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6000], // 60.00% (median)
            ['trial_number' => 3, 'palay_input' => 10000, 'rice_recovery' => 7000], // 70.00% (outlier)
        ];

        $result = $this->service->calculate($trials);

        $this->assertFalse($result->isValid);
        $this->assertNull($result->amrRate);
        $this->assertSame('INVALID_FEWER_VALID_TRIALS', $result->status);
        $this->assertSame(1, $result->validTrialCount);
        $this->assertSame(2, $result->outlierCount);
    }

    public function test_incomplete_calculation_when_fewer_than_three_trials(): void
    {
        $trials = [
            ['trial_number' => 1, 'palay_input' => 10000, 'rice_recovery' => 6200],
            ['trial_number' => 2, 'palay_input' => 10000, 'rice_recovery' => 6250],
        ];

        $result = $this->service->calculate($trials);

        $this->assertFalse($result->isValid);
        $this->assertSame('INCOMPLETE', $result->status);
        $this->assertNull($result->amrRate);
        $this->assertNull($result->median);
    }

    public function test_amr_calculation_snapshot_is_stored_in_database_for_pile(): void
    {
        $branch = Branch::create(['name' => 'Branch 1']);
        $warehouse = Warehouse::create(['name' => 'Warehouse 1', 'branch_id' => $branch->id]);
        $pile = Pile::create(['number' => 'Pile 101', 'warehouse_id' => $warehouse->id]);

        $trial1 = AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'palay_input_kg' => '10000.85',
            'rice_recovery_kg' => '6239.63',
        ]);
        $trial2 = AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'trial_number' => 2,
            'palay_input_kg' => '9999.85',
            'rice_recovery_kg' => '6132.78',
        ]);
        $trial3 = AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'trial_number' => 3,
            'palay_input_kg' => '10000.23',
            'rice_recovery_kg' => '6085.85',
        ]);

        $result = $this->service->calculateAndStoreForPile($pile);

        $this->assertTrue($result->isValid);
        $this->assertSame(61.53, $result->amrRate);

        $this->assertDatabaseHas('amr_calculations', [
            'pile_id' => $pile->id,
            'is_valid' => true,
            'valid_trial_count' => 3,
            'outlier_count' => 0,
            'status' => 'VALID',
        ]);

        $calculation = AmrCalculation::where('pile_id', $pile->id)->first();
        $this->assertNotNull($calculation);
        $this->assertIsArray($calculation->snapshot);
        $this->assertIsArray($calculation->trial_inputs);
        $this->assertSame('61.53', (string) $calculation->amr_rate);

        // Verify trial flags
        $this->assertDatabaseHas('amr_records', [
            'id' => $trial1->id,
            'milling_recovery' => '62.39',
            'is_outlier' => false,
        ]);
    }

    public function test_amr_report_displays_computation_modal_and_clickable_rate(): void
    {
        $branch = Branch::create(['name' => 'Branch Cotabato']);
        $warehouse = Warehouse::create(['name' => 'Warehouse Alpha', 'branch_id' => $branch->id]);
        $pile = Pile::create(['number' => 'P-1', 'warehouse_id' => $warehouse->id]);

        AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'warehouse_name' => $warehouse->name,
            'pile_number' => $pile->number,
            'variety' => 'PD',
            'aged_months' => 5,
            'volume_kg' => '11522.357',
            'rice_millers' => 'MAGDAMO RM',
            'trial_number' => 1,
            'palay_input_kg' => '10000.85',
            'rice_recovery_kg' => '6239.63',
        ]);
        AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'warehouse_name' => $warehouse->name,
            'pile_number' => $pile->number,
            'variety' => 'PD',
            'aged_months' => 5,
            'volume_kg' => '11522.357',
            'rice_millers' => 'MAGDAMO RM',
            'trial_number' => 2,
            'palay_input_kg' => '9999.85',
            'rice_recovery_kg' => '6132.78',
        ]);
        AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'warehouse_name' => $warehouse->name,
            'pile_number' => $pile->number,
            'variety' => 'PD',
            'aged_months' => 5,
            'volume_kg' => '11522.357',
            'rice_millers' => 'MAGDAMO RM',
            'trial_number' => 3,
            'palay_input_kg' => '10000.23',
            'rice_recovery_kg' => '6085.85',
        ]);

        $response = $this->get(route('amr.index'));

        $response->assertOk();
        $response->assertSee('61.53%');
        $response->assertSee('AMR Computation Breakdown');
        $response->assertSee('Median Recovery');
        $response->assertSee('Lower Limit (-2%)');
        $response->assertSee('Upper Limit (+2%)');
        $response->assertSee('data-overlay="#amr-calc-modal-'.$pile->id.'"', false);
    }
}
