<?php
// Simple PDO-based checker that reads .env and reports table existence and a sample count

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

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SHOW TABLES LIKE :t");
    $stmt->execute([':t' => $table]);
    return (bool)$stmt->fetchColumn();
}

function showCreateTable(PDO $pdo, string $table): string {
    $stmt = $pdo->query("SHOW CREATE TABLE `".$table."`");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['Create Table'] ?? '';
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

echo "Using DSN: ".$dsn."\n";
echo "DB user: ".($cfg['DB_USERNAME'] ?? '')."\n";

try {
    $pdo = new PDO($dsn, $cfg['DB_USERNAME'] ?? '', $cfg['DB_PASSWORD'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "Connected: OK\n";

    $tables = ['agent_job_entries','agent_job_entry_expenses','exports','agent_invoices','client_invoices','payslips','remittances','interpreter_jobs','translator_jobs','allupdates'];
    foreach ($tables as $t) {
        $stmt = $pdo->query("SHOW TABLES LIKE '".$t."'");
        $exists = $stmt->fetch();
        echo $t . ": " . ($exists ? "exists" : "missing") . "\n";
        if ($exists) {
            $createStmt = $pdo->query("SHOW CREATE TABLE `".$t."`");
            $create = $createStmt->fetch();
            if ($create && isset($create['Create Table'])) {
                // Print only first line of CREATE for brevity
                $firstLine = strtok($create['Create Table'], "\n");
                echo $t." DDL: ".$firstLine."...\n";
            }
            // Print key columns for date range debugging
            $colsStmt = $pdo->query("SHOW COLUMNS FROM `".$t."`");
            $cols = $colsStmt->fetchAll();
            $colNames = array_map(function($c){ return $c['Field']; }, $cols);
            echo $t." columns: ".implode(', ', $colNames)."\n";
        }
    }

    // Sample count for the exact query causing the error
    try {
        $stmt = $pdo->query(
            "SELECT COUNT(*) AS cnt FROM agent_job_entries " .
            "WHERE job_type='App\\\\InterpreterJob' AND job_id IN (16557,16893,16946,17175,17176,17196,17315,17316,17317,17414)"
        );
        $row = $stmt->fetch();
        echo "agent_job_entries sample count: ".($row['cnt'] ?? 0)."\n";
    } catch (Throwable $inner) {
        echo "Sample count query failed: ".$inner->getMessage()."\n";
    }

    // Sample latest export record check
    try {
        $stmt = $pdo->query("SELECT id, type, status, created_at FROM exports ORDER BY created_at DESC LIMIT 1");
        $row = $stmt->fetch();
        if ($row) {
            echo "latest export -> id=".$row['id'].", type=".$row['type'].", status=".$row['status'].", created_at=".$row['created_at']."\n";
        } else {
            echo "latest export -> none found\n";
        }
    } catch (Throwable $inner) {
        echo "Exports check failed: ".$inner->getMessage()."\n";
    }

    // Quick verification: legacy client_ref alias exists and maps to client_reference
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM translator_jobs WHERE client_ref IS NOT NULL");
        $row = $stmt->fetch();
        echo "translator_jobs client_ref non-null count: ".($row['cnt'] ?? 0)."\n";
        $stmt2 = $pdo->query("SELECT client_reference, client_ref FROM translator_jobs ORDER BY id DESC LIMIT 3");
        $rows = $stmt2->fetchAll();
        foreach ($rows as $i => $r) {
            echo "row ".$i." -> client_reference=".($r['client_reference'] ?? 'NULL').", client_ref=".($r['client_ref'] ?? 'NULL')."\n";
        }
    } catch (Throwable $inner) {
        echo "client_ref check failed: ".$inner->getMessage()."\n";
    }

    // Confirm allupdates.code column type (should be VARCHAR(30))
    try {
        $stmt = $pdo->query("SHOW FIELDS FROM allupdates LIKE 'code'");
        $row = $stmt->fetch();
        echo "allupdates.code type: ".(($row['Type'] ?? 'unknown'))."\n";
    } catch (Throwable $inner) {
        echo "allupdates.code type check failed: ".$inner->getMessage()."\n";
    }

    // Verify agent_invoices has date_from and date_to
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM agent_invoices")->fetchAll(PDO::FETCH_COLUMN, 0);
        $hasFrom = in_array('date_from', $cols, true);
        $hasTo = in_array('date_to', $cols, true);
        echo "agent_invoices date_from: ".($hasFrom ? 'YES' : 'NO').", date_to: ".($hasTo ? 'YES' : 'NO')."\n";
    } catch (Throwable $inner) {
        echo "agent_invoices date_from/date_to check failed: ".$inner->getMessage()."\n";
    }

    // Check existence of bank_accounts and audit_logs tables on current DB
    try {
        $hasBank = tableExists($pdo, 'bank_accounts');
        echo "bank_accounts: ".($hasBank ? 'exists' : 'missing')."\n";
        if ($hasBank) {
            $ddl = showCreateTable($pdo, 'bank_accounts');
            echo "bank_accounts DDL: ".substr($ddl, 0, 120)."...\n";
        }
    } catch (Throwable $inner) {
        echo "bank_accounts check failed: ".$inner->getMessage()."\n";
    }

    try {
        $hasAudit = tableExists($pdo, 'audit_logs');
        echo "audit_logs: ".($hasAudit ? 'exists' : 'missing')."\n";
        if ($hasAudit) {
            $ddl = showCreateTable($pdo, 'audit_logs');
            echo "audit_logs DDL: ".substr($ddl, 0, 120)."...\n";
        }
    } catch (Throwable $inner) {
        echo "audit_logs check failed: ".$inner->getMessage()."\n";
    }
} catch (Throwable $e) {
    echo "ERROR: ".$e->getMessage()."\n";
    exit(1);
}