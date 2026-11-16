<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('bank_name');
            $table->string('account_holder')->nullable();
            // Encrypted sensitive fields
            $table->text('account_number');
            $table->text('sort_code')->nullable();
            // Consent and retention metadata
            $table->boolean('consent')->default(false);
            $table->timestamp('consent_at')->nullable();
            $table->date('retain_until')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};