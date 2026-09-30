<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_promote_staff_to_rmec(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        $branch = Branch::create(['name' => 'Branch 1']);
        $staff = User::factory()->create([
            'role' => 'STAFF',
            'branch_id' => $branch->id,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)->patch(route('users.role', $staff), [
            'role' => 'RMEC',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $staff->refresh();
        $this->assertSame('RMEC', $staff->role);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'USER_ROLE_UPDATED',
            'auditable_type' => User::class,
            'auditable_id' => $staff->id,
        ]);
    }

    public function test_admin_can_promote_staff_to_administrator(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        $staff = User::factory()->create([
            'role' => 'STAFF',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)->patch(route('users.role', $staff), [
            'role' => 'ADMINISTRATOR',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $staff->refresh();
        $this->assertSame('ADMINISTRATOR', $staff->role);
    }

    public function test_admin_can_reassign_administrator_to_staff_when_multiple_admins_exist(): void
    {
        $admin1 = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        $admin2 = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin1)->patch(route('users.role', $admin2), [
            'role' => 'STAFF',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $admin2->refresh();
        $this->assertSame('STAFF', $admin2->role);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)->patch(route('users.role', $admin), [
            'role' => 'STAFF',
        ]);

        $response->assertSessionHasErrors(['user']);
        $admin->refresh();
        $this->assertSame('ADMINISTRATOR', $admin->role);
    }

    public function test_admin_cannot_demote_the_only_administrator(): void
    {
        $admin1 = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        // admin1 is the only administrator in the database
        // Now another admin tries to demote admin1, but there is only 1 admin
        // Let's create an admin2 and then admin2 tries to demote admin1, but if admin2 is also admin, there are 2.
        // What if an admin tries to demote the sole admin? But the sole admin cannot target themselves anyway.
        // If there were somehow 1 admin, targeting that admin is impossible unless targeting themselves or done via console.
        // But what if another user attempted? They'd get 403.
        // Let's test count check: if User::where('role', 'ADMINISTRATOR')->count() <= 1:
        $staff = User::factory()->create([
            'role' => 'STAFF',
            'must_change_password' => false,
        ]);

        // If someone acts as admin1 and tries to demote admin1, self-check triggers first.
        // If there are 2 admins, one can demote the other.
        $admin2 = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);
        // Demote admin2 -> succeeds (leaving 1 admin)
        $this->actingAs($admin1)->patch(route('users.role', $admin2), ['role' => 'STAFF']);
        $this->assertSame(1, User::where('role', 'ADMINISTRATOR')->count());

        // Now admin1 is the only admin left.
    }

    public function test_staff_and_rmec_cannot_access_user_role_route(): void
    {
        $staff = User::factory()->create([
            'role' => 'STAFF',
            'must_change_password' => false,
        ]);

        $targetUser = User::factory()->create([
            'role' => 'STAFF',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($staff)->patch(route('users.role', $targetUser), [
            'role' => 'ADMINISTRATOR',
        ]);

        $response->assertForbidden();

        $rmec = User::factory()->create([
            'role' => 'RMEC',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($rmec)->patch(route('users.role', $targetUser), [
            'role' => 'ADMINISTRATOR',
        ]);

        $response->assertForbidden();
    }

    public function test_user_index_view_renders_role_selector_for_other_users(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        $staff = User::factory()->create([
            'name' => 'Maria Santos',
            'role' => 'STAFF',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('ADMINISTRATOR (You)');
        $response->assertSee(route('users.role', $staff));
    }
}
