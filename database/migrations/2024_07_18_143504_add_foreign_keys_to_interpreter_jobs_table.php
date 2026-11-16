<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddForeignKeysToInterpreterJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // First, add the missing columns (guarded to avoid errors if they already exist)
        Schema::table('interpreter_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('interpreter_jobs', 'interpreter_type_id')) {
                $table->unsignedInteger('interpreter_type_id')->nullable()->after('skill_id');
            }

            if (!Schema::hasColumn('interpreter_jobs', 'security_type_id')) {
                $table->unsignedInteger('security_type_id')->nullable()->after('interpreter_type_id');
            }
        });

        // Then, add foreign key constraints when supported (SQLite cannot add FKs via ALTER TABLE)
        if (Schema::hasTable('interpreter_types') && Schema::hasTable('security_types') && DB::getDriverName() !== 'sqlite') {
            // Guard against duplicate FK creation by checking INFORMATION_SCHEMA
            $hasFkInterpreter = false; $hasFkSecurity = false;
            try {
                $hasFkInterpreter = (int) (collect(DB::select("SELECT COUNT(1) AS c FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'interpreter_jobs' AND constraint_name = 'interpreter_jobs_interpreter_type_id_foreign' AND constraint_type = 'FOREIGN KEY'"))->first()->c ?? 0) > 0;
            } catch (\Throwable $e) { $hasFkInterpreter = false; }
            try {
                $hasFkSecurity = (int) (collect(DB::select("SELECT COUNT(1) AS c FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'interpreter_jobs' AND constraint_name = 'interpreter_jobs_security_type_id_foreign' AND constraint_type = 'FOREIGN KEY'"))->first()->c ?? 0) > 0;
            } catch (\Throwable $e) { $hasFkSecurity = false; }

            Schema::table('interpreter_jobs', function (Blueprint $table) use ($hasFkInterpreter, $hasFkSecurity) {
                if (Schema::hasColumn('interpreter_jobs', 'interpreter_type_id') && !$hasFkInterpreter) {
                    $table->foreign('interpreter_type_id')
                          ->references('id')->on('interpreter_types')
                          ->onDelete('restrict');
                }

                if (Schema::hasColumn('interpreter_jobs', 'security_type_id') && !$hasFkSecurity) {
                    $table->foreign('security_type_id')
                          ->references('id')->on('security_types')
                          ->onDelete('restrict');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop FKs when they exist and are supported
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('interpreter_jobs', function (Blueprint $table) {
                if (Schema::hasColumn('interpreter_jobs', 'interpreter_type_id')) {
                    try { $table->dropForeign(['interpreter_type_id']); } catch (\Throwable $e) { /* ignore */ }
                }
                if (Schema::hasColumn('interpreter_jobs', 'security_type_id')) {
                    try { $table->dropForeign(['security_type_id']); } catch (\Throwable $e) { /* ignore */ }
                }
            });
        }

        // Always drop columns if present
        Schema::table('interpreter_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('interpreter_jobs', 'interpreter_type_id')) {
                $table->dropColumn('interpreter_type_id');
            }
            if (Schema::hasColumn('interpreter_jobs', 'security_type_id')) {
                $table->dropColumn('security_type_id');
            }
        });
    }
}
