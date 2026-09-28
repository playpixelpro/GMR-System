<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_the_forgot_password_page(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Reset Password');
    }

    public function test_forgot_password_emails_a_reset_link_for_a_registered_user(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->post(route('password.email'), ['email' => 'user@example.com'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_does_not_leak_whether_an_email_exists(): void
    {
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('status');
    }

    public function test_guest_can_view_the_reset_password_page(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => 'user@example.com',
        ]))->assertOk()->assertSee('Reset Password');
    }

    public function test_guest_can_reset_their_password_with_a_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('OldPassword123!'),
            'must_change_password' => true,
            'activated_at' => null,
        ]);

        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertSessionHas('status', 'Your password has been reset.');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->temporary_password_expires_at);
        $this->assertNotNull($user->activated_at);
    }

    public function test_reset_password_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => 'user@example.com',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertSessionHasErrors('email');
    }
}
