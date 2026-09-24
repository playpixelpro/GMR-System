<?php

namespace Tests\Feature;

use App\Models\PmrRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmrRecordTest extends TestCase
{
    use RefreshDatabase;

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
            'no_of_trial' => 1,
            'palay_input' => '10000.00',
            'rice_recovery' => '6276.00',
        ]);

        $response->assertRedirect(route('records.create', ['type' => 'pmr']));
        $this->assertDatabaseHas('pmr_records', [
            'warehouse_name' => 'GID#2, MLANG BS',
            'quality' => 'gqa',
            'rice_recovery_kg' => '6276.00',
        ]);
    }

    public function test_pmr_report_calculates_mean_and_sample_standard_deviation(): void
    {
        foreach ([62.76, 62.48, 61.84, 62.2, 61.8] as $trialNumber => $rate) {
            PmrRecord::factory()->create([
                'warehouse_name' => 'GID#2, MLANG BS',
                'pile_number' => '1',
                'variety' => 'PD',
                'purity' => '94.31',
                'mc' => '11.10',
                'quality' => 'gqa',
                'aged_months' => 5,
                'volume_bags' => '11522.357',
                'trial_number' => $trialNumber + 1,
                'palay_input_kg' => '10000.00',
                'rice_recovery_kg' => $rate * 100,
            ]);
        }

        $response = $this->get(route('pmr.index'));

        $response->assertOk();
        $response->assertSee('62.22');
        $response->assertSee('0.41');
        $response->assertSee('Trial 1 Recovery Rate (%)');
        $response->assertSee('Trial 5 Recovery Rate (%)');
    }

    public function test_pmr_cannot_use_more_than_five_trials(): void
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
            'no_of_trial' => 6,
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

        $response->assertRedirect(route('records.create', ['type' => 'pmr']));
        $response->assertSessionHasErrors('rice_recovery');
        $this->assertDatabaseCount('pmr_records', 0);
    }
}
