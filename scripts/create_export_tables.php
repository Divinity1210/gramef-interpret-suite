<?php
// Create exports table via PDO using .env settings.
// Mirrors the approach used for agent_job_entries to avoid relying on artisan migrations.

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

    // Helper to check existence
    $exists = function(string $table) use ($pdo) {
        $stmt = $pdo->query("SHOW TABLES LIKE '".$table."'");
        return (bool)$stmt->fetch();
    };

    if (!$exists('exports')) {
        // Use JSON if available, fallback to LONGTEXT
        $supportsJson = true;
        try {
            $pdo->query("SELECT JSON_VALID('{\"a\":1}')");
        } catch (Throwable $e) {
            $supportsJson = false;
        }

        $filtersType = $supportsJson ? 'JSON' : 'LONGTEXT';

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `exports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(50) NOT NULL,
  `format` VARCHAR(20) NOT NULL,
  `filters` $filtersType NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'processing',
  `file_path` VARCHAR(255) NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type_format` (`type`, `format`),
  KEY `idx_status` (`status`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    echo "Exports table check/create completed.\n";
} catch (Throwable $e) {
    echo "ERROR: ".$e->getMessage()."\n";
    exit(1);
}