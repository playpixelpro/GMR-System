<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AmrCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataCleanupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $this->staff = User::factory()->create([
            'role' => 'STAFF',
            'must_change_password' => false,
            'is_active' => true,
        ]);
    }

    public function test_data_cleanup_page_and_deletions_are_admin_only(): void
    {
        $setup = $this->makePile();

        $this->actingAs($this->staff)
            ->get(route('settings.data-cleanup'))
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->delete(route('settings.data-cleanup.piles.destroy', $setup['pile']))
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->delete(route('settings.data-cleanup.warehouses.destroy', $setup['warehouse']))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('settings.data-cleanup'))
            ->assertOk()
            ->assertSee('Data Cleanup');
    }

    public function test_data_cleanup_menu_link_only_shown_to_administrators(): void
    {
        $this->actingAs($this->admin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Data Cleanup');

        $this->actingAs($this->staff)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Data Cleanup');
    }

    public function test_index_renders_all_tabs_with_data(): void
    {
        $setup = $this->makePile();
        $this->makeAmrTrials($setup['pile'], 1);
        $this->makePmrTrials($setup['pile'], 1);

        $this->actingAs($this->admin)
            ->get(route('settings.data-cleanup', ['tab' => 'warehouses']))
            ->assertOk()
            ->assertSee($setup['warehouse']->name);

        $this->actingAs($this->admin)
            ->get(route('settings.data-cleanup', ['tab' => 'piles']))
            ->assertOk()
            ->assertSee($setup['warehouse']->name)
            ->assertSee('Delete all AMR trials first');

        $this->actingAs($this->admin)
            ->get(route('settings.data-cleanup', ['tab' => 'test-milling']))
            ->assertOk()
            ->assertSee('Delete all AMR');
    }

    public function test_bulk_amr_deletion_removes_trials_and_calculation_and_logs_audit(): void
    {
        $setup = $this->makePile();
        $pile = $setup['pile'];
        $this->makeAmrTrials($pile, 3);
        $this->makePmrTrials($pile, 2);
        app(AmrCalculationService::class)->calculateAndStoreForPile($pile);
        $pile->update(['amr_status' => 'CONFIRMED']);

        $this->actingAs($this->admin)
            ->from(route('settings.data-cleanup', ['tab' => 'test-milling']))
            ->delete(route('settings.data-cleanup.amr.destroy', $pile))
            ->assertRedirect(route('settings.data-cleanup', ['tab' => 'test-milling']));

        $this->assertDatabaseMissing('amr_records', ['pile_id' => $pile->id]);
        $this->assertDatabaseMissing('amr_calculations', ['pile_id' => $pile->id]);
        $this->assertSame(2, $pile->pmrRecords()->count());
        $this->assertNull($pile->refresh()->amr_status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'AMR_DATA_DELETED',
            'module' => 'settings',
            'pile_id' => $pile->id,
        ]);
    }

    public function test_trial_deletion_renumbers_remaining_trials_and_recalculates(): void
    {
        $setup = $this->makePile();
        $pile = $setup['pile'];
        $this->makeAmrTrials($pile, 2);
        app(AmrCalculationService::class)->calculateAndStoreForPile($pile);
        $first = $pile->amrRecords()->orderBy('trial_number')->firstOrFail();

        $this->actingAs($this->admin)
            ->from(route('settings.data-cleanup', ['tab' => 'test-milling']))
            ->delete(route('settings.data-cleanup.trials.destroy', ['amr', $first->id]))
            ->assertRedirect(route('settings.data-cleanup', ['tab' => 'test-milling']));

        $this->assertDatabaseMissing('amr_records', ['id' => $first->id]);

        $remaining = $pile->amrRecords()->orderBy('trial_number')->get();
        $this->assertCount(1, $remaining);
        $this->assertSame(1, $remaining->first()->trial_number);
        $this->assertNotNull($remaining->first()->milling_recovery);
        $this->assertDatabaseHas('amr_calculations', ['pile_id' => $pile->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'AMR_TRIAL_DELETED',
            'module' => 'settings',
        ]);
    }

    public function test_pile_deletion_blocked_until_test_data_deleted_then_succeeds(): void
    {
        $setup = $this->makePile();
        $pile = $setup['pile'];
        $this->makeAmrTrials($pile, 2);

        AuditLog::create([
            'action' => 'DATA_ENCODED',
            'module' => 'data-entry',
            'description' => 'Encoded trial data',
            'pile_id' => $pile->id,
        ]);

        $this->actingAs($this->admin)
            ->from(route('settings.data-cleanup', ['tab' => 'piles']))
            ->delete(route('settings.data-cleanup.piles.destroy', $pile))
            ->assertSessionHasErrors('data_cleanup');
        $this->assertDatabaseHas('piles', ['id' => $pile->id]);

        $this->actingAs($this->admin)
            ->delete(route('settings.data-cleanup.amr.destroy', $pile))
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->from(route('settings.data-cleanup', ['tab' => 'piles']))
            ->delete(route('settings.data-cleanup.piles.destroy', $pile))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('piles', ['id' => $pile->id]);
        $this->assertDatabaseMissing('amr_records', ['pile_id' => $pile->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PILE_DELETED',
            'module' => 'settings',
        ]);
        $this->assertNull(
            AuditLog::where('action', 'DATA_ENCODED')->firstOrFail()->fresh()->pile_id,
        );
    }

    public function test_pile_with_submitted_or_approved_gmr_cannot_be_deleted(): void
    {
        foreach (['submitted', 'approved'] as $status) {
            $setup = $this->makePile(['gmr_status' => $status]);

            $this->actingAs($this->admin)
                ->from(route('settings.data-cleanup', ['tab' => 'piles']))
                ->delete(route('settings.data-cleanup.piles.destroy', $setup['pile']))
                ->assertSessionHasErrors('data_cleanup');

            $this->assertDatabaseHas('piles', ['id' => $setup['pile']->id]);
        }
    }

    public function test_test_data_of_central_office_piles_is_frozen(): void
    {
        $setup = $this->makePile(['gmr_status' => 'approved']);
        $pile = $setup['pile'];
        $this->makeAmrTrials($pile, 2);
        $trial = $pile->amrRecords()->firstOrFail();

        $this->actingAs($this->admin)
            ->from(route('settings.data-cleanup', ['tab' => 'test-milling']))
            ->delete(route('settings.data-cleanup.amr.destroy', $pile))
            ->assertSessionHasErrors('data_cleanup');
        $this->assertSame(2, $pile->amrRecords()->count());

        $this->actingAs($this->admin)
            ->delete(route('settings.data-cleanup.trials.destroy', ['amr', $trial->id]))
            ->assertSessionHasErrors('data_cleanup');
        $this->assertDatabaseHas('amr_records', ['id' => $trial->id]);
    }

    public function test_warehouse_deletion_blocked_until_empty_then_logs_audit(): void
    {
        $setup = $this->makePile();
        $warehouse = $setup['warehouse'];
        $pile = $setup['pile'];

        $this->actingAs($this->admin)
            ->from(route('settings.data-cleanup', ['tab' => 'warehouses']))
            ->delete(route('settings.data-cleanup.warehouses.destroy', $warehouse))
            ->assertSessionHasErrors('data_cleanup');
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);

        $this->actingAs($this->admin)
            ->delete(route('settings.data-cleanup.piles.destroy', $pile))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->from(route('settings.data-cleanup', ['tab' => 'warehouses']))
            ->delete(route('settings.data-cleanup.warehouses.destroy', $warehouse))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'WAREHOUSE_DELETED',
            'module' => 'settings',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{branch: Branch, warehouse: Warehouse, pile: Pile}
     */
    private function makePile(array $attributes = []): array
    {
        $branch = Branch::create(['name' => fake()->unique()->company()]);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => fake()->unique()->streetName(),
        ]);
        $pile = Pile::create(array_merge([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'number' => 'P-1',
            'pile_number' => 'P-1',
        ], $attributes));

        return compact('branch', 'warehouse', 'pile');
    }

    private function makeAmrTrials(Pile $pile, int $count): void
    {
        for ($trial = 1; $trial <= $count; $trial++) {
            AmrRecord::factory()->create([
                'pile_id' => $pile->id,
                'trial_number' => $trial,
                'test_milling_date' => '2026-09-25',
            ]);
        }
    }

    private function makePmrTrials(Pile $pile, int $count): void
    {
        for ($trial = 1; $trial <= $count; $trial++) {
            PmrRecord::factory()->create([
                'pile_id' => $pile->id,
                'trial_number' => $trial,
            ]);
        }
    }
}
