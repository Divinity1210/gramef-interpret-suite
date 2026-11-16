<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupLocalStorage extends Command
{
    protected $signature = 'storage:cleanup {--dry-run}';
    protected $description = 'Remove local storage files to prevent disk growth when using S3.';

    public function handle()
    {
        $dry = (bool) $this->option('dry-run');
        $paths = [
            storage_path('app/public'),
            storage_path('logs'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('app/mpdf-temp'),
        ];
        $removed = 0;
        foreach ($paths as $p) {
            if (is_dir($p)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($p, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($it as $file) {
                    $target = $file->getPathname();
                    if (is_file($target)) {
                        if (!$dry) { @unlink($target); }
                        $removed++;
                    }
                }
            }
        }
        $this->info('Removed files: ' . $removed . ($dry ? ' [DRY-RUN]' : ''));
        return 0;
    }
}

