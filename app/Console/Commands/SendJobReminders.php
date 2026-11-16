<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\InterpreterJob;
use App\Mail\JobMail;
use App\Notifications\JobReminder;
use App\PushSubscription;
use App\Services\PushNotificationService;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class SendJobReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send-interpreterjob-reminders';
    protected $description = 'Send job reminders to interpreters';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $nowLondon = Carbon::now('Europe/London');
        $tomorrowLondon = $nowLondon->copy()->addDay();
        Cache::put('interpreter_reminders:last_run_started_at', now()->toDateTimeString(), now()->addDay());

        Log::info('Reminder run started', [
            'now_london' => $nowLondon->toDateTimeString(),
        ]);

        $this->process24HourReminders($tomorrowLondon);
        $this->process3HourReminders($nowLondon, $tomorrowLondon);

        Cache::put('interpreter_reminders:last_run_finished_at', now()->toDateTimeString(), now()->addDay());
        Log::info('Reminder run finished');
        return self::SUCCESS;
    }

    protected function process24HourReminders(Carbon $tomorrowLondon): void
    {
        $jobs = InterpreterJob::hasEnabledUser()
            ->with(['to_language', 'matchedAgents', 'agent.user', 'assignedMatched.agent.user'])
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
            // Only remind for active assigned/rescheduled jobs (exclude completed, cancelled, DNA, returned, or already-signed timesheets)
            ->whereIn('status', [1, 5])
            ->where('dna', '!=', 1)
            ->where('retrn', '!=', 1)
            ->whereDoesntHave('timesheet', function ($q) {
                $q->whereNotNull('agent_signature')
                    ->orWhereNotNull('client_signature');
            })
            ->where(function ($q) {
                $q->whereHas('agent.user', function ($userQuery) {
                    $userQuery->where('enabled', 1);
                })->orWhereHas('assignedMatched.agent.user', function ($userQuery) {
                    $userQuery->where('enabled', 1);
                });
            })
            ->get();

        $candidates = $jobs->filter(function (InterpreterJob $job) {
            return $job->isExactly24HoursRemaining();
        });

        $this->sendReminders($candidates, 24, 'reminder_sent');
    }

    protected function process3HourReminders(Carbon $nowLondon, Carbon $tomorrowLondon): void
    {
        $jobs = InterpreterJob::hasEnabledUser()
            ->with(['to_language', 'matchedAgents', 'agent.user', 'assignedMatched.agent.user'])
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
            // Only remind for active assigned/rescheduled jobs (exclude completed, cancelled, DNA, returned, or already-signed timesheets)
            ->whereIn('status', [1, 5])
            ->where('dna', '!=', 1)
            ->where('retrn', '!=', 1)
            ->whereDoesntHave('timesheet', function ($q) {
                $q->whereNotNull('agent_signature')
                    ->orWhereNotNull('client_signature');
            })
            ->where(function ($q) {
                $q->whereHas('agent.user', function ($userQuery) {
                    $userQuery->where('enabled', 1);
                })->orWhereHas('assignedMatched.agent.user', function ($userQuery) {
                    $userQuery->where('enabled', 1);
                });
            })
            ->get();

        $candidates = $jobs->filter(function (InterpreterJob $job) {
            return $job->isApproximatelyHoursRemaining(3, 15);
        });

        $this->sendReminders($candidates, 3, 'three_hour_reminder_sent');
    }

    protected function sendReminders($jobs, int $hoursToGo, string $sentColumn): void
    {
        $attempted = 0;
        $sent = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            $user = $this->resolveAssignedUser($job);
            if (!$user) {
                $failed++;
                Log::warning('Reminder skipped: no assigned enabled user', [
                    'job_id' => $job->id,
                    'hours_to_go' => $hoursToGo,
                ]);
                continue;
            }

            $attempted++;
            $user->interpreter_job = $job;
            $language = optional($job->to_language)->name ?? 'Interpreting';
            $pushTitle = $hoursToGo === 24 ? 'Job Tomorrow' : 'Job Starting Soon';
            $pushBody = $hoursToGo === 24
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
                    'hoursToGo' => $hoursToGo,
                ],
                'tag' => "reminder-{$hoursToGo}h-{$job->id}",
            ];
            $pushSubscriptionCount = PushSubscription::where('user_id', $user->id)->count();
            $pushRequired = $pushSubscriptionCount > 0;

            $emailSent = false;
            $appSent = false;
            $smsSent = false;
            $databaseSent = false;

            try {
                $subject = $hoursToGo === 24 ? 'Job Reminder - 24 Hours' : 'Job Reminder - 3 Hours';
                Mail::to($user)->send(
                    (new JobMail(
                        $job,
                        'emails.agents.interpreter-job-reminder',
                        $subject,
                        'agent'
                    ))->with([
                        'hours_to_go' => $hoursToGo,
                    ])
                );
                $emailSent = true;
                Log::info('Reminder email sent', [
                    'job_id' => $job->id,
                    'user_id' => $user->id,
                    'hours_to_go' => $hoursToGo,
                ]);
            } catch (\Throwable $e) {
                Log::error('Reminder email failed', [
                    'job_id' => $job->id,
                    'user_id' => $user->id,
                    'hours_to_go' => $hoursToGo,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $user->notify(new JobReminder($hoursToGo));
                $databaseSent = true;
            } catch (\Throwable $e) {
                Log::warning('Reminder database notification failed', [
                    'job_id' => $job->id,
                    'user_id' => $user->id,
                    'hours_to_go' => $hoursToGo,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $appSent = PushNotificationService::sendToUser($user, $pushTitle, $pushBody, $pushData);
            } catch (\Throwable $e) {
                Log::warning('Reminder push notification failed', [
                    'job_id' => $job->id,
                    'user_id' => $user->id,
                    'hours_to_go' => $hoursToGo,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $phone = optional($user->agent)->contact_number ?? null;
                if ($phone) {
                    $smsMessage = sprintf(
                        'Reminder: Job %s starts in %d hours on %s at %s. Login for details: %s',
                        $job->reference,
                        $hoursToGo,
                        optional($job->appointment_date)->format('d/m/Y'),
                        $job->start_time,
                        route('interpreter-jobs.show', $job->id)
                    );
                    $smsSent = SmsService::send($phone, $smsMessage);
                }
            } catch (\Throwable $e) {
                Log::warning('Reminder SMS failed', [
                    'job_id' => $job->id,
                    'user_id' => $user->id,
                    'hours_to_go' => $hoursToGo,
                    'error' => $e->getMessage(),
                ]);
            }

            $anyChannelSent = $emailSent || $appSent || $smsSent || $databaseSent;
            if ($anyChannelSent && Schema::hasColumn('interpreter_jobs', $sentColumn)) {
                InterpreterJob::whereKey($job->id)->update([$sentColumn => true]);
                $job->{$sentColumn} = true;
                $sent++;
            } else {
                $failed++;
                Log::warning('Reminder delivery failed across all channels', [
                    'job_id' => $job->id,
                    'user_id' => $user->id,
                    'hours_to_go' => $hoursToGo,
                    'email_sent' => $emailSent,
                    'push_sent' => $appSent,
                    'push_required' => $pushRequired,
                    'push_subscriptions' => $pushSubscriptionCount,
                    'database_sent' => $databaseSent,
                    'sms_sent' => $smsSent,
                ]);
            }
        }

        Log::info('Reminder window summary', [
            'hours_to_go' => $hoursToGo,
            'jobs_in_window' => $jobs->count(),
            'attempted' => $attempted,
            'sent' => $sent,
            'failed' => $failed,
        ]);
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
