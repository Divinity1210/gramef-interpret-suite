<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AlterAgentInvoicesStatusToString extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration normalizes the agent_invoices.status column to a varchar,
     * because some databases created this column as an integer/boolean in older
     * installs. The new application logic expects human-readable string values
     * such as: draft, submitted, sent, paid, cancelled.
     *
     * We first alter the column type using a raw SQL statement (so we don't
     * require doctrine/dbal), then map legacy numeric codes to their string
     * equivalents when present.
     */
    public function up()
    {
        // 1) Ensure the column type is VARCHAR(20)
        try {
            $driver = config('database.connections.' . config('database.default') . '.driver');
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `agent_invoices` MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'draft'");
            } else {
                // For SQLite or other drivers, skip altering type; continue with value normalization below.
                // SQLite is flexible with types and will accept string values in an integer column.
            }
        } catch (\Throwable $e) {
            // If the table doesn't exist or the column is already the right type,
            // fail gracefully so other migrations can continue.
            Log::warning('Migration alter agent_invoices.status to string warning: '.$e->getMessage());
        }

        // 2) Map legacy numeric codes to strings, if any rows still contain numbers
        //    These mappings reflect the UI filters currently exposed in the app.
        $mappings = [
            '0' => 'draft',
            '1' => 'submitted',
            '2' => 'sent',
            '3' => 'paid',
            '4' => 'cancelled',
        ];

        foreach ($mappings as $numeric => $string) {
            try {
                DB::table('agent_invoices')->where('status', $numeric)->update(['status' => $string]);
            } catch (\Throwable $e) {
                // Continue even if mapping fails on some environments
                Log::warning("Agent invoice status mapping {$numeric} => {$string} failed: ".$e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * We won't attempt to revert string values to integers since that would
     * be lossy in some environments. This down() simply leaves the column as-is.
     */
    public function down()
    {
        // No-op on purpose. If you really need to revert, write an explicit
        // migration suited to your environment.
    }
}