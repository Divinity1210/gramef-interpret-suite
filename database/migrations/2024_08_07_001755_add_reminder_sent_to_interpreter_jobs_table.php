<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddReminderSentToInterpreterJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('interpreter_jobs') && !Schema::hasColumn('interpreter_jobs', 'reminder_sent')) {
            Schema::table('interpreter_jobs', function (Blueprint $table) {
                $table->boolean('reminder_sent')->default(false);
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('interpreter_jobs') && Schema::hasColumn('interpreter_jobs', 'reminder_sent')) {
            Schema::table('interpreter_jobs', function (Blueprint $table) {
                $table->dropColumn('reminder_sent');
            });
        }
    }
}
