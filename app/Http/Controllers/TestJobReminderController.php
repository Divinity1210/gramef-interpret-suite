<?php

namespace App\Http\Controllers;

use App\InterpreterJob;
use App\Mail\JobMail;
use App\Notifications\JobReminder;
use App\PushSubscription;
use App\Services\PushNotificationService;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class TestJobReminderController extends Controller
{
    public function run()
    {
        Artisan::call('send-interpreterjob-reminders');
        return response()->json([
            'success' => true,
            'message' => 'Job reminders command executed',
            'output' => trim((string) Artisan::output()),
        ]);
    }

    public function diagnose()
    {
        $nowLondon = Carbon::now('Europe/London');
        $tomorrowLondon = $nowLondon->copy()->addDay();

        $jobs24h = InterpreterJob::hasEnabledUser()
            ->with(['toLanguage', 'matchedAgents', 'agent.user', 'assignedMatched.agent.user'])
            ->whereDate('appointment_date', $tomorrowLondon->toDateString())
            ->when(Schema::hasColumn('interpreter_jobs', 'reminder_sent'), function ($q) {
                $q->where('reminder_sent', false);
            })
            ->where(function ($q) {
                $q->whereHas('matchedAgents', function ($query) {
                    $query->where('status', 4);
                })->orWhereNotNull('agent_id');
            })
            ->whereNull('cancelled_at')
            ->where(function ($q) {
                $q->whereHas('agent.user', function ($userQuery) {
                    $userQuery->where('enabled', 1);
                })->orWhereHas('assignedMatched.agent.user', function ($userQuery) {
                    $userQuery->where('enabled', 1);
                });
            })
            ->get();

        $jobs3h = InterpreterJob::hasEnabledUser()
            ->with(['toLanguage', 'matchedAgents', 'agent.user', 'assignedMatched.agent.user'])
            ->where(function ($q) {
                $q->whereHas('matchedAgents', function ($query) {
                    $query->where('status', 4);
                })->orWhereNotNull('agent_id');
            })
            ->where(function ($q) use ($nowLondon, $tomorrowLondon) {
                $q->whereDate('appointment_date', $nowLondon->toDateString())
                    ->orWhereDate('appointment_date', $tomorrowLondon->toDateString());
            })
            ->when(Schema::hasColumn('interpreter_jobs', 'three_hour_reminder_sent'), function ($q) {
                $q->where('three_hour_reminder_sent', false);
            })
            ->whereNull('cancelled_at')
            ->where(function ($q) {
                $q->whereHas('agent.user', function ($userQuery) {
                    $userQuery->where('enabled', 1);
                })->orWhereHas('assignedMatched.agent.user', function ($userQuery) {
                    $userQuery->where('enabled', 1);
                });
            })
            ->get();

        $eligible24h = $jobs24h->filter(function (InterpreterJob $job) {
            return $job->isExactly24HoursRemaining();
        })->values();

        $eligible3h = $jobs3h->filter(function (InterpreterJob $job) {
            return $job->isApproximatelyHoursRemaining(3, 15);
        })->values();

        return response()->json([
            'success' => true,
            'now_london' => $nowLondon->toDateTimeString(),
            'mail' => [
                'default_mailer' => config('mail.default'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
            ],
            'push' => [
                'vapid_public_present' => !empty(config('services.vapid.public_key') ?: env('VAPID_PUBLIC_KEY')),
                'vapid_private_present' => !empty(config('services.vapid.private_key') ?: env('VAPID_PRIVATE_KEY')),
                'vapid_subject' => config('services.vapid.subject') ?: env('VAPID_SUBJECT'),
            ],
            'summary' => [
                'jobs24h_query_count' => $jobs24h->count(),
                'jobs24h_eligible_count' => $eligible24h->count(),
                'jobs3h_query_count' => $jobs3h->count(),
                'jobs3h_eligible_count' => $eligible3h->count(),
            ],
            'eligible_24h' => $this->buildJobDiagnostics($eligible24h),
            'eligible_3h' => $this->buildJobDiagnostics($eligible3h),
        ]);
    }

    public function logStatus()
    {
        $logDir = storage_path('logs');
        $files = [];
        if (File::exists($logDir)) {
            $files = collect(File::files($logDir))
                ->filter(function ($file) {
                    return str_ends_with($file->getFilename(), '.log');
                })
                ->map(function ($file) {
                    return [
                        'name' => $file->getFilename(),
                        'path' => $file->getPathname(),
                        'size_bytes' => $file->getSize(),
                        'last_modified' => Carbon::createFromTimestamp($file->getMTime())->toDateTimeString(),
                    ];
                })
                ->sortByDesc('last_modified')
                ->values();
        }

        $latest = collect($files)->first();
        $latestLaravel = collect($files)
            ->first(function ($file) {
                return str_starts_with((string) ($file['name'] ?? ''), 'laravel-');
            });
        $latestReminderCron = collect($files)
            ->first(function ($file) {
                return ($file['name'] ?? '') === 'cron-reminders.log';
            });
        $latestScheduleCron = collect($files)
            ->first(function ($file) {
                return ($file['name'] ?? '') === 'cron-schedule.log';
            });
        $scanTarget = $latestLaravel ?: $latest;
        $tailLines = [];
        if ($scanTarget && File::exists($scanTarget['path'])) {
            $contents = File::get($scanTarget['path']);
            $lines = preg_split("/\r\n|\n|\r/", $contents);
            $tailLines = collect($lines)
                ->filter(function ($line) {
                    return stripos($line, 'Reminder') !== false
                        || stripos($line, 'send-interpreterjob-reminders') !== false
                        || stripos($line, 'PUSH_') !== false
                        || stripos($line, 'Push delivery failed') !== false
                        || stripos($line, 'Swift_') !== false
                        || stripos($line, 'Mail') !== false;
                })
                ->take(-60)
                ->values();
        }

        return response()->json([
            'success' => true,
            'log_channel' => config('logging.default'),
            'log_directory' => $logDir,
            'log_files_count' => count($files),
            'log_files' => $files,
            'latest_log' => $latest,
            'latest_laravel_log' => $latestLaravel,
            'latest_cron_reminders_log' => $latestReminderCron,
            'latest_cron_schedule_log' => $latestScheduleCron,
            'scanned_log' => $scanTarget,
            'recent_relevant_lines' => $tailLines,
        ]);
    }

    public function channelStatus()
    {
        $mailHost = config('mail.host');
        $mailPort = (int) config('mail.port');
        $mailDriver = config('mail.driver');
        $mailEncryption = config('mail.encryption');
        $mailUsername = config('mail.username');
        $mailFrom = config('mail.from.address');

        $socketCheck = [
            'attempted' => false,
            'connected' => false,
            'error' => null,
        ];

        if ($mailHost && $mailPort > 0) {
            $socketCheck['attempted'] = true;
            $errno = 0;
            $errstr = '';
            $socket = @fsockopen($mailHost, $mailPort, $errno, $errstr, 8);
            if ($socket) {
                $socketCheck['connected'] = true;
                fclose($socket);
            } else {
                $socketCheck['error'] = trim($errstr) ?: ('socket error #' . $errno);
            }
        }

        $mailSendCheck = [
            'attempted' => false,
            'sent' => false,
            'error' => null,
            'recipient' => $mailFrom,
        ];

        if ($mailFrom && filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
            try {
                $mailSendCheck['attempted'] = true;
                Mail::raw('AtoZ reminder diagnostics test mail', function ($message) use ($mailFrom) {
                    $message->to($mailFrom)->subject('AtoZ Reminder Mail Diagnostics');
                });
                $mailSendCheck['sent'] = true;
            } catch (\Throwable $e) {
                $mailSendCheck['error'] = $e->getMessage();
            }
        }

        $logsPath = storage_path('logs');
        $canWriteLogDir = is_dir($logsPath) && is_writable($logsPath);
        $touchPath = $logsPath . '/diagnostic-write-check.log';
        $writeCheck = [
            'attempted' => false,
            'writable' => false,
            'error' => null,
        ];
        try {
            $writeCheck['attempted'] = true;
            $result = @file_put_contents($touchPath, now()->toDateTimeString() . PHP_EOL, FILE_APPEND);
            $writeCheck['writable'] = $result !== false;
            if ($result === false) {
                $writeCheck['error'] = 'file_put_contents returned false';
            }
        } catch (\Throwable $e) {
            $writeCheck['error'] = $e->getMessage();
        }

        return response()->json([
            'success' => true,
            'mail' => [
                'driver' => $mailDriver,
                'host' => $mailHost,
                'port' => $mailPort,
                'encryption' => $mailEncryption,
                'username_present' => !empty($mailUsername),
                'from' => $mailFrom,
            ],
            'mail_socket_check' => $socketCheck,
            'mail_send_check' => $mailSendCheck,
            'logs' => [
                'path' => $logsPath,
                'directory_writable' => $canWriteLogDir,
                'write_check' => $writeCheck,
            ],
            'notifications' => [
                'notifications_table_exists' => Schema::hasTable('notifications'),
                'reminder_sent_column_exists' => Schema::hasColumn('interpreter_jobs', 'reminder_sent'),
                'three_hour_reminder_sent_column_exists' => Schema::hasColumn('interpreter_jobs', 'three_hour_reminder_sent'),
                'queue_default' => config('queue.default'),
                'cache_default' => config('cache.default'),
                'last_reminder_run_started_at' => Cache::get('interpreter_reminders:last_run_started_at'),
                'last_reminder_run_finished_at' => Cache::get('interpreter_reminders:last_run_finished_at'),
            ],
        ]);
    }

    public function fire(int $jobId, int $hoursToGo = 24)
    {
        $hours = in_array($hoursToGo, [3, 24], true) ? $hoursToGo : 24;
        $sentColumn = $hours === 24 ? 'reminder_sent' : 'three_hour_reminder_sent';

        $job = InterpreterJob::with(['toLanguage', 'agent.user', 'assignedMatched.agent.user'])->find($jobId);
        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found',
                'job_id' => $jobId,
            ], 404);
        }

        $user = $this->resolveAssignedUser($job);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No assigned enabled user for this job',
                'job_id' => $job->id,
            ], 422);
        }

        $user->interpreter_job = $job;

        $emailSent = false;
        $appSent = false;
        $smsSent = false;
        $databaseSent = false;
        $pushSubscriptionCount = PushSubscription::where('user_id', $user->id)->count();
        $pushRequired = $pushSubscriptionCount > 0;
        $errors = [];
        $language = optional($job->toLanguage)->name ?? 'Interpreting';
        $pushTitle = $hours === 24 ? 'Job Tomorrow' : 'Job Starting Soon';
        $pushBody = $hours === 24
            ? "Reminder: {$language} job starts tomorrow"
            : "Reminder: {$language} job starts in 3 hours";
        $pushData = [
            'type' => 'reminder',
            'title' => $pushTitle,
            'body' => $pushBody,
            'url' => "/(tabs)/jobs/interpreter-{$job->id}",
            'icon' => '/icon-192.png',
            'extra' => [
                'jobId' => $job->id,
                'hoursToGo' => $hours,
            ],
            'tag' => "reminder-{$hours}h-{$job->id}",
        ];

        try {
            $subject = $hours === 24 ? 'Job Reminder - 24 Hours' : 'Job Reminder - 3 Hours';
            Mail::to($user)->send(
                (new JobMail(
                    $job,
                    'emails.agents.interpreter-job-reminder',
                    $subject,
                    'agent'
                ))->with([
                    'hours_to_go' => $hours,
                ])
            );
            $emailSent = true;
        } catch (\Throwable $e) {
            $errors['email'] = $e->getMessage();
        }

        try {
            $user->notify(new JobReminder($hours));
            $databaseSent = true;
        } catch (\Throwable $e) {
            $errors['database'] = $e->getMessage();
        }

        try {
            $pushReport = PushNotificationService::notifyUserDetailed((int) $user->id, $pushTitle, $pushBody, $pushData);
            $appSent = ((int) ($pushReport['sent'] ?? 0)) > 0;
        } catch (\Throwable $e) {
            $errors['app'] = $e->getMessage();
        }

        try {
            $phone = optional($user->agent)->contact_number ?? null;
            if ($phone) {
                $smsMessage = sprintf(
                    'Reminder: Job %s starts in %d hours on %s at %s. Login for details: %s',
                    $job->reference,
                    $hours,
                    optional($job->appointment_date)->format('d/m/Y'),
                    $job->start_time,
                    route('interpreter-jobs.show', $job->id)
                );
                $smsSent = SmsService::send($phone, $smsMessage);
            }
        } catch (\Throwable $e) {
            $errors['sms'] = $e->getMessage();
        }

        $requiredChannelsSent = $emailSent && (!$pushRequired || $appSent);
        if ($requiredChannelsSent && Schema::hasColumn('interpreter_jobs', $sentColumn)) {
            $job->{$sentColumn} = true;
            $job->save();
        }

        return response()->json([
            'success' => $requiredChannelsSent,
            'job_id' => $job->id,
            'hours_to_go' => $hours,
            'sent_column' => $sentColumn,
            'email_sent' => $emailSent,
            'app_sent' => $appSent,
            'database_sent' => $databaseSent,
            'sms_sent' => $smsSent,
            'push_required' => $pushRequired,
            'push_subscriptions' => $pushSubscriptionCount,
            'push_delivery' => $pushReport ?? null,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'errors' => $errors,
        ]);
    }

    protected function buildJobDiagnostics($jobs)
    {
        return $jobs->map(function (InterpreterJob $job) {
            $user = $this->resolveAssignedUser($job);
            $subscriptionCount = $user ? PushSubscription::where('user_id', $user->id)->count() : 0;

            return [
                'job_id' => $job->id,
                'reference' => $job->reference,
                'appointment_date' => optional($job->appointment_date)->toDateString(),
                'start_time' => $job->start_time,
                'agent_id' => $job->agent_id,
                'assigned_user_id' => optional($user)->id,
                'assigned_user_email' => optional($user)->email,
                'assigned_user_enabled' => (int) (optional($user)->enabled ?? 0),
                'has_assigned_user' => $user !== null,
                'push_subscriptions' => $subscriptionCount,
                'reminder_sent' => Schema::hasColumn('interpreter_jobs', 'reminder_sent') ? (bool) $job->reminder_sent : null,
                'three_hour_reminder_sent' => Schema::hasColumn('interpreter_jobs', 'three_hour_reminder_sent') ? (bool) $job->three_hour_reminder_sent : null,
            ];
        })->values();
    }

    protected function resolveAssignedUser(InterpreterJob $job)
    {
        $primaryUser = ($job->agent && $job->agent->user) ? $job->agent->user : null;
        if ($primaryUser && (int) ($primaryUser->enabled ?? 0) === 1) {
            return $primaryUser;
        }

        $matchedAssignedUser = ($job->assignedMatched && $job->assignedMatched->agent && $job->assignedMatched->agent->user)
            ? $job->assignedMatched->agent->user
            : null;
        if ($matchedAssignedUser && (int) ($matchedAssignedUser->enabled ?? 0) === 1) {
            return $matchedAssignedUser;
        }

        return null;
    }
}
