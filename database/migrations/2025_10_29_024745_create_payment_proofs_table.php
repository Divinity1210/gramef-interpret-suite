<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentProofsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Defensive check: only create the table if it does not already exist
        if (!Schema::hasTable('payment_proofs')) {
            Schema::create('payment_proofs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_invoice_id')->index();
                $table->unsignedInteger('client_id')->nullable()->index();
                $table->unsignedInteger('submitted_by')->nullable()->index(); // user id (client portal user or staff)

                // Flexible payment amounts and metadata
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('currency', 3)->default('GBP');
                $table->dateTime('paid_at')->nullable();
                $table->string('method')->nullable(); // bank_transfer, card, cash, etc.
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();

                // Review workflow
                $table->string('status')->default('pending'); // pending, verified, rejected
                $table->unsignedInteger('verified_by')->nullable()->index();
                $table->dateTime('verified_at')->nullable();
                $table->text('rejection_reason')->nullable();

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
        Schema::dropIfExists('payment_proofs');
    }
}
