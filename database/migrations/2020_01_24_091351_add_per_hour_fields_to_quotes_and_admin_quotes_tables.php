<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPerHourFieldsToQuotesAndAdminQuotesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->decimal('interpreting_cost', 6, 2)->nullable();
            $table->decimal('travel_time', 6, 2)->nullable();
            $table->decimal('travel_cost', 6, 2)->nullable();
            $table->decimal('mileage_miles', 6, 2)->nullable();
            $table->decimal('mileage_cost', 6, 2)->nullable();
            // Group dropColumn operations to a single call to satisfy SQLite limitations
            $table->dropColumn(['mileage', 'mileage_description', 'expenses', 'expenses_description']);
        });

        Schema::table('admin_quotes', function (Blueprint $table) {
            $table->decimal('interpreting_cost', 6, 2)->nullable();
            $table->decimal('travel_time', 6, 2)->nullable();
            $table->decimal('travel_cost', 6, 2)->nullable();
            $table->decimal('mileage_miles', 6, 2)->nullable();
            $table->decimal('mileage_cost', 6, 2)->nullable();
            // Group dropColumn operations to a single call to satisfy SQLite limitations
            $table->dropColumn(['mileage', 'mileage_description', 'expenses', 'expenses_description']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quotes', function (Blueprint $table) {
            // Group dropColumn operations to a single call
            $table->dropColumn(['interpreting_cost', 'travel_time', 'travel_cost', 'mileage_miles', 'mileage_cost']);
            $table->decimal('mileage', 6, 2)->nullable();
            $table->text('mileage_description')->nullable();
            $table->decimal('expenses', 5, 2)->nullable();
            $table->text('expenses_description')->nullable();
        });

        Schema::table('admin_quotes', function (Blueprint $table) {
            // Group dropColumn operations to a single call
            $table->dropColumn(['interpreting_cost', 'travel_time', 'travel_cost', 'mileage_miles', 'mileage_cost']);
            $table->decimal('mileage', 6, 2)->nullable();
            $table->text('mileage_description')->nullable();
            $table->decimal('expenses', 5, 2)->nullable();
            $table->text('expenses_description')->nullable();
        });
    }
}
