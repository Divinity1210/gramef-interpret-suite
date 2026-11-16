<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayslipsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('payslips')) {
            Schema::create('payslips', function (Blueprint $table) {
                $table->id();
                $table->string('payslip_number')->nullable()->index();
                $table->unsignedInteger('agent_id')->index();
                $table->unsignedBigInteger('agent_invoice_id')->nullable()->index();
                $table->json('items')->nullable(); // line items snapshot (job_id, description, amount, adjustments)
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('adjustments_total', 10, 2)->default(0);
                $table->decimal('total', 10, 2)->default(0);
                $table->string('status')->default('pending'); // pending, approved, paid
                $table->json('bank_snapshot')->nullable(); // agent bank details at time of payment
                $table->unsignedInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->string('pdf_path')->nullable();
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
        Schema::dropIfExists('payslips');
    }
}
