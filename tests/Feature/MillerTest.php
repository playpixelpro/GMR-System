<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Miller;
use App\Models\Milling;
use App\Models\Pile;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MillerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create([
            'role' => 'rmec',
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'is_active' => true,
        ]);
        $this->actingAs($user);
    }

    public function test_index_returns_miller_profiles_as_json(): void
    {
        Miller::factory()->create(['name' => 'AAA Rice Mill']);
        Miller::factory()->create(['name' => 'ZZZ Rice Mill']);

        $this->getJson(route('millers.index'))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'category', 'capacity_12h_bags'],
            ])
            ->assertJsonPath('0.name', 'AAA Rice Mill');
    }

    public function test_miller_endpoints_require_authentication(): void
    {
        auth()->logout();

        $this->get('/millers')->assertRedirect(route('login'));

        $this->postJson(route('millers.store'), [
            'name' => 'Guest Mill',
            'category' => 'private',
            'capacity_12h_bags' => 100,
        ])->assertStatus(401);
    }

    public function test_store_creates_a_miller_profile(): void
    {
        $this->postJson(route('millers.store'), [
            'name' => 'North Cotabato Rice Mill',
            'category' => 'nfa_owned',
            'capacity_12h_bags' => 1200,
        ])
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'North Cotabato Rice Mill'])
            ->assertJsonFragment(['category' => 'nfa_owned']);

        $miller = Miller::firstOrFail();
        $this->assertSame('North Cotabato Rice Mill', $miller->name);
        $this->assertSame('nfa_owned', $miller->category);
        $this->assertSame(1200.0, (float) $miller->capacity_12h_bags);
    }

    public function test_store_returns_the_existing_profile_for_a_known_name_regardless_of_case(): void
    {
        $this->postJson(route('millers.store'), [
            'name' => 'Magdamo Rice Mill',
            'category' => 'private',
            'capacity_12h_bags' => 800,
        ])->assertStatus(201);

        $this->postJson(route('millers.store'), [
            'name' => 'MAGDAMO RICE MILL',
            'category' => 'nfa_owned',
            'capacity_12h_bags' => 999,
        ])
            ->assertStatus(200)
            ->assertJsonFragment(['category' => 'private']);

        $this->assertDatabaseCount('millers', 1);
    }

    public function test_store_validates_the_miller_profile_fields(): void
    {
        $this->postJson(route('millers.store'), [
            'name' => 'Missing Details Mill',
            'category' => '',
            'capacity_12h_bags' => '',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category', 'capacity_12h_bags']);

        $this->postJson(route('millers.store'), [
            'name' => 'Bad Category Mill',
            'category' => 'government',
            'capacity_12h_bags' => 100,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category']);

        $this->postJson(route('millers.store'), [
            'name' => 'Negative Capacity Mill',
            'category' => 'private',
            'capacity_12h_bags' => -5,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['capacity_12h_bags']);

        $this->postJson(route('millers.store'), [
            'name' => str_repeat('a', 192),
            'category' => 'private',
            'capacity_12h_bags' => 100,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->postJson(route('millers.store'), [
            'name' => '',
            'category' => 'private',
            'capacity_12h_bags' => 100,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('millers', 0);
    }

    public function test_milling_assignment_links_the_matching_miller_profile(): void
    {
        $miller = Miller::factory()->create(['name' => 'Acme Rice Mill']);
        [$branch, $pile] = $this->createApprovedPile('1', 5000);

        $this->post(route('millings.store'), [
            'branch_id' => $branch->id,
            'pile_id' => $pile->id,
            'miller' => 'Acme Rice Mill',
            'reference_number' => 'PR-2026-001',
            'lot_number' => 'Lot 1',
        ])->assertRedirect();

        $milling = Milling::firstOrFail();
        $this->assertSame($miller->id, $milling->miller_id);
        $this->assertSame('Acme Rice Mill', $milling->miller);

        // A name outside the master list keeps the free-text value, unlinked.
        [$otherBranch, $otherPile] = $this->createApprovedPile('2', 3000);

        $this->post(route('millings.store'), [
            'branch_id' => $otherBranch->id,
            'pile_id' => $otherPile->id,
            'miller' => 'Not In Master List Mill',
            'reference_number' => 'PR-2026-002',
            'lot_number' => 'Lot 2',
        ])->assertRedirect();

        $second = Milling::latest('id')->firstOrFail();
        $this->assertNull($second->miller_id);
        $this->assertSame('Not In Master List Mill', $second->miller);
    }

    public function test_saving_amr_trials_syncs_the_miller_master_list(): void
    {
        $this->post(route('records.store'), [
            'form_type' => 'amr',
            'new_branch_name' => 'Sync Branch',
            'new_warehouse_name' => 'Sync Warehouse',
            'test_milling_date' => '2026-09-24',
            'pile_number' => '10',
            'variety' => 'PD',
            'purity' => 90,
            'mc' => 12,
            'quality' => 'good',
            'aged' => 5,
            'volume' => 10,
            'rice_millers' => 'Synced Miller',
            'no_of_trial' => 1,
            'palay_input' => 100,
            'rice_recovery' => 60,
        ])->assertRedirect();

        $miller = Miller::where('name', 'Synced Miller')->firstOrFail();
        $this->assertNull($miller->category);
        $this->assertNull($miller->capacity_12h_bags);
    }

    public function test_forms_render_the_reusable_miller_combobox(): void
    {
        $this->get(route('records.create'))
            ->assertOk()
            ->assertSee('data-miller-combobox', false)
            ->assertSee('miller-profile-dialog', false);

        $this->createApprovedPile('1', 5000);

        $this->get(route('millings.index'))
            ->assertOk()
            ->assertSee('data-miller-combobox', false);

        $this->get(route('millings.create'))
            ->assertOk()
            ->assertSee('data-miller-combobox', false);
    }

    public function test_rmec_can_open_miller_settings_and_filter_the_list(): void
    {
        Miller::factory()->create(['name' => 'Alpha Rice Mill', 'category' => 'nfa_owned']);
        Miller::factory()->create(['name' => 'Beta Rice Mill', 'category' => 'private']);

        $this->get(route('settings.millers'))
            ->assertOk()
            ->assertSee('Miller Management')
            ->assertSee(route('settings.millers'), false)
            ->assertSee('Alpha Rice Mill')
            ->assertSee('Beta Rice Mill');

        $this->get(route('settings.millers', ['q' => 'alpha']))
            ->assertOk()
            ->assertSee('Alpha Rice Mill')
            ->assertDontSee('Beta Rice Mill');

        $this->get(route('settings.millers', ['category' => 'private']))
            ->assertOk()
            ->assertSee('Beta Rice Mill')
            ->assertDontSee('Alpha Rice Mill');
    }

    public function test_administrator_can_manage_miller_settings(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => 'administrator',
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'is_active' => true,
        ]));

        Miller::factory()->create(['name' => 'Admin Visible Mill']);

        $this->get(route('settings.millers'))
            ->assertOk()
            ->assertSee('Admin Visible Mill');
    }

    public function test_staff_cannot_access_or_modify_miller_settings(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => 'staff',
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'is_active' => true,
        ]));

        $miller = Miller::factory()->create();

        $this->get(route('settings.millers'))->assertForbidden();
        $this->get(route('settings.millers.edit', $miller))->assertForbidden();

        $this->patch(route('settings.millers.update', $miller), [
            'name' => 'Staff Rename Mill',
            'category' => 'private',
            'capacity_12h_bags' => 100,
        ])->assertForbidden();

        $this->delete(route('settings.millers.destroy', $miller))->assertForbidden();
        $this->assertDatabaseHas('millers', ['id' => $miller->id]);
    }

    public function test_miller_can_be_created_from_the_settings_page(): void
    {
        $this->post(route('millers.store'), [
            'name' => 'Newly Added Mill',
            'category' => 'nfa_owned',
            'capacity_12h_bags' => 750,
        ])
            ->assertRedirect(route('settings.millers'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('millers', ['name' => 'Newly Added Mill']);

        // A known name (case-insensitive) redirects back without a new row.
        $this->post(route('millers.store'), [
            'name' => 'NEWLY ADDED MILL',
            'category' => 'private',
            'capacity_12h_bags' => 999,
        ])->assertRedirect(route('settings.millers'));

        $this->assertDatabaseCount('millers', 1);
    }

    public function test_settings_page_creation_validates_required_fields(): void
    {
        $this->from(route('settings.millers'))
            ->post(route('millers.store'), [
                'name' => '',
                'category' => '',
                'capacity_12h_bags' => '',
            ])
            ->assertRedirect(route('settings.millers'))
            ->assertSessionHasErrors(['name', 'category', 'capacity_12h_bags']);

        $this->assertDatabaseCount('millers', 0);
    }

    public function test_miller_profile_can_be_updated_from_settings(): void
    {
        $miller = Miller::factory()->create([
            'name' => 'Old Name Mill',
            'category' => 'nfa_owned',
            'capacity_12h_bags' => 100,
        ]);

        $this->patch(route('settings.millers.update', $miller), [
            'name' => 'Renamed Mill',
            'category' => 'private',
            'capacity_12h_bags' => 2500,
        ])
            ->assertRedirect(route('settings.millers'))
            ->assertSessionHas('status');

        $miller->refresh();
        $this->assertSame('Renamed Mill', $miller->name);
        $this->assertSame('private', $miller->category);
        $this->assertSame(2500.0, (float) $miller->capacity_12h_bags);

        // A different miller already owns that name (case-insensitive).
        Miller::factory()->create(['name' => 'Taken Name Mill']);

        $this->patch(route('settings.millers.update', $miller), [
            'name' => 'TAKEN NAME MILL',
            'category' => 'private',
            'capacity_12h_bags' => 100,
        ])->assertSessionHasErrors(['name']);

        $this->patch(route('settings.millers.update', $miller), [
            'name' => 'Renamed Mill',
            'category' => 'private',
            'capacity_12h_bags' => -5,
        ])->assertSessionHasErrors(['capacity_12h_bags']);

        $this->assertSame('Renamed Mill', $miller->refresh()->name);
    }

    public function test_miller_profile_can_be_deleted_and_assignments_are_unlinked(): void
    {
        $miller = Miller::factory()->create(['name' => 'Doomed Mill']);
        [$branch, $pile] = $this->createApprovedPile('1', 5000);

        $this->post(route('millings.store'), [
            'branch_id' => $branch->id,
            'pile_id' => $pile->id,
            'miller' => 'Doomed Mill',
            'reference_number' => 'PR-2026-001',
            'lot_number' => 'Lot 1',
        ])->assertRedirect();

        $milling = Milling::firstOrFail();
        $this->assertSame($miller->id, $milling->miller_id);

        $this->delete(route('settings.millers.destroy', $miller))
            ->assertRedirect(route('settings.millers'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('millers', ['id' => $miller->id]);
        $this->assertNull($milling->refresh()->miller_id);
        $this->assertSame('Doomed Mill', $milling->miller);
    }

    /**
     * @return array{0: Branch, 1: Pile}
     */
    private function createApprovedPile(string $number, int $volume): array
    {
        $branch = Branch::create(['name' => 'Miller Test Branch '.$number]);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'Miller Test Warehouse '.$number,
        ]);
        $pile = Pile::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => $number,
            'number' => $number,
            'volume_kg' => $volume,
            'gmr_status' => 'approved',
            'gmr_locked_at' => now(),
        ]);

        return [$branch, $pile];
    }
}
