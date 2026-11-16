<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateAllUpdatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up()
    {
        // Guard against existing table to prevent migration failures in legacy databases
        if (Schema::hasTable('allupdates')) {
            return;
        }

        Schema::create('allupdates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('user_type', 255);
            $table->unsignedInteger('job_id');
            $table->string('job_type', 255);
            $table->unsignedInteger('agent_id');
            $table->unsignedTinyInteger('new_status');
            $table->char('code', 15);
            $table->date('update_time');
            $table->dateTime('update_date');
            $table->char('comment', 100)->nullable()->default(null);
            $table->enum('deleted', ['Y', 'N'])->default('N');
            $table->timestamps();

            // Indexes
            $table->index('user_id', 'fk_all_updates_user_id');
            $table->index('user_type', 'fk_all_updates_agent_type_roles_name');

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // Note: We intentionally avoid a foreign key on user_type -> roles.name because
            // the roles table (from spatie/permission) uses a composite unique index on
            // (name, guard_name) and does not index `name` alone, which causes MySQL to
            // reject the FK. Keeping an index on user_type is sufficient for query performance.
        });

        // Set the auto-increment starting point (MySQL only)
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE allupdates AUTO_INCREMENT = 35;');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('allupdates', function (Blueprint $table) {
            // Drop FKs and indexes if they exist
            try { $table->dropForeign(['user_id']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['user_type']); } catch (\Throwable $e) {}
            try { $table->dropIndex('fk_all_updates_user_id'); } catch (\Throwable $e) {}
            try { $table->dropIndex('fk_all_updates_agent_type_roles_name'); } catch (\Throwable $e) {}
        });

        Schema::dropIfExists('allupdates');
    }
}
