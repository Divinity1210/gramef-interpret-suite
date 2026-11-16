<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRemittancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('remittances')) {
            Schema::create('remittances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_invoice_id')->index();
                $table->unsignedInteger('client_id')->index();
                $table->decimal('amount', 10, 2)->default(0);
                $table->date('payment_date')->nullable();
                $table->string('slip_path')->nullable();
                $table->string('status')->default('pending'); // pending, approved
                $table->unsignedInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->text('notes')->nullable();
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
        Schema::dropIfExists('remittances');
    }
}
