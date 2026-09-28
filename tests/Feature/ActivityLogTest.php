<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
            'is_active' => true,
        ]);
    }

    public function test_activity_logger_records_actor_context(): void
    {
        $branch = Branch::create(['name' => 'Alpha Branch']);
        $this->admin->forceFill(['branch_id' => $branch->id])->save();

        $this->actingAs($this->admin);

        $log = app(ActivityLogger::class)->log(
            'pmr',
            'confirmed',
            'PMR laboratory test milling data confirmed',
            null,
            ['trial_number' => 1],
        );

        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('ADMINISTRATOR', $log->role);
        $this->assertSame($branch->id, $log->branch_id);
        $this->assertSame('Alpha Branch', $log->branch_name);
        $this->assertSame('pmr', $log->module);
        $this->assertSame('confirmed', $log->action);
        $this->assertSame('PMR laboratory test milling data confirmed', $log->description);
        $this->assertNotNull($log->ip_address);
        $this->assertSame(['trial_number' => 1], $log->metadata);
    }

    public function test_login_and_logout_are_logged_under_auth_module(): void
    {
        $user = User::factory()->create([
            'role' => 'STAFF',
            'password' => Hash::make('SecretPass123!'),
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'SecretPass123!',
        ])->assertRedirect(route('home'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'LOGIN',
            'module' => 'auth',
            'user_id' => $user->id,
            'role' => 'STAFF',
        ]);

        $this->post(route('logout'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'LOGOUT',
            'module' => 'auth',
            'user_id' => $user->id,
        ]);
    }

    public function test_workflow_action_is_logged_under_the_record_module(): void
    {
        $rmec = User::factory()->create([
            'role' => 'RMEC',
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $record = $this->createRecord();

        $this->actingAs($rmec)->postJson(
            route('tests.action', ['formType' => 'amr', 'record' => $record->id]),
            ['action' => 'recommend'],
        )->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'RECOMMEND',
            'module' => 'amr',
            'description' => 'Test milling data marked as RECOMMENDED',
        ]);
    }

    public function test_only_administrators_can_view_activity_logs(): void
    {
        $staff = User::factory()->create(['role' => 'STAFF']);

        $this->actingAs($staff)
            ->get(route('settings.activity-logs'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('settings.activity-logs'))
            ->assertOk()
            ->assertSee('Activity Logs');
    }

    public function test_filters_combine_on_the_activity_logs_page(): void
    {
        $branch = Branch::create(['name' => 'Target Branch']);
        $otherBranch = Branch::create(['name' => 'Other Branch']);
        $user = User::factory()->create(['role' => 'STAFF']);

        AuditLog::create([
            'module' => 'pmr',
            'action' => 'confirmed',
            'description' => 'match',
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'created_at' => now(),
        ]);
        AuditLog::create([
            'module' => 'amr',
            'action' => 'created',
            'description' => 'different module',
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'created_at' => now(),
        ]);
        AuditLog::create([
            'module' => 'pmr',
            'action' => 'confirmed',
            'description' => 'different branch',
            'user_id' => $user->id,
            'branch_id' => $otherBranch->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('settings.activity-logs', [
            'module' => 'pmr',
            'action' => 'confirmed',
            'branch_id' => $branch->id,
            'user_id' => $user->id,
        ]));

        $response->assertOk()
            ->assertSee('match')
            ->assertDontSee('different module')
            ->assertDontSee('different branch');
    }

    public function test_json_download_respects_filters_and_omits_credentials(): void
    {
        $branch = Branch::create(['name' => 'Match Branch']);
        $user = User::factory()->create(['role' => 'STAFF']);

        AuditLog::create([
            'module' => 'pmr',
            'action' => 'confirmed',
            'description' => 'PMR confirmed',
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'created_at' => now(),
        ]);
        AuditLog::create([
            'module' => 'amr',
            'action' => 'created',
            'description' => 'AMR created',
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('settings.activity-logs.download', [
            'module' => 'pmr',
        ]));

        $response->assertOk();
        $json = $response->json();

        $this->assertCount(1, $json);
        $this->assertSame('PMR confirmed', $json[0]['description']);
        $this->assertArrayHasKey('timestamp', $json[0]);
        $this->assertArrayHasKey('user', $json[0]);
        $this->assertArrayHasKey('branch', $json[0]);
        $this->assertArrayHasKey('ip_address', $json[0]);
        $this->assertArrayNotHasKey('email', $json[0]);
        $this->assertArrayNotHasKey('password', $json[0]);
    }

    public function test_prune_command_deletes_records_older_than_one_month(): void
    {
        AuditLog::create(['action' => 'OLD']);
        AuditLog::where('action', 'OLD')->update(['created_at' => now()->subMonths(2)]);

        AuditLog::create(['action' => 'RECENT']);
        AuditLog::where('action', 'RECENT')->update(['created_at' => now()->subDays(5)]);

        Artisan::call('activity-logs:prune');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'OLD']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'RECENT']);
    }

    private function createRecord(): AmrRecord
    {
        $branch = Branch::create(['name' => fake()->unique()->company()]);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => fake()->unique()->streetName(),
        ]);
        $pile = Pile::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'number' => '1',
            'pile_number' => '1',
        ]);

        return AmrRecord::create([
            'pile_id' => $pile->id,
            'warehouse_name' => $warehouse->name,
            'pile_number' => '1',
            'variety' => 'PD',
            'aged_months' => 5,
            'volume_kg' => 100,
            'rice_millers' => 'Miller',
            'trial_number' => 1,
            'palay_input_kg' => 100,
            'rice_recovery_kg' => 60,
            'test_milling_date' => '2026-09-25',
        ]);
    }
}
