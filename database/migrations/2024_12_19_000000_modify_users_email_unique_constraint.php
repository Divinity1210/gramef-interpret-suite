<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ModifyUsersEmailUniqueConstraint extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $driver = DB::getDriverName();
        // Only PostgreSQL supports partial indexes in the simple form used below.
        // For MySQL and SQLite, skip making any changes and keep the existing unique(email) constraint.
        if ($driver !== 'pgsql') {
            return;
        }

        // PostgreSQL path: replace the original unique(email) with a partial unique index for enabled users
        Schema::table('users', function (Blueprint $table) {
            try {
                $table->dropUnique(['email']);
            } catch (\Throwable $e) {
                // In case the unique constraint name differs or was already removed, continue
            }
        });

        // Create a partial unique index that only applies to enabled users (PostgreSQL only)
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_email_enabled_unique ON users (email) WHERE enabled = 1');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $driver = DB::getDriverName();
        // Only revert on PostgreSQL where we created the partial unique index.
        if ($driver !== 'pgsql') {
            return;
        }

        // Drop the partial unique index (if it exists)
        try {
            DB::statement('DROP INDEX IF EXISTS users_email_enabled_unique');
        } catch (\Throwable $e) {
            // ignore
        }

        Schema::table('users', function (Blueprint $table) {
            // Restore the original unique constraint on email
            try {
                $table->unique('email');
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }
}