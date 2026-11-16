<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayslipItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('payslip_items')) {
            Schema::create('payslip_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payslip_id')->index();
                $table->unsignedInteger('job_id')->nullable()->index();
                $table->enum('type', ['earning', 'deduction'])->default('earning');
                $table->string('description')->nullable();
                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('rate', 10, 2)->default(0);
                $table->decimal('amount', 10, 2)->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
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
        Schema::dropIfExists('payslip_items');
    }
}
