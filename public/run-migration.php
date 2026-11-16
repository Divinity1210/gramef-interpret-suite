<?php
/**
 * Browser-accessible migration runner.
 * Upload this file to your public directory (same folder as index.php).
 * Visit: https://portal.atozinterpreting.com/run-migration.php
 * DELETE THIS FILE AFTER USE for security.
 *
 * SAFETY: This script ONLY creates NEW tables. It never modifies or deletes existing data.
 */

// Bootstrap Laravel (paths relative to public/ directory)
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle($request = Illuminate\Http\Request::capture());

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

echo "<pre style='font-family:monospace; background:#1a1a2e; color:#e0e0e0; padding:20px; border-radius:8px;'>";
echo "<h2 style='color:#4fc3f7;'>🔧 AtoZ Chat Migration Runner</h2>\n";
echo "<span style='color:#81c784;'>✅ SAFE: This only CREATES new tables. No existing data is modified or deleted.</span>\n\n";

try {
    // Detect the users table primary key type to match foreign keys
    $usersIdType = 'integer';
    try {
        $col = DB::select("SHOW COLUMNS FROM users WHERE Field = 'id'");
        if (!empty($col)) {
            $colType = strtolower($col[0]->Type ?? '');
            if (strpos($colType, 'bigint') !== false) {
                $usersIdType = 'bigInteger';
            }
        }
    } catch (\Throwable $e) {
    }
    echo "ℹ️  Detected users.id type: <strong>{$usersIdType}</strong>\n\n";

    // 1. Create conversations table
    if (!Schema::hasTable('conversations')) {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
        echo "✅ Created <strong style='color:#66bb6a;'>conversations</strong> table\n";
    } else {
        echo "⏭️  <strong>conversations</strong> table already exists\n";
    }

    // 2. Create conversation_user pivot table (NO foreign keys to avoid type mismatch)
    if (!Schema::hasTable('conversation_user')) {
        Schema::create('conversation_user', function (Blueprint $table) use ($usersIdType) {
            $table->id();
            if ($usersIdType === 'bigInteger') {
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('user_id');
            } else {
                $table->unsignedInteger('conversation_id');
                $table->unsignedInteger('user_id');
            }
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });
        echo "✅ Created <strong style='color:#66bb6a;'>conversation_user</strong> pivot table\n";
    } else {
        echo "⏭️  <strong>conversation_user</strong> table already exists\n";
    }

    // 3. Create messages table (NO foreign keys to avoid type mismatch)
    if (!Schema::hasTable('messages')) {
        Schema::create('messages', function (Blueprint $table) use ($usersIdType) {
            $table->id();
            if ($usersIdType === 'bigInteger') {
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('user_id');
            } else {
                $table->unsignedInteger('conversation_id');
                $table->unsignedInteger('user_id');
            }
            $table->text('body');
            $table->string('type', 50)->default('text');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('conversation_id');
            $table->index(['conversation_id', 'created_at']);
            $table->index('user_id');
        });
        echo "✅ Created <strong style='color:#66bb6a;'>messages</strong> table\n";
    } else {
        echo "⏭️  <strong>messages</strong> table already exists\n";
    }

    echo "\n<strong style='color:#4fc3f7;'>🎉 Migration complete!</strong>\n";
    echo "\n<span style='color:#ef5350;'>⚠️  DELETE this file (run-migration.php) from your server now for security.</span>\n";

} catch (\Throwable $e) {
    echo "\n<strong style='color:#ef5350;'>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "\n";
    echo "<strong style='color:#ef5350;'>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "\n";
}

echo "</pre>";
