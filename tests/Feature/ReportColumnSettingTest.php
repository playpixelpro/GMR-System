<?php

namespace Tests\Feature;

use App\Models\ReportColumnSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportColumnSettingTest extends TestCase
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

    public function test_guests_cannot_access_column_settings(): void
    {
        $this->get(route('settings.reports.columns'))
            ->assertRedirect(route('login'));
    }

    public function test_staff_cannot_access_column_settings(): void
    {
        $this->actingAs($this->staffUser)
            ->get(route('settings.reports.columns'))
            ->assertForbidden();
    }

    public function test_administrator_and_rmec_can_access_column_settings(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('settings.reports.columns'))
            ->assertOk()
            ->assertSee('Report Column Visibility')
            ->assertSee('Actual Milling Recovery (AMR)')
            ->assertSee('Potential Milling Recovery (PMR)');

        $this->actingAs($this->rmecUser)
            ->get(route('settings.reports.columns'))
            ->assertOk()
            ->assertSee('Report Column Visibility');
    }

    public function test_administrator_can_update_column_settings(): void
    {
        $newAmrColumns = ['no', 'pile_number', 'variety', 'amr_rate', 'status', 'actions'];
        $newPmrColumns = ['no', 'pile_number', 'variety', 'pmr_rate', 'status', 'actions'];

        $response = $this->actingAs($this->adminUser)
            ->put(route('settings.reports.columns.update'), [
                'amr_columns' => $newAmrColumns,
                'pmr_columns' => $newPmrColumns,
            ]);

        $response->assertRedirect(route('settings.reports.columns'))
            ->assertSessionHas('status', 'Report column visibility settings successfully saved.');

        $this->assertEquals($newAmrColumns, ReportColumnSetting::forReport('amr'));
        $this->assertEquals($newPmrColumns, ReportColumnSetting::forReport('pmr'));
    }

    public function test_administrator_can_reset_column_settings_to_system_defaults(): void
    {
        // First set custom columns
        ReportColumnSetting::updateOrCreate(
            ['report_type' => 'amr'],
            ['visible_columns' => ['no', 'pile_number']]
        );

        $response = $this->actingAs($this->adminUser)
            ->post(route('settings.reports.columns.reset'));

        $response->assertRedirect(route('settings.reports.columns'))
            ->assertSessionHas('status', 'Report columns have been reset to default visibility.');

        $this->assertEquals(ReportColumnSetting::defaultColumns('amr'), ReportColumnSetting::forReport('amr'));
    }

    public function test_navigation_menu_contains_column_visibility_link_for_admin(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('amr.index'))
            ->assertOk()
            ->assertSee(route('settings.reports.columns'), false)
            ->assertSee('Column Visibility');
    }

    public function test_amr_and_pmr_views_render_columns_toggle_dropdown(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('amr.index'))
            ->assertOk()
            ->assertSee('id="amr-columns-dropdown-btn"', false)
            ->assertSee('data-col="amr_rate"', false)
            ->assertSee('amr-reset-columns-btn', false);

        $this->actingAs($this->adminUser)
            ->get(route('pmr.index'))
            ->assertOk()
            ->assertSee('id="pmr-columns-dropdown-btn"', false)
            ->assertSee('data-col="pmr_rate"', false)
            ->assertSee('pmr-reset-columns-btn', false);
    }
}
