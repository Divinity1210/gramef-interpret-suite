<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorStorage extends Command
{
    protected $signature = 'monitor:storage {--bucket=} {--prefix=}';
    protected $description = 'Report cPanel storage directory sizes and S3 bucket usage.';

    public function handle()
    {
        $bucket = $this->option('bucket') ?: env('AWS_BUCKET');
        $prefix = trim($this->option('prefix') ?: env('AWS_PREFIX', ''), '/');
        $localDirs = [
            storage_path('app'),
            storage_path('logs'),
            storage_path('framework'),
            public_path('storage'),
        ];

        $local = [];
        foreach ($localDirs as $d) {
            $local[$d] = $this->dirSize($d);
        }

        $s3Total = null;
        $s3Count = null;
        if ($bucket) {
            try {
                $client = new \Aws\S3\S3Client([
                    'version' => 'latest',
                    'region' => env('AWS_DEFAULT_REGION'),
                    'credentials' => [
                        'key' => env('AWS_ACCESS_KEY_ID'),
                        'secret' => env('AWS_SECRET_ACCESS_KEY'),
                    ],
                ]);
                $params = ['Bucket' => $bucket];
                if ($prefix) { $params['Prefix'] = $prefix . '/'; }
                $total = 0;
                $count = 0;
                $continuation = null;
                do {
                    if ($continuation) { $params['ContinuationToken'] = $continuation; } else { unset($params['ContinuationToken']); }
                    $res = $client->listObjectsV2($params);
                    foreach ((array) ($res['Contents'] ?? []) as $obj) {
                        $total += (int) ($obj['Size'] ?? 0);
                        $count++;
                    }
                    $continuation = $res['IsTruncated'] ? ($res['NextContinuationToken'] ?? null) : null;
                } while ($continuation);
                $s3Total = $total;
                $s3Count = $count;
            } catch (\Throwable $e) {
            }
        }

        $report = [
            'local' => $local,
            's3_total_bytes' => $s3Total,
            's3_object_count' => $s3Count,
        ];
        $this->line(json_encode($report));
        Log::info('storage-usage', $report);
        return 0;
    }

    private function dirSize(string $path): int
    {
        $size = 0;
        if (!is_dir($path)) { return 0; }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) { if ($file->isFile()) { $size += $file->getSize(); } }
        return $size;
    }
}

