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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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

        $branch = Branch::create(['name' => 'Branch Test A']);

        $this->actingAs($administrator)
            ->post(route('users.store'), [
                'name' => 'New Staff Member',
                'email' => 'new.staff@example.com',
                'role' => 'STAFF',
                'branch_id' => $branch->id,
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

    public function test_administrator_can_resend_a_temporary_password_to_a_user_awaiting_activation(): void
    {
        Notification::fake();
        $administrator = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);
        $user = User::factory()->create([
            'password' => Hash::make('old-temporary-password'),
            'must_change_password' => true,
            'activated_at' => null,
            'temporary_password_expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($administrator)
            ->post(route('users.resend-temporary-password', $user))
            ->assertRedirect()
            ->assertSessionHas('status', 'A new temporary password was emailed to the user.');

        $user = $user->fresh();
        $this->assertTrue($user->isAwaitingActivation());
        $this->assertTrue($user->temporary_password_expires_at->isFuture());
        $this->assertFalse(Hash::check('old-temporary-password', $user->password));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'TEMPORARY_PASSWORD_RESENT',
            'auditable_id' => $user->id,
        ]);
        Notification::assertSentTo(
            $user,
            TemporaryPasswordNotification::class,
            fn (TemporaryPasswordNotification $notification): bool => Hash::check($notification->temporaryPassword, $user->password),
        );
    }

    public function test_non_administrator_cannot_resend_a_temporary_password(): void
    {
        $staff = User::factory()->create([
            'role' => 'STAFF',
            'must_change_password' => false,
        ]);
        $user = User::factory()->create([
            'must_change_password' => true,
            'activated_at' => null,
        ]);

        $this->actingAs($staff)
            ->post(route('users.resend-temporary-password', $user))
            ->assertForbidden();
    }

    public function test_administrator_can_generate_new_temporary_password_for_user_who_forgot_password(): void
    {
        Notification::fake();
        $administrator = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);
        $user = User::factory()->create([
            'email' => 'forgotten@example.com',
            'password' => Hash::make('established-password'),
            'must_change_password' => false,
            'activated_at' => now(),
        ]);

        $response = $this->actingAs($administrator)
            ->post(route('users.reset-password', $user), [
                'password' => 'NewTempResetPass123!',
            ]);

        $response->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionHas('credentials_modal');

        $user = $user->fresh();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->temporary_password_expires_at->isFuture());
        $this->assertTrue(Hash::check('NewTempResetPass123!', $user->password));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'TEMPORARY_PASSWORD_RESET',
            'auditable_id' => $user->id,
        ]);

        Notification::assertSentTo(
            $user,
            TemporaryPasswordNotification::class,
            fn (TemporaryPasswordNotification $notification): bool => $notification->temporaryPassword === 'NewTempResetPass123!',
        );

        // Forgotten user logs in with new temporary password and is required to change it
        $this->post(route('logout'));
        $loginResponse = $this->post(route('login.store'), [
            'email' => 'forgotten@example.com',
            'password' => 'NewTempResetPass123!',
        ]);
        $loginResponse->assertRedirect(route('password.change'));

        // User updates to their new persistent password
        $this->actingAs($user)
            ->put(route('password.update'), [
                'password' => 'FreshPermanentPassword123!',
                'password_confirmation' => 'FreshPermanentPassword123!',
            ])
            ->assertRedirect(route('home'));

        $user = $user->fresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->temporary_password_expires_at);
        $this->assertTrue(Hash::check('FreshPermanentPassword123!', $user->password));
    }

    public function test_rmec_can_recommend_a_conduct_and_lock_it(): void
    {
        $user = User::factory()->create(['role' => 'RMEC', 'must_change_password' => false]);
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
        $user = User::factory()->create(['role' => 'STAFF', 'must_change_password' => false]);
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
        $user = User::factory()->create(['role' => 'RMEC', 'must_change_password' => false]);
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

    public function test_administrator_can_unlock_staff_edit_mode_with_duration(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);
        $staff = User::factory()->create([
            'role' => 'STAFF',
            'must_change_password' => false,
            'is_edit_locked' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('users.unlock-edit', $staff), ['hours' => 48])
            ->assertRedirect()
            ->assertSessionHas('status');

        $staff->refresh();
        $this->assertFalse($staff->isEditLocked());
        $this->assertTrue($staff->isEditOverrideActive());
        $this->assertNotNull($staff->edit_unlocked_until);
        $this->assertGreaterThan(now()->addHours(47), $staff->edit_unlocked_until);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'STAFF_EDIT_UNLOCKED',
            'auditable_id' => $staff->id,
        ]);
    }

    public function test_administrator_can_lock_staff_edit_mode_immediately(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);
        $staff = User::factory()->create([
            'role' => 'STAFF',
            'must_change_password' => false,
            'is_edit_locked' => false,
            'edit_unlocked_until' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->post(route('users.lock-edit', $staff))
            ->assertRedirect()
            ->assertSessionHas('status');

        $staff->refresh();
        $this->assertTrue($staff->isEditLocked());
        $this->assertNull($staff->edit_unlocked_until);
        $this->assertFalse($staff->isEditOverrideActive());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'STAFF_EDIT_LOCKED',
            'auditable_id' => $staff->id,
        ]);
    }

    public function test_administrator_can_reset_staff_edit_mode_to_default(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);
        $staff = User::factory()->create([
            'role' => 'STAFF',
            'must_change_password' => false,
            'is_edit_locked' => true,
            'edit_unlocked_until' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->post(route('users.reset-edit-mode', $staff))
            ->assertRedirect()
            ->assertSessionHas('status');

        $staff->refresh();
        $this->assertFalse($staff->isEditLocked());
        $this->assertNull($staff->edit_unlocked_until);
        $this->assertFalse($staff->isEditOverrideActive());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'STAFF_EDIT_RESET_DEFAULT',
            'auditable_id' => $staff->id,
        ]);
    }

    public function test_non_administrator_cannot_modify_staff_edit_mode(): void
    {
        $staff1 = User::factory()->create(['role' => 'STAFF', 'must_change_password' => false]);
        $staff2 = User::factory()->create(['role' => 'STAFF', 'must_change_password' => false]);

        $this->actingAs($staff1)
            ->post(route('users.unlock-edit', $staff2))
            ->assertForbidden();

        $this->actingAs($staff1)
            ->post(route('users.lock-edit', $staff2))
            ->assertForbidden();

        $this->actingAs($staff1)
            ->post(route('users.reset-edit-mode', $staff2))
            ->assertForbidden();
    }

    public function test_staff_can_edit_record_older_than_24_hours_when_unlocked_by_admin(): void
    {
        $staff = User::factory()->create([
            'role' => 'STAFF',
            'must_change_password' => false,
            'is_edit_locked' => false,
            'edit_unlocked_until' => now()->addHours(24),
        ]);

        $record = $this->createRecord();
        $record->created_by = $staff->id;
        $record->created_at = now()->subDays(3);
        $record->save();

        $this->actingAs($staff)
            ->patchJson(
                route('records.update', ['formType' => 'amr', 'record' => $record->id]),
                [
                    'rice_millers' => 'Updated Miller',
                    'test_milling_date' => '2026-09-25',
                    'palay_input' => '100',
                    'rice_recovery' => '65',
                ],
            )
            ->assertOk()
            ->assertJson([
                'message' => 'AMR trial updated successfully.',
            ]);

        $record->refresh();
        $this->assertSame('Updated Miller', $record->rice_millers);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DATA_EDITED',
            'auditable_id' => $record->id,
        ]);
    }

    public function test_staff_cannot_edit_recent_record_when_edit_locked_by_admin(): void
    {
        $staff = User::factory()->create([
            'role' => 'STAFF',
            'must_change_password' => false,
            'is_edit_locked' => true,
        ]);

        $record = $this->createRecord();
        $record->created_by = $staff->id;
        $record->created_at = now()->subMinutes(10);
        $record->save();

        $this->actingAs($staff)
            ->patchJson(
                route('records.update', ['formType' => 'amr', 'record' => $record->id]),
                [
                    'rice_millers' => 'Updated Miller',
                    'test_milling_date' => '2026-09-25',
                    'palay_input' => '100',
                    'rice_recovery' => '65',
                ],
            )
            ->assertForbidden();
    }

    public function test_user_management_view_displays_edit_mode_statuses_and_controls(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);
        $lockedStaff = User::factory()->create([
            'name' => 'Locked Staff Member',
            'role' => 'STAFF',
            'must_change_password' => false,
            'is_edit_locked' => true,
        ]);
        $unlockedStaff = User::factory()->create([
            'name' => 'Unlocked Staff Member',
            'role' => 'STAFF',
            'must_change_password' => false,
            'is_edit_locked' => false,
            'edit_unlocked_until' => now()->addHours(12),
        ]);
        $standardStaff = User::factory()->create([
            'name' => 'Standard Staff Member',
            'role' => 'STAFF',
            'must_change_password' => false,
            'is_edit_locked' => false,
            'edit_unlocked_until' => null,
        ]);

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('Edit Mode (24h Window)');
        $response->assertSee('Locked by Admin');
        $response->assertSee('Unlocked by Admin');
        $response->assertSee('Standard (24h)');
        $response->assertSee('Unlock (+24h)');
        $response->assertSee('Lock Edit');
        $response->assertSee('Reset to standard');
    }

    public function test_administrator_can_specify_a_temporary_password_when_creating_user(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        $branch = Branch::create(['name' => 'Branch Test B']);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Custom Password Staff',
                'email' => 'custom.staff@example.com',
                'role' => 'STAFF',
                'branch_id' => $branch->id,
                'password' => 'CustomTempPass123!',
            ])
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionHas('credentials_modal');

        // Accessing the users page shows the popup modal with details and copy/send actions
        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertSee('User Account Created')
            ->assertSee('CustomTempPass123!')
            ->assertSee('Copy all details')
            ->assertSee('Send via Email');

        $user = User::where('email', 'custom.staff@example.com')->firstOrFail();

        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('CustomTempPass123!', $user->password));
        $this->assertNotNull($user->temporary_password_expires_at);
        $this->assertTrue($user->temporary_password_expires_at->isFuture());

        // Log in as the new user with their temporary password
        $this->post(route('logout'));

        $loginResponse = $this->post(route('login.store'), [
            'email' => 'custom.staff@example.com',
            'password' => 'CustomTempPass123!',
        ]);

        $loginResponse->assertRedirect(route('password.change'));

        // Visiting home or protected pages redirects to password.change
        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('password.change'));

        // Changing the password permanently activates the user
        $updateResponse = $this->actingAs($user)
            ->put(route('password.update'), [
                'password' => 'NewPermanentSecurePassword123!',
                'password_confirmation' => 'NewPermanentSecurePassword123!',
            ]);

        $updateResponse->assertRedirect(route('home'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->activated_at);
        $this->assertNull($user->temporary_password_expires_at);
        $this->assertTrue(Hash::check('NewPermanentSecurePassword123!', $user->password));

        // After 48 hours, logging in with the new permanent password remains persistent and never expires
        $this->travel(48)->hours();

        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'email' => 'custom.staff@example.com',
            'password' => 'NewPermanentSecurePassword123!',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_home_dashboard_displays_all_cards_including_gmr_report(): void
    {
        $user = User::factory()->create([
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('Data Entry Form');
        $response->assertSee('AMR Report');
        $response->assertSee('PMR Report');
        $response->assertSee('Expected Milling Recovery');
        $response->assertSee('GMR Report');
        $response->assertSee(route('gmr.summary'));
    }

    public function test_administrator_can_delete_a_user(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);
        $photoPath = 'profile-photos/test.png';
        Storage::disk('public')->put($photoPath, 'dummy');

        $staff = User::factory()->create([
            'name' => 'John Deletable',
            'email' => 'deletable@example.com',
            'role' => 'STAFF',
            'profile_photo_path' => $photoPath,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('users.destroy', $staff));

        $response->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
        $this->assertFalse(Storage::disk('public')->exists($photoPath));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'USER_DELETED',
            'metadata->deleted_user_id' => $staff->id,
            'metadata->email' => 'deletable@example.com',
        ]);
    }

    public function test_administrator_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('users.destroy', $admin));

        $response->assertRedirect()
            ->assertSessionHasErrors(['user']);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_staff_cannot_delete_users(): void
    {
        $staff1 = User::factory()->create(['role' => 'STAFF', 'must_change_password' => false]);
        $staff2 = User::factory()->create(['role' => 'STAFF', 'must_change_password' => false]);

        $this->actingAs($staff1)
            ->delete(route('users.destroy', $staff2))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $staff2->id]);
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
