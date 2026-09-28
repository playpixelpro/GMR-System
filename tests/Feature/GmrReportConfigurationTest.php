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
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->get(route('gmr.config.edit'))
            ->assertRedirect(route('login'));
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
            ->assertSee('Paper Size')
            ->assertSee('Signatories Management');

        $this->actingAs($this->adminUser)
            ->get(route('gmr.config.edit'))
            ->assertOk()
            ->assertSee('GMR Report Configuration');
    }

    public function test_signatory_modals_render_as_native_dialogs(): void
    {
        $response = $this->actingAs($this->rmecUser)->get(route('gmr.config.edit'));

        $response->assertOk()
            ->assertSee('Add Signatory')
            ->assertSee('id="modal-add-signatory"', false)
            ->assertSee('id="modal-edit-signatory"', false)
            ->assertSee('openEditModal', false);

        // The modals must NOT use FlyonUI's `.modal` class, which keeps a native
        // <dialog> at opacity:0 / pointer-events:none and hides it after showModal().
        $response->assertDontSee('id="modal-add-signatory" class="modal"', false)
            ->assertDontSee('id="modal-edit-signatory" class="modal"', false)
            ->assertDontSee('modal-box', false);
    }

    public function test_default_configuration_and_signatories_are_seeded(): void
    {
        $config = GmrReportConfiguration::current();

        $this->assertEquals('REPORT ON PRE-MILLING ACTIVITY', $config->title);
        $this->assertEquals('QUALITY AND QUANTITY, AMR, PMR AND EMR/GMR', $config->subtitle);
        $this->assertEquals('Region XII', $config->region_text);
        $this->assertEquals('Long Bond', $config->paper_size);
        $this->assertEquals('portrait', $config->orientation);

        $signatories = GmrReportSignatory::query()->active()->get();
        $this->assertNotEmpty($signatories);
        $this->assertTrue($signatories->contains('name', 'DINDO O. QUITOR'));
        $this->assertTrue($signatories->contains('name', 'ANGELICA M. PELLIEN'));
    }

    public function test_rmec_can_update_paper_size_orientation_margins_and_headers(): void
    {
        $response = $this->actingAs($this->rmecUser)->put(route('gmr.config.update'), [
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
        ]);

        $response->assertRedirect(route('gmr.config.edit'));
        $response->assertSessionHas('status');

        $config = GmrReportConfiguration::current();
        $this->assertEquals('UPDATED REPORT ON PRE-MILLING ACTIVITY', $config->title);
        $this->assertEquals('A4', $config->paper_size);
        $this->assertEquals('landscape', $config->orientation);
        $this->assertEquals(0.75, $config->margin_top);
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
        $addResponse = $this->actingAs($this->rmecUser)->post(route('gmr.config.signatories.store'), [
            'name' => 'JUAN DELA CRUZ',
            'position' => 'Senior Quality Officer',
            'role_group' => 'member',
            'display_order' => 8,
            'is_active' => '1',
        ]);

        $addResponse->assertRedirect(route('gmr.config.edit'));
        $signatory = GmrReportSignatory::where('name', 'JUAN DELA CRUZ')->first();
        $this->assertNotNull($signatory);
        $this->assertEquals('Senior Quality Officer', $signatory->position);
        $this->assertTrue($signatory->is_active);

        // Update signatory
        $updateResponse = $this->actingAs($this->rmecUser)->put(route('gmr.config.signatories.update', $signatory), [
            'name' => 'JUAN DELA CRUZ JR.',
            'position' => 'Supervising Quality Officer',
            'role_group' => 'member',
            'display_order' => 8,
            'is_active' => '1',
        ]);
        $updateResponse->assertRedirect(route('gmr.config.edit'));
        $this->assertEquals('JUAN DELA CRUZ JR.', $signatory->fresh()->name);

        // Toggle active/inactive
        $this->actingAs($this->rmecUser)->patch(route('gmr.config.signatories.toggle', $signatory));
        $this->assertFalse($signatory->fresh()->is_active);

        // Delete signatory
        $deleteResponse = $this->actingAs($this->rmecUser)->delete(route('gmr.config.signatories.destroy', $signatory));
        $deleteResponse->assertRedirect(route('gmr.config.edit'));
        $this->assertDatabaseMissing('gmr_report_signatories', ['id' => $signatory->id]);
    }

    public function test_rmec_can_preview_report(): void
    {
        $this->actingAs($this->rmecUser)
            ->get(route('gmr.config.preview'))
            ->assertOk()
            ->assertSee('REPORT ON PRE-MILLING ACTIVITY')
            ->assertSee('Preview Mode')
            ->assertSee('DINDO O. QUITOR')
            ->assertSee('MARIETES E. DISTOR');
    }

    public function test_gmr_summary_displays_include_in_report_checkboxes_and_print_button(): void
    {
        [$branch, $warehouse] = $this->createHierarchy();
        $pile = $this->createPileWithRates($warehouse, '1', 5000, 61.53, 62.22);

        $response = $this->actingAs($this->rmecUser)->get(route('gmr.summary'));

        $response->assertOk()
            ->assertSee('Include in Report')
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
        $pile1 = $this->createPileWithRates($warehouse, '1', 11522 * 50, 61.53, 62.22);
        $pile2 = $this->createPileWithRates($warehouse, '2', 12259 * 50, 62.66, 62.77);

        $response = $this->actingAs($this->rmecUser)->post(route('gmr.report.print'), [
            'selected_piles' => [$pile1->id, $pile2->id],
        ]);

        $response->assertOk()
            ->assertSee('REPORT ON PRE-MILLING ACTIVITY')
            ->assertSee('Print Report')
            ->assertSee('GID#2, MLANG BS')
            ->assertSee('11,522', false)
            ->assertSee('12,259', false)
            ->assertSee('61.53')
            ->assertSee('62.22')
            ->assertSee('62.66')
            ->assertSee('62.77')
            ->assertSee('DINDO O. QUITOR')
            ->assertSee('Regional Economist')
            ->assertSee('ANGELICA M. PELLIEN');

        // Selection does not alter any database data
        $this->assertDatabaseHas('piles', ['id' => $pile1->id, 'pile_number' => '1']);
        $this->assertDatabaseHas('piles', ['id' => $pile2->id, 'pile_number' => '2']);
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
