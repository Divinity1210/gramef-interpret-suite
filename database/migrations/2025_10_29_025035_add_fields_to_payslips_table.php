<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToPayslipsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payslips', function (Blueprint $table) {
            // Additive fields for richer lifecycle tracking (non-breaking)
            if (!Schema::hasColumn('payslips', 'paid_at')) {
                $table->dateTime('paid_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('payslips', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('payslips', 'payment_note')) {
                $table->text('payment_note')->nullable()->after('payment_reference');
            }

            if (!Schema::hasColumn('payslips', 'locked_by')) {
                $table->unsignedInteger('locked_by')->nullable()->after('payment_note');
            }
            if (!Schema::hasColumn('payslips', 'locked_at')) {
                $table->dateTime('locked_at')->nullable()->after('locked_by');
            }

            if (!Schema::hasColumn('payslips', 'current_revision')) {
                $table->unsignedInteger('current_revision')->nullable()->after('locked_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payslips', function (Blueprint $table) {
            $dropCols = [];
            foreach (['paid_at','payment_reference','payment_note','locked_by','locked_at','current_revision'] as $col) {
                if (Schema::hasColumn('payslips', $col)) {
                    $dropCols[] = $col;
                }
            }
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
}
