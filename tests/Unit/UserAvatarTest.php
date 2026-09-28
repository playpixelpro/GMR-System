<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    public function test_local_avatar_takes_priority_over_gravatar(): void
    {
        Storage::fake('public');
        config(['services.gravatar.enabled' => true]);

        $user = new User([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'profile_photo_path' => 'profile-photos/avatar.png',
        ]);

        $this->assertStringContainsString('profile-photos/avatar.png', $user->avatar_url);
        $this->assertStringNotContainsString('gravatar.com', $user->avatar_url);
    }

    public function test_avatar_url_returns_gravatar_when_no_local_avatar(): void
    {
        config(['services.gravatar.enabled' => true]);

        $user = new User(['name' => 'John Doe', 'email' => 'john@example.com']);

        $this->assertStringStartsWith('https://www.gravatar.com/avatar/', $user->avatar_url);
        $this->assertStringContainsString(hash('sha256', 'john@example.com'), $user->avatar_url);
    }

    public function test_gravatar_disabled_returns_null(): void
    {
        config(['services.gravatar.enabled' => false]);

        $user = new User(['name' => 'John Doe', 'email' => 'john@example.com']);

        $this->assertNull($user->avatar_url);
        $this->assertNull($user->gravatarUrl());
    }

    public function test_email_is_normalized_before_hashing(): void
    {
        config(['services.gravatar.enabled' => true]);

        $user = new User(['name' => 'John Doe', 'email' => '  Foo@Bar.COM  ']);

        $this->assertSame(hash('sha256', 'foo@bar.com'), $user->gravatarEmailHash());
        $this->assertStringContainsString(hash('sha256', 'foo@bar.com'), $user->avatar_url);
    }

    public function test_correct_sha256_hash_is_generated(): void
    {
        $user = new User(['name' => 'John Doe', 'email' => 'user@example.com']);

        $this->assertSame(hash('sha256', 'user@example.com'), $user->gravatarEmailHash());
    }

    public function test_gravatar_url_includes_size_and_default_fallback(): void
    {
        config([
            'services.gravatar.enabled' => true,
            'services.gravatar.size' => 120,
            'services.gravatar.default' => '404',
        ]);

        $user = new User(['name' => 'John Doe', 'email' => 'john@example.com']);

        $this->assertStringContainsString('s=120', $user->gravatarUrl());
        $this->assertStringContainsString('d=404', $user->gravatarUrl());
    }

    public function test_fallback_order_is_local_then_gravatar_then_default(): void
    {
        Storage::fake('public');
        config(['services.gravatar.enabled' => true]);

        $withLocal = new User([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'profile_photo_path' => 'profile-photos/a.png',
        ]);
        $withGravatar = new User(['name' => 'John Doe', 'email' => 'john@example.com']);
        $withoutAny = new User(['name' => 'John Doe', 'email' => 'john@example.com']);

        $this->assertStringContainsString('profile-photos/a.png', $withLocal->avatar_url);
        $this->assertStringContainsString('gravatar.com', $withGravatar->avatar_url);

        config(['services.gravatar.enabled' => false]);
        $this->assertNull($withoutAny->avatar_url);
    }
}
