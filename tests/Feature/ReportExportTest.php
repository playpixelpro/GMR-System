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

class ReportExportTest extends TestCase
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
    }

    public function test_unauthenticated_users_are_redirected_to_login_for_exports(): void
    {
        $this->get(route('amr.export.excel'))->assertRedirect(route('login'));
        $this->get(route('amr.export.pdf'))->assertRedirect(route('login'));
        $this->get(route('pmr.export.excel'))->assertRedirect(route('login'));
        $this->get(route('pmr.export.pdf'))->assertRedirect(route('login'));
    }

    public function test_amr_report_view_renders_excel_and_pdf_buttons(): void
    {
        $response = $this->actingAs($this->user)->get(route('amr.index'));

        $response->assertOk();
        $response->assertSee(route('amr.export.excel'));
        $response->assertSee(route('amr.export.pdf'));
        $response->assertSee('Excel Export');
        $response->assertSee('PDF Download');
    }

    public function test_pmr_report_view_renders_excel_and_pdf_buttons(): void
    {
        $response = $this->actingAs($this->user)->get(route('pmr.index'));

        $response->assertOk();
        $response->assertSee(route('pmr.export.excel'));
        $response->assertSee(route('pmr.export.pdf'));
        $response->assertSee('Excel Export');
        $response->assertSee('PDF Download');
    }

    public function test_amr_export_excel_returns_valid_xlsx_stream(): void
    {
        $branch = Branch::create(['name' => 'Isabela Branch']);
        $warehouse = Warehouse::create(['name' => 'Santiago GID', 'branch_id' => $branch->id]);
        $pile = Pile::create(['number' => 'Pile 1', 'warehouse_id' => $warehouse->id, 'volume_kg' => 60000]);

        AmrRecord::create([
            'pile_id' => $pile->id,
            'warehouse_name' => $warehouse->name,
            'pile_number' => $pile->number,
            'trial_number' => 1,
            'palay_input_kg' => 1000,
            'rice_recovery_kg' => 650,
            'rice_millers' => 'Golden Rice Mill',
            'status' => 'RECOMMENDED',
            'included_in_computation' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('amr.export.excel'));

        $response->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('AMR_Report_', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_amr_export_pdf_returns_pdf_file(): void
    {
        $branch = Branch::create(['name' => 'Isabela Branch']);
        $warehouse = Warehouse::create(['name' => 'Santiago GID', 'branch_id' => $branch->id]);
        $pile = Pile::create(['number' => 'Pile 1', 'warehouse_id' => $warehouse->id, 'volume_kg' => 60000]);

        AmrRecord::create([
            'pile_id' => $pile->id,
            'warehouse_name' => $warehouse->name,
            'pile_number' => $pile->number,
            'trial_number' => 1,
            'palay_input_kg' => 1000,
            'rice_recovery_kg' => 650,
            'rice_millers' => 'Golden Rice Mill',
            'status' => 'RECOMMENDED',
            'included_in_computation' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('amr.export.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('AMR_Report_', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_pmr_export_excel_returns_valid_xlsx_stream(): void
    {
        $branch = Branch::create(['name' => 'Isabela Branch']);
        $warehouse = Warehouse::create(['name' => 'Santiago GID', 'branch_id' => $branch->id]);
        $pile = Pile::create(['number' => 'Pile 2', 'warehouse_id' => $warehouse->id, 'volume_kg' => 45000]);

        PmrRecord::create([
            'pile_id' => $pile->id,
            'warehouse_name' => $warehouse->name,
            'pile_number' => $pile->number,
            'trial_number' => 1,
            'milling_recovery' => 65.5,
            'status' => 'RECOMMENDED',
            'included_in_computation' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('pmr.export.excel'));

        $response->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('PMR_Report_', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_pmr_export_pdf_returns_pdf_file(): void
    {
        $branch = Branch::create(['name' => 'Isabela Branch']);
        $warehouse = Warehouse::create(['name' => 'Santiago GID', 'branch_id' => $branch->id]);
        $pile = Pile::create(['number' => 'Pile 2', 'warehouse_id' => $warehouse->id, 'volume_kg' => 45000]);

        PmrRecord::create([
            'pile_id' => $pile->id,
            'warehouse_name' => $warehouse->name,
            'pile_number' => $pile->number,
            'trial_number' => 1,
            'milling_recovery' => 65.5,
            'status' => 'RECOMMENDED',
            'included_in_computation' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('pmr.export.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('PMR_Report_', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_exports_with_branch_and_warehouse_filters(): void
    {
        $branch = Branch::create(['name' => 'Isabela Branch']);
        $warehouse = Warehouse::create(['name' => 'Santiago GID', 'branch_id' => $branch->id]);

        $query = [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
        ];

        $this->actingAs($this->user)->get(route('amr.export.excel', $query))->assertOk();
        $this->actingAs($this->user)->get(route('amr.export.pdf', $query))->assertOk();
        $this->actingAs($this->user)->get(route('pmr.export.excel', $query))->assertOk();
        $this->actingAs($this->user)->get(route('pmr.export.pdf', $query))->assertOk();
    }
}
