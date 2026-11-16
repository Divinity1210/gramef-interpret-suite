<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBhulkIdToInterpreterJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasColumn('interpreter_jobs', 'bulk_id')) {
            Schema::table('interpreter_jobs', function (Blueprint $table) {
                $table->unsignedBigInteger('bulk_id')->nullable()->after('retrn');
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
        if (Schema::hasColumn('interpreter_jobs', 'bulk_id')) {
            Schema::table('interpreter_jobs', function (Blueprint $table) {
                $table->dropColumn('bulk_id');
            });
        }
    }
}