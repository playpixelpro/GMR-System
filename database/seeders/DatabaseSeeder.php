<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $adminEmail = env('NFA_ADMIN_EMAIL', 'admin@email.com');
        $temporaryPassword = env('NFA_ADMIN_PASSWORD', 'ChangeMe!2026');

        $existingAdmin = User::where('email', $adminEmail)->first();
        if ($existingAdmin && ! $existingAdmin->must_change_password) {
            $existingAdmin
                ->forceFill([
                    'name' => 'System Administrator',
                    'role' => 'ADMINISTRATOR',
                    'is_active' => true,
                    'temporary_password_expires_at' => null,
                ])
                ->save();
        } else {
            User::updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => 'System Administrator',
                    'password' => $temporaryPassword,
                    'role' => 'ADMINISTRATOR',
                    'is_active' => true,
                    'must_change_password' => true,
                    'temporary_password_expires_at' => now()->addDays(7),
                    'activated_at' => null,
                    'disabled_at' => null,
                ],
            );
        }

        foreach (
            ['North Cotabato', 'South Cotabato', 'Sultan Kudarat'] as $branchName
        ) {
            Branch::firstOrCreate(['name' => $branchName]);
        }
    }
}
