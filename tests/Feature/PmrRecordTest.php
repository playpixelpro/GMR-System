<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmrRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create([
            'role' => 'staff',
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $this->actingAs($user);
    }

    public function test_a_pmr_trial_can_be_saved_from_the_shared_form(): void
    {
        $response = $this->post(route('records.store'), [
            'form_type' => 'pmr',
            'new_branch_name' => 'Branch 1',
            'new_warehouse_name' => 'GID#2, MLANG BS',
            'pile_number' => '1',
            'variety' => 'PD',
            'purity' => '94.31',
            'mc' => '11.1',
            'quality' => 'gqa',
            'aged' => 5,
            'volume' => '11522.357',
            'test_milling_date' => '2026-09-24',
            'no_of_trial' => 1,
            'palay_input' => '10000.00',
            'rice_recovery' => '6276.00',
        ]);

        $response->assertRedirect(route('records.create', ['type' => 'pmr']));
        $this->assertDatabaseHas('pmr_records', [
            'warehouse_name' => 'GID#2, MLANG BS',
            'quality' => 'gqa',
            'test_milling_date' => '2026-09-24',
            'rice_recovery_kg' => '6276.00',
        ]);
    }

    public function test_pmr_trial_accepts_small_leading_dot_float_samples(): void
    {
        $response = $this->post(route('records.store'), [
            'form_type' => 'pmr',
            'new_branch_name' => 'Lab Branch',
            'new_warehouse_name' => 'Lab Warehouse',
            'pile_number' => 'L-1',
            'variety' => 'PD',
            'purity' => '94.31',
            'mc' => '11.1',
            'quality' => 'gqa',
            'aged' => 5,
            'volume' => '100',
            'trials' => [
                [
                    'trial_number' => 1,
                    'test_milling_date' => '2026-09-24',
                    // Laboratory samples may be very small, e.g. 0.063417 kg,
                    // and may be typed with a leading dot. Up to six decimal
                    // places must be accepted and preserved exactly.
                    'palay_input' => '.063417',
                    'rice_recovery' => '0.040125',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('records.create', ['type' => 'pmr']));
        $this->assertDatabaseHas('pmr_records', [
            'pile_number' => 'L-1',
            'trial_number' => 1,
            'palay_input_kg' => '0.063417',
            'rice_recovery_kg' => '0.040125',
        ]);
    }

    public function test_pmr_report_calculates_mean_and_sample_standard_deviation(): void
    {
        foreach ([62.76, 62.48, 61.84] as $trialNumber => $rate) {
            PmrRecord::factory()->create([
                'warehouse_name' => 'GID#2, MLANG BS',
                'pile_number' => '1',
                'variety' => 'PD',
                'purity' => '94.31',
                'mc' => '11.10',
                'quality' => 'gqa',
                'aged_months' => 5,
                'volume_kg' => '11522.357',
                'trial_number' => $trialNumber + 1,
                'palay_input_kg' => '10000.00',
                'rice_recovery_kg' => $rate * 100,
            ]);
        }

        $response = $this->get(route('pmr.index'));

        $response->assertOk();
        $response->assertSee('62.36');
        $response->assertSee('0.47');
        $response->assertSee('Trial 1 Recovery Rate (%)');
        $response->assertSee('Trial 3 Recovery Rate (%)');
    }

    public function test_pmr_report_shows_computed_mean_for_pending_pile_trials(): void
    {
        // Regression: pile-based PMR trials that are still PENDING (not yet
        // RECOMMENDED, included_in_computation = false) must still display the
        // mean computed by PmrCalculationService. Previously the controller
        // used `$rates->avg() ?? 0.0`, which yielded `0.0` (not null) when no
        // trials were RECOMMENDED yet, so the report view's
        // `$group['mean'] ?? $calculation->mean` fallback never triggered and
        // the MEAN column showed "0.00" instead of the computed value.
        $branch = Branch::create(['name' => 'Branch 1']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'GID#2, MLANG BS',
        ]);
        $pile = Pile::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'number' => '1',
            'pile_number' => '1',
            'variety' => 'PD',
        ]);

        foreach ([62.76, 62.48, 61.84] as $trialNumber => $rate) {
            PmrRecord::factory()->create([
                'pile_id' => $pile->id,
                'warehouse_name' => $warehouse->name,
                'pile_number' => '1',
                'variety' => 'PD',
                'purity' => '94.31',
                'mc' => '11.10',
                'quality' => 'gqa',
                'aged_months' => 5,
                'volume_kg' => '11522.357',
                'trial_number' => $trialNumber + 1,
                'palay_input_kg' => '10000.00',
                'rice_recovery_kg' => $rate * 100,
                // Rely on factory/column defaults: status = PENDING,
                // included_in_computation = false — the exact state freshly
                // encoded pile trials are in before being recommended.
            ]);
        }

        $response = $this->get(route('pmr.index'));

        $response->assertOk();
        // The MEAN column must render the computed mean (62.36), not "0.00".
        $response->assertSee(
            '<span class="font-medium text-base-content">62.36</span>',
            false,
        );
        $response->assertDontSee(
            '<span class="font-medium text-base-content">0.00</span>',
            false,
        );
    }

    public function test_pmr_cannot_use_more_than_three_trials(): void
    {
        $response = $this->post(route('records.store'), [
            'form_type' => 'pmr',
            'new_branch_name' => 'Branch 1',
            'new_warehouse_name' => 'Warehouse',
            'pile_number' => '1',
            'variety' => 'PD',
            'rice_millers' => 'Miller',
            'purity' => '94.31',
            'mc' => '11.1',
            'quality' => 'gqa',
            'aged' => 5,
            'volume' => 10,
            'no_of_trial' => 4,
            'palay_input' => 100,
            'rice_recovery' => 60,
        ]);

        $response->assertSessionHasErrors('no_of_trial');
    }

    public function test_rice_recovery_cannot_exceed_palay_input(): void
    {
        $response = $this->from(
            route('records.create', ['type' => 'pmr']),
        )->post(route('records.store'), [
            'form_type' => 'pmr',
            'new_branch_name' => 'Branch 1',
            'new_warehouse_name' => 'Warehouse',
            'pile_number' => '1',
            'variety' => 'PD',
            'rice_millers' => 'Miller',
            'purity' => '94.31',
            'mc' => '11.1',
            'quality' => 'gqa',
            'aged' => 5,
            'volume' => 10,
            'no_of_trial' => 1,
            'palay_input' => 100,
            'rice_recovery' => 101,
        ]);

        $response->assertSessionHasErrors('rice_recovery');
        $this->assertDatabaseCount('pmr_records', 0);
    }

    public function test_pmr_status_shows_ok_when_pmr_is_at_least_60_percent_and_higher_than_amr(): void
    {
        $branch = Branch::create(['name' => 'Branch 1']);
        $warehouse = Warehouse::create(['branch_id' => $branch->id, 'name' => 'WH High PMR']);
        $pile = Pile::create(['warehouse_id' => $warehouse->id, 'number' => '1']);

        // Create AMR records for the same pile with 63% recovery
        foreach ([63.0, 63.0, 63.0] as $i => $rate) {
            AmrRecord::factory()->create([
                'pile_id' => $pile->id,
                'warehouse_name' => 'WH High PMR',
                'pile_number' => '1',
                'variety' => 'PD',
                'trial_number' => $i + 1,
                'palay_input_kg' => '10000.00',
                'rice_recovery_kg' => $rate * 100,
            ]);
        }

        // Create PMR records for the same pile with 65% recovery (higher than AMR, spread 2.0 <= 3.0, and >= 60%)
        foreach ([65.0, 65.0, 65.0] as $i => $rate) {
            PmrRecord::factory()->create([
                'pile_id' => $pile->id,
                'warehouse_name' => 'WH High PMR',
                'pile_number' => '1',
                'variety' => 'PD',
                'trial_number' => $i + 1,
                'palay_input_kg' => '10000.00',
                'rice_recovery_kg' => $rate * 100,
            ]);
        }

        $response = $this->get(route('pmr.index'));

        $response->assertOk();
        $response->assertSee('OK');
        $response->assertDontSee('Lower than 60%');
        $response->assertDontSee('PMR lower than AMR');
        $response->assertDontSee('AMR &lt; PMR by &gt;3%');
    }

    public function test_pmr_status_shows_lower_than_60_percent_when_pmr_is_below_60_percent(): void
    {
        foreach ([58.0, 58.0, 58.0] as $i => $rate) {
            PmrRecord::factory()->create([
                'warehouse_name' => 'WH Low PMR',
                'pile_number' => '2',
                'variety' => 'PD',
                'trial_number' => $i + 1,
                'palay_input_kg' => '10000.00',
                'rice_recovery_kg' => $rate * 100,
            ]);
        }

        $response = $this->get(route('pmr.index'));

        $response->assertOk();
        $response->assertSee('Lower than 60%');
    }

    public function test_pmr_status_shows_pmr_lower_than_amr_when_pmr_is_below_amr(): void
    {
        $branch = Branch::create(['name' => 'Branch 1']);
        $warehouse = Warehouse::create(['branch_id' => $branch->id, 'name' => 'WH Inverted']);
        $pile = Pile::create(['warehouse_id' => $warehouse->id, 'number' => '3']);

        // AMR is 65%
        foreach ([65.0, 65.0, 65.0] as $i => $rate) {
            AmrRecord::factory()->create([
                'pile_id' => $pile->id,
                'warehouse_name' => 'WH Inverted',
                'pile_number' => '3',
                'variety' => 'PD',
                'trial_number' => $i + 1,
                'palay_input_kg' => '10000.00',
                'rice_recovery_kg' => $rate * 100,
            ]);
        }

        // PMR is 62% (>= 60%, but lower than AMR 65%)
        foreach ([62.0, 62.0, 62.0] as $i => $rate) {
            PmrRecord::factory()->create([
                'pile_id' => $pile->id,
                'warehouse_name' => 'WH Inverted',
                'pile_number' => '3',
                'variety' => 'PD',
                'trial_number' => $i + 1,
                'palay_input_kg' => '10000.00',
                'rice_recovery_kg' => $rate * 100,
            ]);
        }

        $response = $this->get(route('pmr.index'));

        $response->assertOk();
        $response->assertSee('PMR lower than AMR');
        $response->assertDontSee('Lower than 60%');
    }

    public function test_pmr_status_shows_divergent_when_amr_is_less_than_pmr_by_more_than_three_percentage_points(): void
    {
        $branch = Branch::create(['name' => 'Branch 1']);
        $warehouse = Warehouse::create(['branch_id' => $branch->id, 'name' => 'WH Divergent']);
        $pile = Pile::create(['warehouse_id' => $warehouse->id, 'number' => '5']);

        // AMR is 61%
        foreach ([61.0, 61.0, 61.0] as $i => $rate) {
            AmrRecord::factory()->create([
                'pile_id' => $pile->id,
                'warehouse_name' => 'WH Divergent',
                'pile_number' => '5',
                'variety' => 'PD',
                'trial_number' => $i + 1,
                'palay_input_kg' => '10000.00',
                'rice_recovery_kg' => $rate * 100,
            ]);
        }

        // PMR is 66% (Spread is 66 - 61 = 5.0% > 3.0 percentage points)
        foreach ([66.0, 66.0, 66.0] as $i => $rate) {
            PmrRecord::factory()->create([
                'pile_id' => $pile->id,
                'warehouse_name' => 'WH Divergent',
                'pile_number' => '5',
                'variety' => 'PD',
                'trial_number' => $i + 1,
                'palay_input_kg' => '10000.00',
                'rice_recovery_kg' => $rate * 100,
            ]);
        }

        $response = $this->get(route('pmr.index'));

        $response->assertOk();
        $response->assertSee('AMR &lt; PMR by &gt;3%', false);
    }

    public function test_pmr_status_shows_both_when_pmr_is_below_60_and_below_amr(): void
    {
        $branch = Branch::create(['name' => 'Branch 1']);
        $warehouse = Warehouse::create(['branch_id' => $branch->id, 'name' => 'WH Both Fail']);
        $pile = Pile::create(['warehouse_id' => $warehouse->id, 'number' => '4']);

        // AMR is 61%
        foreach ([61.0, 61.0, 61.0] as $i => $rate) {
            AmrRecord::factory()->create([
                'pile_id' => $pile->id,
                'warehouse_name' => 'WH Both Fail',
                'pile_number' => '4',
                'variety' => 'PD',
                'trial_number' => $i + 1,
                'palay_input_kg' => '10000.00',
                'rice_recovery_kg' => $rate * 100,
            ]);
        }

        // PMR is 57% (< 60% and < AMR 61%)
        foreach ([57.0, 57.0, 57.0] as $i => $rate) {
            PmrRecord::factory()->create([
                'pile_id' => $pile->id,
                'warehouse_name' => 'WH Both Fail',
                'pile_number' => '4',
                'variety' => 'PD',
                'trial_number' => $i + 1,
                'palay_input_kg' => '10000.00',
                'rice_recovery_kg' => $rate * 100,
            ]);
        }

        $response = $this->get(route('pmr.index'));

        $response->assertOk();
        $response->assertSee('PMR lower than AMR');
        $response->assertSee('Lower than 60%');
    }
}
