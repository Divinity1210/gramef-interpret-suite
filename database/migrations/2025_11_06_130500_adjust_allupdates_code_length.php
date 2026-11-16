<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdjustAllupdatesCodeLength extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (!Schema::hasTable('allupdates')) {
            return;
        }

        // Widen the `code` column to safely accommodate legacy values like
        // "Assigned/Completed" without truncation errors.
        try {
            Schema::table('allupdates', function (Blueprint $table) {
                // Change to VARCHAR(30) while keeping existing data
                $table->string('code', 30)->change();
            });
        } catch (\Throwable $e) {
            // Fallback for environments where change() isn't supported
            try {
                $driver = config('database.connections.' . config('database.default') . '.driver');
                if ($driver === 'mysql') {
                    DB::statement('ALTER TABLE allupdates MODIFY COLUMN code VARCHAR(30)');
                }
                // For sqlite or other drivers, rely on application-side truncation
            } catch (\Throwable $e2) {
                // Log and continue without failing deploys
                Log::warning('Failed to adjust allupdates.code length', [
                    'primary_error' => $e->getMessage(),
                    'fallback_error' => $e2->getMessage(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (!Schema::hasTable('allupdates')) {
            return;
        }

        // Revert back to CHAR/VARCHAR(15) if needed
        try {
            Schema::table('allupdates', function (Blueprint $table) {
                $table->string('code', 15)->change();
            });
        } catch (\Throwable $e) {
            try {
                $driver = config('database.connections.' . config('database.default') . '.driver');
                if ($driver === 'mysql') {
                    DB::statement('ALTER TABLE allupdates MODIFY COLUMN code VARCHAR(15)');
                }
            } catch (\Throwable $e2) {
                Log::warning('Failed to revert allupdates.code length', [
                    'primary_error' => $e->getMessage(),
                    'fallback_error' => $e2->getMessage(),
                ]);
            }
        }
    }
}