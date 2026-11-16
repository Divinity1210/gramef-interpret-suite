<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Adds lightweight, nullable columns to avoid impacting live traffic.
     */
    public function up(): void
    {
        Schema::table('interpreter_jobs', function (Blueprint $table) {
            // Paper vs electronic signature path; keep nullable to avoid heavy default writes
            $table->string('signature_method', 20)->nullable()->comment('electronic|paper');

            // Paper timesheet workflow status; nullable to avoid forced updates on existing rows
            $table->string('timesheet_status', 30)->nullable()->comment('pending_signature|signed_uploaded|verified|rejected');

            // Link to uploaded signed scan (Document model). Nullable and without FK to avoid table locking on busy DBs
            $table->unsignedBigInteger('timesheet_document_id')->nullable();

            // Audit fields for upload/verification events
            $table->unsignedBigInteger('timesheet_uploaded_by')->nullable();
            $table->timestamp('timesheet_uploaded_at')->nullable();
            $table->unsignedBigInteger('timesheet_verified_by')->nullable();
            $table->timestamp('timesheet_verified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interpreter_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'signature_method',
                'timesheet_status',
                'timesheet_document_id',
                'timesheet_uploaded_by',
                'timesheet_uploaded_at',
                'timesheet_verified_by',
                'timesheet_verified_at',
            ]);
        });
    }
};