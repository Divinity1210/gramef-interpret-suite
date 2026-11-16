<?php
// Create agent_job_entries and agent_job_entry_expenses tables via PDO using .env settings.
// This is a fallback when artisan migrate doesn't run or has permission issues.

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

    // Check existence and create if missing
    $exists = function(string $table) use ($pdo) {
        $stmt = $pdo->query("SHOW TABLES LIKE '".$table."'");
        return (bool)$stmt->fetch();
    };

    if (!$exists('agent_job_entries')) {
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `agent_job_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `agent_id` BIGINT UNSIGNED NOT NULL,
  `job_id` BIGINT UNSIGNED NOT NULL,
  `job_type` VARCHAR(255) NOT NULL,
  `base_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `expenses` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `surcharge` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `other_charges` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `notes` TEXT NULL,
  `invoiced` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_agent_invoiced` (`agent_id`, `invoiced`),
  KEY `idx_job_type` (`job_id`, `job_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    if (!$exists('agent_job_entry_expenses')) {
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `agent_job_entry_expenses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `agent_job_entry_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(255) NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_entry_id` (`agent_job_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    echo "Tables check/create completed.\n";
} catch (Throwable $e) {
    echo "ERROR: ".$e->getMessage()."\n";
    exit(1);
}