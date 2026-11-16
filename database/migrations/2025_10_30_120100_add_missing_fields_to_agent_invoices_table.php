<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingFieldsToAgentInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Ensure the agent_invoices table has the columns expected by the
     * current application code (controller, model, and views). We add
     * columns only if they do not already exist to keep this safe on
     * partially-migrated or legacy databases.
     */
    public function up()
    {
        Schema::table('agent_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('agent_invoices', 'invoice_date')) {
                $table->date('invoice_date')->nullable()->after('invoice_number');
            }
            if (!Schema::hasColumn('agent_invoices', 'date_from')) {
                $table->date('date_from')->nullable()->after('invoice_date');
            }
            if (!Schema::hasColumn('agent_invoices', 'date_to')) {
                $table->date('date_to')->nullable()->after('date_from');
            }

            // Monetary columns used by totals in UI and PDF
            if (!Schema::hasColumn('agent_invoices', 'vat_amount')) {
                $table->decimal('vat_amount', 10, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('agent_invoices', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->default(0)->after('vat_amount');
            }

            // Workflow timestamps
            if (!Schema::hasColumn('agent_invoices', 'submitted_at')) {
                $table->dateTime('submitted_at')->nullable()->after('total_amount');
            }
            if (!Schema::hasColumn('agent_invoices', 'approved_at')) {
                $table->dateTime('approved_at')->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('agent_invoices', 'approved_by')) {
                $table->unsignedInteger('approved_by')->nullable()->after('approved_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('agent_invoices', function (Blueprint $table) {
            // Drop newly added columns in reverse order
            if (Schema::hasColumn('agent_invoices', 'approved_by')) {
                $table->dropColumn('approved_by');
            }
            if (Schema::hasColumn('agent_invoices', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (Schema::hasColumn('agent_invoices', 'submitted_at')) {
                $table->dropColumn('submitted_at');
            }
            if (Schema::hasColumn('agent_invoices', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('agent_invoices', 'vat_amount')) {
                $table->dropColumn('vat_amount');
            }
            if (Schema::hasColumn('agent_invoices', 'date_to')) {
                $table->dropColumn('date_to');
            }
            if (Schema::hasColumn('agent_invoices', 'date_from')) {
                $table->dropColumn('date_from');
            }
            if (Schema::hasColumn('agent_invoices', 'invoice_date')) {
                $table->dropColumn('invoice_date');
            }
        });
    }
}