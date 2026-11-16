<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repair migration: re-create agent_invoice_items table if it was dropped
 * but the original migration is marked as run. This guards against the
 * "Base table or view not found" SQLSTATE[42S02] error during invoice generation.
 */
class RecreateMissingAgentInvoiceItemsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('agent_invoice_items')) {
            Schema::create('agent_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agent_invoice_id')->index();
                $table->unsignedInteger('job_id')->nullable()->index();
                $table->string('description')->nullable();
                $table->decimal('base_amount', 10, 2)->default(0);
                $table->decimal('expenses_amount', 10, 2)->default(0);
                $table->decimal('surcharge', 10, 2)->default(0);
                $table->decimal('other_charges', 10, 2)->default(0);
                $table->boolean('vat_applicable')->default(false);
                $table->decimal('total_amount', 10, 2)->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally do not drop the table to avoid accidental data loss.
        // If needed, drop explicitly with Schema::dropIfExists('agent_invoice_items').
    }
}