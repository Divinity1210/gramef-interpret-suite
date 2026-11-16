<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayslipRevisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payslip_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payslip_id')->index();
            $table->unsignedInteger('version')->default(1)->index();
            $table->unsignedInteger('created_by')->nullable()->index();
            $table->json('snapshot'); // items + totals + metadata at the time of save
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payslip_revisions');
    }
}
