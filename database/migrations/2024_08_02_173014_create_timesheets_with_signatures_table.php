<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTimesheetsWithSignaturesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // If the timesheets table already exists (from previous migrations), skip creation
        if (Schema::hasTable('timesheets')) {
            return;
        }

        Schema::create('timesheets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('agent_id'); // Use unsignedInteger
            $table->unsignedInteger('job_id');    // Use unsignedInteger
            $table->string('status');
            $table->longText('agent_signature')->nullable();
            ;
            $table->longText('client_signature')->nullable();
            ;
            $table->string('agent_status')->nullable()->default('N');
            $table->string('client_status')->nullable()->default('N');
            $table->integer('agent_duration_hours')->default(0);
            $table->integer('agent_duration_minutes')->default(0);
            $table->integer('client_duration_hours')->default(0);
            $table->integer('client_duration_minutes')->default(0);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->foreign('job_id')->references('id')->on('interpreter_jobs')->onDelete('cascade');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('timesheets')) {
            return;
        }

        Schema::table('timesheets', function (Blueprint $table) {
            // Drop FKs only if present
            if (Schema::hasColumn('timesheets', 'agent_id')) {
                $table->dropForeign(['agent_id']);
            }
            if (Schema::hasColumn('timesheets', 'job_id')) {
                $table->dropForeign(['job_id']);
            }
        });

        Schema::dropIfExists('timesheets');
    }
}