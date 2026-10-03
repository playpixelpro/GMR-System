<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AmrCalculationService;
use App\Services\EmrGmrGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmrMriEstablishmentTest extends TestCase
{
    use RefreshDatabase;

    protected AmrCalculationService $calculationService;

    protected EmrGmrGateService $gateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculationService = app(AmrCalculationService::class);
        $this->gateService = app(EmrGmrGateService::class);
    }

    public function test_low_volume_pile_establishes_amr_via_mri_deduction_in_service(): void
    {
        $pile = new Pile(['volume_kg' => 45000]);

        $trials = [
            [
                'trial_number' => 1,
                'pmr_rate' => 65.50,
                'mri_rate' => 3.00,
                'establishment_type' => 'mri',
                'palay_input' => null,
                'rice_recovery' => null,
            ],
        ];

        // 45,000 kg <= 50,000 kg
        $result = $this->calculationService->calculate($trials, $pile);

        $this->assertTrue($result->isValid);
        $this->assertTrue($result->isMriEstablished());
        $this->assertSame(62.50, $result->amrRate);
        $this->assertSame(1, $result->validTrialCount);
        $this->assertSame(0, $result->outlierCount);
        $this->assertSame('VALID', $result->status);
        $this->assertSame(65.50, $result->getPmrRate());
        $this->assertSame(3.00, $result->getMriRate());
    }

    public function test_low_volume_pile_can_be_stored_via_form_with_mri_fields(): void
    {
        $branch = Branch::create(['name' => 'Isabela Branch']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Santiago Warehouse',
        ]);
        $pile = Pile::create([
            'warehouse_id' => $warehouse->id,
            'branch_id' => $branch->id,
            'number' => 'P-101',
            'variety' => 'RC-160',
            'volume_kg' => 35000,
            'purity' => 95.0,
            'mc' => 13.5,
            'aged_months' => 3,
            'quality' => 'good',
        ]);

        $staffUser = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($staffUser)->post(route('records.store'), [
            'form_type' => 'amr',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_id' => $pile->id,
            'variety' => 'RC-160',
            'volume' => '35,000',
            'purity' => 95.0,
            'mc' => 13.5,
            'aged' => 3,
            'quality' => 'good',
            'mri_test_milling_date' => '2026-09-30',
            'pmr_rate' => 66.20,
            'mri_rate' => 3.00,
            'mri_remarks' => 'Deduction per C.3.10 based on standard PNS/BAFS guidelines',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('records.create', ['type' => 'amr']));

        $this->assertDatabaseHas('amr_records', [
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'establishment_type' => 'mri',
            'pmr_rate' => 66.20,
            'mri_rate' => 3.00,
            'milling_recovery' => 63.20,
            'mri_remarks' => 'Deduction per C.3.10 based on standard PNS/BAFS guidelines',
        ]);
    }

    public function test_low_volume_form_saves_mri_remarks_explanation(): void
    {
        $branch = Branch::create(['name' => 'Cagayan Branch']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Tuguegarao Warehouse',
        ]);
        $pile = Pile::create([
            'warehouse_id' => $warehouse->id,
            'branch_id' => $branch->id,
            'number' => 'P-505',
            'variety' => 'RC-216',
            'volume_kg' => 30000,
            'purity' => 95.0,
            'mc' => 13.5,
            'aged_months' => 3,
            'quality' => 'good',
        ]);

        $staffUser = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $remarks = 'Based on PNS/BAFS 303:2020 standard deduction of 1.50%.';

        $response = $this->actingAs($staffUser)->post(route('records.store'), [
            'form_type' => 'amr',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_id' => $pile->id,
            'variety' => 'RC-216',
            'volume' => '30,000',
            'purity' => 95.0,
            'mc' => 13.5,
            'aged' => 3,
            'quality' => 'good',
            'mri_test_milling_date' => '2026-09-30',
            'pmr_rate' => 65.00,
            'mri_rate' => 1.50,
            'mri_remarks' => $remarks,
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('amr_records', [
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'establishment_type' => 'mri',
            'mri_remarks' => $remarks,
        ]);
    }

    public function test_editing_low_volume_amr_trial_saves_mri_remarks(): void
    {
        $branch = Branch::create(['name' => 'Ilocos Branch']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Laoag Warehouse',
        ]);
        $pile = Pile::create([
            'warehouse_id' => $warehouse->id,
            'branch_id' => $branch->id,
            'number' => 'P-606',
            'variety' => 'RC-160',
            'volume_kg' => 32000,
            'purity' => 95.0,
            'mc' => 13.5,
            'aged_months' => 3,
            'quality' => 'good',
        ]);

        $staffUser = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $record = AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'conduct_number' => 1,
            'establishment_type' => 'mri',
            'pmr_rate' => 65.00,
            'mri_rate' => 1.50,
            'milling_recovery' => 63.50,
            'status' => 'PENDING',
            'is_locked' => false,
            'mri_remarks' => null,
            'created_by' => $staffUser->id,
        ]);

        $remarks = 'Deduction per PNS/BAFS 303:2020 for stocks aged 3 months.';

        $response = $this->actingAs($staffUser)->patchJson(
            route('records.update', ['formType' => 'amr', 'record' => $record->id]),
            [
                'test_milling_date' => '2026-09-30',
                'pmr_rate' => 65.00,
                'mri_rate' => 1.50,
                'mri_remarks' => $remarks,
            ],
        );

        $response->assertOk();

        $this->assertDatabaseHas('amr_records', [
            'id' => $record->id,
            'mri_remarks' => $remarks,
        ]);
    }

    public function test_low_volume_amr_record_can_be_recommended_by_rmec_with_single_trial(): void
    {
        $branch = Branch::create(['name' => 'Nueva Ecija Branch']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Cabanatuan Warehouse',
        ]);
        $pile = Pile::create([
            'warehouse_id' => $warehouse->id,
            'branch_id' => $branch->id,
            'number' => 'P-202',
            'variety' => 'SL-8H',
            'volume_kg' => 48000,
            'purity' => 96.0,
            'mc' => 14.0,
            'aged_months' => 4,
            'quality' => 'fair',
        ]);

        $amrRecord = AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'conduct_number' => 1,
            'establishment_type' => 'mri',
            'pmr_rate' => 65.00,
            'mri_rate' => 3.00,
            'milling_recovery' => 62.00,
            'palay_input_kg' => null,
            'rice_recovery_kg' => null,
            'status' => 'PENDING',
            'is_locked' => false,
        ]);

        $rmecUser = User::factory()->create([
            'role' => 'rmec',
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($rmecUser)->post(route('piles.rmec-action', $pile), [
            'form_type' => 'amr',
            'action' => 'recommend',
            'remarks' => 'Approved via Guideline C.3.10 MRI deduction.',
        ]);

        $response->assertSessionHasNoErrors();

        $amrRecord->refresh();
        $pile->refresh();

        $this->assertSame('RECOMMENDED', $amrRecord->status);
        $this->assertTrue($amrRecord->is_locked);
        $this->assertSame('recommended', $pile->amr_status);
    }

    public function test_emr_gmr_gate_allows_low_volume_pile_with_single_recommended_mri_trial(): void
    {
        $branch = Branch::create(['name' => 'Pangasinan Branch']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Rosales Warehouse',
        ]);
        $pile = Pile::create([
            'warehouse_id' => $warehouse->id,
            'branch_id' => $branch->id,
            'number' => 'P-303',
            'variety' => 'RC-216',
            'volume_kg' => 25000,
            'amr_status' => 'recommended',
        ]);

        AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'conduct_number' => 1,
            'establishment_type' => 'mri',
            'pmr_rate' => 64.80,
            'mri_rate' => 2.80,
            'milling_recovery' => 62.00,
            'status' => 'RECOMMENDED',
            'is_locked' => true,
        ]);

        $evaluation = $this->gateService->evaluateAmr($pile);

        $this->assertTrue($evaluation['eligible']);
        $this->assertSame(62.00, $evaluation['rate']);
        $this->assertSame('RECOMMENDED', $evaluation['status']);
    }

    public function test_large_volume_pile_cannot_use_mri_establishment(): void
    {
        $pile = new Pile(['volume_kg' => 75000]);

        $trials = [
            [
                'trial_number' => 1,
                'pmr_rate' => 65.50,
                'mri_rate' => 3.00,
                'establishment_type' => 'mri',
            ],
        ];

        // 75,000 kg > 50,000 kg -> Must complete 3 trials
        $result = $this->calculationService->calculate($trials, $pile);

        $this->assertFalse($result->isValid);
        $this->assertFalse($result->isMriEstablished());
        $this->assertSame('INCOMPLETE', $result->status);
    }

    public function test_amr_report_page_and_modal_displays_c310_mri_details(): void
    {
        $branch = Branch::create(['name' => 'Tarlac Branch']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Capas Warehouse',
        ]);
        $pile = Pile::create([
            'warehouse_id' => $warehouse->id,
            'branch_id' => $branch->id,
            'number' => 'P-404',
            'variety' => 'RC-222',
            'volume_kg' => 40000,
            'amr_status' => 'recommended',
        ]);

        AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'conduct_number' => 1,
            'establishment_type' => 'mri',
            'pmr_rate' => 66.50,
            'mri_rate' => 3.00,
            'milling_recovery' => 63.50,
            'palay_input_kg' => null,
            'rice_recovery_kg' => null,
            'status' => 'RECOMMENDED',
            'is_locked' => true,
        ]);

        $staffUser = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($staffUser)->get(route('amr.index'));

        $response->assertOk();
        $response->assertSee('C.3.10 (MRI)');
        $response->assertSee('63.50%');
        $response->assertSee('NFA Guideline C.3.10 — Stockpiles &lt; 50,000 kg', false);
        $response->assertSee('Explanation of the MRI Used');
        $response->assertSee('Dindo O. Quitor - R12 RECO');
    }

    public function test_staff_cannot_edit_low_volume_amr_record_locked_with_recommended(): void
    {
        $branch = Branch::create(['name' => 'Bataan Branch']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Balanga Warehouse',
        ]);
        $pile = Pile::create([
            'warehouse_id' => $warehouse->id,
            'branch_id' => $branch->id,
            'number' => 'P-707',
            'variety' => 'RC-160',
            'volume_kg' => 38000,
            'amr_status' => 'pending',
        ]);

        $staffUser = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $rmecUser = User::factory()->create([
            'role' => 'rmec',
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $record = AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'conduct_number' => 1,
            'establishment_type' => 'mri',
            'pmr_rate' => 65.00,
            'mri_rate' => 1.50,
            'milling_recovery' => 63.50,
            'status' => 'PENDING',
            'is_locked' => false,
            'created_by' => $staffUser->id,
        ]);

        // RMEC recommends the test milling
        $this->actingAs($rmecUser)->post(route('piles.rmec-action', $pile), [
            'form_type' => 'amr',
            'action' => 'recommend',
            'remarks' => 'Recommended by RMEC.',
        ])->assertSessionHasNoErrors();

        $record->refresh();
        $pile->refresh();
        $this->assertTrue($record->is_locked);
        $this->assertSame('RECOMMENDED', $record->status);
        $this->assertSame('recommended', $pile->amr_status);

        // 1. Staff cannot edit the record via records.update
        $this->actingAs($staffUser)->patchJson(
            route('records.update', ['formType' => 'amr', 'record' => $record->id]),
            [
                'pmr_rate' => 64.00,
                'mri_rate' => 2.00,
            ],
        )->assertForbidden();

        // 2. Staff cannot delete the record via records.destroy
        $this->actingAs($staffUser)->deleteJson(
            route('records.destroy', ['formType' => 'amr', 'record' => $record->id]),
        )->assertForbidden();

        // 3. Staff cannot submit new data to override conduct 1 via records.store
        $this->actingAs($staffUser)->post(route('records.store'), [
            'form_type' => 'amr',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_id' => $pile->id,
            'variety' => 'RC-160',
            'volume' => '38,000',
            'purity' => 95.0,
            'mc' => 13.5,
            'aged' => 3,
            'quality' => 'good',
            'mri_test_milling_date' => '2026-09-30',
            'pmr_rate' => 64.00,
            'mri_rate' => 2.00,
            'mri_remarks' => 'Attempted overwrite',
        ])->assertSessionHasErrors(['pile']);

        // 4. Staff cannot modify pile details when AMR is locked
        $this->actingAs($staffUser)->patchJson(
            route('piles.details.update', $pile),
            [
                'variety' => 'Modified Variety',
                'purity' => 99.0,
                'aged' => 5,
                'mc' => 12.0,
                'quality' => 'good',
                'volume' => 39000,
            ],
        )->assertForbidden();

        // Ensure record was not modified
        $record->refresh();
        $this->assertSame(63.50, (float) $record->milling_recovery);
        $this->assertSame('RC-160', $record->variety);
    }

    public function test_staff_cannot_modify_old_conduct_data_when_low_volume_amr_is_actioned_with_retest(): void
    {
        $branch = Branch::create(['name' => 'Tarlac Branch']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Tarlac City Warehouse',
        ]);
        $pile = Pile::create([
            'warehouse_id' => $warehouse->id,
            'branch_id' => $branch->id,
            'number' => 'P-808',
            'variety' => 'RC-216',
            'volume_kg' => 42000,
            'amr_status' => 'pending',
        ]);

        $staffUser = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $rmecUser = User::factory()->create([
            'role' => 'rmec',
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $conduct1 = AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'conduct_number' => 1,
            'establishment_type' => 'mri',
            'pmr_rate' => 64.00,
            'mri_rate' => 3.00,
            'milling_recovery' => 61.00,
            'status' => 'PENDING',
            'is_locked' => false,
            'created_by' => $staffUser->id,
        ]);

        // RMEC actions RETEST
        $this->actingAs($rmecUser)->post(route('piles.rmec-action', $pile), [
            'form_type' => 'amr',
            'action' => 'retest',
            'remarks' => 'Retest required per RMEC.',
        ])->assertSessionHasNoErrors();

        $conduct1->refresh();
        $pile->refresh();
        $this->assertTrue($conduct1->is_locked);
        $this->assertSame('RETEST', $conduct1->status);
        $this->assertSame('retest', $pile->amr_status);

        // 1. Staff cannot edit conduct 1 via records.update
        $this->actingAs($staffUser)->patchJson(
            route('records.update', ['formType' => 'amr', 'record' => $conduct1->id]),
            [
                'pmr_rate' => 65.00,
                'mri_rate' => 2.00,
            ],
        )->assertForbidden();

        // 2. Staff cannot delete conduct 1 via records.destroy
        $this->actingAs($staffUser)->deleteJson(
            route('records.destroy', ['formType' => 'amr', 'record' => $conduct1->id]),
        )->assertForbidden();

        // 3. Staff cannot modify pile details while locked
        $this->actingAs($staffUser)->patchJson(
            route('piles.details.update', $pile),
            [
                'variety' => 'Modified Variety',
                'purity' => 99.0,
                'aged' => 5,
                'mc' => 12.0,
                'quality' => 'good',
                'volume' => 43000,
            ],
        )->assertForbidden();

        // 4. Staff CAN create a new test conduct (Conduct 2) without modifying conduct 1
        $this->actingAs($staffUser)->post(route('records.store'), [
            'form_type' => 'amr',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_id' => $pile->id,
            'variety' => 'RC-216',
            'volume' => '42,000',
            'purity' => 95.0,
            'mc' => 13.5,
            'aged' => 4,
            'quality' => 'good',
            'mri_test_milling_date' => '2026-10-01',
            'pmr_rate' => 66.00,
            'mri_rate' => 1.50,
            'mri_remarks' => 'Retest Conduct 2 MRI establishment',
        ])->assertSessionHasNoErrors();

        // Conduct 1 remains unchanged, RETEST, and locked
        $conduct1->refresh();
        $this->assertSame(1, $conduct1->conduct_number);
        $this->assertSame('RETEST', $conduct1->status);
        $this->assertTrue($conduct1->is_locked);
        $this->assertSame(61.00, (float) $conduct1->milling_recovery);

        // Conduct 2 exists with status PENDING and not locked
        $this->assertDatabaseHas('amr_records', [
            'pile_id' => $pile->id,
            'conduct_number' => 2,
            'status' => 'PENDING',
            'is_locked' => 0,
            'milling_recovery' => 64.50,
        ]);
    }
}
