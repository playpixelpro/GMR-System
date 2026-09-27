<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AmrCalculationService;
use App\Services\PmrCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmrRecoveryRateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);
        $this->actingAs($this->user);
    }

    private function createPile(float $volumeKg = 30000): Pile
    {
        $branch = Branch::create(['name' => 'Tarlac Branch']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Warehouse B',
        ]);

        return Pile::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => 'P-202',
            'variety' => 'Well Milled Rice',
            'purity' => 96.0,
            'aged_months' => 4,
            'mc' => 13.5,
            'quality' => 'good',
            'volume_kg' => $volumeKg,
        ]);
    }

    public function test_amr_low_volume_allows_manual_recovery_rate_when_inputs_are_blank(): void
    {
        $pile = $this->createPile(40000); // <= 50,000 kg

        $response = $this->post(route('records.store'), [
            'form_type' => 'amr',
            'branch_id' => $pile->branch_id,
            'warehouse_id' => $pile->warehouse_id,
            'pile_id' => $pile->id,
            'variety' => $pile->variety,
            'purity' => $pile->purity,
            'aged' => $pile->aged_months,
            'mc' => $pile->mc,
            'quality' => $pile->quality,
            'volume' => $pile->volume_kg,
            'trials' => [
                [
                    'trial_number' => 1,
                    'test_milling_date' => '2026-03-01',
                    'rice_millers' => 'Miller Alpha',
                    'palay_input' => null,
                    'rice_recovery' => null,
                    'recovery_rate' => 64.5,
                ],
            ],
        ]);

        $response->assertRedirect();
        $record = AmrRecord::where('pile_id', $pile->id)
            ->where('trial_number', 1)
            ->first();

        $this->assertNotNull($record);
        $this->assertNull($record->palay_input_kg);
        $this->assertNull($record->rice_recovery_kg);
        $this->assertEquals(64.5, (float) $record->milling_recovery);
        $this->assertEquals(64.5, $record->milling_recovery_percentage);
    }

    public function test_amr_low_volume_auto_calculates_recovery_rate_when_both_inputs_provided(): void
    {
        $pile = $this->createPile(35000);

        $response = $this->post(route('records.store'), [
            'form_type' => 'amr',
            'branch_id' => $pile->branch_id,
            'warehouse_id' => $pile->warehouse_id,
            'pile_id' => $pile->id,
            'variety' => $pile->variety,
            'purity' => $pile->purity,
            'aged' => $pile->aged_months,
            'mc' => $pile->mc,
            'quality' => $pile->quality,
            'volume' => $pile->volume_kg,
            'trials' => [
                [
                    'trial_number' => 1,
                    'test_milling_date' => '2026-03-01',
                    'rice_millers' => 'Miller Alpha',
                    'palay_input' => 1000,
                    'rice_recovery' => 650,
                    'recovery_rate' => null,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('amr_records', [
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'palay_input_kg' => 1000.0,
            'rice_recovery_kg' => 650.0,
            'milling_recovery' => 65.0,
        ]);
    }

    public function test_amr_high_volume_requires_palay_and_rice_inputs(): void
    {
        $pile = $this->createPile(75000); // > 50,000 kg

        $response = $this->post(route('records.store'), [
            'form_type' => 'amr',
            'branch_id' => $pile->branch_id,
            'warehouse_id' => $pile->warehouse_id,
            'pile_id' => $pile->id,
            'variety' => $pile->variety,
            'purity' => $pile->purity,
            'aged' => $pile->aged_months,
            'mc' => $pile->mc,
            'quality' => $pile->quality,
            'volume' => 75000,
            'trials' => [
                [
                    'trial_number' => 1,
                    'test_milling_date' => '2026-03-01',
                    'rice_millers' => 'Miller Alpha',
                    'palay_input' => null,
                    'rice_recovery' => null,
                    'recovery_rate' => 64.0,
                ],
            ],
        ]);

        $response->assertSessionHasErrors([
            'trials.0.palay_input',
            'trials.0.rice_recovery',
        ]);
    }

    public function test_amr_low_volume_rejects_when_inputs_and_recovery_rate_are_all_blank(): void
    {
        $pile = $this->createPile(25000);

        $response = $this->post(route('records.store'), [
            'form_type' => 'amr',
            'branch_id' => $pile->branch_id,
            'warehouse_id' => $pile->warehouse_id,
            'pile_id' => $pile->id,
            'variety' => $pile->variety,
            'purity' => $pile->purity,
            'aged' => $pile->aged_months,
            'mc' => $pile->mc,
            'quality' => $pile->quality,
            'volume' => $pile->volume_kg,
            'trials' => [
                [
                    'trial_number' => 1,
                    'test_milling_date' => '2026-03-01',
                    'rice_millers' => 'Miller Alpha',
                    'palay_input' => null,
                    'rice_recovery' => null,
                    'recovery_rate' => null,
                ],
            ],
        ]);

        $response->assertSessionHasErrors([
            'recovery_rate',
            'trials.0.recovery_rate',
        ]);
    }

    public function test_amr_trial_inline_update_with_manual_recovery_rate_for_low_volume(): void
    {
        $pile = $this->createPile(30000);
        $record = AmrRecord::create([
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'test_milling_date' => '2026-03-01',
            'rice_millers' => 'Miller Beta',
            'palay_input_kg' => 1000,
            'rice_recovery_kg' => 630,
            'milling_recovery' => 63.0,
        ]);

        $response = $this->patchJson(
            route('records.update', [
                'formType' => 'amr',
                'record' => $record->id,
            ]),
            [
                'test_milling_date' => '2026-03-02',
                'rice_millers' => 'Miller Beta Updated',
                'palay_input' => null,
                'rice_recovery' => null,
                'recovery_rate' => 66.0,
            ],
        );

        $response->assertOk();
        $record->refresh();
        $this->assertNull($record->palay_input_kg);
        $this->assertNull($record->rice_recovery_kg);
        $this->assertEquals('Miller Beta Updated', $record->rice_millers);
        $this->assertEquals(66.0, (float) $record->milling_recovery);
    }

    public function test_amr_calculation_service_and_report_with_direct_recovery_rates(): void
    {
        $pile = $this->createPile(45000);

        // 3 trials entered with direct recovery rate (null palay/rice)
        AmrRecord::create([
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'test_milling_date' => '2026-03-01',
            'rice_millers' => 'Miller Gamma',
            'palay_input_kg' => null,
            'rice_recovery_kg' => null,
            'milling_recovery' => 64.0,
            'status' => 'RECOMMENDED',
            'included_in_computation' => true,
        ]);
        AmrRecord::create([
            'pile_id' => $pile->id,
            'trial_number' => 2,
            'test_milling_date' => '2026-03-02',
            'rice_millers' => 'Miller Gamma',
            'palay_input_kg' => null,
            'rice_recovery_kg' => null,
            'milling_recovery' => 65.0,
            'status' => 'RECOMMENDED',
            'included_in_computation' => true,
        ]);
        AmrRecord::create([
            'pile_id' => $pile->id,
            'trial_number' => 3,
            'test_milling_date' => '2026-03-03',
            'rice_millers' => 'Miller Gamma',
            'palay_input_kg' => null,
            'rice_recovery_kg' => null,
            'milling_recovery' => 66.0,
            'status' => 'RECOMMENDED',
            'included_in_computation' => true,
        ]);

        /** @var AmrCalculationService $service */
        $service = app(AmrCalculationService::class);
        $calc = $service->calculateAndStoreForPile($pile);

        $this->assertTrue($calc->isValid);
        $this->assertEquals(65.0, $calc->median);
        $this->assertEquals(65.0, $calc->amrRate);

        // Check AMR report page renders and shows '—' for missing palay inputs
        $response = $this->get(route('amr.index'));
        $response->assertOk();
        $response->assertSee('65.00%');
        $response->assertSee('—');
    }

    public function test_gmr_summary_calculates_correctly_with_amr_direct_recovery_rate(): void
    {
        $pile = $this->createPile(30000);

        // Create 3 valid AMR trials with direct recovery rate
        for ($i = 1; $i <= 3; $i++) {
            AmrRecord::create([
                'pile_id' => $pile->id,
                'trial_number' => $i,
                'test_milling_date' => '2026-03-0'.$i,
                'rice_millers' => 'Miller Omega',
                'palay_input_kg' => null,
                'rice_recovery_kg' => null,
                'milling_recovery' => 64.0,
                'status' => 'RECOMMENDED',
                'included_in_computation' => true,
            ]);
        }

        // Create 3 valid PMR trials with direct recovery rate
        for ($i = 1; $i <= 3; $i++) {
            PmrRecord::create([
                'pile_id' => $pile->id,
                'trial_number' => $i,
                'test_milling_date' => '2026-03-0'.$i,
                'palay_input_kg' => null,
                'rice_recovery_kg' => null,
                'milling_recovery' => 66.0,
                'status' => 'RECOMMENDED',
                'included_in_computation' => true,
            ]);
        }

        // Run calculation services
        app(AmrCalculationService::class)->calculateAndStoreForPile($pile);
        app(PmrCalculationService::class)->calculateAndStoreForPile($pile);

        $response = $this->get(route('gmr.summary'));
        $response->assertOk();
        $response->assertSee('64.00%'); // AMR
        $response->assertSee('66.00%'); // PMR
        $response->assertSee('65.00%'); // GMR: (64 + 66) / 2
    }
}
