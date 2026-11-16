<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPerformanceIndexesToInterpreterJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('interpreter_jobs', function (Blueprint $table) {
            // Index for agent_id queries (critical for excludeDoubleBookings)
            $table->index('agent_id', 'idx_interpreter_jobs_agent_id');
            
            // Composite index for appointment_date and agent_id (for double booking checks)
            $table->index(['appointment_date', 'agent_id'], 'idx_interpreter_jobs_date_agent');
            
            // Composite index for time overlap queries
            $table->index(['appointment_date', 'start_time', 'end_time'], 'idx_interpreter_jobs_time_overlap');
            
            // Index for status queries
            $table->index('status', 'idx_interpreter_jobs_status');
            
            // Index for language queries
            $table->index('to_language_id', 'idx_interpreter_jobs_to_language');
            
            // Index for client queries
            $table->index('client_id', 'idx_interpreter_jobs_client_id');
            
            // Composite index for showableToUser queries
            $table->index(['status', 'client_id'], 'idx_interpreter_jobs_status_client');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('interpreter_jobs', function (Blueprint $table) {
            $table->dropIndex('idx_interpreter_jobs_agent_id');
            $table->dropIndex('idx_interpreter_jobs_date_agent');
            $table->dropIndex('idx_interpreter_jobs_time_overlap');
            $table->dropIndex('idx_interpreter_jobs_status');
            $table->dropIndex('idx_interpreter_jobs_to_language');
            $table->dropIndex('idx_interpreter_jobs_client_id');
            $table->dropIndex('idx_interpreter_jobs_status_client');
        });
    }
}