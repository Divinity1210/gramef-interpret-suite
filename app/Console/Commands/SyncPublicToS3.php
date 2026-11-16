<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncPublicToS3 extends Command
{
    protected $signature = 'storage:sync-s3 {--dir=*} {--dry-run}';
    protected $description = 'Copy files from storage/app/public to S3, preserving paths and skipping existing objects.';

    public function handle()
    {
        $dirs = (array) $this->option('dir');
        $dryRun = (bool) $this->option('dry-run');
        if (empty($dirs)) {
            $dirs = [
                'receipts',
                'payment-proofs',
                'interpreter-jobs',
                'translator-jobs',
                'remittances',
                ''
            ];
        }

        $files = [];
        foreach ($dirs as $dir) {
            if ($dir === '' || $dir === null) {
                $files = array_merge($files, Storage::disk('public')->files());
            } else {
                $files = array_merge($files, Storage::disk('public')->allFiles($dir));
            }
        }
        $files = array_values(array_unique($files));

        $total = count($files);
        $this->info('Files to process: ' . $total);
        if ($total === 0) {
            return 0;
        }

        $this->output->progressStart($total);
        $copied = 0;
        $skipped = 0;
        $errors = 0;
        foreach ($files as $p) {
            try {
                if (!Storage::disk('s3')->exists($p)) {
                    if (!$dryRun) {
                        $content = Storage::disk('public')->get($p);
                        Storage::disk('s3')->put($p, $content);
                    }
                    $copied++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors++;
            }
            $this->output->progressAdvance();
        }
        $this->output->progressFinish();

        $this->info('Total: ' . $total . ' Copied: ' . $copied . ' Skipped: ' . $skipped . ' Errors: ' . $errors . ($dryRun ? ' [DRY-RUN]' : ''));
        return $errors ? 1 : 0;
    }
}