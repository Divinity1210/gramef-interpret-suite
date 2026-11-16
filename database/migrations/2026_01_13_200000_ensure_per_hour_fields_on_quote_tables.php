<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnsurePerHourFieldsOnQuoteTables extends Migration
{
    public function up()
    {
        foreach (['quotes', 'admin_quotes'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columns = [
                    'interpreting_cost' => ['decimal', 6, 2],
                    'travel_time' => ['decimal', 6, 2],
                    'travel_cost' => ['decimal', 6, 2],
                    'mileage_miles' => ['decimal', 6, 2],
                    'mileage_cost' => ['decimal', 6, 2],
                ];

                foreach ($columns as $name => $spec) {
                    if (!Schema::hasColumn($tableName, $name)) {
                        $table->decimal($name, $spec[1], $spec[2])->nullable();
                    }
                }
            });
        }
    }

    public function down()
    {
        foreach (['quotes', 'admin_quotes'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            $toDrop = [];
            foreach (['interpreting_cost', 'travel_time', 'travel_cost', 'mileage_miles', 'mileage_cost'] as $col) {
                if (Schema::hasColumn($tableName, $col)) {
                    $toDrop[] = $col;
                }
            }

            if (!empty($toDrop)) {
                Schema::table($tableName, function (Blueprint $table) use ($toDrop) {
                    $table->dropColumn($toDrop);
                });
            }
        }
    }
}
