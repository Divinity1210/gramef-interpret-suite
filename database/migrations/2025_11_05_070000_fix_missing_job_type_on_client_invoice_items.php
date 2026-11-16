<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add job_type column if it's missing
        if (!Schema::hasColumn('client_invoice_items', 'job_type')) {
            Schema::table('client_invoice_items', function (Blueprint $table) {
                $table->string('job_type', 64)->nullable()->after('job_id');
            });
        }

        // Best-effort indexes (ignore if they already exist)
        try {
            Schema::table('client_invoice_items', function (Blueprint $table) {
                $table->index('job_type', 'client_invoice_items_job_type_index');
            });
        } catch (\Throwable $e) { /* ignore duplicate index errors */ }

        try {
            Schema::table('client_invoice_items', function (Blueprint $table) {
                $table->index(['job_type', 'job_id'], 'client_invoice_items_job_type_job_id_index');
            });
        } catch (\Throwable $e) { /* ignore duplicate index errors */ }
    }

    public function down(): void
    {
        // Drop indexes and column if present
        try {
            Schema::table('client_invoice_items', function (Blueprint $table) {
                try { $table->dropIndex('client_invoice_items_job_type_index'); } catch (\Throwable $e) {}
                try { $table->dropIndex('client_invoice_items_job_type_job_id_index'); } catch (\Throwable $e) {}
                if (Schema::hasColumn('client_invoice_items', 'job_type')) {
                    $table->dropColumn('job_type');
                }
            });
        } catch (\Throwable $e) { /* ignore */ }
    }
};