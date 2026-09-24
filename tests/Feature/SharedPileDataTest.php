<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedPileDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_amr_and_pmr_share_pile_metadata_across_forms_and_reports(): void
    {
        $branch = Branch::firstOrCreate(['name' => 'South Cotabato']);
        $warehouse = Warehouse::create(['name' => 'GID#2, MLANG BS', 'branch_id' => $branch->id]);

        // 1. Submit AMR trial for Pile 1
        $amrResponse = $this->post(route('records.store'), [
            'form_type' => 'amr',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'new_pile_number' => '1',
            'variety' => 'RC 160',
            'rice_millers' => 'MAGDAMO RM',
            'purity' => '94.50',
            'mc' => '12.30',
            'quality' => 'gqa',
            'aged' => 6,
            'volume' => '10,500.500',
            'no_of_trial' => 1,
            'test_milling_date' => '2026-09-24',
            'palay_input' => '10000.00',
            'rice_recovery' => '6250.00',
        ]);

        $amrResponse->assertRedirect(route('records.create', ['type' => 'amr']));

        $pile = Pile::where('warehouse_id', $warehouse->id)->where('number', '1')->firstOrFail();

        // 2. Open PMR creation form and assert the shared pile details are populated for Pile 1
        $createFormResponse = $this->get(route('records.create', ['type' => 'pmr']));
        $createFormResponse->assertOk();
        $createFormResponse->assertSee('RC 160');
        $createFormResponse->assertSee('94.5');

        // 3. Submit PMR trial for the SAME pile
        $pmrResponse = $this->post(route('records.store'), [
            'form_type' => 'pmr',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_id' => $pile->id,
            'variety' => 'RC 160',
            'purity' => '94.50',
            'mc' => '12.30',
            'quality' => 'gqa',
            'aged' => 6,
            'volume' => '10,500.500',
            'no_of_trial' => 1,
            'test_milling_date' => '2026-09-24',
            'palay_input' => '10000.00',
            'rice_recovery' => '6200.00',
        ]);

        $pmrResponse->assertRedirect(route('records.create', ['type' => 'pmr']));

        // Assert database records in both tables have identical shared metadata
        $this->assertDatabaseHas('amr_records', [
            'pile_id' => $pile->id,
            'variety' => 'RC 160',
            'purity' => '94.50',
            'mc' => '12.30',
            'quality' => 'gqa',
            'aged_months' => 6,
            'volume_bags' => '10500.500',
        ]);

        $this->assertDatabaseHas('pmr_records', [
            'pile_id' => $pile->id,
            'variety' => 'RC 160',
            'purity' => '94.50',
            'mc' => '12.30',
            'quality' => 'gqa',
            'aged_months' => 6,
            'volume_bags' => '10500.500',
        ]);

        // 4. Assert both reports display the exact same shared data
        $amrReport = $this->get(route('amr.index'));
        $amrReport->assertOk();
        $amrReport->assertSee('South Cotabato');
        $amrReport->assertSee('GID#2, MLANG BS');
        $amrReport->assertSee('RC 160');
        $amrReport->assertSee('94.50');
        $amrReport->assertSee('12.3');
        $amrReport->assertSee('GQA');
        $amrReport->assertSee('10,500.500');

        $pmrReport = $this->get(route('pmr.index'));
        $pmrReport->assertOk();
        $pmrReport->assertSee('South Cotabato');
        $pmrReport->assertSee('GID#2, MLANG BS');
        $pmrReport->assertSee('RC 160');
        $pmrReport->assertSee('94.50');
        $pmrReport->assertSee('12.3');
        $pmrReport->assertSee('GQA');
        $pmrReport->assertSee('10,500.500');
    }

    public function test_updating_shared_pile_data_synchronizes_both_amr_and_pmr_records(): void
    {
        $branch = Branch::firstOrCreate(['name' => 'South Cotabato']);
        $warehouse = Warehouse::create(['name' => 'WH-SYNC', 'branch_id' => $branch->id]);
        $pile = Pile::create(['warehouse_id' => $warehouse->id, 'number' => '10']);

        AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'warehouse_name' => $warehouse->name,
            'pile_number' => '10',
            'variety' => 'Old Variety',
            'purity' => 90.00,
            'mc' => 14.00,
            'quality' => 'poor',
            'aged_months' => 2,
            'volume_bags' => 5000.000,
            'trial_number' => 1,
        ]);

        PmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'warehouse_name' => $warehouse->name,
            'pile_number' => '10',
            'variety' => 'Old Variety',
            'purity' => 90.00,
            'mc' => 14.00,
            'quality' => 'poor',
            'aged_months' => 2,
            'volume_bags' => 5000.000,
            'trial_number' => 1,
        ]);

        // Re-save via PMR form with updated pile metadata
        $this->post(route('records.store'), [
            'form_type' => 'pmr',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_id' => $pile->id,
            'variety' => 'Updated NSIC Rc 222',
            'purity' => '98.20',
            'mc' => '11.40',
            'quality' => 'premium',
            'aged' => 8,
            'volume' => '15,000.000',
            'no_of_trial' => 2,
            'test_milling_date' => '2026-09-24',
            'palay_input' => '10000.00',
            'rice_recovery' => '6300.00',
        ]);

        // Both tables must now have the updated shared data
        $this->assertDatabaseHas('amr_records', [
            'pile_id' => $pile->id,
            'variety' => 'Updated NSIC Rc 222',
            'purity' => '98.20',
            'mc' => '11.40',
            'quality' => 'premium',
            'aged_months' => 8,
            'volume_bags' => '15000.000',
        ]);

        $this->assertDatabaseHas('pmr_records', [
            'pile_id' => $pile->id,
            'variety' => 'Updated NSIC Rc 222',
            'purity' => '98.20',
            'mc' => '11.40',
            'quality' => 'premium',
            'aged_months' => 8,
            'volume_bags' => '15000.000',
        ]);
    }
}
