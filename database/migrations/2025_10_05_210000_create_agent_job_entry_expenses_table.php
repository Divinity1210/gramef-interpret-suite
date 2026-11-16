<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('agent_job_entry_expenses')) {
            Schema::create('agent_job_entry_expenses', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('agent_job_entry_id');
                $table->string('type')->nullable();
                $table->decimal('amount', 10, 2)->default(0);
                $table->timestamps();

                $table->foreign('agent_job_entry_id')
                    ->references('id')->on('agent_job_entries')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_job_entry_expenses');
    }
};