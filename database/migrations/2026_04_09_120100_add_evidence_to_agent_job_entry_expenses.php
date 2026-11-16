<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('agent_job_entry_expenses') && !Schema::hasColumn('agent_job_entry_expenses', 'evidence_path')) {
            Schema::table('agent_job_entry_expenses', function (Blueprint $table) {
                $table->string('evidence_path')->nullable()->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('agent_job_entry_expenses', 'evidence_path')) {
            Schema::table('agent_job_entry_expenses', function (Blueprint $table) {
                $table->dropColumn('evidence_path');
            });
        }
    }
};
