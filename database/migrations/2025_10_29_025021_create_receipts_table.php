<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReceiptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('receipts')) {
            Schema::create('receipts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_invoice_id')->index();
                $table->string('receipt_number')->nullable()->index();
                $table->decimal('total_paid', 10, 2)->default(0);
                $table->dateTime('issued_at')->nullable();
                $table->string('pdf_path')->nullable();
                $table->unsignedInteger('generated_by')->nullable()->index();
                // store the list of payment_proof ids included in this receipt for traceability
                $table->json('payment_proof_ids')->nullable();
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
        Schema::dropIfExists('receipts');
    }
}
