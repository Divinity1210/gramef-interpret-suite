<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agent_invoice_items', function (Blueprint $table) {
            // Add column only if missing
            if (!Schema::hasColumn('agent_invoice_items', 'job_type')) {
                $table->string('job_type', 64)->nullable()->after('job_id');
            }

            // Helper to check index existence across drivers (MySQL + SQLite)
            $indexExists = function (string $indexName): bool {
                try {
                    $driver = \DB::getDriverName();
                    if ($driver === 'sqlite') {
                        $res = \DB::select("SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?", [$indexName]);
                        return !empty($res);
                    }
                    // Default to MySQL check
                    $dbName = config('database.connections.mysql.database');
                    $res = \DB::select(
                        'SELECT COUNT(1) AS cnt FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
                        [$dbName, 'agent_invoice_items', $indexName]
                    );
                    $cnt = ($res[0]->cnt ?? 0);
                    return (int)$cnt > 0;
                } catch (\Throwable $e) { return false; }
            };

            // Create indexes defensively
            if (!$indexExists('agent_invoice_items_job_type_index')) {
                $table->index('job_type', 'agent_invoice_items_job_type_index');
            }
            if (!$indexExists('agent_invoice_items_job_id_index')) {
                $table->index('job_id', 'agent_invoice_items_job_id_index');
            }
            if (!$indexExists('agent_invoice_items_job_type_job_id_index')) {
                $table->index(['job_type', 'job_id'], 'agent_invoice_items_job_type_job_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agent_invoice_items', function (Blueprint $table) {
            // Drop indexes carefully if they exist
            $indexExists = function (string $indexName): bool {
                try {
                    $driver = \DB::getDriverName();
                    if ($driver === 'sqlite') {
                        $res = \DB::select("SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?", [$indexName]);
                        return !empty($res);
                    }
                    $dbName = config('database.connections.mysql.database');
                    $res = \DB::select(
                        'SELECT COUNT(1) AS cnt FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
                        [$dbName, 'agent_invoice_items', $indexName]
                    );
                    $cnt = ($res[0]->cnt ?? 0);
                    return (int)$cnt > 0;
                } catch (\Throwable $e) { return false; }
            };

            foreach ([
                'agent_invoice_items_job_type_job_id_index',
                'agent_invoice_items_job_id_index',
                'agent_invoice_items_job_type_index',
            ] as $idx) {
                try {
                    if ($indexExists($idx)) {
                        $table->dropIndex($idx);
                    }
                } catch (\Throwable $e) { /* ignore */ }
            }

            if (Schema::hasColumn('agent_invoice_items', 'job_type')) {
                $table->dropColumn('job_type');
            }
        });
    }
};