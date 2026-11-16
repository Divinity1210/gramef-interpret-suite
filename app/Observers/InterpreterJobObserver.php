<?php

namespace App\Observers;

use App\Agent;
use Carbon\Carbon;
use App\Mail\JobMail;
use GuzzleHttp\Client;
use App\InterpreterJob;
use App\Jobs\MatchAgents;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Timesheet;
use App\PushSubscription;
use App\Notifications\JobReminder;
use App\Notifications\JobUpdatedNotification;
use App\Services\NotificationHelper;
use App\Services\PushNotificationService;
use App\Services\SmsService;
use Illuminate\Support\Facades\Schema;

class InterpreterJobObserver
{
    public function created(InterpreterJob $interpreterJob)
    {

        try {
            Mail::to(config('app.to.address'))->send(new JobMail(
                $interpreterJob,
                'emails.interpreter-jobs.created',
                'Job Request',
                'admin'
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to send job created email to admin (job ' . $interpreterJob->id . '): ' . $e->getMessage());
        }

        $this->sendClientMail(
            $interpreterJob,
            'emails.interpreter-jobs.created',
            'Job Request Confirmation'
        );

        MatchAgents::dispatch($interpreterJob, null, $interpreterJob->requested_agent_id);
    }

    public function saving(InterpreterJob $interpreterJob)
    {
        if ($interpreterJob->isDirty(['appointment_date', 'start_time'])) {
            if (Schema::hasColumn('interpreter_jobs', 'reminder_sent')) {
                $interpreterJob->reminder_sent = false;
            }
            if (Schema::hasColumn('interpreter_jobs', 'three_hour_reminder_sent')) {
                $interpreterJob->three_hour_reminder_sent = false;
            }
        }
        if ($interpreterJob->isDirty(['start_time', 'duration_hours', 'duration_minutes'])) {
            $interpreterJob->end_time = Carbon::parse($interpreterJob->start_time)
                ->addHours($interpreterJob->duration_hours)
                ->addMinutes($interpreterJob->duration_minutes);
        }

        if (!$interpreterJob->hasAddressFields()) {
            return;
        }
        /*
                if ($interpreterJob->isDirty('postcode') && isset($interpreterJob->postcode)) {
                    $location = json_decode(file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($interpreterJob->postcode) . '&key=AIzaSyDKypyV_to1UeVcCmygrW9UIa_VVHGHFXU'), true);

                    if (!empty($location['results'])) {
                        $interpreterJob->longitude = $location['results'][0]['geometry']['location']['lng'];
                        $interpreterJob->latitude = $location['results'][0]['geometry']['location']['lat'];
                    } else {
                        $location = json_decode(file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($interpreterJob->address_line_1) . ',' . urlencode($interpreterJob->address_line_2) . ',' . urlencode($interpreterJob->county) . '&key=AIzaSyDKypyV_to1UeVcCmygrW9UIa_VVHGHFXU'), true);

                        if (!empty($location['results'])) {
                            $interpreterJob->longitude = $location['results'][0]['geometry']['location']['lng'];
                            $interpreterJob->latitude = $location['results'][0]['geometry']['location']['lat'];
                        }
                    }
                }
                    */
        //Added by Sanmi Amos to fix the issue of the longitude and latitude not being updated by removing google api call and using postcodes.io api(for UK alone)
        $postcode = $interpreterJob->postcode;
        if ($interpreterJob->isDirty('postcode') && isset($interpreterJob->postcode)) {
            $client = new Client();
            $url = "https://api.postcodes.io/postcodes/{$postcode}";

            try {
                $response = $client->request('GET', $url);
                $data = json_decode($response->getBody()->getContents(), true);

                if (isset($data['result'])) {
                    $interpreterJob->longitude = $data['result']['longitude'];
                    $interpreterJob->latitude = $data['result']['latitude'];
                }
            } catch (\Exception $e) {
                return null;
            }

        }
    }

    public function updated(InterpreterJob $interpreterJob)
    {
        Log::info('Interpreter Job Updated');

        // GUARD: Skip external notifications (agent/client emails, push) for past-date jobs.
        // Admin-only emails for cancellations are still allowed via the cancel controller.
        $appointmentDate = $interpreterJob->appointment_date;
        $isPastJob = $appointmentDate && Carbon::parse($appointmentDate)->endOfDay()->lt(Carbon::now('Europe/London'));
        if ($isPastJob) {
            Log::info('Observer skipping external notifications for past-date job', [
                'job_id' => $interpreterJob->id,
                'appointment_date' => optional($appointmentDate)->toDateString(),
                'dirty' => $interpreterJob->getDirty(),
            ]);
            return;
        }
        if ($interpreterJob->isDirty('status') && $interpreterJob->statusName === 'pending') {

            $original = new InterpreterJob($interpreterJob->getOriginal());

            try {
                Mail::to(config('app.to.address'))->send(new JobMail(
                    [
                        'updated' => $interpreterJob,
                        'original' => $original,
                    ],
                    'emails.interpreter-jobs.updated',
                    'Job Updated',
                    'admin'
                ));
            } catch (\Throwable $e) {
                Log::error('Failed to send job updated email (pending) to admin (job ' . $interpreterJob->id . '): ' . $e->getMessage());
            }

            // Notify client that job is back to pending / agent unassigned
            $this->sendClientMail(
                $interpreterJob,
                'emails.matched-agents.agent-unavailable',
                'Agent no longer available'
            );

            return;
        }

        if ($interpreterJob->agent_id) {
            if (!$interpreterJob->agent) {
                $interpreterJob->load('agent');
            }
        }

        if ($interpreterJob->isDirty('status') && $interpreterJob->statusName === 'rejected') {

            try {

                Mail::to(config('app.to.address'))->send(new JobMail(
                    $interpreterJob,
                    'emails.agents.quote-rejected',
                    'Quoted not successful',
                    'admin'
                ));

                Mail::to($interpreterJob->agent->user)->send(new JobMail(
                    $interpreterJob,
                    'emails.agents.quote-rejected',
                    'Quoted not successful',
                    'agent'
                ));

            } catch (\Exception $e) {
                Log::error('Failed to send quote rejected emails (job ' . $interpreterJob->id . '): ' . $e->getMessage());
            } catch (\Throwable $e) {
                Log::error('Failed to send quote rejected emails (job ' . $interpreterJob->id . '): ' . $e->getMessage());
            }

            return;
        }

        if ($interpreterJob->isDirty('status')) {
            if ($interpreterJob->statusName === 'assigned' && $interpreterJob->agent_id) {
                try {
                    $ts = Timesheet::firstOrNew(['job_id' => $interpreterJob->id]);
                    $ts->agent_id = $interpreterJob->agent_id;
                    $ts->status = 'Y';
                    $ts->save();
                } catch (\Throwable $e) { /* fail-safe: do not break assignment due to timesheet */
                }
            }

            if ($interpreterJob->statusName !== 'completed') {
                try {

                    Mail::to(config('app.to.address'))->send(new JobMail(
                        $interpreterJob,
                        'emails.interpreter-jobs.status-update',
                        'Job ' . ucfirst($interpreterJob->statusName),
                        'admin'
                    ));

                } catch (\Exception $e) {
                    Log::error('Failed to send job status update to admin (job ' . $interpreterJob->id . '): ' . $e->getMessage());
                } catch (\Throwable $e) {
                    Log::error('Failed to send job status update to admin (job ' . $interpreterJob->id . '): ' . $e->getMessage());
                }
            }

            $clientSubject = 'Job ' . ucfirst($interpreterJob->statusName);
            Log::info('Observer: About to send client status email', [
                'job_id' => $interpreterJob->id,
                'status' => $interpreterJob->statusName,
                'subject' => $clientSubject,
                'client_id' => $interpreterJob->client_id,
                'client_email' => optional(optional($interpreterJob->client)->user)->email,
            ]);

            $this->sendClientMail(
                $interpreterJob,
                'emails.interpreter-jobs.status-update',
                $clientSubject
            );



            if ($interpreterJob->agent_id) {

                try {

                    Mail::to($interpreterJob->agent->user)->send(new JobMail(
                        $interpreterJob,
                        'emails.interpreter-jobs.status-update',
                        'Job ' . ucfirst($interpreterJob->statusName),
                        'agent'
                    ));

                } catch (\Exception $e) {
                    Log::error('Failed to send job status update to agent (job ' . $interpreterJob->id . '): ' . $e->getMessage());
                } catch (\Throwable $e) {
                    Log::error('Failed to send job status update to agent (job ' . $interpreterJob->id . '): ' . $e->getMessage());
                }


            } elseif ($interpreterJob->statusName === 'cancelled') {
                $agent = Agent::find($interpreterJob->getOriginal('agent_id'));
                if ($agent) {

                    try {

                        Mail::to($agent->user)->send(new JobMail(
                            $interpreterJob,
                            'emails.interpreter-jobs.status-update',
                            'Job ' . ucfirst($interpreterJob->statusName),
                            'agent'
                        ));

                    } catch (\Exception $e) {
                        Log::error('Failed to send job status update to agent (cancelled) (job ' . $interpreterJob->id . '): ' . $e->getMessage());
                    } catch (\Throwable $e) {
                        Log::error('Failed to send job status update to agent (cancelled) (job ' . $interpreterJob->id . '): ' . $e->getMessage());
                    }

                }
            }

            return;
        }

        // Handle agent_id change WITHOUT status change (e.g., admin reassigning via edit form)
        if ($interpreterJob->isDirty('agent_id') && $interpreterJob->statusName === 'assigned') {
            $oldAgentId = $interpreterJob->getOriginal('agent_id');
            $newAgentId = $interpreterJob->agent_id;

            // Update timesheet to new agent
            if ($newAgentId) {
                try {
                    $ts = Timesheet::firstOrNew(['job_id' => $interpreterJob->id]);
                    $ts->agent_id = $newAgentId;
                    $ts->status = 'Y';
                    $ts->save();
                } catch (\Throwable $e) { /* fail-safe */
                }
            }

            // Notify the OLD agent they have been removed
            if ($oldAgentId && $oldAgentId != $newAgentId) {
                try {
                    $oldAgent = Agent::find($oldAgentId);
                    if ($oldAgent && $oldAgent->user) {
                        Mail::to($oldAgent->user)->send(new JobMail(
                            $interpreterJob,
                            'emails.interpreter-jobs.status-update',
                            'Job Unassigned',
                            'agent'
                        ));
                        Log::info('Sent unassignment email to previous agent #' . $oldAgentId . ' for job #' . $interpreterJob->id);
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to send unassignment email to old agent (job ' . $interpreterJob->id . '): ' . $e->getMessage());
                }
            }

            // Notify the NEW agent they have been assigned
            if ($newAgentId) {
                try {
                    Mail::to($interpreterJob->agent->user)->send(new JobMail(
                        $interpreterJob,
                        'emails.interpreter-jobs.status-update',
                        'Job Assigned',
                        'agent'
                    ));
                    Log::info('Sent assignment email to new agent #' . $newAgentId . ' for job #' . $interpreterJob->id);
                } catch (\Throwable $e) {
                    Log::error('Failed to send assignment email to new agent (job ' . $interpreterJob->id . '): ' . $e->getMessage());
                }
            }

            // Notify the CLIENT that an interpreter has been assigned / reassigned
            $this->sendClientMail(
                $interpreterJob,
                'emails.interpreter-jobs.status-update',
                'Job Assigned'
            );

            return;
        }

        if ($interpreterJob->isDirty(['appointment_date', 'start_time', 'end_time', 'duration_hours', 'duration_minutes'])) {

            $original = new InterpreterJob($interpreterJob->getOriginal());
            try {
                $nowLondon = Carbon::now('Europe/London');
                $oldDate = optional($original->appointment_date)->toDateString();
                $oldStart = $original->start_time;
                $newDate = optional($interpreterJob->appointment_date)->toDateString();
                $newStart = $interpreterJob->start_time;
                $newStartDt = $interpreterJob->appointment_date
                    ? Carbon::parse($interpreterJob->appointment_date->setTimeFromTimeString($interpreterJob->start_time))->timezone('Europe/London')
                    : null;
                $diffMinutes = $newStartDt ? $newStartDt->diffInMinutes($nowLondon, false) : null;
                Log::info('Interpreter job time fields changed', [
                    'job_id' => $interpreterJob->id,
                    'old_date' => $oldDate,
                    'old_start' => $oldStart,
                    'new_date' => $newDate,
                    'new_start' => $newStart,
                    'system_now_london' => $nowLondon->toDateTimeString(),
                    'minutes_until_start' => $diffMinutes,
                ]);
            } catch (\Throwable $e) { /* ignore logging failures */
            }

            try {
                Mail::to(config('app.to.address'))->send(new JobMail(
                    [
                        'updated' => $interpreterJob,
                        'original' => $original,
                    ],
                    'emails.interpreter-jobs.updated',
                    'Job Updated',
                    'admin'
                ));
            } catch (\Throwable $e) {
                Log::error('Failed to send job updated email to admin (time change) (job ' . $interpreterJob->id . '): ' . $e->getMessage());
            }

            $this->sendClientMail(
                [
                    'updated' => $interpreterJob,
                    'original' => $original,
                ],
                'emails.interpreter-jobs.updated',
                'Job Updated'
            );

            if ($interpreterJob->agent_id) {
                try {
                    Mail::to($interpreterJob->agent->user)->send(new JobMail(
                        [
                            'updated' => $interpreterJob,
                            'original' => $original,
                        ],
                        'emails.agents.interpreter-job-updated',
                        'Job Updated',
                        'agent'
                    ));
                } catch (\Exception $e) {
                    Log::error('Failed to send job updated email to agent (time change) (job ' . $interpreterJob->id . '): ' . $e->getMessage());
                } catch (\Throwable $e) {
                    Log::error('Failed to send job updated email to agent (time change) (job ' . $interpreterJob->id . '): ' . $e->getMessage());
                }

                NotificationHelper::send($interpreterJob->agent->user, new JobUpdatedNotification($interpreterJob, 'interpreter'));
            }

            $this->sendReminderIfDueAfterTimeChange($interpreterJob);
            return;
        }

        if (\App\Helpers\RoleHelper::hasAnyRole(auth()->user(), ['admin', 'client'])) {
            $meaningfulFields = [
                'appointment_date',
                'start_time',
                'end_time',
                'duration_hours',
                'duration_minutes',
                'to_language_id',
                'from_language_id',
                'skill_id',
                'department',
                'contact_information',
                'user_first_name',
                'user_last_name',
                'file_reference',
                'personal_identity_number',
                'client_reference',
                'address_line_1',
                'address_line_2',
                'county',
                'postcode',
                'agent_id',
                'requested_agent_id',
                'require_qualified',
                'security_type_id',
                'gender',
                'special_requirements'
            ];
            if ($interpreterJob->isDirty($meaningfulFields)) {
                $original = new InterpreterJob($interpreterJob->getOriginal());

                try {
                    Mail::to(config('app.to.address'))->send(new JobMail(
                        [
                            'updated' => $interpreterJob,
                            'original' => $original,
                        ],
                        'emails.interpreter-jobs.updated',
                        'Job Updated',
                        'admin'
                    ));
                } catch (\Throwable $e) {
                    Log::error('Failed to send job updated email to admin (job ' . $interpreterJob->id . '): ' . $e->getMessage());
                }

                $this->sendClientMail(
                    [
                        'updated' => $interpreterJob,
                        'original' => $original,
                    ],
                    'emails.interpreter-jobs.updated',
                    'Job Updated'
                );

                if ($interpreterJob->agent_id) {
                    try {
                        Mail::to($interpreterJob->agent->user)->send(new JobMail(
                            [
                                'updated' => $interpreterJob,
                                'original' => $original,
                            ],
                            'emails.agents.interpreter-job-updated',
                            'Job Updated',
                            'agent'
                        ));
                    } catch (\Exception $e) {
                        Log::error('Failed to send job updated email to agent (job ' . $interpreterJob->id . '): ' . $e->getMessage());
                    } catch (\Throwable $e) {
                        Log::error('Failed to send job updated email to agent (job ' . $interpreterJob->id . '): ' . $e->getMessage());
                    }

                    NotificationHelper::send($interpreterJob->agent->user, new JobUpdatedNotification($interpreterJob, 'interpreter'));
                }

                return;
            }
        }
    }

    protected function sendReminderIfDueAfterTimeChange(InterpreterJob $interpreterJob): void
    {
        $user = $this->resolveAssignedUser($interpreterJob);
        if (!$user || (int) ($user->enabled ?? 0) !== 1) {
            return;
        }

        $windows = [
            ['hours' => 3, 'column' => 'three_hour_reminder_sent', 'subject' => 'Job Reminder - 3 Hours'],
            ['hours' => 24, 'column' => 'reminder_sent', 'subject' => 'Job Reminder - 24 Hours'],
        ];

        foreach ($windows as $window) {
            $hours = (int) $window['hours'];
            $sentColumn = (string) $window['column'];
            if (Schema::hasColumn('interpreter_jobs', $sentColumn) && (bool) $interpreterJob->{$sentColumn}) {
                continue;
            }
            if (!$interpreterJob->isApproximatelyHoursRemaining($hours, 15)) {
                continue;
            }

            $language = optional($interpreterJob->toLanguage)->name ?? 'Interpreting';
            $pushTitle = $hours === 24 ? 'Job Tomorrow' : 'Job Starting Soon';
            $pushBody = $hours === 24
                ? "Reminder: {$language} job starts tomorrow"
                : "Reminder: {$language} job starts in 3 hours";
            $pushData = [
                'type' => 'reminder',
                'title' => $pushTitle,
                'body' => $pushBody,
                'url' => "/(tabs)/jobs/interpreter-{$interpreterJob->id}",
                'icon' => '/icon-192.png',
                'extra' => [
                    'jobId' => $interpreterJob->id,
                    'hoursToGo' => $hours,
                ],
                'tag' => "reminder-{$hours}h-{$interpreterJob->id}",
            ];

            $emailSent = false;
            $pushSent = false;
            $databaseSent = false;
            $smsSent = false;
            $pushRequired = PushSubscription::where('user_id', $user->id)->exists();

            try {
                Mail::to($user)->send(
                    (new JobMail(
                        $interpreterJob,
                        'emails.agents.interpreter-job-reminder',
                        (string) $window['subject'],
                        'agent'
                    ))->with([
                        'hours_to_go' => $hours,
                    ])
                );
                $emailSent = true;
            } catch (\Throwable $e) {
                Log::warning('Immediate reminder email failed after time update', [
                    'job_id' => $interpreterJob->id,
                    'user_id' => $user->id,
                    'hours_to_go' => $hours,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $user->interpreter_job = $interpreterJob;
                $user->notify(new JobReminder($hours));
                $databaseSent = true;
            } catch (\Throwable $e) {
                Log::warning('Immediate reminder database notify failed after time update', [
                    'job_id' => $interpreterJob->id,
                    'user_id' => $user->id,
                    'hours_to_go' => $hours,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $pushSent = PushNotificationService::sendToUser($user, $pushTitle, $pushBody, $pushData);
            } catch (\Throwable $e) {
                Log::warning('Immediate reminder push failed after time update', [
                    'job_id' => $interpreterJob->id,
                    'user_id' => $user->id,
                    'hours_to_go' => $hours,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $phone = optional($user->agent)->contact_number ?? null;
                if ($phone) {
                    $smsMessage = sprintf(
                        'Reminder: Job %s starts in %d hours on %s at %s. Login for details: %s',
                        $interpreterJob->reference,
                        $hours,
                        optional($interpreterJob->appointment_date)->format('d/m/Y'),
                        $interpreterJob->start_time,
                        route('interpreter-jobs.show', $interpreterJob->id)
                    );
                    $smsSent = SmsService::send($phone, $smsMessage);
                }
            } catch (\Throwable $e) {
                Log::warning('Immediate reminder SMS failed after time update', [
                    'job_id' => $interpreterJob->id,
                    'user_id' => $user->id,
                    'hours_to_go' => $hours,
                    'error' => $e->getMessage(),
                ]);
            }

            $anyChannelSent = $emailSent || $pushSent || $smsSent || $databaseSent;
            if ($anyChannelSent && Schema::hasColumn('interpreter_jobs', $sentColumn)) {
                InterpreterJob::whereKey($interpreterJob->id)->update([$sentColumn => true]);
                $interpreterJob->{$sentColumn} = true;
                Log::info('Immediate reminder delivered after time update', [
                    'job_id' => $interpreterJob->id,
                    'user_id' => $user->id,
                    'hours_to_go' => $hours,
                    'email_sent' => $emailSent,
                    'push_sent' => $pushSent,
                    'database_sent' => $databaseSent,
                    'sms_sent' => $smsSent,
                ]);
            } else {
                Log::warning('Immediate reminder failed across all channels after time update', [
                    'job_id' => $interpreterJob->id,
                    'user_id' => $user->id,
                    'hours_to_go' => $hours,
                    'email_sent' => $emailSent,
                    'push_sent' => $pushSent,
                    'database_sent' => $databaseSent,
                    'sms_sent' => $smsSent,
                    'push_required' => $pushRequired,
                ]);
            }
        }
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

    /**
     * Send email to client with error resilience and relation auto-loading.
     */
    protected function sendClientMail($jobPayload, string $view, string $subject, ?array $extraData = null): void
    {
        try {
            $jobModel = is_array($jobPayload) ? ($jobPayload['updated'] ?? ($jobPayload['original'] ?? null)) : $jobPayload;
            if (!$jobModel) {
                return;
            }

            // Ensure client and user relations are loaded on model
            if ($jobModel instanceof \Illuminate\Database\Eloquent\Model) {
                if (!$jobModel->relationLoaded('client') || !$jobModel->client || !optional($jobModel->client)->relationLoaded('user') || !optional($jobModel->client)->user) {
                    $jobModel->load('client.user');
                }
            }

            $clientUser = optional($jobModel->client)->user;
            $clientEmail = optional($clientUser)->email;

            // Robust fallback if relationship didn't resolve: query Client model directly via client_id
            if ((!$clientEmail || !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) && !empty($jobModel->client_id)) {
                $client = \App\Client::with('user')->find($jobModel->client_id);
                if ($client && $client->user && filter_var($client->user->email, FILTER_VALIDATE_EMAIL)) {
                    $clientUser = $client->user;
                    $clientEmail = $clientUser->email;
                }
            }

            if (!$clientEmail || !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                Log::error('Cannot send client email (job ' . ($jobModel->id ?? 'unknown') . '): missing or invalid client email for client_id ' . ($jobModel->client_id ?? 'unknown'));
                return;
            }

            $mail = new JobMail($jobPayload, $view, $subject, 'client');
            if (!empty($extraData)) {
                $mail->withData($extraData);
            }

            Mail::to($clientUser)->send($mail);
            Log::info('Client notification sent: ' . $subject . ' for job #' . ($jobModel->id ?? '') . ' to ' . $clientEmail);
        } catch (\Throwable $e) {
            $id = is_object($jobPayload) ? ($jobPayload->id ?? '') : '';
            Log::error('Failed to send client email (' . $subject . ') for job ' . $id . ': ' . $e->getMessage());
        }
    }
}
