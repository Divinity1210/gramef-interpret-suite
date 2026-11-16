<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class EnsureDocumentsPolymorphicColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'documentable_id')) {
                $table->integer('documentable_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('documents', 'documentable_type')) {
                $table->string('documentable_type')->nullable()->after('documentable_id');
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
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'documentable_type')) {
                $table->dropColumn('documentable_type');
            }
            if (Schema::hasColumn('documents', 'documentable_id')) {
                $table->dropColumn('documentable_id');
            }
        });
    }
}