<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AugmentAllupdatesTableForRobustAuditing extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (!Schema::hasTable('allupdates')) {
            // If the table doesn't exist in this environment, skip to avoid breaking deploys.
            return;
        }

        Schema::table('allupdates', function (Blueprint $table) {
            // More detailed auditing context
            if (!Schema::hasColumn('allupdates', 'old_status')) {
                $table->unsignedTinyInteger('old_status')->nullable()->after('new_status');
            }
            if (!Schema::hasColumn('allupdates', 'action')) {
                $table->string('action', 50)->nullable()->after('old_status');
            }
            if (!Schema::hasColumn('allupdates', 'result')) {
                $table->enum('result', ['attempted', 'succeeded', 'failed', 'reverted'])->default('succeeded')->after('action');
            }
            if (!Schema::hasColumn('allupdates', 'context')) {
                // Store JSON as TEXT for maximum compatibility; app will JSON-encode/decode
                $table->text('context')->nullable()->after('result');
            }

            // Widen comment field to accommodate richer notes
            // Some DBs may not support MODIFY easily; use change() when possible
            try {
                $table->text('comment')->nullable()->default(null)->change();
            } catch (\Throwable $e) {
                // Fallback: add a new notes column if change() not supported
                if (!Schema::hasColumn('allupdates', 'notes')) {
                    $table->text('notes')->nullable()->after('context');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (!Schema::hasTable('allupdates')) {
            return;
        }

        Schema::table('allupdates', function (Blueprint $table) {
            try { $table->dropColumn('old_status'); } catch (\Throwable $e) {}
            try { $table->dropColumn('action'); } catch (\Throwable $e) {}
            try { $table->dropColumn('result'); } catch (\Throwable $e) {}
            try { $table->dropColumn('context'); } catch (\Throwable $e) {}
            try { $table->dropColumn('notes'); } catch (\Throwable $e) {}
        });
    }
}