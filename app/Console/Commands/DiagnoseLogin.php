<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseLogin extends Command
{
    protected $signature = 'diagnose:login {email : Email to check (exact or any case)} {--limit=20 : Max rows to show for duplicate emails}';
    protected $description = 'Run safe, read-only diagnostics for login issues (hash types, email duplicates, column details).';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $limit = (int) $this->option('limit');

        // Show connection summary (no secrets)
        $conn = config('database.default');
        $cfg = config("database.connections.$conn");
        $this->info("DB connection: {$conn}");
        $this->line("Host: ".($cfg['host'] ?? 'n/a')."  Database: ".($cfg['database'] ?? 'n/a')."  Driver: ".($cfg['driver'] ?? 'n/a'));

        // 1) SHOW CREATE TABLE users
        try {
            $create = DB::select('SHOW CREATE TABLE `users`');
            if ($create && isset($create[0]->{'Create Table'})) {
                $this->info('SHOW CREATE TABLE users:');
                // Print first 500 chars to avoid extremely long output
                $sql = (string) $create[0]->{'Create Table'};
                $this->line(substr($sql, 0, 500).(strlen($sql) > 500 ? ' ...[truncated]' : ''));
            }
        } catch (\Throwable $e) {
            $this->warn('Could not run SHOW CREATE TABLE users: '.$e->getMessage());
        }

        // 2) Collation information
        try {
            $variables = DB::select("SHOW VARIABLES LIKE 'collation%'");
            $this->info('Collation variables:');
            foreach ($variables as $row) {
                $this->line(($row->Variable_name ?? $row->variable_name ?? 'collation').': '.($row->Value ?? $row->value ?? ''));
            }
            $emailCol = DB::select("SHOW FULL COLUMNS FROM `users` WHERE Field = 'email'");
            if ($emailCol) {
                $row = $emailCol[0];
                $this->line('users.email Collation: '.($row->Collation ?? $row->collation ?? 'n/a').', Type: '.($row->Type ?? $row->type ?? 'n/a'));
            }
        } catch (\Throwable $e) {
            $this->warn('Could not fetch collation info: '.$e->getMessage());
        }

        // 3) Hash type distribution
        try {
            $bcrypt = DB::selectOne('SELECT COUNT(*) AS c FROM `users` WHERE `password` LIKE ?', ['$2y$%']);
            $argon  = DB::selectOne('SELECT COUNT(*) AS c FROM `users` WHERE `password` LIKE ? OR `password` LIKE ?', ['$argon2id$%', '$argon2i$%']);
            $md5    = DB::selectOne("SELECT COUNT(*) AS c FROM `users` WHERE `password` REGEXP '^[a-f0-9]{32}$'");
            $blank  = DB::selectOne("SELECT COUNT(*) AS c FROM `users` WHERE `password` IS NULL OR `password` = ''");
            $total  = DB::selectOne("SELECT COUNT(*) AS c FROM `users`");
            $this->info('Password hash distribution:');
            $this->line('Total users: '.($total->c ?? 0));
            $this->line('bcrypt ($2y$): '.($bcrypt->c ?? 0));
            $this->line('argon2 (argon2id/argon2i): '.($argon->c ?? 0));
            $this->line('md5 (32-hex): '.($md5->c ?? 0));
            $this->line('blank/NULL: '.($blank->c ?? 0));
        } catch (\Throwable $e) {
            $this->warn('Could not compute hash distribution: '.$e->getMessage());
        }

        // 4) Specific email check (case-insensitive)
        try {
            $rows = DB::select(
                "SELECT id, email, enabled, LENGTH(password) AS len, LEFT(password, 15) AS prefix\n                 FROM `users`\n                 WHERE LOWER(email) = LOWER(?)\n                 LIMIT 5",
                [$email]
            );
            if (count($rows) === 0) {
                $this->warn('No user found for email (case-insensitive match): '.$email);
            } else {
                $this->info('User(s) found for provided email:');
                foreach ($rows as $r) {
                    $this->line(sprintf('id=%s email=%s enabled=%s len=%s prefix=%s', $r->id, $r->email, $r->enabled, $r->len, $r->prefix));
                }
            }
        } catch (\Throwable $e) {
            $this->warn('Could not query specific email: '.$e->getMessage());
        }

        // 5) Duplicate emails by LOWER(email)
        try {
            $dups = DB::select(
                "SELECT LOWER(email) AS email_norm, COUNT(*) AS c\n                 FROM `users`\n                 GROUP BY email_norm\n                 HAVING c > 1\n                 ORDER BY c DESC\n                 LIMIT $limit"
            );
            if (count($dups) > 0) {
                $this->info('Duplicate emails detected (case-insensitive):');
                foreach ($dups as $d) {
                    $this->line(sprintf('%s => %s', $d->email_norm, $d->c));
                }
            } else {
                $this->info('No case-insensitive duplicate emails found.');
            }
        } catch (\Throwable $e) {
            $this->warn('Could not query duplicates: '.$e->getMessage());
        }

        $this->info('Diagnostics complete (read-only).');
        return self::SUCCESS;
    }
}