<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SharedPileDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_amr_and_pmr_share_pile_metadata_across_forms_and_reports(): void
    {
        $branch = Branch::firstOrCreate(['name' => 'South Cotabato']);
        $warehouse = Warehouse::create([
            'name' => 'GID#2, MLANG BS',
            'branch_id' => $branch->id,
        ]);

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

        $amrResponse->assertRedirect(
            route('records.create', ['type' => 'amr']),
        );

        $pile = Pile::where('warehouse_id', $warehouse->id)
            ->where('number', '1')
            ->firstOrFail();

        // 2. Open PMR creation form and assert the shared pile details are populated for Pile 1
        $createFormResponse = $this->get(
            route('records.create', ['type' => 'pmr']),
        );
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

        $pmrResponse->assertRedirect(
            route('records.create', ['type' => 'pmr']),
        );

        // Assert master pile table has the shared metadata
        $this->assertDatabaseHas('piles', [
            'id' => $pile->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => '1',
            'variety' => 'RC 160',
            'purity' => '94.50',
            'mc' => '12.30',
            'quality' => 'gqa',
            'aged_months' => 6,
            'volume_kg' => '10500.500',
        ]);

        // Assert database records in both tables have identical shared metadata
        $this->assertDatabaseHas('amr_records', [
            'pile_id' => $pile->id,
            'variety' => 'RC 160',
            'purity' => '94.50',
            'mc' => '12.30',
            'quality' => 'gqa',
            'aged_months' => 6,
            'volume_kg' => '10500.500',
        ]);

        $this->assertDatabaseHas('pmr_records', [
            'pile_id' => $pile->id,
            'variety' => 'RC 160',
            'purity' => '94.50',
            'mc' => '12.30',
            'quality' => 'gqa',
            'aged_months' => 6,
            'volume_kg' => '10500.500',
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
        $amrReport->assertSee('210.010');

        $pmrReport = $this->get(route('pmr.index'));
        $pmrReport->assertOk();
        $pmrReport->assertSee('South Cotabato');
        $pmrReport->assertSee('GID#2, MLANG BS');
        $pmrReport->assertSee('RC 160');
        $pmrReport->assertSee('94.50');
        $pmrReport->assertSee('12.3');
        $pmrReport->assertSee('GQA');
        $pmrReport->assertSee('210.010');
    }

    public function test_updating_shared_pile_data_synchronizes_both_amr_and_pmr_records(): void
    {
        $branch = Branch::firstOrCreate(['name' => 'South Cotabato']);
        $warehouse = Warehouse::create([
            'name' => 'WH-SYNC',
            'branch_id' => $branch->id,
        ]);
        $pile = Pile::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => '10',
            'number' => '10',
            'variety' => 'Old Variety',
            'purity' => 90.0,
            'mc' => 14.0,
            'quality' => 'poor',
            'aged_months' => 2,
            'volume_kg' => 5000.0,
        ]);

        AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'warehouse_name' => $warehouse->name,
            'pile_number' => '10',
            'variety' => 'Old Variety',
            'purity' => 90.0,
            'mc' => 14.0,
            'quality' => 'poor',
            'aged_months' => 2,
            'volume_kg' => 5000.0,
            'trial_number' => 1,
        ]);

        PmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'warehouse_name' => $warehouse->name,
            'pile_number' => '10',
            'variety' => 'Old Variety',
            'purity' => 90.0,
            'mc' => 14.0,
            'quality' => 'poor',
            'aged_months' => 2,
            'volume_kg' => 5000.0,
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

        // Pile master table must have the updated shared data
        $this->assertDatabaseHas('piles', [
            'id' => $pile->id,
            'variety' => 'Updated NSIC Rc 222',
            'purity' => '98.20',
            'mc' => '11.40',
            'quality' => 'premium',
            'aged_months' => 8,
            'volume_kg' => '15000.000',
        ]);

        // Both tables must now have the updated shared data
        $this->assertDatabaseHas('amr_records', [
            'pile_id' => $pile->id,
            'variety' => 'Updated NSIC Rc 222',
            'purity' => '98.20',
            'mc' => '11.40',
            'quality' => 'premium',
            'aged_months' => 8,
            'volume_kg' => '15000.000',
        ]);

        $this->assertDatabaseHas('pmr_records', [
            'pile_id' => $pile->id,
            'variety' => 'Updated NSIC Rc 222',
            'purity' => '98.20',
            'mc' => '11.40',
            'quality' => 'premium',
            'aged_months' => 8,
            'volume_kg' => '15000.000',
        ]);
    }

    public function test_one_master_pile_record_is_created_and_never_duplicated_between_amr_and_pmr(): void
    {
        $branch = Branch::create(['name' => 'Bukidnon']);
        $warehouse = Warehouse::create([
            'name' => 'Maramag Warehouse',
            'branch_id' => $branch->id,
        ]);

        $this->assertEquals(0, Pile::count());

        // 1. Submit AMR form
        $this->post(route('records.store'), [
            'form_type' => 'amr',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'new_pile_number' => '55',
            'variety' => 'NSIC Rc 160',
            'rice_millers' => 'Miller A',
            'purity' => '95.00',
            'mc' => '13.50',
            'quality' => 'good',
            'aged' => 4,
            'volume' => '8,000.000',
            'no_of_trial' => 1,
            'test_milling_date' => '2026-09-25',
            'palay_input' => '5000.00',
            'rice_recovery' => '3250.00',
        ])->assertRedirect();

        // Exactly 1 pile must exist
        $this->assertEquals(1, Pile::count());
        $pile = Pile::first();
        $this->assertEquals('55', $pile->pile_number);
        $this->assertEquals('NSIC Rc 160', $pile->variety);
        $this->assertEquals('95.00', (string) $pile->purity);
        $this->assertEquals(4, $pile->aged_months);
        $this->assertEquals('good', $pile->quality);
        $this->assertEquals('8000.000', (string) $pile->volume_kg);

        // 2. Submit PMR form using same Branch + Warehouse + Pile Number
        $this->post(route('records.store'), [
            'form_type' => 'pmr',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => '55',
            'variety' => 'NSIC Rc 160',
            'purity' => '95.00',
            'mc' => '13.50',
            'quality' => 'good',
            'aged' => 4,
            'volume' => '8,000.000',
            'no_of_trial' => 1,
            'test_milling_date' => '2026-09-25',
            'palay_input' => '5000.00',
            'rice_recovery' => '3200.00',
        ])->assertRedirect();

        // There MUST STILL be only 1 pile! Never duplicated
        $this->assertEquals(1, Pile::count());

        // Pile has 1 AMR record and 1 PMR record
        $this->assertEquals(1, $pile->amrRecords()->count());
        $this->assertEquals(1, $pile->pmrRecords()->count());
    }

    public function test_submitting_existing_trial_updates_in_place_without_duplicating(): void
    {
        $branch = Branch::create(['name' => 'Davao del Sur']);
        $warehouse = Warehouse::create([
            'name' => 'Digos WH',
            'branch_id' => $branch->id,
        ]);

        // Submit trial 1
        $this->post(route('records.store'), [
            'form_type' => 'amr',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'new_pile_number' => '7',
            'variety' => 'Rc 216',
            'rice_millers' => 'Miller 1',
            'purity' => '90.00',
            'mc' => '14.00',
            'quality' => 'fair',
            'aged' => 3,
            'volume' => '2,000.000',
            'no_of_trial' => 1,
            'test_milling_date' => '2026-09-25',
            'palay_input' => '1000.00',
            'rice_recovery' => '600.00',
        ])->assertRedirect();

        $this->assertEquals(1, Pile::count());
        $this->assertEquals(1, AmrRecord::count());
        $this->assertEquals(
            '600.00',
            (string) AmrRecord::first()->rice_recovery_kg,
        );

        // Re-submit trial 1 with updated recovery kg
        $this->post(route('records.store'), [
            'form_type' => 'amr',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => '7',
            'variety' => 'Rc 216',
            'rice_millers' => 'Miller 1',
            'purity' => '90.00',
            'mc' => '14.00',
            'quality' => 'fair',
            'aged' => 3,
            'volume' => '2,000.000',
            'no_of_trial' => 1,
            'test_milling_date' => '2026-09-25',
            'palay_input' => '1000.00',
            'rice_recovery' => '650.00',
        ])->assertRedirect();

        // Must still be only 1 pile and 1 AMR record
        $this->assertEquals(1, Pile::count());
        $this->assertEquals(1, AmrRecord::count());
        $this->assertEquals(
            '650.00',
            (string) AmrRecord::first()->rice_recovery_kg,
        );
    }

    public function test_database_unique_constraint_enforces_one_pile_per_branch_warehouse_and_pile_number(): void
    {
        $branch = Branch::create(['name' => 'General Santos']);
        $warehouse = Warehouse::create([
            'name' => 'GenSan WH',
            'branch_id' => $branch->id,
        ]);

        Pile::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => 'P-100',
            'number' => 'P-100',
        ]);

        $this->expectException(QueryException::class);

        // Attempting to insert a duplicate master pile with the same branch, warehouse, and pile number must fail at DB level
        DB::table('piles')->insert([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => 'P-100',
            'number' => 'P-100-alt',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
