<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Improve EXISTS lookups for agent-job matching
        if (Schema::hasTable('matched_agents')) {
            Schema::table('matched_agents', function (Blueprint $table) {
                // Composite index used by: WHERE job_id = ? AND job_type = ? AND agent_id = ? AND status NOT IN (...)
                $table->index(['job_id', 'job_type', 'agent_id', 'status'], 'matched_agents_job_type_agent_status_idx');
            });
        }

        // Optimize agent language pivot lookups used in EXISTS clauses
        if (Schema::hasTable('agent_language')) {
            Schema::table('agent_language', function (Blueprint $table) {
                // Index for: WHERE agent_id = ? AND language_id = to_language_id
                $table->index(['agent_id', 'language_id'], 'agent_language_agent_language_idx');
            });
        }

        // Add covering index to speed up overlap check in excludeDoubleBookings
        if (Schema::hasTable('interpreter_jobs')) {
            Schema::table('interpreter_jobs', function (Blueprint $table) {
                // Used by subquery with alias ij2: WHERE ij2.agent_id = ? AND ij2.status = ? AND ij2.appointment_date = ?
                // AND (interpreter_jobs.start_time < ij2.end_time AND interpreter_jobs.end_time > ij2.start_time)
                $table->index(['agent_id', 'status', 'appointment_date', 'start_time', 'end_time'], 'interpreter_jobs_overlap_cover_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('matched_agents')) {
            Schema::table('matched_agents', function (Blueprint $table) {
                $table->dropIndex('matched_agents_job_type_agent_status_idx');
            });
        }

        if (Schema::hasTable('agent_language')) {
            Schema::table('agent_language', function (Blueprint $table) {
                $table->dropIndex('agent_language_agent_language_idx');
            });
        }

        if (Schema::hasTable('interpreter_jobs')) {
            Schema::table('interpreter_jobs', function (Blueprint $table) {
                $table->dropIndex('interpreter_jobs_overlap_cover_idx');
            });
        }
    }
};