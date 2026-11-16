<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRetrnToInterpreterJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasColumn('interpreter_jobs', 'retrn')) {
            Schema::table('interpreter_jobs', function (Blueprint $table) {
                $table->boolean('retrn')->default(false)->after('dna');
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
        if (Schema::hasColumn('interpreter_jobs', 'retrn')) {
            Schema::table('interpreter_jobs', function (Blueprint $table) {
                $table->dropColumn('retrn');
            });
        }
    }
}
