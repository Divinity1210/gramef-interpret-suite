<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('client_invoices')) {
            Schema::create('client_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('client_id')->index();
                $table->string('invoice_number')->nullable()->index();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->date('date_range_start')->nullable();
                $table->date('date_range_end')->nullable();
                $table->string('template')->nullable();
                $table->boolean('include_vat')->default(false);
                $table->decimal('vat_rate', 5, 2)->default(0);
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('vat_amount', 10, 2)->default(0);
                $table->decimal('total', 10, 2)->default(0);
                $table->string('status')->default('pending'); // pending, sent, paid
                $table->dateTime('sent_at')->nullable();
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
        Schema::dropIfExists('client_invoices');
    }
}
