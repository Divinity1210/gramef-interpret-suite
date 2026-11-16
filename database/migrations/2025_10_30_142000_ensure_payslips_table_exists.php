<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnsurePayslipsTableExists extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payslips')) {
            Schema::create('payslips', function (Blueprint $table) {
                $table->id();
                $table->string('payslip_number')->nullable()->index();
                $table->unsignedInteger('agent_id')->index();
                $table->unsignedBigInteger('agent_invoice_id')->nullable()->index();
                $table->json('items')->nullable();
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('adjustments_total', 10, 2)->default(0);
                $table->decimal('total', 10, 2)->default(0);
                $table->string('status')->default('pending');
                $table->json('bank_snapshot')->nullable();
                $table->unsignedInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->string('pdf_path')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // No-op: Do not drop on rollback if it existed before
    }
}