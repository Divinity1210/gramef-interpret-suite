<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Subject of the audit event (e.g., whose data was accessed)
            $table->unsignedInteger('user_id')->nullable();
            // Actor who performed the action
            $table->unsignedInteger('actor_id')->nullable();
            $table->string('action'); // view, create, update, delete, snapshot
            $table->string('entity_type')->nullable(); // e.g., BankAccount, Payslip
            $table->unsignedInteger('entity_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};