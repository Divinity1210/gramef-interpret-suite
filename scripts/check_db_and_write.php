<?php
// PDO-based checker that reads .env and writes results to tmp/db_check_output.txt

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

$out = [];
$out[] = "Using DSN: " . $dsn;
$out[] = "DB user: " . ($cfg['DB_USERNAME'] ?? '');

try {
    $pdo = new PDO($dsn, $cfg['DB_USERNAME'] ?? '', $cfg['DB_PASSWORD'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $out[] = "Connected: OK";

    $tables = ['agent_job_entries','agent_job_entry_expenses'];
    foreach ($tables as $t) {
        $stmt = $pdo->query("SHOW TABLES LIKE '".$t."'");
        $exists = $stmt->fetch();
        $out[] = $t . ": " . ($exists ? "exists" : "missing");
    }

    try {
        $stmt = $pdo->query(
            "SELECT COUNT(*) AS cnt FROM agent_job_entries " .
            "WHERE job_type='App\\\\InterpreterJob' AND job_id IN (16557,16893,16946,17175,17176,17196,17315,17316,17317,17414)"
        );
        $row = $stmt->fetch();
        $out[] = "agent_job_entries sample count: " . ($row['cnt'] ?? 0);
    } catch (Throwable $inner) {
        $out[] = "Sample count query failed: " . $inner->getMessage();
    }
} catch (Throwable $e) {
    $out[] = "ERROR: " . $e->getMessage();
}

// Ensure tmp directory exists
if (!is_dir($base . '/tmp')) {
    @mkdir($base . '/tmp', 0777, true);
}

$dest = $base . '/tmp/db_check_output.txt';
file_put_contents($dest, implode("\n", $out) . "\n");
echo "Wrote: " . $dest . "\n";