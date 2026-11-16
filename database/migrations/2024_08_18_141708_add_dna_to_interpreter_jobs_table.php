<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDnaToInterpreterJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */

  public function up()
{
    if (! Schema::hasColumn('interpreter_jobs', 'dna')) {
        Schema::table('interpreter_jobs', function (Blueprint $table) {
            $table->boolean('dna')->default(false)->after('status');
        });
    }
}

public function down()
{
    if (Schema::hasColumn('interpreter_jobs', 'dna')) {
        Schema::table('interpreter_jobs', function (Blueprint $table) {
            $table->dropColumn('dna');
        });
    }
}
}
