<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThreeHourReminderSentToInterpreterJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('interpreter_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('interpreter_jobs', 'three_hour_reminder_sent')) {
                $table->boolean('three_hour_reminder_sent')->default(false)->after('reminder_sent');
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
        Schema::table('interpreter_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('interpreter_jobs', 'three_hour_reminder_sent')) {
                $table->dropColumn('three_hour_reminder_sent');
            }
        });
    }
}