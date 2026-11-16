<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateInterpreterTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Prevent errors when the table already exists in legacy databases
        if (Schema::hasTable('interpreter_types')) {
            return;
        }

        Schema::create('interpreter_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type', 25);
            $table->string('name', 255);
            $table->boolean('deleted')->default(0);
            if (DB::getDriverName() === 'sqlite') {
                $table->timestamp('created_at')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('updated_at')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
            } else {
                $table->timestamp('created_at')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->timestamp('updated_at')->nullable()->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
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
        Schema::dropIfExists('interpreter_types');
    }
}
