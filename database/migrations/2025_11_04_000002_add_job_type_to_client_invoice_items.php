<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_invoice_items', function (Blueprint $table) {
            // Add column only if it doesn't already exist
            if (!Schema::hasColumn('client_invoice_items', 'job_type')) {
                $table->string('job_type', 64)->nullable()->after('job_id');
            }
        });

        // Add indexes defensively (skip if they already exist)
        try {
            $exists = collect(DB::select("
                SELECT COUNT(1) AS c
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'client_invoice_items'
                  AND INDEX_NAME = 'client_invoice_items_job_type_index'
            "))->first()->c ?? 0;
            if (!$exists) {
                Schema::table('client_invoice_items', function (Blueprint $table) {
                    $table->index('job_type');
                });
            }
        } catch (\Throwable $e) {
            // Silently ignore if database doesn't support INFORMATION_SCHEMA or index already exists
        }

        try {
            $exists = collect(DB::select("
                SELECT COUNT(1) AS c
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'client_invoice_items'
                  AND INDEX_NAME = 'client_invoice_items_job_type_job_id_index'
            "))->first()->c ?? 0;
            if (!$exists) {
                Schema::table('client_invoice_items', function (Blueprint $table) {
                    $table->index(['job_type', 'job_id']);
                });
            }
        } catch (\Throwable $e) {
            // Ignore in non-MySQL databases
        }
    }

    public function down(): void
    {
        // Drop indexes defensively
        try {
            Schema::table('client_invoice_items', function (Blueprint $table) {
                if (Schema::hasColumn('client_invoice_items', 'job_type')) {
                    // Drop indexes only if the column exists
                    try { $table->dropIndex('client_invoice_items_job_type_index'); } catch (\Throwable $e) {}
                    try { $table->dropIndex('client_invoice_items_job_type_job_id_index'); } catch (\Throwable $e) {}
                }
            });
        } catch (\Throwable $e) {}

        // Finally drop column if present
        Schema::table('client_invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('client_invoice_items', 'job_type')) {
                $table->dropColumn('job_type');
            }
        });
    }
};