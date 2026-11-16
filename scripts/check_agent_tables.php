<?php
// Verify existence of agent_job_entries and agent_job_entry_expenses using .env settings.

function readEnv($path) {
    $cfg = [];
    if (!file_exists($path)) return $cfg;
    $env = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (preg_match('/^(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=(.*)$/', trim($line), $m)) {
            $cfg[$m[1]] = $m[2];
        }
    }
    return $cfg;
}

$base = getcwd();
$envPath = $base . "/.env";
$cfg = readEnv($envPath);

$dsn = sprintf(
    "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
    $cfg['DB_HOST'] ?? 'localhost',
    $cfg['DB_PORT'] ?? '3306',
    $cfg['DB_DATABASE'] ?? ''
);

try {
    $pdo = new PDO($dsn, $cfg['DB_USERNAME'] ?? '', $cfg['DB_PASSWORD'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "Connected to DB: " . ($cfg['DB_DATABASE'] ?? '') . "\n";
    $tables = ['agent_job_entries','agent_job_entry_expenses'];
    foreach ($tables as $t) {
        $stmt = $pdo->query("SHOW TABLES LIKE '".$t."'");
        $exists = (bool)$stmt->fetch();
        echo $t . ": " . ($exists ? "exists" : "missing") . "\n";
    }

    // Optional sample count if main table exists
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM agent_job_entries");
    $row = $stmt->fetch();
    echo "agent_job_entries total rows: ".$row['cnt']."\n";
} catch (Throwable $e) {
    echo "ERROR: ".$e->getMessage()."\n";
    exit(1);
}