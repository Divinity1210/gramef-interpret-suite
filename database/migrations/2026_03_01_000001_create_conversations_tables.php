<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConversationsTables extends Migration
{
    public function up()
    {
        // Main conversations table
        if (!Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }

        // Pivot table linking users to conversations
        if (!Schema::hasTable('conversation_user')) {
            Schema::create('conversation_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();

                $table->foreign('conversation_id')
                    ->references('id')
                    ->on('conversations')
                    ->onDelete('cascade');

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');

                $table->unique(['conversation_id', 'user_id']);
            });
        }

        // Messages table
        if (!Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('user_id');
                $table->text('body');
                $table->string('type', 50)->default('text');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->foreign('conversation_id')
                    ->references('id')
                    ->on('conversations')
                    ->onDelete('cascade');

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');

                $table->index('conversation_id');
                $table->index(['conversation_id', 'created_at']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_user');
        Schema::dropIfExists('conversations');
    }
}
