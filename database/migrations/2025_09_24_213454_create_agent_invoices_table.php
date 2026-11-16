<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('agent_invoices')) {
            Schema::create('agent_invoices', function (Blueprint $table) {
                $table->id(); // bigIncrements
                $table->unsignedInteger('agent_id')->index();
                $table->string('invoice_number')->nullable()->index();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('expenses_total', 10, 2)->default(0);
                $table->decimal('vat_rate', 5, 2)->default(0); // e.g. 20.00
                $table->decimal('vat_amount', 10, 2)->default(0);
                $table->decimal('total', 10, 2)->default(0);
                $table->string('status')->default('pending'); // pending, submitted, reviewed, paid
                $table->dateTime('sent_to_admin_at')->nullable();
                $table->string('pdf_path')->nullable();
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
        Schema::dropIfExists('agent_invoices');
    }
}
