<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetestVolumeRolloverAndAutoCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Branch $branch;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['name' => 'South Cotabato Retest']);
        $this->warehouse = Warehouse::firstOrCreate([
            'branch_id' => $this->branch->id,
            'name' => 'Warehouse Retest Hub',
        ]);

        $this->adminUser = User::factory()->create([
            'role' => 'administrator',
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser);
    }

    public function test_amr_commercial_trials_auto_compute_test_milling_volume(): void
    {
        $response = $this->post(route('records.store'), [
            'form_type' => 'amr',
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'pile_number' => 'PILE-AUTO-1',
            'variety' => 'PD1',
            'purity' => 95,
            'aged' => 4,
            'mc' => 12,
            'quality' => 'good',
            'volume' => '100,000',
            'trials' => [
                [
                    'trial_number' => 1,
                    'test_milling_date' => '2026-10-01',
                    'rice_millers' => 'Miller Alpha',
                    'palay_input' => '200',
                    'rice_recovery' => '130',
                ],
                [
                    'trial_number' => 2,
                    'test_milling_date' => '2026-10-02',
                    'rice_millers' => 'Miller Alpha',
                    'palay_input' => '300',
                    'rice_recovery' => '195',
                ],
                [
                    'trial_number' => 3,
                    'test_milling_date' => '2026-10-03',
                    'rice_millers' => 'Miller Alpha',
                    'palay_input' => '500',
                    'rice_recovery' => '325',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $pile = Pile::where('pile_number', 'PILE-AUTO-1')->firstOrFail();
        // 200 + 300 + 500 = 1,000 kg
        $this->assertSame(1000.0, (float) $pile->test_milling_volume_kg);

        // Child records also have test_milling_volume_kg set
        $records = AmrRecord::where('pile_id', $pile->id)->get();
        $this->assertCount(3, $records);
        foreach ($records as $record) {
            $this->assertSame(1000.0, (float) $record->test_milling_volume_kg);
        }

        // Volume after test milling in bags: (100,000 - 1,000) / 50 = 1,980 bags
        $bagsAfter = Pile::calculateVolumeAfterTestMillingBags($pile->volume_kg, $pile->test_milling_volume_kg);
        $this->assertSame(1980.0, (float) $bagsAfter);
    }

    public function test_retest_pile_automatically_rolls_over_remaining_volume(): void
    {
        // 10,000 bags = 500,000 kg
        $pile = Pile::create([
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'pile_number' => 'PILE-RETEST-1',
            'number' => 'PILE-RETEST-1',
            'variety' => 'PD1',
            'purity' => 95,
            'aged_months' => 4,
            'mc' => 12,
            'quality' => 'good',
            'volume_kg' => 500000,
            'amr_status' => 'retest',
        ]);

        // Conduct 1 consumed 30,000 kg (600 bags)
        AmrRecord::create([
            'pile_id' => $pile->id,
            'conduct_number' => 1,
            'trial_number' => 1,
            'status' => 'RETEST',
            'is_locked' => true,
            'volume_kg' => 500000,
            'palay_input_kg' => 10000,
            'rice_recovery_kg' => 6500,
            'milling_recovery' => 65.0,
            'test_milling_date' => '2026-10-01',
            'warehouse_name' => $this->warehouse->name,
            'pile_number' => $pile->pile_number,
            'variety' => 'PD1',
            'purity' => 95,
            'mc' => 12,
            'quality' => 'good',
            'aged_months' => 4,
        ]);
        AmrRecord::create([
            'pile_id' => $pile->id,
            'conduct_number' => 1,
            'trial_number' => 2,
            'status' => 'RETEST',
            'is_locked' => true,
            'volume_kg' => 500000,
            'palay_input_kg' => 10000,
            'rice_recovery_kg' => 6500,
            'milling_recovery' => 65.0,
            'test_milling_date' => '2026-10-02',
            'warehouse_name' => $this->warehouse->name,
            'pile_number' => $pile->pile_number,
            'variety' => 'PD1',
            'purity' => 95,
            'mc' => 12,
            'quality' => 'good',
            'aged_months' => 4,
        ]);
        AmrRecord::create([
            'pile_id' => $pile->id,
            'conduct_number' => 1,
            'trial_number' => 3,
            'status' => 'RETEST',
            'is_locked' => true,
            'volume_kg' => 500000,
            'palay_input_kg' => 10000,
            'rice_recovery_kg' => 6500,
            'milling_recovery' => 65.0,
            'test_milling_date' => '2026-10-03',
            'warehouse_name' => $this->warehouse->name,
            'pile_number' => $pile->pile_number,
            'variety' => 'PD1',
            'purity' => 95,
            'mc' => 12,
            'quality' => 'good',
            'aged_months' => 4,
        ]);

        // When loading the form, check that pileData receives the adjusted starting volume: 500,000 - 30,000 = 470,000 kg
        $formResponse = $this->get(route('records.create', ['type' => 'amr', 'pile_id' => $pile->id]));
        $formResponse->assertOk();

        $pilesInView = $formResponse->viewData('piles');
        $pileData = collect($pilesInView)->firstWhere('id', $pile->id);
        $this->assertNotNull($pileData);
        $this->assertNotNull($pileData['retest_info']);
        $this->assertTrue($pileData['retest_info']['is_retest']);
        $this->assertSame(30000.0, (float) $pileData['retest_info']['previous_milled_kg']);
        $this->assertSame(600.0, (float) $pileData['retest_info']['previous_milled_bags']);
        $this->assertSame(470000.0, (float) $pileData['retest_info']['adjusted_volume_kg']);
        $this->assertSame(470000.0, (float) $pileData['amr']['volume']);

        // Now, submit Conduct 2 with 3 trials consuming 30,000 kg (600 bags)
        $storeResponse = $this->post(route('records.store'), [
            'form_type' => 'amr',
            'pile_id' => $pile->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'variety' => 'PD1',
            'purity' => 95,
            'aged' => 4,
            'mc' => 12,
            'quality' => 'good',
            'volume' => '470,000',
            'trials' => [
                [
                    'trial_number' => 1,
                    'test_milling_date' => '2026-10-10',
                    'rice_millers' => 'Miller Beta',
                    'palay_input' => '10000',
                    'rice_recovery' => '6520',
                ],
                [
                    'trial_number' => 2,
                    'test_milling_date' => '2026-10-11',
                    'rice_millers' => 'Miller Beta',
                    'palay_input' => '10000',
                    'rice_recovery' => '6540',
                ],
                [
                    'trial_number' => 3,
                    'test_milling_date' => '2026-10-12',
                    'rice_millers' => 'Miller Beta',
                    'palay_input' => '10000',
                    'rice_recovery' => '6530',
                ],
            ],
        ]);

        $storeResponse->assertSessionHasNoErrors();

        $freshPile = $pile->fresh();
        // Conduct 2 starting volume: 470,000 kg (9,400 bags)
        $this->assertSame(470000.0, (float) $freshPile->volume_kg);
        // Conduct 2 test milling volume: 30,000 kg (600 bags)
        $this->assertSame(30000.0, (float) $freshPile->test_milling_volume_kg);

        // Remaining volume after Conduct 2: (470,000 - 30,000) / 50 = 8,800 bags
        $netBags = Pile::calculateVolumeAfterTestMillingBags($freshPile->volume_kg, $freshPile->test_milling_volume_kg);
        $this->assertSame(8800.0, (float) $netBags);

        // Verify that Conduct 1 historical records remain intact and preserved with their 500,000 kg volume
        $conduct1Records = AmrRecord::where('pile_id', $pile->id)->where('conduct_number', 1)->get();
        $this->assertCount(3, $conduct1Records);
        foreach ($conduct1Records as $record) {
            $this->assertSame(500000.0, (float) $record->volume_kg);
            $this->assertSame('RETEST', $record->status);
            $this->assertTrue($record->is_locked);
        }

        // Verify Conduct 2 records
        $conduct2Records = AmrRecord::where('pile_id', $pile->id)->where('conduct_number', 2)->get();
        $this->assertCount(3, $conduct2Records);
        foreach ($conduct2Records as $record) {
            $this->assertSame(470000.0, (float) $record->volume_kg);
            $this->assertSame(30000.0, (float) $record->test_milling_volume_kg);
        }
    }
}
