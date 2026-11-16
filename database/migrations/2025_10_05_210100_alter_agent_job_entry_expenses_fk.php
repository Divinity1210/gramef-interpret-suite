<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // SQLite does not support dropping foreign keys or altering column types in-place.
        // Skip this migration when using SQLite for local development/testing.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }
        // Ensure column type matches agent_job_entries.id (unsigned big integer)
        // Drop existing FK if present to avoid duplicate constraint errors
        try {
            $hasFk = (int) (collect(DB::select("SELECT COUNT(1) AS c FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'agent_job_entry_expenses' AND constraint_name = 'agent_job_entry_expenses_agent_job_entry_id_foreign' AND constraint_type = 'FOREIGN KEY'"))->first()->c ?? 0) > 0;
        } catch (\Throwable $e) { $hasFk = false; }
        if ($hasFk) {
            Schema::table('agent_job_entry_expenses', function (Blueprint $table) {
                try { $table->dropForeign(['agent_job_entry_id']); } catch (\Throwable $e) {}
            });
        }

        Schema::table('agent_job_entry_expenses', function (Blueprint $table) {
            try { $table->unsignedBigInteger('agent_job_entry_id')->change(); } catch (\Throwable $e) {}
        });

        Schema::table('agent_job_entry_expenses', function (Blueprint $table) {
            try {
                $table->foreign('agent_job_entry_id')
                    ->references('id')->on('agent_job_entries')
                    ->onDelete('cascade');
            } catch (\Throwable $e) { /* ignore if already exists */ }
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }
        // Only attempt to drop FK if it exists
        try {
            $hasFk = (int) (collect(DB::select("SELECT COUNT(1) AS c FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'agent_job_entry_expenses' AND constraint_name = 'agent_job_entry_expenses_agent_job_entry_id_foreign' AND constraint_type = 'FOREIGN KEY'"))->first()->c ?? 0) > 0;
        } catch (\Throwable $e) { $hasFk = false; }
        Schema::table('agent_job_entry_expenses', function (Blueprint $table) use ($hasFk) {
            if ($hasFk) {
                try { $table->dropForeign(['agent_job_entry_id']); } catch (\Throwable $e) {}
            }
            try { $table->unsignedInteger('agent_job_entry_id')->change(); } catch (\Throwable $e) {}
        });
    }
};