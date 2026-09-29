<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffBranchScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_creation_requires_a_branch(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Branchless Staff',
                'email' => 'branchless@example.com',
                'role' => 'STAFF',
            ]);

        $response->assertSessionHasErrors('branch_id');
        $this->assertDatabaseMissing('users', ['email' => 'branchless@example.com']);
    }

    public function test_administrator_creation_does_not_require_a_branch(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'New Admin',
                'email' => 'newadmin@example.com',
                'role' => 'ADMINISTRATOR',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'newadmin@example.com',
            'role' => 'ADMINISTRATOR',
            'branch_id' => null,
        ]);
    }

    public function test_administrator_can_update_user_assigned_branch(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        $branchA = Branch::create(['name' => 'Branch A']);
        $branchB = Branch::create(['name' => 'Branch B']);

        $staff = User::factory()->create([
            'role' => 'STAFF',
            'branch_id' => $branchA->id,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('users.branch', $staff), [
                'branch_id' => $branchB->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'branch_id' => $branchB->id,
        ]);
    }

    public function test_data_entry_page_locks_branch_for_staff(): void
    {
        $branchA = Branch::create(['name' => 'Branch Alpha']);
        $branchB = Branch::create(['name' => 'Branch Beta']);

        $warehouseA = Warehouse::create([
            'branch_id' => $branchA->id,
            'name' => 'Warehouse Alpha 1',
        ]);
        $warehouseB = Warehouse::create([
            'branch_id' => $branchB->id,
            'name' => 'Warehouse Beta 1',
        ]);

        $staff = User::factory()->create([
            'role' => 'STAFF',
            'branch_id' => $branchA->id,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($staff)->get(route('records.create'));

        $response->assertOk();
        // Staff's assigned branch is selected and disabled
        $response->assertSee('Branch Alpha');
        $response->assertSee('cursor-not-allowed');
        // Only warehouses belonging to branch A are sent to the view
        $response->assertSee('Warehouse Alpha 1');
        $response->assertDontSee('Warehouse Beta 1');
    }

    public function test_staff_cannot_create_data_entry_for_another_branch_warehouse(): void
    {
        $branchA = Branch::create(['name' => 'Branch Alpha']);
        $branchB = Branch::create(['name' => 'Branch Beta']);

        $warehouseB = Warehouse::create([
            'branch_id' => $branchB->id,
            'name' => 'Warehouse Beta 1',
        ]);

        $staff = User::factory()->create([
            'role' => 'STAFF',
            'branch_id' => $branchA->id,
            'must_change_password' => false,
        ]);

        // Attempting to post data entry with warehouse belonging to Branch B
        $response = $this->actingAs($staff)->post(route('records.store'), [
            'form_type' => 'pmr',
            'branch_id' => $branchB->id,
            'warehouse_id' => $warehouseB->id,
            'pile_number' => 'PILE-001',
            'variety' => 'RC216',
            'purity' => 95.0,
            'mc' => 12.0,
            'quality' => 'gqa',
            'aged' => 3,
            'volume' => 10000,
            'test_milling_date' => now()->toDateString(),
            'palay_input' => 10000,
            'rice_recovery' => 6500,
        ]);

        $response->assertSessionHasErrors('warehouse_id');
    }

    public function test_staff_cannot_modify_pile_details_belonging_to_another_branch(): void
    {
        $branchA = Branch::create(['name' => 'Branch Alpha']);
        $branchB = Branch::create(['name' => 'Branch Beta']);

        $warehouseB = Warehouse::create([
            'branch_id' => $branchB->id,
            'name' => 'Warehouse Beta 2',
        ]);
        $pileB = Pile::create([
            'branch_id' => $branchB->id,
            'warehouse_id' => $warehouseB->id,
            'number' => 'PILE-B-01',
        ]);

        $staff = User::factory()->create([
            'role' => 'STAFF',
            'branch_id' => $branchA->id,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($staff)->patch(route('piles.details.update', $pileB), [
            'variety' => 'Modified Variety',
        ]);

        $response->assertForbidden();
    }

    public function test_staff_cannot_create_warehouse_in_another_branch(): void
    {
        $branchA = Branch::create(['name' => 'Branch Alpha']);
        $branchB = Branch::create(['name' => 'Branch Beta']);

        $staff = User::factory()->create([
            'role' => 'STAFF',
            'branch_id' => $branchA->id,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($staff)->post(route('warehouses.store'), [
            'branch_id' => $branchB->id,
            'name' => 'Illegal Warehouse',
        ]);

        $response->assertSessionHasErrors('branch_id');
        $this->assertDatabaseMissing('warehouses', ['name' => 'Illegal Warehouse']);
    }

    public function test_reports_are_strictly_scoped_to_staff_assigned_branch(): void
    {
        $branchA = Branch::create(['name' => 'Branch Alpha']);
        $branchB = Branch::create(['name' => 'Branch Beta']);

        $warehouseA = Warehouse::create(['branch_id' => $branchA->id, 'name' => 'Warehouse A']);
        $warehouseB = Warehouse::create(['branch_id' => $branchB->id, 'name' => 'Warehouse B']);

        $pileA = Pile::create(['branch_id' => $branchA->id, 'warehouse_id' => $warehouseA->id, 'number' => 'PA-01', 'pile_number' => 'PA-01']);
        $pileB = Pile::create(['branch_id' => $branchB->id, 'warehouse_id' => $warehouseB->id, 'number' => 'PB-01', 'pile_number' => 'PB-01']);

        PmrRecord::factory()->create([
            'pile_id' => $pileA->id,
            'warehouse_name' => $warehouseA->name,
            'pile_number' => 'PA-01',
            'palay_input_kg' => 10000,
            'rice_recovery_kg' => 6400,
        ]);
        PmrRecord::factory()->create([
            'pile_id' => $pileB->id,
            'warehouse_name' => $warehouseB->name,
            'pile_number' => 'PB-01',
            'palay_input_kg' => 10000,
            'rice_recovery_kg' => 6600,
        ]);

        $staff = User::factory()->create([
            'role' => 'STAFF',
            'branch_id' => $branchA->id,
            'must_change_password' => false,
        ]);

        // Access PMR report - even if staff requests branch_id = branchB, it gets overridden to branchA
        $response = $this->actingAs($staff)->get(route('pmr.index', ['branch_id' => $branchB->id]));
        $response->assertOk();
        $response->assertSee('PA-01');
        $response->assertDontSee('PB-01');

        // Access GMR summary
        $responseGmr = $this->actingAs($staff)->get(route('gmr.summary', ['branch_id' => $branchB->id]));
        $responseGmr->assertOk();
        $responseGmr->assertSee('PA-01');
        $responseGmr->assertDontSee('PB-01');
    }

    public function test_staff_can_save_against_an_existing_pile_in_their_branch(): void
    {
        $branchA = Branch::create(['name' => 'Branch Alpha']);
        $warehouseA = Warehouse::create(['branch_id' => $branchA->id, 'name' => 'Warehouse A']);
        // Pile intentionally has no branch_id set — it is scoped to the branch
        // only through its warehouse, exercising the warehouse-based branch check.
        $pileA = Pile::create(['warehouse_id' => $warehouseA->id, 'number' => 'PA-01', 'pile_number' => 'PA-01']);

        $staff = User::factory()->create([
            'role' => 'STAFF',
            'branch_id' => $branchA->id,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($staff)->post(route('records.store'), [
            'form_type' => 'pmr',
            'warehouse_id' => $warehouseA->id,
            'pile_id' => $pileA->id,
            'variety' => 'RC216',
            'purity' => 95.0,
            'mc' => 12.0,
            'quality' => 'gqa',
            'aged' => 3,
            'volume' => 10000,
            'trials' => [
                [
                    'trial_number' => 1,
                    'test_milling_date' => now()->toDateString(),
                    'recovery_rate' => 64.0,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('pmr_records', ['pile_id' => $pileA->id, 'trial_number' => 1]);
    }

    public function test_staff_cannot_save_against_an_existing_pile_in_another_branch(): void
    {
        $branchA = Branch::create(['name' => 'Branch Alpha']);
        $branchB = Branch::create(['name' => 'Branch Beta']);
        $warehouseB = Warehouse::create(['branch_id' => $branchB->id, 'name' => 'Warehouse B']);
        $pileB = Pile::create(['branch_id' => $branchB->id, 'warehouse_id' => $warehouseB->id, 'number' => 'PB-01', 'pile_number' => 'PB-01']);

        $staff = User::factory()->create([
            'role' => 'STAFF',
            'branch_id' => $branchA->id,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($staff)->post(route('records.store'), [
            'form_type' => 'pmr',
            'warehouse_id' => $warehouseB->id,
            'pile_id' => $pileB->id,
            'variety' => 'RC216',
            'purity' => 95.0,
            'mc' => 12.0,
            'quality' => 'gqa',
            'aged' => 3,
            'volume' => 10000,
            'trials' => [
                [
                    'trial_number' => 1,
                    'test_milling_date' => now()->toDateString(),
                    'recovery_rate' => 64.0,
                ],
            ],
        ]);

        // Previously this threw BadMethodCallException (orWhereHas on the base
        // query builder used by Rule::exists). It must now fail validation and
        // return to the form with a pile_id error.
        $response->assertSessionHasErrors('pile_id');
        $this->assertDatabaseMissing('pmr_records', ['pile_id' => $pileB->id]);
    }
}
