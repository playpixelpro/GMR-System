<?php

namespace Tests\Feature;

use App\Models\IpBlock;
use App\Models\User;
use App\Support\AuthThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const IP = '203.0.113.10';

    protected function loginAttempts(string $password = 'wrong-password'): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::IP])
            ->post(route('login.submit'), [
                'email' => 'user@example.com',
                'password' => $password,
            ]);
    }

    public function test_ip_is_locked_after_repeated_failed_logins(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        foreach (range(1, AuthThrottle::MAX_ATTEMPTS) as $i) {
            $this->loginAttempts();
        }

        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => self::IP,
            'reason' => AuthThrottle::REASON_LOGIN,
        ]);

        $block = IpBlock::where('ip_address', self::IP)->first();
        $this->assertTrue($block->isCurrentlyBlocked());

        // Even a correct password is rejected while the IP is locked.
        $this->loginAttempts('CorrectPassword123!')
            ->assertSessionHasErrors('email');
    }

    public function test_successful_login_clears_the_ip_lockout(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        foreach (range(1, AuthThrottle::MAX_ATTEMPTS - 1) as $i) {
            $this->loginAttempts();
        }

        $this->loginAttempts('CorrectPassword123!')
            ->assertRedirect(route('home'));

        $this->assertDatabaseMissing('blocked_ips', ['ip_address' => self::IP]);
    }

    public function test_forgot_password_endpoint_blocks_ip_after_repeated_submissions(): void
    {
        foreach (range(1, AuthThrottle::MAX_ATTEMPTS) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => self::IP])
                ->post(route('password.email'), ['email' => 'user@example.com']);
        }

        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => self::IP,
            'reason' => AuthThrottle::REASON_PASSWORD_RESET,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => self::IP])
            ->post(route('password.email'), ['email' => 'user@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_administrator_can_view_blocked_ips(): void
    {
        $admin = User::factory()->create(['role' => 'ADMINISTRATOR']);
        app(AuthThrottle::class)->recordFailure(self::IP, AuthThrottle::REASON_LOGIN);
        app(AuthThrottle::class)->recordFailure(self::IP, AuthThrottle::REASON_LOGIN);
        app(AuthThrottle::class)->recordFailure(self::IP, AuthThrottle::REASON_LOGIN);
        app(AuthThrottle::class)->recordFailure(self::IP, AuthThrottle::REASON_LOGIN);
        app(AuthThrottle::class)->recordFailure(self::IP, AuthThrottle::REASON_LOGIN);

        $this->actingAs($admin)
            ->get(route('settings.blocked-ips'))
            ->assertOk()
            ->assertSee(self::IP);
    }

    public function test_administrator_can_unlock_a_blocked_ip(): void
    {
        $admin = User::factory()->create(['role' => 'ADMINISTRATOR']);
        app(AuthThrottle::class)->recordFailure(self::IP, AuthThrottle::REASON_LOGIN);
        app(AuthThrottle::class)->recordFailure(self::IP, AuthThrottle::REASON_LOGIN);
        app(AuthThrottle::class)->recordFailure(self::IP, AuthThrottle::REASON_LOGIN);
        app(AuthThrottle::class)->recordFailure(self::IP, AuthThrottle::REASON_LOGIN);
        app(AuthThrottle::class)->recordFailure(self::IP, AuthThrottle::REASON_LOGIN);

        $this->actingAs($admin)
            ->post(route('settings.blocked-ips.unlock', self::IP))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('blocked_ips', ['ip_address' => self::IP]);
    }

    public function test_non_administrator_cannot_access_blocked_ip_settings(): void
    {
        $staff = User::factory()->create(['role' => 'STAFF']);

        $this->actingAs($staff)
            ->get(route('settings.blocked-ips'))
            ->assertForbidden();
    }
}
