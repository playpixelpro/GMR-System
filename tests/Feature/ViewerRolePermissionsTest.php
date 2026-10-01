<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Pile;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewerRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch1;

    private Branch $branch2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        $this->branch1 = Branch::create(['name' => 'Branch Alpha']);
        $this->branch2 = Branch::create(['name' => 'Branch Beta']);
    }

    public function test_admin_can_create_viewer_user_without_branch(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'National Auditor',
            'email' => 'auditor@example.com',
            'role' => 'VIEWER',
            'branch_id' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $viewer = User::where('email', 'auditor@example.com')->first();
        $this->assertNotNull($viewer);
        $this->assertSame('VIEWER', $viewer->role);
        $this->assertNull($viewer->branch_id);
    }

    public function test_admin_can_create_viewer_user_with_assigned_branch(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Branch Monitor',
            'email' => 'monitor@example.com',
            'role' => 'VIEWER',
            'branch_id' => $this->branch1->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $viewer = User::where('email', 'monitor@example.com')->first();
        $this->assertNotNull($viewer);
        $this->assertSame('VIEWER', $viewer->role);
        $this->assertSame($this->branch1->id, $viewer->branch_id);
    }

    public function test_admin_can_reassign_user_to_viewer(): void
    {
        $staff = User::factory()->create([
            'role' => 'STAFF',
            'branch_id' => $this->branch1->id,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('users.role', $staff), [
            'role' => 'VIEWER',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $staff->refresh();
        $this->assertSame('VIEWER', $staff->role);
    }

    public function test_viewer_is_redirected_to_amr_report_from_home(): void
    {
        $viewer = User::factory()->create([
            'role' => 'VIEWER',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($viewer)->get(route('home'));

        $response->assertRedirect(route('amr.index'));
    }

    public function test_viewer_cannot_access_data_entry_module(): void
    {
        $viewer = User::factory()->create([
            'role' => 'VIEWER',
            'must_change_password' => false,
        ]);

        $this->actingAs($viewer)->get(route('records.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('records.store'), [])->assertForbidden();
        $this->actingAs($viewer)->post(route('warehouses.store'), [])->assertForbidden();
        $this->actingAs($viewer)->post(route('piles.store'), [])->assertForbidden();
    }

    public function test_viewer_cannot_access_milling_module(): void
    {
        $viewer = User::factory()->create([
            'role' => 'VIEWER',
            'must_change_password' => false,
        ]);

        $this->actingAs($viewer)->get(route('millings.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('millers.index'))->assertForbidden();
    }

    public function test_viewer_cannot_access_user_management(): void
    {
        $viewer = User::factory()->create([
            'role' => 'VIEWER',
            'must_change_password' => false,
        ]);

        $this->actingAs($viewer)->get(route('users.index'))->assertForbidden();
    }

    public function test_viewer_can_access_and_update_profile_settings(): void
    {
        $viewer = User::factory()->create([
            'name' => 'Initial Viewer',
            'role' => 'VIEWER',
            'must_change_password' => false,
        ]);

        $this->actingAs($viewer)->get(route('profile.edit'))->assertOk();

        $response = $this->actingAs($viewer)->put(route('profile.update'), [
            'name' => 'Updated Viewer Name',
        ]);

        $response->assertRedirect();
        $viewer->refresh();
        $this->assertSame('Updated Viewer Name', $viewer->name);
    }

    public function test_viewer_can_access_all_report_views(): void
    {
        $viewer = User::factory()->create([
            'role' => 'VIEWER',
            'must_change_password' => false,
        ]);

        $this->actingAs($viewer)->get(route('amr.index'))->assertOk();
        $this->actingAs($viewer)->get(route('pmr.index'))->assertOk();
        $this->actingAs($viewer)->get(route('emr.index'))->assertOk();
        $this->actingAs($viewer)->get(route('gmr.summary'))->assertOk();
    }

    public function test_viewer_can_download_amr_and_pmr_exports(): void
    {
        $viewer = User::factory()->create([
            'role' => 'VIEWER',
            'must_change_password' => false,
        ]);

        $this->actingAs($viewer)->get(route('amr.export.excel'))->assertOk();
        $this->actingAs($viewer)->get(route('amr.export.pdf'))->assertOk();
        $this->actingAs($viewer)->get(route('pmr.export.excel'))->assertOk();
        $this->actingAs($viewer)->get(route('pmr.export.pdf'))->assertOk();
    }

    public function test_viewer_with_assigned_branch_is_scoped_to_that_branch(): void
    {
        $viewer = User::factory()->create([
            'role' => 'VIEWER',
            'branch_id' => $this->branch1->id,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($viewer)->get(route('amr.index'));

        $response->assertOk();
        $response->assertSee($this->branch1->name);
        $response->assertDontSee($this->branch2->name);
    }

    public function test_viewer_without_assigned_branch_sees_all_branches(): void
    {
        $viewer = User::factory()->create([
            'role' => 'VIEWER',
            'branch_id' => null,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($viewer)->get(route('amr.index'));

        $response->assertOk();
        $response->assertSee($this->branch1->name);
        $response->assertSee($this->branch2->name);
        $response->assertSee('All Branches');
    }

    public function test_viewer_navigation_and_reports_do_not_render_data_entry_links(): void
    {
        $viewer = User::factory()->create([
            'role' => 'VIEWER',
            'branch_id' => $this->branch1->id,
            'must_change_password' => false,
        ]);

        $warehouse = Warehouse::create([
            'branch_id' => $this->branch1->id,
            'name' => 'WH Alpha',
        ]);
        $pile = Pile::create([
            'branch_id' => $this->branch1->id,
            'warehouse_id' => $warehouse->id,
            'number' => 'P-100',
            'pile_number' => 'P-100',
            'variety' => 'Palay',
        ]);

        $response = $this->actingAs($viewer)->get(route('amr.index'));

        $response->assertOk();
        // Navigation sidebar should not contain Data Entry, Home, or Rice Milling
        $response->assertDontSee('title="Data Entry"', false);
        $response->assertDontSee('title="Rice Milling"', false);
        // Table should not render "Add Trials" button
        $response->assertDontSee('Add Trials');
        $response->assertSee('No trials');
    }
}
