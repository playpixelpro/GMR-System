<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\GmrReportConfiguration;
use App\Models\GmrReportSignatory;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\GmrReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class GmrReportConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $rmecUser;

    private User $staffUser;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rmecUser = User::factory()->create([
            'role' => 'RMEC',
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'is_active' => true,
        ]);

        $this->staffUser = User::factory()->create([
            'role' => 'STAFF',
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'is_active' => true,
        ]);
    }

    public function test_guests_cannot_access_gmr_configuration(): void
    {
        $this->get(route('gmr.config.edit'))->assertRedirect(route('login'));
    }

    public function test_staff_cannot_access_gmr_configuration(): void
    {
        $this->actingAs($this->staffUser)
            ->get(route('gmr.config.edit'))
            ->assertForbidden();
    }

    public function test_rmec_and_admin_can_access_gmr_configuration(): void
    {
        $this->actingAs($this->rmecUser)
            ->get(route('gmr.config.edit'))
            ->assertOk()
            ->assertSee('GMR Report Configuration')
            ->assertSee('Report Table Columns')
            ->assertSee('Report heading')
            ->assertSee('name="column_labels[volume_before_test_milling]"', false)
            ->assertSee('Volume in Bags Before Test Milling')
            ->assertSee('Volume in Bags After Test Milling')
            ->assertSee('Paper Size')
            ->assertSee('Signatories Management');

        $this->actingAs($this->adminUser)
            ->get(route('gmr.config.edit'))
            ->assertOk()
            ->assertSee('GMR Report Configuration');
    }

    public function test_gmr_report_configuration_is_in_settings_reports_submenu_and_not_in_reports_menu(): void
    {
        $response = $this->actingAs($this->adminUser)->get(
            route('gmr.config.edit'),
        );

        $response->assertOk();

        $content = $response->getContent();

        // Ensure Reports dropdown does not contain GMR Report Configuration
        $reportsDropdownHtml = str($content)
            ->between('id="reports-dropdown"', 'id="settings-dropdown"')
            ->toString();
        $this->assertStringNotContainsString(
            'GMR Report Configuration',
            $reportsDropdownHtml,
        );

        // Ensure Settings dropdown contains Reports submenu and GMR Report Configuration link
        $settingsDropdownHtml = str($content)
            ->after('id="settings-dropdown"')
            ->before('</aside>')
            ->toString();
        $this->assertStringContainsString('Reports', $settingsDropdownHtml);
        $this->assertStringContainsString('GMR Report', $settingsDropdownHtml);
        $this->assertStringContainsString(
            route('gmr.config.edit'),
            $settingsDropdownHtml,
        );

        // Ensure nested reports dropdown is open when active on gmr.config.edit
        $this->assertStringContainsString(
            'id="settings-reports-dropdown"',
            $settingsDropdownHtml,
        );
        $this->assertStringContainsString(
            'dropdown relative open',
            $settingsDropdownHtml,
        );
    }

    public function test_signatory_modals_render_as_native_dialogs(): void
    {
        $response = $this->actingAs($this->rmecUser)->get(
            route('gmr.config.edit'),
        );

        $response
            ->assertOk()
            ->assertSee('Add Signatory')
            ->assertSee('id="modal-add-signatory"', false)
            ->assertSee('id="modal-edit-signatory"', false)
            ->assertSee('openEditModal', false);

        // The modals must NOT use FlyonUI's `.modal` class, which keeps a native
        // <dialog> at opacity:0 / pointer-events:none and hides it after showModal().
        $response
            ->assertDontSee('id="modal-add-signatory" class="modal"', false)
            ->assertDontSee('id="modal-edit-signatory" class="modal"', false)
            ->assertDontSee('modal-box', false);
    }

    public function test_default_configuration_and_signatories_are_seeded(): void
    {
        $config = GmrReportConfiguration::current();

        $this->assertEquals('REPORT ON PRE-MILLING ACTIVITY', $config->title);
        $this->assertEquals(
            'QUALITY AND QUANTITY, AMR, PMR AND EMR/GMR',
            $config->subtitle,
        );
        $this->assertEquals('Region XII', $config->region_text);
        $this->assertEquals('Long Bond', $config->paper_size);
        $this->assertEquals('portrait', $config->orientation);

        $signatories = GmrReportSignatory::query()->active()->get();
        $this->assertNotEmpty($signatories);
        $this->assertTrue($signatories->contains('name', 'DINDO O. QUITOR'));
        $this->assertTrue(
            $signatories->contains('name', 'ANGELICA M. PELLIEN'),
        );
    }

    public function test_rmec_can_update_paper_size_orientation_margins_and_headers(): void
    {
        $response = $this->actingAs($this->rmecUser)->put(
            route('gmr.config.update'),
            [
                'title' => 'UPDATED REPORT ON PRE-MILLING ACTIVITY',
                'subtitle' => 'UPDATED SUBTITLE',
                'region_text' => 'Region XII Central Mindanao',
                'branch_text' => 'South Cotabato Branch',
                'paper_size' => 'A4',
                'custom_width' => null,
                'custom_height' => null,
                'custom_unit' => 'in',
                'orientation' => 'landscape',
                'margin_top' => 0.75,
                'margin_right' => 0.75,
                'margin_bottom' => 0.75,
                'margin_left' => 0.75,
                'margin_unit' => 'in',
                'visible_columns' => [
                    'volume_before_test_milling',
                    'volume_after_test_milling',
                ],
                'column_labels' => [
                    ...array_map(
                        fn (array $column): string => $column['label'],
                        GmrReportConfiguration::availableReportColumns(),
                    ),
                    'volume_before_test_milling' => 'Pre-test volume (bags)',
                    'volume_after_test_milling' => 'Post-test volume (bags)',
                ],
            ],
        );

        $response->assertRedirect(route('gmr.config.edit'));
        $response->assertSessionHas('status');

        $config = GmrReportConfiguration::current();
        $this->assertEquals(
            'UPDATED REPORT ON PRE-MILLING ACTIVITY',
            $config->title,
        );
        $this->assertEquals('A4', $config->paper_size);
        $this->assertEquals('landscape', $config->orientation);
        $this->assertEquals(0.75, $config->margin_top);
        $this->assertSame(
            ['volume_before_test_milling', 'volume_after_test_milling'],
            $config->getVisibleReportColumns(),
        );
        $this->assertSame(
            'Pre-test volume (bags)',
            $config->getReportColumnLabels()['volume_before_test_milling'],
        );
    }

    public function test_rmec_cannot_save_unrecognized_gmr_report_columns(): void
    {
        $config = GmrReportConfiguration::current();
        $requestData = $config->only([
            'title',
            'subtitle',
            'region_text',
            'branch_text',
            'paper_size',
            'custom_width',
            'custom_height',
            'custom_unit',
            'orientation',
            'margin_top',
            'margin_right',
            'margin_bottom',
            'margin_left',
            'margin_unit',
        ]);
        $requestData['visible_columns'] = ['unknown_column'];
        $requestData['column_labels'] = $config->getReportColumnLabels();

        $this->actingAs($this->rmecUser)
            ->put(route('gmr.config.update'), $requestData)
            ->assertSessionHasErrors('visible_columns.0');

        $this->assertSame(
            array_keys(GmrReportConfiguration::availableReportColumns()),
            $config->fresh()->getVisibleReportColumns(),
        );
    }

    public function test_rmec_cannot_save_unrecognized_gmr_report_column_labels(): void
    {
        $config = GmrReportConfiguration::current();
        $requestData = $config->only([
            'title',
            'subtitle',
            'region_text',
            'branch_text',
            'paper_size',
            'custom_width',
            'custom_height',
            'custom_unit',
            'orientation',
            'margin_top',
            'margin_right',
            'margin_bottom',
            'margin_left',
            'margin_unit',
        ]);
        $requestData['visible_columns'] = array_keys(
            GmrReportConfiguration::availableReportColumns(),
        );
        $requestData['column_labels'] = [
            ...$config->getReportColumnLabels(),
            'unknown_column' => 'Unknown heading',
        ];

        $this->actingAs($this->rmecUser)
            ->put(route('gmr.config.update'), $requestData)
            ->assertSessionHasErrors('column_labels');
    }

    public function test_rmec_can_save_custom_paper_dimensions(): void
    {
        $this->actingAs($this->rmecUser)->put(route('gmr.config.update'), [
            'title' => 'REPORT ON PRE-MILLING ACTIVITY',
            'subtitle' => 'QUALITY AND QUANTITY, AMR, PMR AND EMR/GMR',
            'region_text' => 'Region XII',
            'branch_text' => 'North Cotabato Branch',
            'paper_size' => 'Custom',
            'custom_width' => 8.5,
            'custom_height' => 12.0,
            'custom_unit' => 'in',
            'orientation' => 'portrait',
            'margin_top' => 0.5,
            'margin_right' => 0.5,
            'margin_bottom' => 0.5,
            'margin_left' => 0.5,
            'margin_unit' => 'in',
            'visible_columns' => array_keys(
                GmrReportConfiguration::availableReportColumns(),
            ),
            'column_labels' => array_map(
                fn (array $column): string => $column['label'],
                GmrReportConfiguration::availableReportColumns(),
            ),
        ]);

        $config = GmrReportConfiguration::current();
        $this->assertEquals('Custom', $config->paper_size);
        $this->assertEquals(8.5, $config->custom_width);
        $this->assertEquals(12.0, $config->custom_height);
        $this->assertEquals('8.5in 12in portrait', $config->getCssPageSize());
    }

    public function test_rmec_can_manage_signatories(): void
    {
        // Add new signatory
        $addResponse = $this->actingAs($this->rmecUser)->post(
            route('gmr.config.signatories.store'),
            [
                'name' => 'JUAN DELA CRUZ',
                'position' => 'Senior Quality Officer',
                'role_group' => 'member',
                'display_order' => 8,
                'is_active' => '1',
            ],
        );

        $addResponse->assertRedirect(route('gmr.config.edit'));
        $signatory = GmrReportSignatory::where(
            'name',
            'JUAN DELA CRUZ',
        )->first();
        $this->assertNotNull($signatory);
        $this->assertEquals('Senior Quality Officer', $signatory->position);
        $this->assertTrue($signatory->is_active);

        // Update signatory
        $updateResponse = $this->actingAs($this->rmecUser)->put(
            route('gmr.config.signatories.update', $signatory),
            [
                'name' => 'JUAN DELA CRUZ JR.',
                'position' => 'Supervising Quality Officer',
                'role_group' => 'member',
                'display_order' => 8,
                'is_active' => '1',
            ],
        );
        $updateResponse->assertRedirect(route('gmr.config.edit'));
        $this->assertEquals('JUAN DELA CRUZ JR.', $signatory->fresh()->name);

        // Toggle active/inactive
        $this->actingAs($this->rmecUser)->patch(
            route('gmr.config.signatories.toggle', $signatory),
        );
        $this->assertFalse($signatory->fresh()->is_active);

        // Delete signatory
        $deleteResponse = $this->actingAs($this->rmecUser)->delete(
            route('gmr.config.signatories.destroy', $signatory),
        );
        $deleteResponse->assertRedirect(route('gmr.config.edit'));
        $this->assertDatabaseMissing('gmr_report_signatories', [
            'id' => $signatory->id,
        ]);
    }

    public function test_rmec_can_preview_report(): void
    {
        $this->actingAs($this->rmecUser)
            ->get(route('gmr.config.preview'))
            ->assertOk()
            ->assertSee('REPORT ON PRE-MILLING ACTIVITY')
            ->assertSee('Preview Mode')
            ->assertSee('PMR(%)')
            ->assertSee('AMR(%)')
            ->assertSee('EMR(%)')
            ->assertSee('GMR(%)')
            ->assertSee('DINDO O. QUITOR')
            ->assertSee('MARIETES E. DISTOR');
    }

    public function test_gmr_summary_displays_include_in_report_checkboxes_and_print_button(): void
    {
        [$branch, $warehouse] = $this->createHierarchy();
        $pile = $this->createPileWithRates($warehouse, '1', 5000, 61.53, 62.22);

        $response = $this->actingAs($this->rmecUser)->get(route('gmr.summary'));

        $response
            ->assertOk()
            ->assertSee('Select')
            ->assertSee('Print Report')
            ->assertSee('name="selected_piles[]"', false)
            ->assertSee('value="'.$pile->id.'"', false);
    }

    public function test_print_without_selection_redirects_with_error(): void
    {
        $this->actingAs($this->rmecUser)
            ->post(route('gmr.report.print'), ['selected_piles' => []])
            ->assertRedirect(route('gmr.summary'))
            ->assertSessionHas('error');
    }

    public function test_print_with_selected_records_generates_print_ready_report(): void
    {
        [$branch, $warehouse] = $this->createHierarchy();
        $pile1 = $this->createPileWithRates(
            $warehouse,
            '1',
            11522 * 50,
            61.53,
            62.22,
        );
        $pile2 = $this->createPileWithRates(
            $warehouse,
            '2',
            12259 * 50,
            62.66,
            62.77,
        );

        $response = $this->actingAs($this->rmecUser)->post(
            route('gmr.report.print'),
            [
                'selected_piles' => [$pile1->id, $pile2->id],
            ],
        );

        $response
            ->assertOk()
            ->assertSee('REPORT ON PRE-MILLING ACTIVITY')
            ->assertSee('Print Report')
            ->assertSee('Export Excel')
            ->assertSee('PMR(%)')
            ->assertSee('AMR(%)')
            ->assertSee('EMR(%)')
            ->assertSee('GMR(%)')
            ->assertSee('GID#2, MLANG BS')
            ->assertSee('11,522', false)
            ->assertSee('12,259', false)
            ->assertSee('61.53')
            ->assertSee('62.22')
            ->assertSee('62.66')
            ->assertSee('62.77')
            ->assertDontSee('61.53%')
            ->assertDontSee('62.22%')
            ->assertSee('DINDO O. QUITOR')
            ->assertSee('Regional Economist')
            ->assertSee('ANGELICA M. PELLIEN');

        // Selection does not alter any database data
        $this->assertDatabaseHas('piles', [
            'id' => $pile1->id,
            'pile_number' => '1',
        ]);
        $this->assertDatabaseHas('piles', [
            'id' => $pile2->id,
            'pile_number' => '2',
        ]);
    }

    public function test_print_report_appends_branch_suffix_to_configured_branch_name(): void
    {
        [, $warehouse] = $this->createHierarchy();
        $pile = $this->createPileWithRates(
            $warehouse,
            '1',
            11522 * 50,
            61.53,
            62.22,
        );
        $config = GmrReportConfiguration::current();
        $config->update(['branch_text' => 'North Cotabato']);

        $this->actingAs($this->rmecUser)
            ->post(route('gmr.report.print'), [
                'selected_piles' => [$pile->id],
            ])
            ->assertOk()
            ->assertSee('North Cotabato Branch')
            ->assertDontSee('North Cotabato Branch Branch');
    }

    public function test_print_report_displays_both_test_milling_volumes_and_only_visible_columns(): void
    {
        [, $warehouse] = $this->createHierarchy();
        $pile = $this->createPileWithRates(
            $warehouse,
            '1',
            11522 * 50,
            61.53,
            62.22,
        );
        $pile->update(['test_milling_volume_kg' => 10000 * 50]);

        GmrReportConfiguration::current()->update([
            'visible_columns' => [
                'volume_before_test_milling',
                'volume_after_test_milling',
            ],
            'column_labels' => [
                'volume_before_test_milling' => 'Before Milling Bags',
                'volume_after_test_milling' => 'Remaining Bags',
            ],
        ]);

        $this->actingAs($this->rmecUser)
            ->post(route('gmr.report.print'), [
                'selected_piles' => [$pile->id],
            ])
            ->assertOk()
            ->assertSee('Volume before test milling is the pile’s original volume')
            ->assertSee('Before Milling Bags')
            ->assertSee('Remaining Bags')
            ->assertSee('11,522')
            ->assertSee('1,522')
            ->assertDontSee('Warehouse')
            ->assertDontSee('GMR(%)');
    }

    public function test_print_with_excel_parameter_downloads_xlsx_file(): void
    {
        [$branch, $warehouse] = $this->createHierarchy();
        $pile1 = $this->createPileWithRates(
            $warehouse,
            '1',
            11522 * 50,
            61.53,
            62.22,
        );

        $response = $this->actingAs($this->rmecUser)->post(
            route('gmr.report.print'),
            [
                'selected_piles' => [$pile1->id],
                'excel' => 1,
            ],
        );

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type'),
        );
        $this->assertStringContainsString(
            'GMR_Report_',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringContainsString(
            '.xlsx',
            (string) $response->headers->get('content-disposition'),
        );

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'gmr',
            'action' => 'GMR_EXCEL_EXPORTED',
        ]);
    }

    public function test_gmr_report_service_export_excel_produces_valid_spreadsheet(): void
    {
        [$branch, $warehouse] = $this->createHierarchy();
        $pile1 = $this->createPileWithRates(
            $warehouse,
            '1',
            11522 * 50,
            61.53,
            62.22,
        );
        $pile1->update(['test_milling_volume_kg' => 10000 * 50]);

        $service = app(GmrReportService::class);
        $rows = $service->getRowsForPiles([$pile1->id]);
        $config = $service->getConfiguration();
        $config->branch_text = 'North Cotabato';
        $columnLabels = array_map(
            fn (array $column): string => $column['label'],
            GmrReportConfiguration::availableReportColumns(),
        );
        $columnLabels['volume_before_test_milling'] = 'Before Milling Bags';
        $config->column_labels = $columnLabels;
        $signatories = $service->getActiveSignatories();

        $response = $service->exportExcel(
            $rows,
            $config,
            $signatories,
            $branch->name,
        );

        $tempFile = tempnam(sys_get_temp_dir(), 'gmr_test_xlsx_');
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        file_put_contents($tempFile, $content);

        $spreadsheet = IOFactory::load($tempFile);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame(
            'REPORT ON PRE-MILLING ACTIVITY',
            $sheet->getCell('A1')->getValue(),
        );
        $this->assertSame('North Cotabato Branch', $sheet->getCell('A4')->getValue());
        $this->assertSame('Warehouse', $sheet->getCell('A6')->getValue());
        $this->assertSame('Before Milling Bags', $sheet->getCell('C6')->getValue());
        $this->assertSame('Volume in Bags After Test Milling', $sheet->getCell('D6')->getValue());
        $this->assertSame('PMR(%)', $sheet->getCell('F6')->getValue());
        $this->assertSame('AMR(%)', $sheet->getCell('G6')->getValue());
        $this->assertSame('EMR(%)', $sheet->getCell('H6')->getValue());
        $this->assertSame('GMR(%)', $sheet->getCell('I6')->getValue());
        $this->assertSame('GID#2, MLANG BS', $sheet->getCell('A7')->getValue());
        $this->assertSame('1', (string) $sheet->getCell('B7')->getValue());
        $this->assertEquals(11522, $sheet->getCell('C7')->getValue());
        $this->assertEquals(1522, $sheet->getCell('D7')->getValue());
        $this->assertEquals(62.22, $sheet->getCell('F7')->getValue());
        $this->assertEquals(61.53, $sheet->getCell('G7')->getValue());
        $this->assertEquals(61.88, $sheet->getCell('I7')->getValue());
        $this->assertStringNotContainsString(
            '%',
            (string) $sheet->getCell('F7')->getValue(),
        );
        $this->assertStringNotContainsString(
            '%',
            (string) $sheet->getCell('G7')->getValue(),
        );
        $this->assertStringNotContainsString(
            '%',
            (string) $sheet->getCell('H7')->getValue(),
        );
        $this->assertStringNotContainsString(
            '%',
            (string) $sheet->getCell('I7')->getValue(),
        );
        unlink($tempFile);
    }

    public function test_gmr_report_excel_export_includes_only_configured_columns(): void
    {
        [, $warehouse] = $this->createHierarchy();
        $pile = $this->createPileWithRates(
            $warehouse,
            '1',
            11522 * 50,
            61.53,
            62.22,
        );
        $pile->update(['test_milling_volume_kg' => 10000 * 50]);

        $service = app(GmrReportService::class);
        $config = $service->getConfiguration();
        $config->visible_columns = [
            'warehouse',
            'volume_after_test_milling',
        ];
        $response = $service->exportExcel(
            $service->getRowsForPiles([$pile->id]),
            $config,
            $service->getActiveSignatories(),
        );

        $tempFile = tempnam(sys_get_temp_dir(), 'gmr_hidden_columns_');
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        file_put_contents($tempFile, $content);

        $spreadsheet = IOFactory::load($tempFile);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Warehouse', $sheet->getCell('A6')->getValue());
        $this->assertSame(
            'Volume in Bags After Test Milling',
            $sheet->getCell('B6')->getValue(),
        );
        $this->assertSame(1522.0, $sheet->getCell('B7')->getValue());
        $this->assertSame('B', $sheet->getHighestColumn());

        unlink($tempFile);
    }

    public function test_gmr_report_print_supports_pdf_export_and_has_correct_headers(): void
    {
        [$branch, $warehouse] = $this->createHierarchy();
        $pile1 = $this->createPileWithRates(
            $warehouse,
            '1',
            11522 * 50,
            61.53,
            62.22,
        );

        $response = $this->actingAs($this->rmecUser)->post(
            route('gmr.report.print'),
            [
                'selected_piles' => [$pile1->id],
                'pdf' => 1,
            ],
        );

        $response->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            (string) $response->headers->get('content-type'),
        );
        $this->assertStringContainsString(
            'GMR_Report_',
            (string) $response->headers->get('content-disposition'),
        );
    }

    /**
     * @return array{0: Branch, 1: Warehouse}
     */
    private function createHierarchy(): array
    {
        $branch = Branch::create(['name' => 'NORTH COTABATO']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'GID#2, MLANG BS',
        ]);

        return [$branch, $warehouse];
    }

    private function createPileWithRates(
        Warehouse $warehouse,
        string $number,
        int $volume,
        float $amr,
        float $pmr,
    ): Pile {
        $pile = Pile::create([
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => $number,
            'number' => $number,
            'variety' => 'PD',
            'purity' => 94.31,
            'aged_months' => 5,
            'quality' => 'GQA',
            'volume_kg' => $volume,
        ]);

        $recordDetails = [
            'warehouse_name' => $warehouse->name,
            'pile_number' => $number,
            'variety' => 'PD',
            'purity' => 94.31,
            'mc' => 14.0,
            'quality' => 'GQA',
            'aged_months' => 5,
            'volume_kg' => $volume,
            'status' => 'RECOMMENDED',
            'action' => 'RECOMMEND',
            'is_locked' => true,
            'included_in_computation' => true,
        ];

        for ($trial = 1; $trial <= 3; $trial++) {
            AmrRecord::create([
                ...$recordDetails,
                'pile_id' => $pile->id,
                'rice_millers' => 'Test Miller',
                'palay_input_kg' => 1000,
                'rice_recovery_kg' => $amr * 10,
                'milling_recovery' => $amr,
                'trial_number' => $trial,
                'conduct_number' => 1,
            ]);

            PmrRecord::create([
                ...$recordDetails,
                'pile_id' => $pile->id,
                'sample_number' => $trial,
                'trial_number' => $trial,
                'conduct_number' => 1,
                'rice_millers' => 'Test Miller',
                'palay_input_kg' => 1000,
                'rice_recovery_kg' => $pmr * 10,
                'milling_recovery' => $pmr,
            ]);
        }

        return $pile;
    }
}
