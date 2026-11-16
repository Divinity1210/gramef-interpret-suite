<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('exports')) {
            Schema::create('exports', function (Blueprint $table) {
                $table->id();
                $table->string('type'); // invoices, payslips, remittances, bulk
                $table->string('format'); // excel, csv, pdf, zip
                $table->json('filters')->nullable(); // export filters/parameters
                $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
                $table->string('file_path')->nullable(); // path to generated file
                // Match existing users.id type (unsigned integer) and enforce FK
                $table->unsignedInteger('user_id');
                $table->timestamps();
                
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->index(['user_id', 'status']);
                $table->index('created_at');
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
        Schema::dropIfExists('exports');
    }
}
