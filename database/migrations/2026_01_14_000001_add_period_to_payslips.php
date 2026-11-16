<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPeriodToPayslips extends Migration
{
    public function up()
    {
        Schema::table('payslips', function (Blueprint $table) {
            if (!Schema::hasColumn('payslips', 'period_start')) {
                $table->date('period_start')->nullable()->after('agent_invoice_id');
            }
            if (!Schema::hasColumn('payslips', 'period_end')) {
                $table->date('period_end')->nullable()->after('period_start');
            }
        });
    }

    public function down()
    {
        Schema::table('payslips', function (Blueprint $table) {
            if (Schema::hasColumn('payslips', 'period_start')) {
                $table->dropColumn('period_start');
            }
            if (Schema::hasColumn('payslips', 'period_end')) {
                $table->dropColumn('period_end');
            }
        });
    }
}

