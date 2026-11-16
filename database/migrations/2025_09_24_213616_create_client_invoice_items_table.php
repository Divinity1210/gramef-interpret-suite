<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientInvoiceItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('client_invoice_items')) {
            Schema::create('client_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_invoice_id')->index();
                $table->unsignedInteger('job_id')->nullable()->index();
                $table->unsignedInteger('agent_id')->nullable()->index();
                $table->string('description')->nullable();
                $table->decimal('amount', 10, 2)->default(0);
                $table->decimal('surcharge', 10, 2)->default(0);
                $table->decimal('other_charges', 10, 2)->default(0);
                $table->boolean('vat_applicable')->default(false);
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
        Schema::dropIfExists('client_invoice_items');
    }
}
