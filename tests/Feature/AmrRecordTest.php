<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\Warehouse;
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
            'test_milling_date' => '2026-09-24',
            'palay_input' => '10000.85',
            'rice_recovery' => '6239.63',
        ]);

        $response->assertRedirect(route('records.create', ['type' => 'amr']));
        $this->assertDatabaseHas('amr_records', [
            'warehouse_name' => 'GID#2, MLANG BS',
            'trial_number' => 1,
            'purity' => '94.31',
            'rice_recovery_kg' => '6239.63',
            'test_milling_date' => '2026-09-24',
        ]);
    }

    public function test_amr_trial_can_be_updated_inline_from_the_data_entry_form(): void
    {
        $trial = AmrRecord::factory()->create([
            'rice_millers' => 'Old Miller',
            'palay_input_kg' => '100.00',
            'rice_recovery_kg' => '60.00',
        ]);

        $response = $this->patchJson(route('records.update', [
            'formType' => 'amr',
            'record' => $trial->id,
        ]), [
            'rice_millers' => 'Updated Miller',
            'palay_input' => '120.00',
            'rice_recovery' => '75.00',
            'test_milling_date' => '2026-09-23',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'AMR trial updated successfully.');
        $this->assertDatabaseHas('amr_records', [
            'id' => $trial->id,
            'rice_millers' => 'Updated Miller',
            'palay_input_kg' => '120.00',
            'rice_recovery_kg' => '75.00',
            'test_milling_date' => '2026-09-23',
        ]);
    }

    public function test_pile_details_update_existing_trials_without_adding_records(): void
    {
        $branch = Branch::create(['name' => 'Branch']);
        $warehouse = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Warehouse']);
        $pile = Pile::create(['warehouse_id' => $warehouse->id, 'number' => 'Pile 1']);
        $trials = AmrRecord::factory()->count(2)->create(['pile_id' => $pile->id]);

        $response = $this->patchJson(route('piles.details.update', $pile), [
            'variety' => 'Updated Variety',
            'purity' => '97.50',
            'aged' => 7,
            'mc' => '12.25',
            'quality' => 'premium',
            'volume' => '12,500.75',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Pile details updated successfully.');
        $this->assertDatabaseCount('amr_records', 2);

        foreach ($trials as $trial) {
            $this->assertDatabaseHas('amr_records', [
                'id' => $trial->id,
                'variety' => 'Updated Variety',
                'purity' => '97.50',
                'aged_months' => 7,
                'mc' => '12.25',
                'quality' => 'premium',
                'volume_bags' => '12500.750',
            ]);
        }
    }

    public function test_a_saved_amr_trial_can_be_deleted_from_the_data_entry_form(): void
    {
        $trial = AmrRecord::factory()->create();

        $response = $this->deleteJson(route('records.destroy', [
            'formType' => 'amr',
            'record' => $trial->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('message', 'AMR trial deleted successfully.');
        $this->assertDatabaseMissing('amr_records', ['id' => $trial->id]);
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
