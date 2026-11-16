<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\AgentInvoiceItem;
use App\ClientInvoiceItem;

class BackfillInvoiceItemJobType extends Command
{
    protected $signature = 'invoice-items:backfill-job-type {--chunk=1000} {--dry-run}';
    protected $description = 'Backfill materialized job_type on invoice items from metadata or job existence';

    public function handle(): int
    {
        $chunk = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Starting backfill for agent_invoice_items...');
        $this->backfillAgent($chunk, $dryRun);

        $this->info('Starting backfill for client_invoice_items...');
        $this->backfillClient($chunk, $dryRun);

        $this->info('Backfill completed.');
        return Command::SUCCESS;
    }

    private function backfillAgent(int $chunk, bool $dryRun): void
    {
        AgentInvoiceItem::whereNull('job_type')
            ->orderBy('id')
            ->chunkById($chunk, function ($items) use ($dryRun) {
                $updates = [];
                foreach ($items as $item) {
                    $jobType = $this->resolveJobType($item->job_id, $item->metadata);
                    if ($jobType) {
                        $updates[] = ['id' => $item->id, 'job_type' => $jobType];
                    }
                }
                if (!empty($updates)) {
                    if ($dryRun) {
                        $this->line('DRY-RUN Agent updates (sample): ' . json_encode(array_slice($updates, 0, 5)));
                    } else {
                        foreach ($updates as $u) {
                            AgentInvoiceItem::where('id', $u['id'])->update(['job_type' => $u['job_type']]);
                        }
                    }
                }
            });
    }

    private function backfillClient(int $chunk, bool $dryRun): void
    {
        ClientInvoiceItem::whereNull('job_type')
            ->orderBy('id')
            ->chunkById($chunk, function ($items) use ($dryRun) {
                $updates = [];
                foreach ($items as $item) {
                    $jobType = $this->resolveJobType($item->job_id, $item->metadata);
                    if ($jobType) {
                        $updates[] = ['id' => $item->id, 'job_type' => $jobType];
                    }
                }
                if (!empty($updates)) {
                    if ($dryRun) {
                        $this->line('DRY-RUN Client updates (sample): ' . json_encode(array_slice($updates, 0, 5)));
                    } else {
                        foreach ($updates as $u) {
                            ClientInvoiceItem::where('id', $u['id'])->update(['job_type' => $u['job_type']]);
                        }
                    }
                }
            });
    }

    private function resolveJobType($jobId, $metadata): ?string
    {
        // Prefer metadata (array or JSON string)
        try {
            $meta = is_array($metadata) ? $metadata : (is_string($metadata) ? (json_decode($metadata, true) ?: []) : []);
            if (is_array($meta) && isset($meta['job_type']) && is_string($meta['job_type']) && $meta['job_type'] !== '') {
                return $meta['job_type'];
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Fallback: resolve via job_id existence in tables
        $jid = (int) ($jobId ?? 0);
        if ($jid > 0) {
            try {
                $existsInterpreter = DB::table('interpreter_jobs')->where('id', $jid)->exists();
            } catch (\Throwable $e) { $existsInterpreter = false; }
            if ($existsInterpreter) { return 'App\\InterpreterJob'; }
            try {
                $existsTranslator = DB::table('translator_jobs')->where('id', $jid)->exists();
            } catch (\Throwable $e) { $existsTranslator = false; }
            if ($existsTranslator) { return 'App\\TranslatorJob'; }
        }
        return null;
    }
}