<?php
// Simple diagnostic script to show the current DB configuration in use
// and attempt a live connection to report the active database and server details.

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$default = config('database.default');
$cfg = config("database.connections.$default");

echo "Database config in use:\n";
echo " Connection: $default\n";
echo " Host: " . ($cfg['host'] ?? '') . "\n";
echo " Port: " . ($cfg['port'] ?? '') . "\n";
echo " Database: " . ($cfg['database'] ?? '') . "\n";
echo " Username: " . ($cfg['username'] ?? '') . "\n";

try {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
    $stmt = $pdo->query("SELECT DATABASE() AS db, @@hostname AS server_host, @@version AS server_version");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Resolved by server:\n";
    echo " Active DB: " . ($row['db'] ?? '') . "\n";
    echo " Server host: " . ($row['server_host'] ?? '') . "\n";
    echo " Server version: " . ($row['server_version'] ?? '') . "\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "DB connect error: " . $e->getMessage() . "\n");
    exit(1);
}