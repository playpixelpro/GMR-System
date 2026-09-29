<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\EmrGmrGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RmecActionAndGateTest extends TestCase
{
    use RefreshDatabase;

    protected User $rmec;

    protected User $admin;

    protected User $staff;

    protected Warehouse $warehouse;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Region IV']);
        $this->warehouse = Warehouse::create([
            'branch_id' => $this->branch->id,
            'name' => 'Main GID',
        ]);

        $this->rmec = User::factory()->create([
            'role' => 'RMEC',
            'must_change_password' => false,
        ]);

        $this->admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        $this->staff = User::factory()->create([
            'role' => 'STAFF',
            'must_change_password' => false,
        ]);
    }

    private function createPile(string $pileNumber = 'P-101'): Pile
    {
        return Pile::create([
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'pile_number' => $pileNumber,
            'number' => $pileNumber,
            'variety' => 'RC 160',
            'purity' => 95.5,
            'mc' => 13.5,
            'quality' => 'premium',
            'aged_months' => 6,
            'volume_kg' => 50000,
        ]);
    }

    private function createAmrConduct(
        Pile $pile,
        int $conductNumber = 1,
        string $status = 'PENDING',
        bool $isLocked = false,
        float $rate = 65.0,
    ): void {
        for ($i = 1; $i <= 3; $i++) {
            AmrRecord::create([
                'pile_id' => $pile->id,
                'conduct_number' => $conductNumber,
                'trial_number' => $i,
                'palay_input_kg' => 100,
                'rice_recovery_kg' => $rate,
                'milling_recovery' => $rate,
                'is_outlier' => false,
                'status' => $status,
                'included_in_computation' => $status === 'RECOMMENDED',
                'is_locked' => $isLocked,
                'created_by' => $this->staff->id,
                'actioned_by' => $status !== 'PENDING' ? $this->rmec->id : null,
                'actioned_at' => $status !== 'PENDING' ? now() : null,
            ]);
        }
        $pile->update(['amr_status' => strtolower($status)]);
    }

    private function createPmrConduct(
        Pile $pile,
        int $conductNumber = 1,
        string $status = 'PENDING',
        bool $isLocked = false,
        float $rate = 67.0,
    ): void {
        for ($i = 1; $i <= 3; $i++) {
            PmrRecord::create([
                'pile_id' => $pile->id,
                'conduct_number' => $conductNumber,
                'trial_number' => $i,
                'palay_input_kg' => 100,
                'rice_recovery_kg' => $rate,
                'milling_recovery' => $rate,
                'is_outlier' => false,
                'status' => $status,
                'included_in_computation' => $status === 'RECOMMENDED',
                'is_locked' => $isLocked,
                'created_by' => $this->staff->id,
                'actioned_by' => $status !== 'PENDING' ? $this->rmec->id : null,
                'actioned_at' => $status !== 'PENDING' ? now() : null,
            ]);
        }
        $pile->update(['pmr_status' => strtolower($status)]);
    }

    /**
     * 1. AMR Test Milling = RECOMMEND and PMR Laboratory Test Milling = RECOMMEND
     * -> EMR/GMR COMPUTED
     */
    public function test_case_1_emr_gmr_computed_when_amr_and_pmr_both_recommend(): void
    {
        $pile = $this->createPile();
        $this->createAmrConduct($pile, 1, 'RECOMMENDED', true, 64.0);
        $this->createPmrConduct($pile, 1, 'RECOMMENDED', true, 66.0);

        $gate = app(EmrGmrGateService::class)->evaluateGate($pile);

        $this->assertTrue($gate['can_compute']);
        $this->assertEquals(64.0, $gate['amr_rate']);
        $this->assertEquals(66.0, $gate['pmr_rate']);
        $this->assertEquals(65.0, $gate['gmr']);
        $this->assertEquals('VALID', $gate['status']);

        $response = $this->actingAs($this->rmec)->get(route('emr.index'));
        $response->assertOk()
            ->assertSee('64.00% – 66.00%');
    }

    /**
     * 2. AMR = RECOMMEND and PMR = Pending
     * -> EMR/GMR BLOCKED
     */
    public function test_case_2_emr_gmr_blocked_when_amr_recommend_and_pmr_pending(): void
    {
        $pile = $this->createPile();
        $this->createAmrConduct($pile, 1, 'RECOMMENDED', true, 64.0);
        $this->createPmrConduct($pile, 1, 'PENDING', false, 66.0);

        $gate = app(EmrGmrGateService::class)->evaluateGate($pile);

        $this->assertFalse($gate['can_compute']);
        $this->assertNull($gate['gmr']);
        $this->assertEquals('N/A', $gate['emr_display']);
        $this->assertEquals('PMR Recommendation Required', $gate['status']);

        $response = $this->actingAs($this->rmec)->get(route('emr.index'));
        $response->assertOk()
            ->assertSee('PMR Recommendation Required');
    }

    /**
     * 3. AMR = Pending and PMR = RECOMMEND
     * -> EMR/GMR BLOCKED
     */
    public function test_case_3_emr_gmr_blocked_when_amr_pending_and_pmr_recommend(): void
    {
        $pile = $this->createPile();
        $this->createAmrConduct($pile, 1, 'PENDING', false, 64.0);
        $this->createPmrConduct($pile, 1, 'RECOMMENDED', true, 66.0);

        $gate = app(EmrGmrGateService::class)->evaluateGate($pile);

        $this->assertFalse($gate['can_compute']);
        $this->assertNull($gate['gmr']);
        $this->assertEquals('N/A', $gate['emr_display']);
        $this->assertEquals('AMR Recommendation Required', $gate['status']);

        $response = $this->actingAs($this->rmec)->get(route('emr.index'));
        $response->assertOk()
            ->assertSee('AMR Recommendation Required');
    }

    /**
     * 4. AMR = RETEST and PMR = RECOMMEND
     * -> EMR/GMR BLOCKED
     */
    public function test_case_4_emr_gmr_blocked_when_amr_retest_and_pmr_recommend(): void
    {
        $pile = $this->createPile();
        $this->createAmrConduct($pile, 1, 'RETEST', true, 64.0);
        $this->createPmrConduct($pile, 1, 'RECOMMENDED', true, 66.0);

        $gate = app(EmrGmrGateService::class)->evaluateGate($pile);

        $this->assertFalse($gate['can_compute']);
        $this->assertNull($gate['gmr']);
        $this->assertEquals('Retest Required', $gate['status']);
    }

    /**
     * 5. AMR = RECOMMEND and PMR = RETEST
     * -> EMR/GMR BLOCKED
     */
    public function test_case_5_emr_gmr_blocked_when_amr_recommend_and_pmr_retest(): void
    {
        $pile = $this->createPile();
        $this->createAmrConduct($pile, 1, 'RECOMMENDED', true, 64.0);
        $this->createPmrConduct($pile, 1, 'RETEST', true, 66.0);

        $gate = app(EmrGmrGateService::class)->evaluateGate($pile);

        $this->assertFalse($gate['can_compute']);
        $this->assertNull($gate['gmr']);
        $this->assertEquals('Retest Required', $gate['status']);
    }

    /**
     * 6. AMR and PMR data exist but neither has RMEC RECOMMEND
     * -> EMR/GMR MUST NOT BE COMPUTED
     */
    public function test_case_6_emr_gmr_not_computed_when_neither_has_rmec_recommend(): void
    {
        $pile = $this->createPile();
        $this->createAmrConduct($pile, 1, 'PENDING', false, 64.0);
        $this->createPmrConduct($pile, 1, 'PENDING', false, 66.0);

        $gate = app(EmrGmrGateService::class)->evaluateGate($pile);

        $this->assertFalse($gate['can_compute']);
        $this->assertNull($gate['gmr']);
        $this->assertEquals('RMEC Action Required', $gate['status']);
    }

    /**
     * 7. AMR Test Milling #1 = RETEST, AMR Test Milling #2 = RECOMMEND, PMR Laboratory Test Milling = RECOMMEND
     * -> EMR/GMR COMPUTED using AMR Test Milling #2
     */
    public function test_case_7_emr_gmr_computed_using_amr_conduct_2_after_amr_conduct_1_retest(): void
    {
        $pile = $this->createPile();
        // AMR #1: RETEST (rate 58)
        $this->createAmrConduct($pile, 1, 'RETEST', true, 58.0);
        // AMR #2: RECOMMEND (rate 64.5)
        $this->createAmrConduct($pile, 2, 'RECOMMENDED', true, 64.5);
        // PMR #1: RECOMMEND (rate 67.5)
        $this->createPmrConduct($pile, 1, 'RECOMMENDED', true, 67.5);

        $gate = app(EmrGmrGateService::class)->evaluateGate($pile);

        $this->assertTrue($gate['can_compute']);
        $this->assertEquals(64.5, $gate['amr_rate']);
        $this->assertEquals(67.5, $gate['pmr_rate']);
        $this->assertEquals(66.0, $gate['gmr']);
        $this->assertEquals('64.50% – 67.50%', $gate['emr_display']);
    }

    /**
     * 8. PMR Laboratory Test Milling #1 = RETEST, PMR Laboratory Test Milling #2 = RECOMMEND, AMR Test Milling = RECOMMEND
     * -> EMR/GMR COMPUTED using PMR Laboratory Test Milling #2
     */
    public function test_case_8_emr_gmr_computed_using_pmr_conduct_2_after_pmr_conduct_1_retest(): void
    {
        $pile = $this->createPile();
        // AMR #1: RECOMMEND (rate 63.0)
        $this->createAmrConduct($pile, 1, 'RECOMMENDED', true, 63.0);
        // PMR #1: RETEST (rate 55.0)
        $this->createPmrConduct($pile, 1, 'RETEST', true, 55.0);
        // PMR #2: RECOMMEND (rate 67.0)
        $this->createPmrConduct($pile, 2, 'RECOMMENDED', true, 67.0);

        $gate = app(EmrGmrGateService::class)->evaluateGate($pile);

        $this->assertTrue($gate['can_compute']);
        $this->assertEquals(63.0, $gate['amr_rate']);
        $this->assertEquals(67.0, $gate['pmr_rate']);
        $this->assertEquals(65.0, $gate['gmr']);
        $this->assertEquals('63.00% – 67.00%', $gate['emr_display']);
    }

    /**
     * 9. Locked AMR Test Milling Data -> Staff cannot edit or delete it
     */
    public function test_case_9_locked_amr_test_milling_data_cannot_be_edited_or_deleted_by_staff(): void
    {
        $pile = $this->createPile();
        $this->createAmrConduct($pile, 1, 'RECOMMENDED', true, 64.0);
        $record = AmrRecord::where('pile_id', $pile->id)->firstOrFail();

        // Staff tries to edit locked record
        $this->actingAs($this->staff)
            ->patchJson(route('records.update', ['formType' => 'amr', 'record' => $record->id]), [
                'palay_input' => 120,
                'rice_recovery' => 78,
            ])
            ->assertForbidden();

        // Staff tries to delete locked record
        $this->actingAs($this->staff)
            ->delete(route('records.destroy', ['formType' => 'amr', 'record' => $record->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('amr_records', [
            'id' => $record->id,
            'palay_input_kg' => 100,
            'rice_recovery_kg' => 64.0,
            'is_locked' => 1,
        ]);
    }

    /**
     * 10. Locked PMR Laboratory Test Milling Data -> Staff cannot edit or delete it
     */
    public function test_case_10_locked_pmr_laboratory_test_milling_data_cannot_be_edited_or_deleted_by_staff(): void
    {
        $pile = $this->createPile();
        $this->createPmrConduct($pile, 1, 'RECOMMENDED', true, 66.0);
        $record = PmrRecord::where('pile_id', $pile->id)->firstOrFail();

        // Staff tries to edit locked PMR record
        $this->actingAs($this->staff)
            ->patchJson(route('records.update', ['formType' => 'pmr', 'record' => $record->id]), [
                'palay_input' => 120,
                'rice_recovery' => 78,
            ])
            ->assertForbidden();

        // Staff tries to delete locked PMR record
        $this->actingAs($this->staff)
            ->delete(route('records.destroy', ['formType' => 'pmr', 'record' => $record->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('pmr_records', [
            'id' => $record->id,
            'palay_input_kg' => 100,
            'rice_recovery_kg' => 66.0,
            'is_locked' => 1,
        ]);
    }

    /**
     * 11. RETEST -> Staff can create new test-milling data but cannot modify the old data
     */
    public function test_case_11_retest_allows_staff_to_create_new_data_without_modifying_old_data(): void
    {
        $pile = $this->createPile();
        $this->createAmrConduct($pile, 1, 'RETEST', true, 58.0);

        // Staff creates new AMR test milling data for this pile
        $response = $this->actingAs($this->staff)
            ->post(route('records.store'), [
                'form_type' => 'amr',
                'branch_id' => $this->branch->id,
                'warehouse_id' => $this->warehouse->id,
                'pile_id' => $pile->id,
                'variety' => 'RC 160',
                'purity' => 95.5,
                'mc' => 13.5,
                'quality' => 'premium',
                'aged' => 6,
                'volume' => 50000,
                'trials' => [
                    ['trial_number' => 1, 'test_milling_date' => '2026-09-28', 'palay_input' => 100, 'rice_recovery' => 65],
                    ['trial_number' => 2, 'test_milling_date' => '2026-09-28', 'palay_input' => 100, 'rice_recovery' => 65],
                    ['trial_number' => 3, 'test_milling_date' => '2026-09-28', 'palay_input' => 100, 'rice_recovery' => 65],
                ],
            ]);

        $response->assertRedirect();

        // Old conduct 1 is preserved and still RETEST/locked
        $this->assertDatabaseHas('amr_records', [
            'pile_id' => $pile->id,
            'conduct_number' => 1,
            'status' => 'RETEST',
            'is_locked' => 1,
            'rice_recovery_kg' => 58.0,
        ]);

        // New conduct 2 is created with status PENDING and not locked
        $this->assertDatabaseHas('amr_records', [
            'pile_id' => $pile->id,
            'conduct_number' => 2,
            'status' => 'PENDING',
            'is_locked' => 0,
            'rice_recovery_kg' => 65.0,
        ]);

        // Pile status is pending for the new conduct
        $this->assertEquals('pending', $pile->fresh()->amr_status);
    }

    /**
     * 12. Administrator resets RMEC action -> Previous action and historical data remain preserved
     */
    public function test_case_12_administrator_can_reset_rmec_action_preserving_previous_action_and_history(): void
    {
        $pile = $this->createPile();
        $this->createAmrConduct($pile, 1, 'RECOMMENDED', true, 64.0);

        // Administrator performs reset with a reason
        $response = $this->actingAs($this->admin)
            ->post(route('piles.rmec-reset', $pile), [
                'form_type' => 'amr',
                'reason' => 'Data entry discrepancy identified during laboratory cross-verification.',
            ]);

        $response->assertRedirect();

        // Records should now be PENDING, unlocked, with previous_action, reset_by, reset_at, and reset_reason preserved
        $record = AmrRecord::where('pile_id', $pile->id)->firstOrFail();
        $this->assertEquals('PENDING', $record->status);
        $this->assertFalse((bool) $record->is_locked);
        $this->assertEquals('RECOMMENDED', $record->previous_action);
        $this->assertEquals($this->admin->id, $record->reset_by);
        $this->assertNotNull($record->reset_at);
        $this->assertEquals('Data entry discrepancy identified during laboratory cross-verification.', $record->reset_reason);

        // Pile status is reset to pending
        $this->assertEquals('pending', $pile->fresh()->amr_status);

        // Audit log exists for the reset
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'RMEC_ACTION_RESET',
            'auditable_id' => $record->id,
        ]);
    }
}
