<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\TemporaryPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WorkflowAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_creation_sends_a_temporary_password_notification(): void
    {
        Notification::fake();
        $administrator = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        $this->actingAs($administrator)
            ->post(route('users.store'), [
                'name' => 'New Staff Member',
                'email' => 'new.staff@example.com',
                'role' => 'STAFF',
            ])
            ->assertRedirect();

        $user = User::where('email', 'new.staff@example.com')->firstOrFail();

        Notification::assertSentTo(
            $user,
            TemporaryPasswordNotification::class,
            function (TemporaryPasswordNotification $notification): bool {
                return $notification->temporaryPassword !== '';
            },
        );
    }

    public function test_password_reset_uses_the_custom_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])->assertRedirect();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_rmec_can_recommend_a_conduct_and_lock_it(): void
    {
        $user = User::factory()->create(['role' => 'RMEC']);
        $record = $this->createRecord();

        $this->actingAs($user)
            ->postJson(
                route('tests.action', [
                    'formType' => 'amr',
                    'record' => $record->id,
                ]),
                ['action' => 'recommend'],
            )
            ->assertOk()
            ->assertJson([
                'status' => 'RECOMMENDED',
                'included_in_computation' => true,
                'is_locked' => true,
            ]);

        $this->assertDatabaseHas('amr_records', [
            'id' => $record->id,
            'status' => 'RECOMMENDED',
            'included_in_computation' => 1,
            'is_locked' => 1,
            'actioned_by' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'RECOMMEND',
            'auditable_id' => $record->id,
        ]);
    }

    public function test_staff_cannot_perform_recommendation_actions(): void
    {
        $user = User::factory()->create(['role' => 'STAFF']);
        $record = $this->createRecord();

        $this->actingAs($user)
            ->postJson(
                route('tests.action', [
                    'formType' => 'amr',
                    'record' => $record->id,
                ]),
                ['action' => 'recommend'],
            )
            ->assertForbidden();
    }

    public function test_retest_is_locked_and_excluded_without_deleting_history(): void
    {
        $user = User::factory()->create(['role' => 'RMEC']);
        $record = $this->createRecord();

        $this->actingAs($user)
            ->postJson(
                route('tests.action', [
                    'formType' => 'amr',
                    'record' => $record->id,
                ]),
                ['action' => 'retest'],
            )
            ->assertOk();

        $this->assertDatabaseHas('amr_records', [
            'id' => $record->id,
            'status' => 'RETEST',
            'included_in_computation' => 0,
            'is_locked' => 1,
        ]);
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
