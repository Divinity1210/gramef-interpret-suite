<?php

namespace App\Observers;

use App\Mail\JobMail;
use App\TranslatorJob;
use App\Jobs\MatchAgents;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Helpers\RoleHelper;

class TranslatorJobObserver
{
    public function created(TranslatorJob $translatorJob)
    {
        try {
            Mail::to(config('app.to.address'))->send(new JobMail(
                $translatorJob,
                'emails.translator-jobs.created',
                'Job Request',
                'admin'
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to send translator job created email to admin (job ' . $translatorJob->id . '): ' . $e->getMessage());
        }

        $this->sendClientMail(
            $translatorJob,
            'emails.translator-jobs.created',
            'Job Request Confirmation'
        );

        MatchAgents::dispatch($translatorJob, null, $translatorJob->requested_agent_id);
    }

    public function updated(TranslatorJob $translatorJob)
    {
        if ($translatorJob->isDirty('status') && $translatorJob->statusName === 'pending') {
            // Notify client that job is back to pending / agent unassigned
            $this->sendClientMail(
                $translatorJob,
                'emails.matched-agents.agent-unavailable',
                'Agent no longer available'
            );
            return;
        }

        if ($translatorJob->isDirty('status') && $translatorJob->statusName === 'rejected') {
            try {
                Mail::to(config('app.to.address'))->send(new JobMail(
                    $translatorJob,
                    'emails.agents.quote-rejected',
                    'Quote was not successful',
                    'admin'
                ));

                if ($translatorJob->agent && $translatorJob->agent->user) {
                    Mail::to($translatorJob->agent->user)->send(new JobMail(
                        $translatorJob,
                        'emails.agents.quote-rejected',
                        'Quote was not successful',
                        'agent'
                    ));
                } else {
                    Log::error('Cannot send quote rejected email to agent (translator job ' . $translatorJob->id . '): missing agent or user');
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send quote rejected emails (translator job ' . $translatorJob->id . '): ' . $e->getMessage());
            }

            return;
        }

        if ($translatorJob->isDirty('status')) {
            try {
                Mail::to(config('app.to.address'))->send(new JobMail(
                    $translatorJob,
                    'emails.translator-jobs.status-update',
                    'Job ' . ucfirst($translatorJob->statusName),
                    'admin'
                ));
            } catch (\Throwable $e) {
                Log::error('Failed to send translator job status update to admin (job ' . $translatorJob->id . '): ' . $e->getMessage());
            }

            $this->sendClientMail(
                $translatorJob,
                'emails.translator-jobs.status-update',
                'Job ' . ucfirst($translatorJob->statusName)
            );

            if ($translatorJob->agent && $translatorJob->agent->user) {
                try {
                    Mail::to($translatorJob->agent->user)->send(new JobMail(
                        $translatorJob,
                        'emails.translator-jobs.status-update',
                        'Job ' . ucfirst($translatorJob->statusName),
                        'agent'
                    ));
                } catch (\Throwable $e) {
                    Log::error('Failed to send translator job status update to agent (job ' . $translatorJob->id . '): ' . $e->getMessage());
                }
            }

            return;
        }

        if (RoleHelper::hasAnyRole(auth()->user(), ['admin', 'client'])) {
            $meaningfulFields = [
                'target_date','word_count','to_language_id','from_language_id','client_reference','notes','title',
                'first_name','last_name','email','contact_number','company','affirmation','affidavit','agent_id',
                'requested_agent_id'
            ];
            if ($translatorJob->isDirty($meaningfulFields)) {
                $original = new TranslatorJob($translatorJob->getOriginal());

                try {
                    Mail::to(config('app.to.address'))->send(new JobMail(
                        [
                            'updated' => $translatorJob,
                            'original' => $original,
                        ],
                        'emails.translator-jobs.updated',
                        'Job Updated',
                        'admin'
                    ));
                } catch (\Throwable $e) {
                    Log::error('Failed to send translator job updated emails to admin (job ' . $translatorJob->id . '): ' . $e->getMessage());
                }

                $this->sendClientMail(
                    [
                        'updated' => $translatorJob,
                        'original' => $original,
                    ],
                    'emails.translator-jobs.updated',
                    'Job Updated'
                );

                if ($translatorJob->agent_id && $translatorJob->agent && $translatorJob->agent->user) {
                    try {
                        Mail::to($translatorJob->agent->user)->send(new JobMail(
                            [
                                'updated' => $translatorJob,
                                'original' => $original,
                            ],
                            'emails.agents.translator-job-updated',
                            'Job Updated',
                            'agent'
                        ));
                    } catch (\Throwable $e) {
                        Log::error('Failed to send translator job updated email to agent (job ' . $translatorJob->id . '): ' . $e->getMessage());
                    }
                }

                return;
            }
        }
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
                Log::error('Cannot send client email (translator job ' . ($jobModel->id ?? 'unknown') . '): missing or invalid client email for client_id ' . ($jobModel->client_id ?? 'unknown'));
                return;
            }

            $mail = new JobMail($jobPayload, $view, $subject, 'client');
            if (!empty($extraData)) {
                $mail->withData($extraData);
            }

            Mail::to($clientUser)->send($mail);
            Log::info('Client notification sent: ' . $subject . ' for translator job #' . ($jobModel->id ?? '') . ' to ' . $clientEmail);
        } catch (\Throwable $e) {
            $id = is_object($jobPayload) ? ($jobPayload->id ?? '') : '';
            Log::error('Failed to send client email (' . $subject . ') for translator job ' . $id . ': ' . $e->getMessage());
        }
    }
}
