<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('timesheets')) {
            return; // safety: table must exist
        }

        Schema::table('timesheets', function (Blueprint $table) {
            // Add job_id for relationship to interpreter_jobs
            if (!Schema::hasColumn('timesheets', 'job_id')) {
                $table->unsignedInteger('job_id')->nullable()->after('agent_id');
                $table->index('job_id', 'timesheets_job_id_index');
                // FK optional; keep as index to avoid legacy type mismatches
                // $table->foreign('job_id')->references('id')->on('interpreter_jobs')->onDelete('cascade');
            }

            // Add signature columns
            if (!Schema::hasColumn('timesheets', 'agent_signature')) {
                $table->longText('agent_signature')->nullable()->after('status');
            }
            if (!Schema::hasColumn('timesheets', 'client_signature')) {
                $table->longText('client_signature')->nullable()->after('agent_signature');
            }

            // Add agent/client status flags
            if (!Schema::hasColumn('timesheets', 'agent_status')) {
                $table->string('agent_status')->nullable()->default('N')->after('client_signature');
            }
            if (!Schema::hasColumn('timesheets', 'client_status')) {
                $table->string('client_status')->nullable()->default('N')->after('agent_status');
            }

            // Add agent/client duration fields (distinct from any legacy duration columns)
            if (!Schema::hasColumn('timesheets', 'agent_duration_hours')) {
                $table->integer('agent_duration_hours')->default(0)->after('client_status');
            }
            if (!Schema::hasColumn('timesheets', 'agent_duration_minutes')) {
                $table->integer('agent_duration_minutes')->default(0)->after('agent_duration_hours');
            }
            if (!Schema::hasColumn('timesheets', 'client_duration_hours')) {
                $table->integer('client_duration_hours')->default(0)->after('agent_duration_minutes');
            }
            if (!Schema::hasColumn('timesheets', 'client_duration_minutes')) {
                $table->integer('client_duration_minutes')->default(0)->after('client_duration_hours');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('timesheets')) {
            return;
        }

        Schema::table('timesheets', function (Blueprint $table) {
            // Drop index before column removal
            if (Schema::hasColumn('timesheets', 'job_id')) {
                $table->dropIndex('timesheets_job_id_index');
            }

            foreach ([
                'job_id',
                'agent_signature',
                'client_signature',
                'agent_status',
                'client_status',
                'agent_duration_hours',
                'agent_duration_minutes',
                'client_duration_hours',
                'client_duration_minutes',
            ] as $col) {
                if (Schema::hasColumn('timesheets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};