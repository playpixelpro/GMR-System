<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_name_and_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Updated User',
                'profile_photo' => UploadedFile::fake()->image('profile.png'),
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Updated User', $user->name);
        $this->assertNotNull($user->profile_photo_path);
        $this->assertTrue(Storage::disk('public')->exists($user->profile_photo_path));
    }

    public function test_authenticated_user_can_update_password_from_profile_settings(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CurrentPassword123!'),
            'must_change_password' => false,
            'temporary_password_expires_at' => now()->subDay(), // even if an old timestamp existed
        ]);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'current_password' => 'CurrentPassword123!',
                'password' => 'NewPermanentPass123!',
                'password_confirmation' => 'NewPermanentPass123!',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Profile updated.');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPermanentPass123!', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->temporary_password_expires_at);

        // Logging in works persistently
        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'NewPermanentPass123!',
        ])->assertRedirect(route('home'));
    }

    public function test_user_management_remains_restricted_to_administrators(): void
    {
        $user = User::factory()->create([
            'role' => 'STAFF',
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden();
    }
}
