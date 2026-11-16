<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\User;
use App\Helpers\RoleHelper;

class ResetUserPassword extends Command
{
    protected $signature = 'user:reset-password {email} {password} {--enable} {--verify} {--as-admin}';
    protected $description = 'Reset a user\'s password (and optionally enable, verify, and assign admin role)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        /** @var \App\User|null $user */
        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->warn("User not found. Creating new user for {$email}.");
            $user = User::create([
                'email' => $email,
                'first_name' => 'Debug',
                'last_name' => 'User',
                'title' => 0,
                'enabled' => 1,
                'email_verified_at' => now(),
                'password' => $password,
            ]);
        } else {
            $user->update(['password' => $password]);
        }

        if ($this->option('enable')) {
            $user->update(['enabled' => 1]);
        }
        if ($this->option('verify')) {
            $user->update(['email_verified_at' => now()]);
        }

        if ($this->option('as-admin')) {
            try {
                if (class_exists('Spatie\\Permission\\Models\\Role')) {
                    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
                    if (!RoleHelper::hasRole($user, 'admin')) {
                        $user->assignRole($role);
                    }
                }
            } catch (\Throwable $e) {
                $this->warn('Could not assign admin role (roles package not configured?).');
            }
        }

        $this->info("Password reset complete for {$email}.");
        return self::SUCCESS;
    }
}