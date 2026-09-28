<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\GmrApproval;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GmrApprovalTest extends TestCase
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

    public function test_submit_creates_approval_and_marks_piles_submitted(): void
    {
        [$branch, $pile] = $this->createComputedPile('1', 5000, 62.0, 64.0);

        $response = $this->post(route('gmr-approvals.store'), [
            'branch_id' => $branch->id,
            'reference_number' => 'RM-2026-01-001',
            'selected_piles' => [$pile->id],
            'remarks' => 'Submitting for central office approval',
        ]);

        $response->assertRedirect(route('gmr-approvals.index'));
        $this->assertDatabaseHas('gmr_approvals', [
            'branch_id' => $branch->id,
            'status' => 'submitted',
            'reference_number' => 'RM-2026-01-001',
        ]);

        $pile->refresh();
        $this->assertSame('submitted', $pile->gmr_status);
        $this->assertNull($pile->gmr_locked_at);
    }

    public function test_approve_permanently_locks_piles_and_blocks_rmec_action(): void
    {
        [$branch, $pile] = $this->createComputedPile('1', 5000, 62.0, 64.0);

        $this->post(route('gmr-approvals.store'), [
            'branch_id' => $branch->id,
            'reference_number' => 'RM-2026-01-001',
            'selected_piles' => [$pile->id],
        ]);

        $approval = GmrApproval::first();
        $approvalPile = $approval->piles->first();
        $recommendedGmr = (float) $approvalPile->gmr;

        $response = $this->post(route('gmr-approvals.approve', $approval), [
            'co_approval_memo_no' => 'AO-2026-01-001',
            'remarks' => 'Approved by central office',
            // CO approved GMR deliberately differs from the recommended GMR.
            'co_approved_gmr' => [$approvalPile->id => '70.00'],
        ]);

        $response->assertRedirect(route('gmr-approvals.index'));

        $pile->refresh();
        $this->assertSame('approved', $pile->gmr_status);
        $this->assertNotNull($pile->gmr_locked_at);
        $this->assertTrue($pile->isGmrLocked());

        // The CO memo reference is preserved on the approval record.
        $approval->refresh();
        $this->assertSame('AO-2026-01-001', $approval->co_approval_memo_no);

        // Recommended GMR is never overwritten; CO approved takes precedence as Final GMR.
        $approvalPile->refresh();
        $this->assertSame($recommendedGmr, (float) $approvalPile->gmr);
        $this->assertSame(70.00, (float) $approvalPile->co_approved_gmr);
        $this->assertSame(70.00, $approvalPile->finalGmr());
        $this->assertSame('co_approved', $approvalPile->finalGmrSource());
        $this->assertTrue($pile->finalGmrIsCoApproved());
        $this->assertSame(70.00, $pile->finalGmr());

        // The locked pile can no longer receive an RMEC action.
        $latestAmr = AmrRecord::where('pile_id', $pile->id)->latest('id')->first();

        $this->post(route('tests.action', ['formType' => 'amr', 'record' => $latestAmr->id]), [
            'action' => 'retest',
        ])->assertSessionHasErrors(['action']);
    }

    public function test_approve_without_co_approved_gmr_falls_back_to_recommended(): void
    {
        [$branch, $pile] = $this->createComputedPile('1', 5000, 62.0, 64.0);

        $this->post(route('gmr-approvals.store'), [
            'branch_id' => $branch->id,
            'reference_number' => 'RM-2026-01-001',
            'selected_piles' => [$pile->id],
        ]);

        $approval = GmrApproval::first();
        $approvalPile = $approval->piles->first();
        $recommendedGmr = (float) $approvalPile->gmr;

        $this->post(route('gmr-approvals.approve', $approval), [
            'co_approval_memo_no' => 'AO-2026-01-002',
            // No co_approved_gmr supplied -> Final GMR falls back to Recommended.
        ])->assertRedirect(route('gmr-approvals.index'));

        $approvalPile->refresh();
        $this->assertNull($approvalPile->co_approved_gmr);
        $this->assertSame($recommendedGmr, $approvalPile->finalGmr());
        $this->assertSame('recommended', $approvalPile->finalGmrSource());

        $pile->refresh();
        $this->assertFalse($pile->finalGmrIsCoApproved());
        $this->assertSame($recommendedGmr, $pile->finalGmr());
    }

    public function test_approve_requires_co_approval_memo_no(): void
    {
        [$branch, $pile] = $this->createComputedPile('1', 5000, 62.0, 64.0);

        $this->post(route('gmr-approvals.store'), [
            'branch_id' => $branch->id,
            'reference_number' => 'RM-2026-01-001',
            'selected_piles' => [$pile->id],
        ]);

        $approval = GmrApproval::first();

        $this->post(route('gmr-approvals.approve', $approval), [
            'remarks' => 'Approved by central office',
        ])->assertSessionHasErrors(['co_approval_memo_no']);
    }

    public function test_reject_releases_piles_for_resubmission(): void
    {
        [$branch, $pile] = $this->createComputedPile('1', 5000, 62.0, 64.0);

        $this->post(route('gmr-approvals.store'), [
            'branch_id' => $branch->id,
            'reference_number' => 'RM-2026-01-001',
            'selected_piles' => [$pile->id],
        ]);

        $approval = GmrApproval::first();

        $this->post(route('gmr-approvals.reject', $approval), [
            'rejection_reason' => 'Data discrepancy in volume',
        ])->assertRedirect(route('gmr-approvals.index'));

        $approval->refresh();
        $this->assertSame('rejected', $approval->status);

        $pile->refresh();
        $this->assertNull($pile->gmr_status);
        $this->assertFalse($pile->isGmrLocked());
    }

    public function test_cannot_submit_non_computed_pile(): void
    {
        $branch = Branch::create(['name' => 'Branch']);
        $warehouse = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Warehouse']);
        $pile = Pile::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => '1',
            'number' => '1',
            'volume_kg' => 5000,
        ]);
        // No AMR/PMR records -> GMR not computed.

        $response = $this->post(route('gmr-approvals.store'), [
            'branch_id' => $branch->id,
            'reference_number' => 'RM-2026-01-001',
            'selected_piles' => [$pile->id],
        ]);

        $response->assertRedirect(route('gmr.summary'));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('gmr_approvals', ['branch_id' => $branch->id]);
    }

    public function test_submit_requires_recommendation_memo_number(): void
    {
        [$branch, $pile] = $this->createComputedPile('1', 5000, 62.0, 64.0);

        $this->post(route('gmr-approvals.store'), [
            'branch_id' => $branch->id,
            'selected_piles' => [$pile->id],
        ])->assertSessionHasErrors(['reference_number']);

        $this->assertDatabaseMissing('gmr_approvals', ['branch_id' => $branch->id]);
    }

    public function test_summary_renders_submit_to_central_office_modal_for_rmec(): void
    {
        [$branch, $pile] = $this->createComputedPile('1', 5000, 62.0, 64.0);

        // The GMR summary page exposes a popup (not a plain confirm) for RMEC
        // that captures the Recommendation Memo No. before submitting.
        $this->get(route('gmr.summary'))
            ->assertOk()
            ->assertSee('Submit to Central Office')
            ->assertSee('Recommendation Memo No.')
            ->assertSee('gmr-submit-modal');
    }

    public function test_staff_cannot_submit_to_central_office(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'is_active' => true,
        ]);
        $this->actingAs($staff);

        [$branch, $pile] = $this->createComputedPile('1', 5000, 62.0, 64.0);

        $this->post(route('gmr-approvals.store'), [
            'branch_id' => $branch->id,
            'selected_piles' => [$pile->id],
        ])->assertForbidden();
    }

    public function test_show_route_renders_submission_without_query_errors(): void
    {
        [$branch, $pile] = $this->createComputedPile('1', 5000, 62.0, 64.0);

        $this->post(route('gmr-approvals.store'), [
            'branch_id' => $branch->id,
            'reference_number' => 'RM-2026-01-001',
            'selected_piles' => [$pile->id],
        ]);

        $approval = GmrApproval::first();

        $this->get(route('gmr-approvals.show', $approval))
            ->assertOk()
            ->assertSee('GMR Submission Detail')
            ->assertSee($pile->pile_number);
    }

    public function test_index_renders_approval_modal_with_recommended_and_final_gmr(): void
    {
        [$branch, $pile] = $this->createComputedPile('1', 5000, 62.0, 64.0);

        $this->post(route('gmr-approvals.store'), [
            'branch_id' => $branch->id,
            'reference_number' => 'RM-2026-01-001',
            'selected_piles' => [$pile->id],
        ]);

        $approval = GmrApproval::first();
        $approvalPile = $approval->piles->first();
        $recommended = number_format((float) $approvalPile->gmr, 2).'%';

        // The index page renders a View button + a modal per submission that
        // exposes the Recommended GMR (read-only) and the Final GMR display,
        // plus the Recommendation Memo No. (reference) column.
        $this->get(route('gmr-approvals.index'))
            ->assertOk()
            ->assertSee('GMR Central Office Approvals')
            ->assertSee('Recommendation Memo No.')
            ->assertSee('Recommended GMR')
            ->assertSee('CO Approved GMR')
            ->assertSee('Final GMR')
            ->assertSee('CO Approval Memorandum No.')
            ->assertSee('Approved GMR')
            ->assertSee($recommended)
            ->assertSee('RM-2026-01-001');
    }

    /**
     * @return array{0: Branch, 1: Pile}
     */
    private function createComputedPile(string $number, int $volume, float $amrRate, float $pmrRate): array
    {
        $branch = Branch::create(['name' => 'Branch '.$number]);
        $warehouse = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Warehouse '.$number]);
        $pile = Pile::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => $number,
            'number' => $number,
            'volume_kg' => $volume,
        ]);

        for ($trial = 1; $trial <= 3; $trial++) {
            AmrRecord::create([
                'pile_id' => $pile->id,
                'warehouse_name' => $warehouse->name,
                'pile_number' => $pile->pile_number,
                'trial_number' => $trial,
                'conduct_number' => 1,
                'rice_millers' => 'Test Miller',
                'palay_input_kg' => 1000,
                'rice_recovery_kg' => $amrRate * 10,
                'milling_recovery' => $amrRate,
                'status' => 'RECOMMENDED',
                'is_locked' => true,
                'included_in_computation' => true,
            ]);
            PmrRecord::create([
                'pile_id' => $pile->id,
                'warehouse_name' => $warehouse->name,
                'pile_number' => $pile->pile_number,
                'trial_number' => $trial,
                'conduct_number' => 1,
                'palay_input_kg' => 1000,
                'rice_recovery_kg' => $pmrRate * 10,
                'milling_recovery' => $pmrRate,
                'status' => 'RECOMMENDED',
                'is_locked' => true,
                'included_in_computation' => true,
            ]);
        }

        return [$branch, $pile];
    }
}
