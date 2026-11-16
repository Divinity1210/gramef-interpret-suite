<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use App\InterpreterJob;
use App\Timesheet;
use App\Document;
use App\JobSnapshot;

class ImportDeltas extends Command
{
    protected $signature = 'atoz:import-deltas {path} {--dry-run}';
    protected $description = 'Import operational deltas (timesheet paper workflow) into current DB; supports dry-run and idempotent upserts.';

    public function handle()
    {
        $path = $this->argument('path');
        $dryRun = (bool)$this->option('dry-run');

        if (!is_file($path)) {
            $this->error('JSON not found: ' . $path);
            return 1;
        }

        $json = json_decode(file_get_contents($path), true);
        if (!$json || !is_array($json)) {
            $this->error('Invalid JSON payload.');
            return 1;
        }

        $filesDir = dirname($path) . '/files';

        $jobs = $json['jobs'] ?? [];
        $timesheets = $json['timesheets'] ?? [];
        $documents = $json['documents'] ?? [];
        $snapshots = $json['snapshots'] ?? [];

        $this->info('Importing ' . count($jobs) . ' jobs, ' . count($timesheets) . ' timesheets, ' . count($documents) . ' documents, ' . count($snapshots) . ' snapshots' . ($dryRun ? ' [DRY-RUN]' : ''));

        DB::beginTransaction();
        try {
            // Jobs: update paper workflow fields only
            foreach ($jobs as $j) {
                $job = InterpreterJob::find($j['id']);
                if (!$job) { continue; }
                $job->signature_method = $j['signature_method'] ?? $job->signature_method;
                $job->timesheet_status = $j['timesheet_status'] ?? $job->timesheet_status;
                $job->timesheet_uploaded_at = $j['timesheet_uploaded_at'] ?? $job->timesheet_uploaded_at;
                $job->timesheet_verified_at = $j['timesheet_verified_at'] ?? $job->timesheet_verified_at;
                if (!$dryRun) { $job->save(); }
            }

            // Timesheets: upsert by job_id
            foreach ($timesheets as $ts) {
                $jobId = (int)$ts['job_id'];
                $existing = Timesheet::where('job_id', $jobId)->first();
                if ($existing) {
                    $existing->fill($ts);
                    if (!$dryRun) { $existing->save(); }
                } else {
                    if (!$dryRun) { Timesheet::create($ts); }
                }
            }

            // Documents: signed_timesheet only; dedupe by job_id + type
            $signedType = config('enums.document_types')['signed_timesheet'];
            foreach ($documents as $doc) {
                if ((int)$doc['type'] !== (int)$signedType) { continue; }
                $jobId = (int)$doc['job_id'];
                $existing = Document::where('documentable_type', 'App\\InterpreterJob')
                    ->where('documentable_id', $jobId)
                    ->where('type', $signedType)
                    ->first();

                $newUrl = $doc['url'];
                $newName = $doc['name'];

                // File import (if present)
                $fileName = $doc['export_file'] ?? null;
                if ($fileName) {
                    $srcPath = $filesDir . '/' . $fileName;
                    if (is_file($srcPath)) {
                        $destPath = 'interpreter-jobs/' . $jobId . '/' . basename($srcPath);
                        $disk = env('INTERPRETER_DOCS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
                        if (!$dryRun) {
                            Storage::disk($disk)->put($destPath, file_get_contents($srcPath));
                        }
                        $newUrl = $destPath;
                        $newName = $newName ?: basename($srcPath);
                    }
                }

                if ($existing) {
                    $existing->url = $newUrl;
                    $existing->name = $newName;
                    if (!$dryRun) { $existing->save(); }
                } else {
                    if (!$dryRun) {
                        Document::create([
                            'documentable_type' => 'App\\InterpreterJob',
                            'documentable_id' => $jobId,
                            'type' => $signedType,
                            'url' => $newUrl,
                            'name' => $newName,
                        ]);
                    }
                }
            }

            // Snapshots: import latest only by job_id + version; avoid overwriting higher versions
            foreach ($snapshots as $snap) {
                $jobId = (int)$snap['job_id'];
                $version = (int)$snap['version'];
                $existsHigher = JobSnapshot::where('job_id', $jobId)->where('version', '>=', $version)->exists();
                if ($existsHigher) { continue; }
                if (!$dryRun) {
                    JobSnapshot::create([
                        'job_id' => $jobId,
                        'timesheet_id' => (int)($snap['timesheet_id'] ?? 0) ?: null,
                        'version' => $version,
                        'hash' => $snap['hash'] ?? null,
                        'source' => $snap['source'] ?? 'import',
                        'data' => $snap['data'] ?? [],
                        'created_by' => (int)($snap['created_by'] ?? 0) ?: null,
                    ]);
                }
            }

            if ($dryRun) {
                DB::rollBack();
                $this->info('Dry-run completed: no changes were applied.');
            } else {
                DB::commit();
                $this->info('Import completed successfully.');
            }
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Import failed: ' . $e->getMessage());
            Log::error('ImportDeltas error', ['error' => $e->getMessage()]);
            return 1;
        }
    }
}
