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

        $adminEmail = config('nfa.admin.email');
        $temporaryPassword = config('nfa.admin.password');

        if (filled($adminEmail) && filled($temporaryPassword)) {
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
                if (strlen($temporaryPassword) < 16) {
                    throw new \RuntimeException(
                        'NFA_ADMIN_PASSWORD must contain at least 16 characters when creating the initial administrator.',
                    );
                }

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
        } elseif (! User::where('role', 'ADMINISTRATOR')->exists()) {
            throw new \RuntimeException(
                'Set NFA_ADMIN_EMAIL and NFA_ADMIN_PASSWORD before seeding the initial administrator.',
            );
        }

        foreach (
            ['North Cotabato', 'South Cotabato', 'Sultan Kudarat'] as $branchName
        ) {
            Branch::firstOrCreate(['name' => $branchName]);
        }
    }
}
