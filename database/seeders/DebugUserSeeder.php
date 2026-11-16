<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\User;

class DebugUserSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $email = env('DEBUG_SEED_USER_EMAIL', 'admin@example.com');
            $password = env('DEBUG_SEED_USER_PASSWORD', 'password');
            $firstName = env('DEBUG_SEED_USER_FIRST_NAME', 'Debug');
            $lastName = env('DEBUG_SEED_USER_LAST_NAME', 'Admin');

            // Create or update the debug user
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'title' => 0,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    // Plain password; App\User mutator will hash it
                    'password' => $password,
                    'enabled' => 1,
                    'email_verified_at' => now(),
                ]
            );

            // Ensure password is reset to the provided value (handles re-runs)
            $user->update(['password' => $password]);

            // Assign 'admin' role if available
            try {
                if (class_exists('Spatie\\Permission\\Models\\Role')) {
                    $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
                    if (! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
                        $user->assignRole($adminRole);
                    }
                }
            } catch (\Throwable $e) {
                // Continue silently if roles package is not configured
            }
        });
    }
}