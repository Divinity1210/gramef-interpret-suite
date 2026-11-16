<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    public function up(): void
    {
        // Only run on MySQL; skip for SQLite and others
        $driver = config('database.connections.' . config('database.default') . '.driver');
        if ($driver !== 'mysql') {
            return;
        }
        // Only run if table exists and column is BIGINT
        if (Schema::hasTable('bank_accounts')) {
            // Drop existing foreign key if any
            try { DB::statement('ALTER TABLE bank_accounts DROP FOREIGN KEY bank_accounts_user_id_foreign'); } catch (\Throwable $e) {}
            // Modify column type to INT UNSIGNED to match users.id
            DB::statement('ALTER TABLE bank_accounts MODIFY user_id INT UNSIGNED NOT NULL');
            // Add FK constraint back
            try {
                DB::statement('ALTER TABLE bank_accounts ADD CONSTRAINT bank_accounts_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
            } catch (\Throwable $e) {
                // ignore if cannot add; column type is at least corrected
                Log::warning('Could not re-add FK bank_accounts_user_id_foreign after type change: '.$e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // Only run on MySQL; skip for SQLite and others
        $driver = config('database.connections.' . config('database.default') . '.driver');
        if ($driver !== 'mysql') {
            return;
        }
        // Revert to BIGINT UNSIGNED (if needed)
        if (Schema::hasTable('bank_accounts')) {
            try { DB::statement('ALTER TABLE bank_accounts DROP FOREIGN KEY bank_accounts_user_id_foreign'); } catch (\Throwable $e) {}
            DB::statement('ALTER TABLE bank_accounts MODIFY user_id BIGINT UNSIGNED NOT NULL');
        }
    }
};