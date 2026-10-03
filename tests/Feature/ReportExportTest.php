<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
        $response->assertSee('Note: Volume values shown are before test milling.');
        $response->assertSee('Volume Before Test Milling');
    }

    public function test_pmr_report_view_renders_excel_and_pdf_buttons(): void
    {
        $response = $this->actingAs($this->user)->get(route('pmr.index'));

        $response->assertOk();
        $response->assertSee(route('pmr.export.excel'));
        $response->assertSee(route('pmr.export.pdf'));
        $response->assertSee('Excel Export');
        $response->assertSee('PDF Download');
        $response->assertSee('Note: Volume values shown are before test milling.');
        $response->assertSee('VOLUME BEFORE TEST MILLING');
    }

    public function test_amr_export_excel_returns_valid_xlsx_stream(): void
    {
        $branch = Branch::create(['name' => 'Isabela Branch']);
        $warehouse = Warehouse::create(['name' => 'Santiago GID', 'branch_id' => $branch->id]);
        $pile = Pile::create([
            'number' => 'Pile 1',
            'warehouse_id' => $warehouse->id,
            'volume_kg' => 60000,
            'test_milling_volume_kg' => 10000,
        ]);

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

        $tempFile = tempnam(sys_get_temp_dir(), 'amr_xlsx_');
        file_put_contents($tempFile, $response->streamedContent());
        $spreadsheet = IOFactory::load($tempFile);
        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame('Volume Before Test Milling (50kg bags)', $sheet->getCell('J5')->getValue());
        $this->assertSame('Volume After Test Milling (50kg bags)', $sheet->getCell('K5')->getValue());
        $this->assertSame('Rec Rate (%)', $sheet->getCell('P5')->getValue());
        $this->assertSame('Mean (%)', $sheet->getCell('Q5')->getValue());
        $this->assertSame('AMR (%)', $sheet->getCell('R5')->getValue());
        $this->assertEquals(1200, $sheet->getCell('J6')->getValue());
        $this->assertEquals(1000, $sheet->getCell('K6')->getValue());
        $this->assertEquals(65, $sheet->getCell('P6')->getValue());
        $this->assertStringNotContainsString('%', (string) $sheet->getCell('P6')->getValue());
        $this->assertEquals(65, $sheet->getCell('Q6')->getValue());
        $this->assertStringNotContainsString('%', (string) $sheet->getCell('Q6')->getValue());
        $this->assertEquals(65, $sheet->getCell('R6')->getValue());
        $this->assertStringNotContainsString('%', (string) $sheet->getCell('R6')->getValue());
        unlink($tempFile);
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
        $pile = Pile::create([
            'number' => 'Pile 2',
            'warehouse_id' => $warehouse->id,
            'volume_kg' => 45000,
            'test_milling_volume_kg' => 5000,
        ]);

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

        $tempFile = tempnam(sys_get_temp_dir(), 'pmr_xlsx_');
        file_put_contents($tempFile, $response->streamedContent());
        $spreadsheet = IOFactory::load($tempFile);
        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame('Volume Before Test Milling (50kg bags)', $sheet->getCell('J5')->getValue());
        $this->assertSame('Volume After Test Milling (50kg bags)', $sheet->getCell('K5')->getValue());
        $this->assertSame('Recovery Rate (%)', $sheet->getCell('M5')->getValue());
        $this->assertSame('Mean (%)', $sheet->getCell('N5')->getValue());
        $this->assertSame('PMR (%)', $sheet->getCell('O5')->getValue());
        $this->assertEquals(900, $sheet->getCell('J6')->getValue());
        $this->assertEquals(800, $sheet->getCell('K6')->getValue());
        $this->assertEquals(65.5, $sheet->getCell('M6')->getValue());
        $this->assertStringNotContainsString('%', (string) $sheet->getCell('M6')->getValue());
        $this->assertEquals(65.5, $sheet->getCell('N6')->getValue());
        $this->assertStringNotContainsString('%', (string) $sheet->getCell('N6')->getValue());
        $this->assertEquals(65.5, $sheet->getCell('O6')->getValue());
        $this->assertStringNotContainsString('%', (string) $sheet->getCell('O6')->getValue());
        unlink($tempFile);
    }

    public function test_emr_export_excel_includes_volumes_before_and_after_test_milling(): void
    {
        $branch = Branch::create(['name' => 'EMR Export Branch']);
        $warehouse = Warehouse::create([
            'name' => 'EMR Export Warehouse',
            'branch_id' => $branch->id,
        ]);
        Pile::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'number' => 'EMR-1',
            'pile_number' => 'EMR-1',
            'variety' => 'PD',
            'volume_kg' => 60000,
            'test_milling_volume_kg' => 10000,
        ]);
        $rmec = User::factory()->create([
            'role' => 'RMEC',
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($rmec)->get(route('emr.export.excel'));
        $response->assertOk();

        $tempFile = tempnam(sys_get_temp_dir(), 'emr_xlsx_');
        file_put_contents($tempFile, $response->streamedContent());
        $spreadsheet = IOFactory::load($tempFile);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Volume Before Test Milling (net kg)', $sheet->getCell('G5')->getValue());
        $this->assertSame('Volume Before Test Milling (50kg bags)', $sheet->getCell('H5')->getValue());
        $this->assertSame('Volume After Test Milling (50kg bags)', $sheet->getCell('I5')->getValue());
        $this->assertSame(60000.0, (float) $sheet->getCell('G6')->getValue());
        $this->assertSame(1200.0, (float) $sheet->getCell('H6')->getValue());
        $this->assertSame(1000.0, (float) $sheet->getCell('I6')->getValue());

        unlink($tempFile);
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

    public function test_pdf_views_do_not_contain_percent_symbol_in_rate_cells(): void
    {
        $branch = Branch::create(['name' => 'Isabela Branch']);
        $warehouse = Warehouse::create(['name' => 'Santiago GID', 'branch_id' => $branch->id]);
        $pile = Pile::create(['number' => 'Pile 1', 'warehouse_id' => $warehouse->id, 'volume_kg' => 60000]);

        $amr = AmrRecord::create([
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

        $pmr = PmrRecord::create([
            'pile_id' => $pile->id,
            'warehouse_name' => $warehouse->name,
            'pile_number' => $pile->number,
            'trial_number' => 1,
            'milling_recovery' => 65.5,
            'status' => 'RECOMMENDED',
            'included_in_computation' => true,
        ]);

        $amrGroups = collect([
            [
                'records' => collect([$amr]),
                'pile' => $pile,
                'warehouse_name' => $warehouse->name,
                'branch_name' => $branch->name,
                'pile_number' => $pile->number,
                'variety' => 'V1',
                'purity' => 95,
                'mc' => 14,
                'quality' => 'good',
                'aged_months' => 2,
                'volume_kg' => 60000,
                'rice_millers' => 'Golden Rice Mill',
            ],
        ]);

        $amrHtml = view('reports.pdf.amr', [
            'recordGroups' => $amrGroups,
            'filterBranch' => null,
            'filterWarehouse' => null,
            'generatedAt' => 'October 02, 2026 12:00 PM',
        ])->render();

        $this->assertStringContainsString('Rec Rate (%)', $amrHtml);
        $this->assertStringContainsString('Mean (%)', $amrHtml);
        $this->assertStringContainsString('AMR (%)', $amrHtml);
        $this->assertStringContainsString('65.00', $amrHtml);
        $this->assertStringNotContainsString('65.00%', $amrHtml);

        $pmrGroups = collect([
            [
                'records' => collect([$pmr]),
                'pile' => $pile,
                'warehouse_name' => $warehouse->name,
                'branch_name' => $branch->name,
                'pile_number' => $pile->number,
                'variety' => 'V1',
                'purity' => 95,
                'mc' => 14,
                'quality' => 'good',
                'aged_months' => 2,
                'volume_kg' => 60000,
            ],
        ]);

        $pmrHtml = view('reports.pdf.pmr', [
            'recordGroups' => $pmrGroups,
            'filterBranch' => null,
            'filterWarehouse' => null,
            'generatedAt' => 'October 02, 2026 12:00 PM',
        ])->render();

        $this->assertStringContainsString('Recovery Rate (%)', $pmrHtml);
        $this->assertStringContainsString('Mean (%)', $pmrHtml);
        $this->assertStringContainsString('PMR (%)', $pmrHtml);
        $this->assertStringContainsString('65.50', $pmrHtml);
        $this->assertStringNotContainsString('65.50%', $pmrHtml);
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
