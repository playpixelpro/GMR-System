<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GmrSummaryDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'staff',
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'is_active' => true,
        ]);
        $this->actingAs($this->user);
    }

    public function test_summary_dashboard_computes_gmr_and_applies_filters(): void
    {
        $branch = Branch::create(['name' => 'Included Branch']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Included Warehouse',
        ]);
        $otherBranch = Branch::create(['name' => 'Excluded Branch']);
        $otherWarehouse = Warehouse::create([
            'branch_id' => $otherBranch->id,
            'name' => 'Excluded Warehouse',
        ]);

        $includedPile = $this->createPile($warehouse, '1', 5000);
        $excludedPile = $this->createPile($otherWarehouse, '2', 2500);

        $this->createAmrRecord($includedPile, 61.53);
        $this->createPmrRecord($includedPile, 62.22);
        $this->createAmrRecord($excludedPile, 63.0);
        $this->createPmrRecord($excludedPile, 64.0);

        $response = $this->get(
            route('gmr.summary', [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
            ]),
        );

        $response
            ->assertOk()
            ->assertSee('Total Piles')
            ->assertDontSee('Total Warehouses')
            ->assertSee('61.88%')
            ->assertSee('61.53%–62.22%')
            ->assertSee('GMR = (AMR + PMR) / 2')
            ->assertSee(
                'data-gmr-open="gmr-breakdown-'.$includedPile->id.'"',
                false,
            )
            ->assertSee('Included Warehouse')
            ->assertDontSee('Excluded Warehouse')
            ->assertDontSee('EMR vs GMR by Pile');
    }

    public function test_summary_dashboard_flags_review_conditions(): void
    {
        $branch = Branch::create(['name' => 'Review Branch']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Review Warehouse',
        ]);
        $pile = $this->createPile($warehouse, '1', 5000);

        $this->createAmrRecord($pile, 58.0);
        $this->createPmrRecord($pile, 60.0);

        $this->get(route('gmr.summary'))
            ->assertOk()
            ->assertSee('Total Warehouses')
            ->assertSee('Re-establish')
            ->assertSee('GMR is 60% or lower');
    }

    public function test_summary_dashboard_renders_excel_export_button(): void
    {
        $this->get(route('gmr.summary'))
            ->assertOk()
            ->assertSee(route('gmr.summary.export.excel'), false)
            ->assertSee('Excel Export');
    }

    public function test_summary_dashboard_exports_excel(): void
    {
        $branch = Branch::create(['name' => 'Export Branch']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Export Warehouse',
        ]);
        $pile = $this->createPile($warehouse, '101', 50000);
        $this->createAmrRecord($pile, 65.0);
        $this->createPmrRecord($pile, 66.0);

        $response = $this->get(route('gmr.summary.export.excel', [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=GMR_Summary_', (string) $response->headers->get('Content-Disposition'));
    }

    private function createPile(
        Warehouse $warehouse,
        string $number,
        int $volume,
    ): Pile {
        return Pile::create([
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => $number,
            'number' => $number,
            'volume_kg' => $volume,
        ]);
    }

    private function createAmrRecord(Pile $pile, float $rate): void
    {
        for ($trial = 1; $trial <= 3; $trial++) {
            AmrRecord::create([
                'pile_id' => $pile->id,
                'warehouse_name' => $pile->warehouse->name,
                'pile_number' => $pile->pile_number,
                'trial_number' => $trial,
                'conduct_number' => 1,
                'rice_millers' => 'Test Miller',
                'palay_input_kg' => 1000,
                'rice_recovery_kg' => $rate * 10,
                'milling_recovery' => $rate,
                'status' => 'RECOMMENDED',
                'action' => 'RECOMMEND',
                'is_locked' => true,
                'included_in_computation' => true,
            ]);
        }
    }

    private function createPmrRecord(Pile $pile, float $rate): void
    {
        for ($trial = 1; $trial <= 3; $trial++) {
            PmrRecord::create([
                'pile_id' => $pile->id,
                'warehouse_name' => $pile->warehouse->name,
                'pile_number' => $pile->pile_number,
                'sample_number' => $trial,
                'trial_number' => $trial,
                'conduct_number' => 1,
                'palay_input_kg' => 1000,
                'rice_recovery_kg' => $rate * 10,
                'milling_recovery' => $rate,
                'status' => 'RECOMMENDED',
                'action' => 'RECOMMEND',
                'is_locked' => true,
                'included_in_computation' => true,
            ]);
        }
    }
}
