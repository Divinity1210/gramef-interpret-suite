<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnsureClientInvoiceTablesExist extends Migration
{
    public function up(): void
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
                $table->string('status')->default('pending');
                $table->dateTime('sent_at')->nullable();
                $table->string('pdf_path')->nullable();
                $table->timestamps();
            });
        }

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

    public function down(): void
    {
        // Only drop the tables if they exist (safe rollback)
        if (Schema::hasTable('client_invoice_items')) {
            Schema::drop('client_invoice_items');
        }
        if (Schema::hasTable('client_invoices')) {
            Schema::drop('client_invoices');
        }
    }
}