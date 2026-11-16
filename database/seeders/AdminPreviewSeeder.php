<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\User;
use Spatie\Permission\Models\Role;

class AdminPreviewSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Ensure the 'admin' role exists
            $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

            // Create (or fetch) a preview admin user
            $user = User::firstOrCreate(
                ['email' => 'admin.preview@example.com'],
                [
                    'title' => 0,
                    'first_name' => 'Preview',
                    'last_name' => 'Admin',
                    // Set plain password; User model mutator will hash it once
                    'password' => 'password',
                    'enabled' => 1,
                    'email_verified_at' => now(),
                ]
            );

            // Ensure password is updated to plain 'password' (mutator hashes it once)
            // Covers case where existing user had a double-hashed password from previous seeding
            $user->update(['password' => 'password']);

            // Assign role if not already assigned
            if (! $user->hasRole('admin')) {
                $user->assignRole($adminRole);
            }
        });
    }
}