<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class FilamentAdminUserSeeder extends Seeder
{
    /**
     * Seed the application's database with a Filament admin user.
     */
    public function run(): void
    {
        $email = env('FILAMENT_ADMIN_EMAIL');
        $password = env('FILAMENT_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('FILAMENT_ADMIN_EMAIL or FILAMENT_ADMIN_PASSWORD not set. Skipping Filament admin seeding.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                // The "hashed" cast on the User model will hash this value.
                'password' => $password,
            ],
        );

        $this->command?->info("Filament admin user seeded for email: {$email}");
    }
}
