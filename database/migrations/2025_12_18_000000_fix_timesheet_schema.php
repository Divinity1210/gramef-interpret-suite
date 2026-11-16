<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixTimesheetSchema extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('timesheets', function (Blueprint $table) {
            // Fix client_phone type
            if (Schema::hasColumn('timesheets', 'client_phone')) {
                $table->string('client_phone')->nullable()->change();
            } else {
                $table->string('client_phone')->nullable();
            }

            // Add missing columns
            if (!Schema::hasColumn('timesheets', 'client_id')) {
                $table->unsignedInteger('client_id')->nullable()->index();
            }
            if (!Schema::hasColumn('timesheets', 'from_language_id')) {
                $table->unsignedInteger('from_language_id')->nullable();
            }
            if (!Schema::hasColumn('timesheets', 'to_language_id')) {
                $table->unsignedInteger('to_language_id')->nullable();
            }
            if (!Schema::hasColumn('timesheets', 'client_reference')) {
                $table->string('client_reference')->nullable();
            }
            if (!Schema::hasColumn('timesheets', 'appointment_date')) {
                $table->date('appointment_date')->nullable();
            }
            if (!Schema::hasColumn('timesheets', 'start_time')) {
                $table->time('start_time')->nullable();
            }
            if (!Schema::hasColumn('timesheets', 'end_time')) {
                $table->time('end_time')->nullable();
            }
            if (!Schema::hasColumn('timesheets', 'duration_hours')) {
                $table->integer('duration_hours')->default(0);
            }
            if (!Schema::hasColumn('timesheets', 'duration_minutes')) {
                $table->integer('duration_minutes')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('timesheets', function (Blueprint $table) {
            // Revert client_phone to bigint (not recommended but strictly reversing)
            // We won't revert it to bigint because varchar is the correct type.
            
            $columns = [
                'client_id',
                'from_language_id',
                'to_language_id',
                'client_reference',
                'appointment_date',
                'start_time',
                'end_time',
                'duration_hours',
                'duration_minutes'
            ];
            
            $toDrop = [];
            foreach ($columns as $col) {
                if (Schema::hasColumn('timesheets', $col)) {
                    $toDrop[] = $col;
                }
            }
            
            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
}
