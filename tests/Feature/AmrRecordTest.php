<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmrRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_amr_trial_can_be_saved_from_the_shared_form(): void
    {
        $response = $this->post(route('records.store'), [
            'form_type' => 'amr',
            'new_branch_name' => 'Branch 1',
            'new_warehouse_name' => 'GID#2, MLANG BS',
            'pile_number' => '1',
            'variety' => 'PD',
            'rice_millers' => 'MAGDAMO RM',
            'purity' => '94.31',
            'mc' => '11.1',
            'quality' => 'gqa',
            'aged' => 5,
            'volume' => '11522.357',
            'no_of_trial' => 1,
            'palay_input' => '10000.85',
            'rice_recovery' => '6239.63',
        ]);

        $response->assertRedirect(route('records.create', ['type' => 'amr']));
        $this->assertDatabaseHas('amr_records', [
            'warehouse_name' => 'GID#2, MLANG BS',
            'trial_number' => 1,
            'purity' => '94.31',
            'rice_recovery_kg' => '6239.63',
        ]);
    }

    public function test_amr_cannot_use_more_than_three_trials(): void
    {
        $response = $this->post(route('records.store'), [
            'form_type' => 'amr',
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
            route('records.create', ['type' => 'amr']),
        )->post(route('records.store'), [
            'form_type' => 'amr',
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

        $response->assertRedirect(route('records.create', ['type' => 'amr']));
        $response->assertSessionHasErrors('rice_recovery');
        $this->assertDatabaseCount('amr_records', 0);
    }

    public function test_amr_report_groups_trials_and_calculates_milling_recovery(): void
    {
        AmrRecord::factory()->create([
            'warehouse_name' => 'Warehouse',
            'pile_number' => '1',
            'variety' => 'PD',
            'aged_months' => 5,
            'volume_bags' => '10.000',
            'rice_millers' => 'Miller',
            'trial_number' => 1,
            'palay_input_kg' => '10000.00',
            'rice_recovery_kg' => '6250.00',
        ]);

        AmrRecord::factory()->create([
            'warehouse_name' => 'Warehouse',
            'pile_number' => '1',
            'variety' => 'PD',
            'aged_months' => 5,
            'volume_bags' => '10.000',
            'rice_millers' => 'Miller',
            'trial_number' => 2,
            'palay_input_kg' => '10000.00',
            'rice_recovery_kg' => '6300.00',
        ]);

        $response = $this->get(route('amr.index'));

        $response->assertOk();
        $response->assertSee('62.50');
        $response->assertSee('63.00');
        $response->assertSee('Trial 1');
        $response->assertSee('Trial 2');
        $response->assertSee('Trial 3');
    }
}
