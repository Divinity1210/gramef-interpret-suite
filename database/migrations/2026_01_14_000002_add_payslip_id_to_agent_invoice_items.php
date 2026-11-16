<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPayslipIdToAgentInvoiceItems extends Migration
{
    public function up()
    {
        Schema::table('agent_invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('agent_invoice_items', 'payslip_id')) {
                $table->unsignedBigInteger('payslip_id')->nullable()->index()->after('agent_invoice_id');
            }
        });
    }

    public function down()
    {
        Schema::table('agent_invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('agent_invoice_items', 'payslip_id')) {
                $table->dropColumn('payslip_id');
            }
        });
    }
}

