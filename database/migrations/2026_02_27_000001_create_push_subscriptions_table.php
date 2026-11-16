<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePushSubscriptionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('push_subscriptions')) {
            return; // Table already exists (created before migration tracking)
        }

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->text('endpoint');
            $table->string('p256dh_key')->nullable();
            $table->string('auth_key')->nullable();
            $table->string('platform', 20)->default('web'); // web, ios, android
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('endpoint', 'push_subs_endpoint_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('push_subscriptions');
    }
}
