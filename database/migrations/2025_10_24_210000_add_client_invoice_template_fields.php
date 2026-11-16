<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddClientInvoiceTemplateFields extends Migration
{
    public function up(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('client_invoices', 'invoice_date')) {
                $table->date('invoice_date')->nullable()->after('invoice_number');
            }
            if (!Schema::hasColumn('client_invoices', 'payment_terms_text')) {
                $table->string('payment_terms_text')->nullable()->after('invoice_date');
            }
            if (!Schema::hasColumn('client_invoices', 'file_reference')) {
                $table->string('file_reference')->nullable()->after('payment_terms_text');
            }
            if (!Schema::hasColumn('client_invoices', 'client_reference')) {
                $table->string('client_reference')->nullable()->after('file_reference');
            }
            if (!Schema::hasColumn('client_invoices', 'patient_number')) {
                $table->string('patient_number')->nullable()->after('client_reference');
            }
            if (!Schema::hasColumn('client_invoices', 'pid')) {
                $table->string('pid')->nullable()->after('patient_number');
            }
            if (!Schema::hasColumn('client_invoices', 'interpreting_time_minutes')) {
                $table->unsignedInteger('interpreting_time_minutes')->nullable()->after('pid');
            }
            if (!Schema::hasColumn('client_invoices', 'travel_time_minutes')) {
                $table->unsignedInteger('travel_time_minutes')->nullable()->after('interpreting_time_minutes');
            }
            if (!Schema::hasColumn('client_invoices', 'parking_amount')) {
                $table->decimal('parking_amount', 10, 2)->default(0)->after('travel_time_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('client_invoices', 'parking_amount')) {
                $table->dropColumn('parking_amount');
            }
            if (Schema::hasColumn('client_invoices', 'travel_time_minutes')) {
                $table->dropColumn('travel_time_minutes');
            }
            if (Schema::hasColumn('client_invoices', 'interpreting_time_minutes')) {
                $table->dropColumn('interpreting_time_minutes');
            }
            if (Schema::hasColumn('client_invoices', 'pid')) {
                $table->dropColumn('pid');
            }
            if (Schema::hasColumn('client_invoices', 'patient_number')) {
                $table->dropColumn('patient_number');
            }
            if (Schema::hasColumn('client_invoices', 'client_reference')) {
                $table->dropColumn('client_reference');
            }
            if (Schema::hasColumn('client_invoices', 'file_reference')) {
                $table->dropColumn('file_reference');
            }
            if (Schema::hasColumn('client_invoices', 'payment_terms_text')) {
                $table->dropColumn('payment_terms_text');
            }
            if (Schema::hasColumn('client_invoices', 'invoice_date')) {
                $table->dropColumn('invoice_date');
            }
        });
    }
}