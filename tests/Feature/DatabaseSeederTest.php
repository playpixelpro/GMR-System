<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_administrator_seeding_requires_explicit_credentials(): void
    {
        config()->set('nfa.admin.email', null);
        config()->set('nfa.admin.password', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Set NFA_ADMIN_EMAIL and NFA_ADMIN_PASSWORD before seeding the initial administrator.',
        );

        app(DatabaseSeeder::class)->run();
    }

    public function test_seeding_without_credentials_preserves_an_existing_administrator(): void
    {
        config()->set('nfa.admin.email', null);
        config()->set('nfa.admin.password', null);
        $administrator = User::factory()->create([
            'name' => 'Existing Administrator',
            'role' => 'ADMINISTRATOR',
            'must_change_password' => false,
        ]);

        app(DatabaseSeeder::class)->run();

        $this->assertSame('Existing Administrator', $administrator->fresh()->name);
    }

    public function test_initial_administrator_seeding_rejects_a_short_temporary_password(): void
    {
        config()->set('nfa.admin.email', 'administrator@example.com');
        config()->set('nfa.admin.password', 'ShortPassword1');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'NFA_ADMIN_PASSWORD must contain at least 16 characters when creating the initial administrator.',
        );

        app(DatabaseSeeder::class)->run();
    }
}
