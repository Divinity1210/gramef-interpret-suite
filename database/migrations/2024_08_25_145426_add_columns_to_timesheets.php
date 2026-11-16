<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnsToTimesheets extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('timesheets', function (Blueprint $table) {
            if (! Schema::hasColumn('timesheets', 'agent_start_time')) {
                $table->integer('agent_start_time')->nullable()->default(0);
            }
            if (! Schema::hasColumn('timesheets', 'agent_end_time')) {
                $table->integer('agent_end_time')->nullable()->default(0);
            }
            if (! Schema::hasColumn('timesheets', 'client_phone')) {
                $table->integer('client_phone')->nullable();
            }
            if (! Schema::hasColumn('timesheets', 'client_name')) {
                $table->string('client_name')->nullable();
            }
            if (! Schema::hasColumn('timesheets', 'client_designation')) {
                $table->string('client_designation')->nullable();
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
            $columns = ['agent_start_time', 'agent_end_time', 'client_phone', 'client_name', 'client_designation'];
            $existing = array_filter($columns, function ($col) {
                return Schema::hasColumn('timesheets', $col);
            });
            if (! empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
}
