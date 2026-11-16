<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('interpreter_jobs', 'checked_in_at')) {
            Schema::table('interpreter_jobs', function (Blueprint $table) {
                $table->timestamp('checked_in_at')->nullable()->after('status');
                $table->decimal('checked_in_lat', 10, 7)->nullable()->after('checked_in_at');
                $table->decimal('checked_in_lng', 10, 7)->nullable()->after('checked_in_lat');
                $table->timestamp('checked_out_at')->nullable()->after('checked_in_lng');
                $table->decimal('checked_out_lat', 10, 7)->nullable()->after('checked_out_at');
                $table->decimal('checked_out_lng', 10, 7)->nullable()->after('checked_out_lat');
            });
        }
    }

    public function down(): void
    {
        Schema::table('interpreter_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'checked_in_at', 'checked_in_lat', 'checked_in_lng',
                'checked_out_at', 'checked_out_lat', 'checked_out_lng',
            ]);
        });
    }
};
