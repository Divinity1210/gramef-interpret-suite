<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeTimesheetsAgentIdNullable extends Migration
{
    public function up()
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        // First: find the actual FK constraint name
        $fk = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'timesheets' 
              AND COLUMN_NAME = 'agent_id' 
              AND REFERENCED_TABLE_NAME = 'agents'
        ");

        if (!empty($fk)) {
            $constraintName = $fk[0]->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE timesheets DROP FOREIGN KEY `{$constraintName}`");
        }

        // Make agent_id nullable (keep same type: bigint unsigned to match existing)
        DB::statement('ALTER TABLE timesheets MODIFY agent_id BIGINT(20) UNSIGNED NULL DEFAULT NULL');

        // Re-add FK with the correct referenced type
        // agents.id is int(10) unsigned, but timesheets.agent_id is bigint(20) unsigned
        // MySQL allows FK between bigint and int as long as both are unsigned
        // Use raw SQL to ensure constraint name matches
        if (!empty($fk)) {
            DB::statement("ALTER TABLE timesheets ADD CONSTRAINT `{$constraintName}` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE");
        }
    }

    public function down()
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $fk = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'timesheets' 
              AND COLUMN_NAME = 'agent_id' 
              AND REFERENCED_TABLE_NAME = 'agents'
        ");

        if (!empty($fk)) {
            $constraintName = $fk[0]->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE timesheets DROP FOREIGN KEY `{$constraintName}`");
        }

        // Delete rows with NULL agent_id before making NOT NULL
        DB::statement('DELETE FROM timesheets WHERE agent_id IS NULL');
        DB::statement('ALTER TABLE timesheets MODIFY agent_id BIGINT(20) UNSIGNED NOT NULL');

        if (!empty($fk)) {
            DB::statement("ALTER TABLE timesheets ADD CONSTRAINT `{$constraintName}` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE");
        }
    }
}
