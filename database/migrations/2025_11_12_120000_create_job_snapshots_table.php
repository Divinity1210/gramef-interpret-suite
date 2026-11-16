<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('job_id');
            $table->unsignedBigInteger('timesheet_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('hash', 64); // sha256
            $table->string('source', 50)->nullable(); // assignment | print | manual
            $table->longText('data'); // canonical JSON snapshot
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['job_id', 'version']);
            $table->index(['timesheet_id']);
            $table->index(['created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_snapshots');
    }
};