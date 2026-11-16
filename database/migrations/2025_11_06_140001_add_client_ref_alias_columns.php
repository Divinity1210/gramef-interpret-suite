<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddClientRefAliasColumns extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $tables = ['interpreter_jobs', 'translator_jobs'];
        $driver = config('database.connections.' . config('database.default') . '.driver');

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            // Ensure base column exists; if not, create client_reference for safety
            if (!Schema::hasColumn($table, 'client_reference')) {
                try {
                    Schema::table($table, function (Blueprint $blueprint) {
                        $blueprint->string('client_reference')->nullable();
                    });
                } catch (\Throwable $e) {
                    Log::warning("Failed to create client_reference column on {$table}", ['error' => $e->getMessage()]);
                }
            }

            if (!Schema::hasColumn($table, 'client_ref')) {
                try {
                    if ($driver === 'mysql') {
                        // MySQL/MariaDB generated column to mirror client_reference
                        DB::statement("ALTER TABLE `{$table}` ADD COLUMN `client_ref` VARCHAR(255) GENERATED ALWAYS AS (`client_reference`) STORED");
                    } elseif ($driver === 'pgsql') {
                        // PostgreSQL 12+ supports generated columns; try and fall back if not supported
                        try {
                            DB::statement("ALTER TABLE \"{$table}\" ADD COLUMN client_ref text GENERATED ALWAYS AS (client_reference) STORED");
                        } catch (\Throwable $ePg) {
                            Schema::table($table, function (Blueprint $blueprint) {
                                $blueprint->string('client_ref')->nullable();
                            });
                            DB::statement("UPDATE \"{$table}\" SET client_ref = client_reference WHERE client_ref IS NULL");
                        }
                    } else {
                        // Generic fallback for SQLite or other drivers: add a real column and backfill
                        Schema::table($table, function (Blueprint $blueprint) {
                            $blueprint->string('client_ref')->nullable();
                        });
                        DB::statement("UPDATE `{$table}` SET `client_ref` = `client_reference` WHERE `client_ref` IS NULL");
                    }
                } catch (\Throwable $e) {
                    Log::warning("Failed to add client_ref alias column on {$table}", ['error' => $e->getMessage()]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        $tables = ['interpreter_jobs', 'translator_jobs'];
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'client_ref')) {
                try {
                    Schema::table($table, function (Blueprint $blueprint) {
                        $blueprint->dropColumn('client_ref');
                    });
                } catch (\Throwable $e) {
                    Log::warning("Failed to drop client_ref column on {$table}", ['error' => $e->getMessage()]);
                }
            }
        }
    }
}