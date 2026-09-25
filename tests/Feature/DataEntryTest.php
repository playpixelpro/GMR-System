<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Pile;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shared_form_creates_the_branch_warehouse_and_pile_hierarchy(): void
    {
        $payload = [
            'form_type' => 'amr',
            'new_branch_name' => 'North Branch',
            'new_warehouse_name' => 'Warehouse A',
            'test_milling_date' => '2026-09-24',
            'pile_number' => '1',
            'variety' => 'PD',
            'purity' => 94.31,
            'mc' => 11.1,
            'quality' => 'gqa',
            'aged' => 5,
            'volume' => 10,
            'rice_millers' => 'Miller',
            'no_of_trial' => 1,
            'palay_input' => 100,
            'rice_recovery' => 60,
        ];

        $this->post(route('records.store'), $payload)->assertRedirect();
        $this->post(route('records.store'), [
            ...$payload,
            'new_branch_name' => 'South Branch',
        ]);

        $this->assertDatabaseCount('branches', 5);
        $this->assertDatabaseCount('warehouses', 2);
        $this->assertDatabaseCount('piles', 2);
        $this->assertSame(
            1,
            Branch::where('name', 'North Branch')
                ->first()
                ->warehouses()
                ->count(),
        );
        $this->assertSame(
            1,
            Warehouse::where('name', 'Warehouse A')->first()->piles()->count(),
        );
        $this->assertSame(2, Pile::where('number', '1')->count());
    }

    public function test_form_displays_warehouses_for_existing_branches(): void
    {
        $branch = Branch::create(['name' => 'North Branch']);
        $branch->warehouses()->create(['name' => 'Warehouse A']);

        $response = $this->get(route('records.create'));

        $response->assertOk();
        $response->assertSee('North Branch');
        $response->assertSee('Warehouse A');
        $response->assertSee('Add new branch...');
        $response->assertSee('Add new warehouse...');
    }

    public function test_a_warehouse_can_be_created_from_the_popup_endpoint(): void
    {
        $branch = Branch::where('name', 'North Cotabato')->firstOrFail();

        $response = $this->postJson(route('warehouses.store'), [
            'branch_id' => $branch->id,
            'name' => 'Popup Warehouse',
        ]);

        $response->assertCreated()->assertJson([
            'name' => 'Popup Warehouse',
            'branch_id' => $branch->id,
        ]);
        $this->assertDatabaseHas('warehouses', [
            'branch_id' => $branch->id,
            'name' => 'Popup Warehouse',
        ]);
    }

    public function test_amr_trials_are_limited_and_lock_after_a_report_action(): void
    {
        $branch = Branch::where('name', 'North Cotabato')->firstOrFail();
        $warehouse = $branch->warehouses()->create(['name' => 'Trial Warehouse']);
        $payload = [
            'form_type' => 'amr',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => '99',
            'test_milling_date' => '2026-09-24',
            'variety' => 'PD',
            'purity' => 94.31,
            'mc' => 11.1,
            'quality' => 'gqa',
            'aged' => 5,
            'volume' => 10,
            'rice_millers' => 'Miller',
            'palay_input' => 100,
            'rice_recovery' => 60,
        ];

        for ($trial = 1; $trial <= 3; $trial++) {
            $this->post(route('records.store'), [
                ...$payload,
                'no_of_trial' => $trial,
            ])->assertRedirect();
        }

        $pile = Pile::where('warehouse_id', $warehouse->id)->where('number', '99')->firstOrFail();
        $this->post(route('piles.status', $pile), [
            'form_type' => 'amr',
            'action' => 'approved',
        ])->assertRedirect();

        $response = $this->post(route('records.store'), [
            ...$payload,
            'no_of_trial' => 4,
        ]);

        $response->assertSessionHasErrors('no_of_trial');
        $this->assertDatabaseHas('piles', ['id' => $pile->id, 'amr_status' => 'approved']);
    }

    public function test_a_pile_can_be_created_from_the_popup_endpoint(): void
    {
        $branch = Branch::where('name', 'North Cotabato')->firstOrFail();
        $warehouse = $branch->warehouses()->create(['name' => 'Pile Popup Warehouse']);

        $response = $this->postJson(route('piles.store'), [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'number' => 'P-100',
        ]);

        $response->assertCreated()->assertJson([
            'number' => 'P-100',
            'warehouse_id' => $warehouse->id,
            'branch_id' => $branch->id,
        ]);
        $this->assertDatabaseHas('piles', [
            'warehouse_id' => $warehouse->id,
            'number' => 'P-100',
        ]);
    }

    public function test_a_warehouse_cannot_be_used_from_another_branch(): void
    {
        $north = Branch::where('name', 'North Cotabato')->firstOrFail();
        $south = Branch::where('name', 'South Cotabato')->firstOrFail();
        $warehouse = $north->warehouses()->create(['name' => 'North Warehouse']);

        $response = $this->post(route('records.store'), [
            'form_type' => 'amr',
            'branch_id' => $south->id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => '1',
            'test_milling_date' => '2026-09-24',
            'variety' => 'PD',
            'purity' => 94.31,
            'mc' => 11.1,
            'quality' => 'gqa',
            'aged' => 5,
            'volume' => 10,
            'rice_millers' => 'Miller',
            'no_of_trial' => 1,
            'palay_input' => 100,
            'rice_recovery' => 60,
        ]);

        $response->assertSessionHasErrors('warehouse_id');
        $this->assertDatabaseCount('amr_records', 0);
    }

    public function test_form_type_has_no_default_value_and_quality_defaults_to_good_treated_fair_poor(): void
    {
        $response = $this->get(route('records.create'));
        $response->assertOk();

        // Branches default to only three
        $defaultBranches = Branch::orderBy('name')->pluck('name')->all();
        $this->assertSame(['North Cotabato', 'South Cotabato', 'Sultan Kudarat'], $defaultBranches);

        // Form type dropdown has no default selection
        $response->assertSee('Select Form Type');
        $response->assertSee('<option value="" selected>Select Form Type</option>', false);

        // Quality dropdown contains Good, Fair, Treated, and Poor
        $response->assertSee('Good</option>', false);
        $response->assertSee('Fair</option>', false);
        $response->assertSee('Treated</option>', false);
        $response->assertDontSee('Treated Fair</option>', false);
        $response->assertSee('Poor</option>', false);
        $response->assertDontSee('<option value="gqa"', false);
        $response->assertDontSee('<option value="premium"', false);
    }

    public function test_trial_numbering_is_automatically_assigned_when_not_provided_or_empty(): void
    {
        $branch = Branch::where('name', 'North Cotabato')->firstOrFail();
        $warehouse = $branch->warehouses()->create(['name' => 'Auto Trial Warehouse']);
        $pile = $warehouse->piles()->create(['number' => '10']);

        $payload = [
            'form_type' => 'amr',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_id' => $pile->id,
            'variety' => 'PD',
            'purity' => 94.31,
            'mc' => 11.1,
            'quality' => 'good',
            'aged' => 5,
            'volume' => 10,
            'trials' => [
                [
                    'trial_number' => '', // Empty string as sent by form when not provided
                    'test_milling_date' => '2026-09-24',
                    'rice_millers' => 'Miller 1',
                    'palay_input' => 100,
                    'rice_recovery' => 65,
                ],
            ],
        ];

        // 1. First trial should be automatically assigned trial 1
        $response = $this->post(route('records.store'), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('amr_records', [
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'rice_millers' => 'Miller 1',
        ]);

        // 2. Second trial without trial_number should be automatically assigned trial 2
        $payload['trials'][0]['trial_number'] = null;
        $payload['trials'][0]['rice_millers'] = 'Miller 2';
        $response2 = $this->post(route('records.store'), $payload);
        $response2->assertSessionHasNoErrors();
        $response2->assertRedirect();

        $this->assertDatabaseHas('amr_records', [
            'pile_id' => $pile->id,
            'trial_number' => 2,
            'rice_millers' => 'Miller 2',
        ]);
    }

    public function test_form_has_default_trial_number_and_disables_inactive_section(): void
    {
        $response = $this->get(route('records.create'));
        $response->assertOk();

        // AMR trial input has default value=1
        $response->assertSee('name="trials[0][trial_number]" data-trial-value value="1"', false);

        // PMR trial input is disabled initially to prevent duplicate empty submission
        $response->assertSee('name="trials[0][trial_number]" data-trial-value value="1" disabled', false);
    }
}
