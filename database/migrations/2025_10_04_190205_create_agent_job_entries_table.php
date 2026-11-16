<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentJobEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('agent_job_entries')) {
            Schema::create('agent_job_entries', function (Blueprint $table) {
                $table->id();
                $table->integer('agent_id')->unsigned();
                $table->integer('job_id')->unsigned();
                $table->string('job_type'); // 'interpreter_job' or 'translator_job'
                $table->decimal('base_amount', 10, 2)->default(0);
                $table->decimal('expenses', 10, 2)->default(0);
                $table->decimal('surcharge', 10, 2)->default(0);
                $table->decimal('other_charges', 10, 2)->default(0);
                $table->decimal('total_amount', 10, 2)->default(0);
                $table->text('notes')->nullable();
                $table->boolean('invoiced')->default(false);
                $table->timestamps();
                
                // Foreign key constraints
                $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
                
                // Indexes for performance
                $table->index(['agent_id', 'invoiced']);
                $table->index(['job_id', 'job_type']);
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
        Schema::dropIfExists('agent_job_entries');
    }
}
