<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\Warehouse;
use App\Services\PmrCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmrRecoveryRateTest extends TestCase
{
    use RefreshDatabase;

    private function createPile(): Pile
    {
        $branch = Branch::create(['name' => 'Bulacan Branch']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Warehouse A',
        ]);

        return Pile::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => 'P-101',
            'variety' => 'Well Milled Rice',
            'purity' => 95.0,
            'aged_months' => 6,
            'mc' => 14.0,
            'quality' => 'good',
            'volume_kg' => 1500,
        ]);
    }

    public function test_pmr_auto_calculates_recovery_rate_when_both_inputs_are_provided(): void
    {
        $pile = $this->createPile();

        $response = $this->post(route('records.store'), [
            'form_type' => 'pmr',
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
                    'palay_input' => 100,
                    'rice_recovery' => 63,
                    'recovery_rate' => null,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pmr_records', [
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'palay_input_kg' => 100.0,
            'rice_recovery_kg' => 63.0,
            'milling_recovery' => 63.0,
        ]);
    }

    public function test_pmr_allows_manual_recovery_rate_when_inputs_are_blank(): void
    {
        $pile = $this->createPile();

        $response = $this->post(route('records.store'), [
            'form_type' => 'pmr',
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
                    'palay_input' => null,
                    'rice_recovery' => null,
                    'recovery_rate' => 63.0,
                ],
            ],
        ]);

        $response->assertRedirect();
        $record = PmrRecord::where('pile_id', $pile->id)
            ->where('trial_number', 1)
            ->first();
        $this->assertNotNull($record);
        $this->assertNull($record->palay_input_kg);
        $this->assertNull($record->rice_recovery_kg);
        $this->assertEquals(63.0, (float) $record->milling_recovery);
        $this->assertEquals(63.0, $record->recovery_rate_percentage);
    }

    public function test_pmr_allows_manual_recovery_rate_when_only_palay_input_is_provided(): void
    {
        $pile = $this->createPile();

        $response = $this->post(route('records.store'), [
            'form_type' => 'pmr',
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
                    'palay_input' => 100,
                    'rice_recovery' => null,
                    'recovery_rate' => 63.0,
                ],
            ],
        ]);

        $response->assertRedirect();
        $record = PmrRecord::where('pile_id', $pile->id)
            ->where('trial_number', 1)
            ->first();
        $this->assertNotNull($record);
        $this->assertEquals(100.0, (float) $record->palay_input_kg);
        $this->assertNull($record->rice_recovery_kg);
        $this->assertEquals(63.0, (float) $record->milling_recovery);
    }

    public function test_pmr_allows_manual_recovery_rate_when_only_rice_output_is_provided(): void
    {
        $pile = $this->createPile();

        $response = $this->post(route('records.store'), [
            'form_type' => 'pmr',
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
                    'palay_input' => null,
                    'rice_recovery' => 63,
                    'recovery_rate' => 63.0,
                ],
            ],
        ]);

        $response->assertRedirect();
        $record = PmrRecord::where('pile_id', $pile->id)
            ->where('trial_number', 1)
            ->first();
        $this->assertNotNull($record);
        $this->assertNull($record->palay_input_kg);
        $this->assertEquals(63.0, (float) $record->rice_recovery_kg);
        $this->assertEquals(63.0, (float) $record->milling_recovery);
    }

    public function test_pmr_rejects_when_inputs_and_recovery_rate_are_all_blank(): void
    {
        $pile = $this->createPile();

        $response = $this->post(route('records.store'), [
            'form_type' => 'pmr',
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

    public function test_amr_form_still_requires_palay_input_and_rice_output(): void
    {
        $pile = $this->createPile();

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
                    'rice_millers' => 'Miller 1',
                    'palay_input' => null,
                    'rice_recovery' => null,
                    'recovery_rate' => 63.0,
                ],
            ],
        ]);

        $response->assertSessionHasErrors([
            'trials.0.palay_input',
            'trials.0.rice_recovery',
        ]);
    }

    public function test_pmr_trial_inline_update_with_manual_recovery_rate(): void
    {
        $pile = $this->createPile();
        $record = PmrRecord::create([
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'test_milling_date' => '2026-03-01',
            'palay_input_kg' => 100,
            'rice_recovery_kg' => 63,
            'milling_recovery' => 63.0,
        ]);

        $response = $this->patchJson(
            route('records.update', [
                'formType' => 'pmr',
                'record' => $record->id,
            ]),
            [
                'test_milling_date' => '2026-03-02',
                'palay_input' => null,
                'rice_recovery' => null,
                'recovery_rate' => 65.5,
            ],
        );

        $response->assertOk();
        $record->refresh();
        $this->assertNull($record->palay_input_kg);
        $this->assertNull($record->rice_recovery_kg);
        $this->assertEquals(65.5, (float) $record->milling_recovery);
    }

    public function test_pmr_calculation_service_and_report_with_direct_recovery_rates(): void
    {
        $pile = $this->createPile();

        // 3 trials entered purely with recovery_rate and without input/output weights
        PmrRecord::create([
            'pile_id' => $pile->id,
            'trial_number' => 1,
            'test_milling_date' => '2026-03-01',
            'palay_input_kg' => null,
            'rice_recovery_kg' => null,
            'milling_recovery' => 64.0,
        ]);
        PmrRecord::create([
            'pile_id' => $pile->id,
            'trial_number' => 2,
            'test_milling_date' => '2026-03-02',
            'palay_input_kg' => null,
            'rice_recovery_kg' => null,
            'milling_recovery' => 64.5,
        ]);
        PmrRecord::create([
            'pile_id' => $pile->id,
            'trial_number' => 3,
            'test_milling_date' => '2026-03-03',
            'palay_input_kg' => null,
            'rice_recovery_kg' => null,
            'milling_recovery' => 65.0,
        ]);

        /** @var PmrCalculationService $service */
        $service = app(PmrCalculationService::class);
        $calc = $service->calculateAndStoreForPile($pile);

        $this->assertTrue($calc->isValid);
        $this->assertEquals(64.5, $calc->median);
        $this->assertEquals(64.5, $calc->pmrRate);

        // Check PMR report page renders and shows '—' for missing palay inputs
        $response = $this->get(route('pmr.index'));
        $response->assertOk();
        $response->assertSee('64.50%');
        $response->assertSee('—');
    }
}
