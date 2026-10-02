<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GmrApproval;
use App\Models\GmrApprovalPile;
use App\Models\Milling;
use App\Models\Pile;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MillingTest extends TestCase
{
    use RefreshDatabase;

    private User $rmec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rmec = User::factory()->create([
            'role' => 'rmec',
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'is_active' => true,
        ]);
        $this->actingAs($this->rmec);
    }

    public function test_can_assign_milling_only_to_approved_gmr_pile(): void
    {
        [$branch, $approvedPile] = $this->createApprovedPile('1', 5000);
        $unapprovedPile = $this->createUnapprovedPile($branch, '2', 3000);

        // Unapproved pile is rejected.
        $this->post(route('millings.store'), [
            'branch_id' => $branch->id,
            'pile_id' => $unapprovedPile->id,
            'miller' => 'Acme Mill',
            'reference_number' => 'PR-2026-001',
            'lot_number' => 'Lot 1',
        ])
            ->assertSessionHasErrors(['pile_id']);

        $this->assertDatabaseMissing('millings', ['pile_id' => $unapprovedPile->id]);

        // Approved pile is accepted and freezes the target volume.
        $this->post(route('millings.store'), [
            'branch_id' => $branch->id,
            'pile_id' => $approvedPile->id,
            'miller' => 'Acme Mill',
            'reference_number' => 'PR-2026-001',
            'lot_number' => 'Lot 1',
        ])->assertRedirect(route('millings.show', Milling::first()));

        $milling = Milling::first();
        $this->assertSame($approvedPile->id, $milling->pile_id);
        $this->assertSame('assigned', $milling->status);
        $this->assertSame('Lot 1', $milling->lot_number);
        $this->assertEquals(5000, (float) $milling->target_volume_kg);
        $this->assertEquals(100.0, (float) $milling->target_volume_bags);
    }

    public function test_cannot_assign_second_active_milling_to_same_pile(): void
    {
        [$branch, $pile] = $this->createApprovedPile('1', 5000);

        $this->post(route('millings.store'), [
            'branch_id' => $branch->id,
            'pile_id' => $pile->id,
            'miller' => 'First Mill',
            'reference_number' => 'PR-2026-001',
            'lot_number' => 'Lot 1',
        ])->assertRedirect();

        $this->post(route('millings.store'), [
            'branch_id' => $branch->id,
            'pile_id' => $pile->id,
            'miller' => 'Second Mill',
            'reference_number' => 'PR-2026-002',
            'lot_number' => 'Lot 2',
        ])->assertSessionHasErrors(['pile_id']);
    }

    public function test_assigning_milling_requires_miller(): void
    {
        [$branch, $pile] = $this->createApprovedPile('1', 5000);

        $this->post(route('millings.store'), [
            'branch_id' => $branch->id,
            'pile_id' => $pile->id,
            'miller' => '',
            'reference_number' => 'PR-2026-001',
            'lot_number' => 'Lot 1',
        ])
            ->assertSessionHasErrors(['miller']);

        $this->assertDatabaseMissing('millings', ['pile_id' => $pile->id]);
    }

    public function test_staff_can_log_progress_and_completion_auto_flips_status(): void
    {
        [$branch, $pile] = $this->createApprovedPile('1', 5000);

        $this->post(route('millings.store'), [
            'branch_id' => $branch->id,
            'pile_id' => $pile->id,
            'miller' => 'Acme Mill',
            'reference_number' => 'PR-2026-001',
            'lot_number' => 'Lot 1',
        ]);

        $milling = Milling::first();

        $staff = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'is_active' => true,
        ]);
        $this->actingAs($staff);

        // First progress entry auto-starts the milling.
        $this->post(route('millings.progress.store', $milling), [
            'progress_date' => now()->format('Y-m-d'),
            'batch_number' => 'Batch 1',
            'palay_input_kg' => 3000,
            'milled_rice_kg' => 2000,
        ])->assertRedirect(route('millings.show', $milling));

        $milling->refresh();
        $this->assertSame('ongoing', $milling->status);
        $this->assertNotNull($milling->started_at);
        $this->assertEquals(2000, $milling->cumulativeMilledKg());

        // Final entry reaches the 5000 kg target -> auto-completes.
        $this->post(route('millings.progress.store', $milling), [
            'progress_date' => now()->format('Y-m-d'),
            'batch_number' => 'Batch 2',
            'palay_input_kg' => 3000,
            'milled_rice_kg' => 3000,
        ]);

        $milling->refresh();
        $this->assertSame('completed', $milling->status);
        $this->assertNotNull($milling->completed_at);
        $this->assertEquals(5000, $milling->cumulativeMilledKg());
    }

    public function test_recording_progress_requires_batch_no_palay_input_and_milled_rice(): void
    {
        [$branch, $pile] = $this->createApprovedPile('1', 5000);

        $milling = Milling::create([
            'branch_id' => $branch->id,
            'pile_id' => $pile->id,
            'miller' => 'Acme Mill',
            'reference_number' => 'PR-2026-001',
            'status' => 'assigned',
            'target_volume_kg' => 5000,
            'assigned_at' => now(),
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $this->actingAs($staff);

        // Omitting required fields
        $this->post(route('millings.progress.store', $milling), [
            'progress_date' => now()->format('Y-m-d'),
        ])->assertSessionHasErrors(['batch_number', 'palay_input_kg', 'milled_rice_kg']);

        // Submitting with required fields succeeds
        $this->post(route('millings.progress.store', $milling), [
            'progress_date' => now()->format('Y-m-d'),
            'batch_number' => 'Batch 001',
            'palay_input_kg' => 2500,
            'milled_rice_kg' => 1625,
        ])->assertRedirect(route('millings.show', $milling));

        $this->assertDatabaseHas('milling_progress', [
            'milling_id' => $milling->id,
            'batch_number' => 'Batch 001',
            'palay_input_kg' => 2500,
            'milled_rice_kg' => 1625,
        ]);

        $this->get(route('millings.show', $milling))
            ->assertOk()
            ->assertSee('Batch No.')
            ->assertSee('Batch 001');
    }

    public function test_staff_cannot_assign_millings(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'is_active' => true,
        ]);
        $this->actingAs($staff);

        [$branch, $pile] = $this->createApprovedPile('1', 5000);

        $this->post(route('millings.store'), [
            'branch_id' => $branch->id,
            'pile_id' => $pile->id,
        ])->assertForbidden();
    }

    public function test_milling_freezes_co_approved_final_gmr_as_official_basis(): void
    {
        [$branch, $pile] = $this->createApprovedPile('1', 5000);

        // Simulate a Central Office approval that overrides the recommended GMR.
        $approval = GmrApproval::create([
            'branch_id' => $branch->id,
            'reference_number' => 'GMR-CO-1',
            'status' => 'approved',
            'co_approval_memo_no' => 'AO-2026-01-001',
            'submitted_by' => $this->rmec->id,
            'approved_by' => $this->rmec->id,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
        $approvalPile = GmrApprovalPile::create([
            'gmr_approval_id' => $approval->id,
            'pile_id' => $pile->id,
            'gmr' => 63.00,            // Recommended GMR (frozen snapshot)
            'co_approved_gmr' => 70.00, // Central Office Approved GMR (takes precedence)
            'volume_kg' => 5000,
        ]);
        $pile->update(['gmr_approval_pile_id' => $approvalPile->id]);

        $this->post(route('millings.store'), [
            'branch_id' => $branch->id,
            'pile_id' => $pile->id,
            'miller' => 'Acme Mill',
            'reference_number' => 'PR-2026-001',
            'lot_number' => 'Lot 1',
        ])->assertRedirect(route('millings.show', Milling::first()));

        $milling = Milling::first();
        // The milling must use the CO Approved GMR as the official Final GMR basis,
        // never the Recommended GMR, when a CO Approved GMR exists.
        $this->assertEquals(70.00, (float) $milling->final_gmr);
        $this->assertSame('co_approved', $milling->final_gmr_source);
    }

    public function test_milling_freezes_recommended_gmr_when_no_co_approved(): void
    {
        [$branch, $pile] = $this->createApprovedPile('1', 5000);

        $approval = GmrApproval::create([
            'branch_id' => $branch->id,
            'reference_number' => 'GMR-CO-2',
            'status' => 'approved',
            'co_approval_memo_no' => 'AO-2026-01-002',
            'submitted_by' => $this->rmec->id,
            'approved_by' => $this->rmec->id,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
        $approvalPile = GmrApprovalPile::create([
            'gmr_approval_id' => $approval->id,
            'pile_id' => $pile->id,
            'gmr' => 63.00,
            'co_approved_gmr' => null, // No CO override -> Final GMR is the Recommended GMR.
            'volume_kg' => 5000,
        ]);
        $pile->update(['gmr_approval_pile_id' => $approvalPile->id]);

        $this->post(route('millings.store'), [
            'branch_id' => $branch->id,
            'pile_id' => $pile->id,
            'miller' => 'Acme Mill',
            'reference_number' => 'PR-2026-001',
            'lot_number' => 'Lot 1',
        ])->assertRedirect(route('millings.show', Milling::first()));

        $milling = Milling::first();
        $this->assertEquals(63.00, (float) $milling->final_gmr);
        $this->assertSame('recommended', $milling->final_gmr_source);
    }

    public function test_assign_requires_project_or_memo_reference_number(): void
    {
        [$branch, $pile] = $this->createApprovedPile('1', 5000);

        $this->post(route('millings.store'), [
            'branch_id' => $branch->id,
            'pile_id' => $pile->id,
            'miller' => 'Acme Mill',
            'lot_number' => 'Lot 1',
        ])->assertSessionHasErrors(['reference_number']);

        $this->assertDatabaseMissing('millings', ['pile_id' => $pile->id]);
    }

    public function test_assign_requires_lot_number(): void
    {
        [$branch, $pile] = $this->createApprovedPile('1', 5000);

        $this->post(route('millings.store'), [
            'branch_id' => $branch->id,
            'pile_id' => $pile->id,
            'miller' => 'Acme Mill',
            'reference_number' => 'PR-2026-001',
            'lot_number' => '',
        ])->assertSessionHasErrors(['lot_number']);

        $this->assertDatabaseMissing('millings', ['pile_id' => $pile->id]);
    }

    public function test_index_renders_assign_milling_modal_with_warehouse_details(): void
    {
        [$branch, $pile] = $this->createApprovedPile('1', 5000);

        // Simulate a Central Office approval so the modal can show the Approved GMR.
        $approval = GmrApproval::create([
            'branch_id' => $branch->id,
            'reference_number' => 'GMR-CO-MODAL',
            'status' => 'approved',
            'co_approval_memo_no' => 'AO-2026-01-003',
            'submitted_by' => $this->rmec->id,
            'approved_by' => $this->rmec->id,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
        $approvalPile = GmrApprovalPile::create([
            'gmr_approval_id' => $approval->id,
            'pile_id' => $pile->id,
            'gmr' => 63.00,
            'co_approved_gmr' => 70.00,
            'volume_kg' => 5000,
        ]);
        $pile->update(['gmr_approval_pile_id' => $approvalPile->id]);

        // The index page renders the Assign Milling modal (not a separate page
        // link) and exposes warehouse details alongside the pile number, with
        // warehouse and pile as separate cascading dropdowns.
        $this->get(route('millings.index'))
            ->assertOk()
            ->assertSee('Rice Milling Progress')
            ->assertSee('Assign Milling')
            ->assertSee('assign-milling-modal')
            ->assertSee('assign-warehouse-select')
            ->assertSee('Project / Memo Reference No.')
            ->assertSee('Lot No. *')
            ->assertSee('Rice Mill *')
            ->assertSee('Warehouse')
            ->assertSee($pile->warehouse->name)
            ->assertSee('info-approved-gmr')
            ->assertSee('Approved GMR')
            ->assertSee('70.00%');
    }

    public function test_index_filters_by_warehouse(): void
    {
        [$branch, $pile1] = $this->createApprovedPile('1', 5000);
        $warehouse2 = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Second Warehouse']);
        $pile2 = Pile::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse2->id,
            'pile_number' => '2',
            'number' => '2',
            'volume_kg' => 4000,
            'gmr_status' => 'approved',
            'gmr_locked_at' => now(),
        ]);

        Milling::create([
            'branch_id' => $branch->id,
            'pile_id' => $pile1->id,
            'reference_number' => 'REF-WH-1',
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        Milling::create([
            'branch_id' => $branch->id,
            'pile_id' => $pile2->id,
            'reference_number' => 'REF-WH-2',
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $this->get(route('millings.index', ['warehouse_id' => $pile1->warehouse_id]))
            ->assertOk()
            ->assertSee('REF-WH-1')
            ->assertDontSee('REF-WH-2');

        $this->get(route('millings.index', ['warehouse_id' => $warehouse2->id]))
            ->assertOk()
            ->assertSee('REF-WH-2')
            ->assertDontSee('REF-WH-1');
    }

    public function test_index_filters_by_date_range(): void
    {
        [$branch, $pile1] = $this->createApprovedPile('1', 5000);

        Milling::create([
            'branch_id' => $branch->id,
            'pile_id' => $pile1->id,
            'reference_number' => 'REF-OLD-DATE',
            'status' => 'completed',
            'assigned_at' => '2026-01-10',
        ]);

        $warehouse2 = Warehouse::create(['branch_id' => $branch->id, 'name' => 'WH 2']);
        $pile2 = Pile::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse2->id,
            'pile_number' => '2',
            'number' => '2',
            'volume_kg' => 3000,
            'gmr_status' => 'approved',
            'gmr_locked_at' => now(),
        ]);

        Milling::create([
            'branch_id' => $branch->id,
            'pile_id' => $pile2->id,
            'reference_number' => 'REF-NEW-DATE',
            'status' => 'assigned',
            'assigned_at' => '2026-05-20',
        ]);

        // Filter for January 2026
        $this->get(route('millings.index', ['date_from' => '2026-01-01', 'date_to' => '2026-01-31']))
            ->assertOk()
            ->assertSee('REF-OLD-DATE')
            ->assertDontSee('REF-NEW-DATE');

        // Filter for May 2026
        $this->get(route('millings.index', ['date_from' => '2026-05-01', 'date_to' => '2026-05-31']))
            ->assertOk()
            ->assertSee('REF-NEW-DATE')
            ->assertDontSee('REF-OLD-DATE');
    }

    public function test_index_displays_target_accomplishment_and_balance_columns(): void
    {
        [$branch, $pile] = $this->createApprovedPile('1', 5000);

        $milling = Milling::create([
            'branch_id' => $branch->id,
            'pile_id' => $pile->id,
            'miller' => 'Test Miller',
            'reference_number' => 'REF-PROGRESS-COL',
            'status' => 'ongoing',
            'target_volume_kg' => 5000,
            'assigned_at' => now(),
        ]);

        $milling->progress()->create([
            'pile_id' => $pile->id,
            'progress_date' => now()->format('Y-m-d'),
            'palay_input_kg' => 3000,
            'milled_rice_kg' => 2000,
            'recovery_percentage' => 66.67,
        ]);

        $this->get(route('millings.index'))
            ->assertOk()
            ->assertSee('Target - Palay (kg)')
            ->assertSee('Issued Palay (kg)')
            ->assertSee('Rice Recovery (kg)')
            ->assertSee('Balance Palay (kg)')
            ->assertSee('Recovery Rate (%)')
            ->assertDontSee('<th>Assigned</th>', false)
            ->assertSee('5,000.000')
            ->assertSee('3,000.000')
            ->assertSee('2,000.000')
            ->assertSee('66.67%');
    }

    /**
     * @return array{0: Branch, 1: Pile}
     */
    private function createApprovedPile(string $number, int $volume): array
    {
        $branch = Branch::create(['name' => 'Branch '.$number]);
        $warehouse = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Warehouse '.$number]);
        $pile = Pile::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => $number,
            'number' => $number,
            'volume_kg' => $volume,
            'gmr_status' => 'approved',
            'gmr_locked_at' => now(),
        ]);

        return [$branch, $pile];
    }

    private function createUnapprovedPile(Branch $branch, string $number, int $volume): Pile
    {
        $warehouse = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Warehouse '.$number]);

        return Pile::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => $number,
            'number' => $number,
            'volume_kg' => $volume,
        ]);
    }
}
