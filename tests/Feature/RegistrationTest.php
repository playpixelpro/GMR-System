<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Notifications\TemporaryPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_the_registration_page_with_branches(): void
    {
        Branch::create(['name' => 'Cebu Branch']);
        Branch::create(['name' => 'Davao Branch']);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Cebu Branch')
            ->assertSee('Davao Branch');
    }

    public function test_registration_confirmation_page_advises_checking_email(): void
    {
        $this->get(route('register.confirmation', ['email' => 'new.staff@example.com']))
            ->assertOk()
            ->assertSee('Check your email')
            ->assertSee('new.staff@example.com')
            ->assertSee('temporary login credentials');
    }

    public function test_guest_can_register_as_a_staff_user_with_an_8_hour_window(): void
    {
        Notification::fake();
        $branch = Branch::create(['name' => 'Cebu Branch']);

        $this->post(route('register.store'), [
            'name' => 'New Staff',
            'email' => 'new.staff@example.com',
            'branch_id' => $branch->id,
        ])->assertRedirect(route('register.confirmation', ['email' => 'new.staff@example.com']));

        $user = User::where('email', 'new.staff@example.com')->firstOrFail();

        $this->assertSame('STAFF', $user->role);
        $this->assertSame($branch->id, $user->branch_id);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->must_change_password);
        $this->assertNotNull($user->temporary_password_expires_at);
        $this->assertTrue($user->isRegistrationPending());
        $this->assertNotNull($user->registration_expires_at);
        $this->assertGreaterThan(now()->addHours(7), $user->registration_expires_at);
        $this->assertLessThanOrEqual(now()->addHours(9), $user->registration_expires_at);

        Notification::assertSentTo(
            $user,
            TemporaryPasswordNotification::class,
            fn (TemporaryPasswordNotification $notification): bool => Hash::check($notification->temporaryPassword, $user->password),
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'USER_REGISTERED',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_registration_requires_a_branch(): void
    {
        $this->post(route('register.store'), [
            'name' => 'No Branch Staff',
            'email' => 'nobranch@example.com',
            'branch_id' => '',
        ])->assertSessionHasErrors('branch_id');

        $this->assertDatabaseMissing('users', ['email' => 'nobranch@example.com']);
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
                ->post(route('register.store'), []);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
            ->post(route('register.store'), [])
            ->assertStatus(429);
    }

    public function test_registered_user_can_log_in_with_temporary_password(): void
    {
        Notification::fake();
        $branch = Branch::create(['name' => 'Cebu Branch']);

        $this->post(route('register.store'), [
            'name' => 'New Staff',
            'email' => 'new.staff@example.com',
            'branch_id' => $branch->id,
        ]);

        $user = User::where('email', 'new.staff@example.com')->firstOrFail();
        $notification = Notification::sent($user, TemporaryPasswordNotification::class)->first();
        $temporaryPassword = $notification->temporaryPassword;

        $this->post(route('login.store'), [
            'email' => 'new.staff@example.com',
            'password' => $temporaryPassword,
        ])->assertRedirect(route('password.change'));
    }

    public function test_expired_registration_is_disabled_and_blocks_login(): void
    {
        $branch = Branch::create(['name' => 'Cebu Branch']);
        $user = User::factory()->create([
            'email' => 'expired@example.com',
            'role' => 'STAFF',
            'branch_id' => $branch->id,
            'must_change_password' => true,
            'registration_expires_at' => now()->subMinute(),
        ]);

        $this->post(route('login.store'), [
            'email' => 'expired@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertNotNull($user->disabled_at);
        $this->assertNull($user->registration_expires_at);
    }

    public function test_administrator_can_confirm_a_registration_as_permanent(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);
        $branch = Branch::create(['name' => 'Cebu Branch']);
        $user = User::factory()->create([
            'role' => 'STAFF',
            'branch_id' => $branch->id,
            'registration_expires_at' => now()->addHours(8),
        ]);

        $this->actingAs($admin)
            ->post(route('users.confirm-registration', $user))
            ->assertRedirect()
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertNull($user->registration_expires_at);
        $this->assertNotNull($user->registration_confirmed_at);
        $this->assertFalse($user->isRegistrationPending());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'USER_REGISTRATION_CONFIRMED',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_non_administrator_cannot_confirm_a_registration(): void
    {
        $staff = User::factory()->create(['role' => 'STAFF']);
        $branch = Branch::create(['name' => 'Cebu Branch']);
        $user = User::factory()->create([
            'role' => 'STAFF',
            'branch_id' => $branch->id,
            'registration_expires_at' => now()->addHours(8),
        ]);

        $this->actingAs($staff)
            ->post(route('users.confirm-registration', $user))
            ->assertForbidden();
    }

    public function test_admin_created_users_have_no_registration_window(): void
    {
        Notification::fake();
        $admin = User::factory()->create([
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);
        $branch = Branch::create(['name' => 'Cebu Branch']);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Admin Created Staff',
                'email' => 'admin.created@example.com',
                'role' => 'STAFF',
                'branch_id' => $branch->id,
            ])
            ->assertRedirect();

        $user = User::where('email', 'admin.created@example.com')->firstOrFail();
        $this->assertNull($user->registration_expires_at);
        $this->assertNull($user->registration_confirmed_at);
        $this->assertFalse($user->isRegistrationPending());
    }
}
