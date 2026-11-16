<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDbsLevelIdToAgentsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('agents') && !Schema::hasColumn('agents', 'dbs_level_id')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->unsignedInteger('dbs_level_id')->nullable()->after('dbs_update_reference_number');
            });

            try {
                Schema::table('agents', function (Blueprint $table) {
                    $table->foreign('dbs_level_id')
                        ->references('id')->on('security_types')
                        ->onDelete('restrict');
                });
            } catch (\Throwable $e) {}
        }
    }

    public function down()
    {
        if (Schema::hasTable('agents') && Schema::hasColumn('agents', 'dbs_level_id')) {
            try {
                Schema::table('agents', function (Blueprint $table) {
                    $table->dropForeign(['dbs_level_id']);
                });
            } catch (\Throwable $e) {}

            Schema::table('agents', function (Blueprint $table) {
                $table->dropColumn('dbs_level_id');
            });
        }
    }
}
