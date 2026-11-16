<?php

namespace App\Jobs;

use App\Agent;
use App\MatchedAgent;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\PushNotificationService;
use App\Services\NotificationHelper;
use App\Notifications\JobAssignedNotification;

class MatchAgents
{
    use Dispatchable;
    use SerializesModels;

    protected $newJob;
    protected $matchedAgentIdToIgnore;
    protected $matchedAgentIdToSelect;

    public function __construct($newJob, ?int $matchedAgentIdToIgnore = null, ?int $matchedAgentIdToSelect = null)
    {
        $this->newJob = $newJob;
        $this->matchedAgentIdToIgnore = $matchedAgentIdToIgnore;
        $this->matchedAgentIdToSelect = $matchedAgentIdToSelect;
    }

    public function handle()
    {
        // GUARD: Never match agents for jobs with past appointment dates
        // This prevents notifications being sent for old/expired jobs when
        // someone unassigns, edits, or otherwise triggers re-matching.
        if (get_class($this->newJob) == 'App\InterpreterJob') {
            $appointmentDate = $this->newJob->appointment_date;
            if ($appointmentDate && \Carbon\Carbon::parse($appointmentDate)->endOfDay()->lt(\Carbon\Carbon::now('Europe/London'))) {
                \Illuminate\Support\Facades\Log::info('MatchAgents skipped: appointment date in the past', [
                    'job_id' => $this->newJob->id,
                    'appointment_date' => optional($appointmentDate)->toDateString(),
                ]);
                return;
            }
        } elseif (get_class($this->newJob) == 'App\TranslatorJob') {
            $targetDate = $this->newJob->target_date ?? null;
            if ($targetDate && \Carbon\Carbon::parse($targetDate)->endOfDay()->lt(\Carbon\Carbon::now('Europe/London'))) {
                \Illuminate\Support\Facades\Log::info('MatchAgents skipped: target date in the past', [
                    'job_id' => $this->newJob->id,
                    'target_date' => $targetDate,
                ]);
                return;
            }
        }

        if (get_class($this->newJob) == 'App\InterpreterJob') {

            $matchedAgents = Agent::when($this->matchedAgentIdToSelect === null, function ($query) {
                return $query->canBeMatchedToInterpreterJobs($this->newJob)
                    ->whereDoesntHave('matchedJobs', function ($q) {
                        $q->where('id', $this->matchedAgentIdToIgnore);
                        //  $q->orWhere('status', 1);
                    })
                    ->hasEnabledUser()
                    ->matchesInterpreterJob($this->newJob);
            }, function ($query) {
                // FIXED: Apply the same filtering logic even for requested agents
                return $query->where('id', $this->matchedAgentIdToSelect)
                    ->canBeMatchedToInterpreterJobs($this->newJob)
                    ->hasEnabledUser()
                    ->matchesInterpreterJob($this->newJob);
            })
                ->withDistanceFromInterpreterJob($this->newJob)
                ->get()
                ->map(function ($agent) {
                    return [
                        'agent_id' => $agent->id,
                        'distance' => $agent->distance,
                    ];
                });


            #################### DON'T TOUCH #######################

            // $previouslyRejectedAgents = MatchedAgent::where('job_id', $this->newJob->id)
            // ->where('status', 3) // Status 1 indicates rejected
            // ->get();

            // foreach ($previouslyRejectedAgents as $rejectedAgent) {
            //     $matchedAgents->push([
            //         'agent_id' => $rejectedAgent->agent_id,
            //         'distance' => $rejectedAgent->distance,
            //     ]);
            // }

        } elseif (get_class($this->newJob) == 'App\TranslatorJob') {

            $matchedAgents = Agent::when($this->matchedAgentIdToSelect === null, function ($query) {
                return $query->canBeMatchedToTranslatorJobs()
                    ->whereDoesntHave('matchedJobs', function ($q) {
                        $q->where('id', $this->matchedAgentIdToIgnore);
                    })
                    ->hasEnabledUser()
                    ->matchesTranslatorJob($this->newJob);
            }, function ($query) {
                // FIXED: Apply the same filtering logic even for requested agents
                return $query->where('id', $this->matchedAgentIdToSelect)
                    ->canBeMatchedToTranslatorJobs()
                    ->hasEnabledUser()
                    ->matchesTranslatorJob($this->newJob);
            })
                ->get()
                ->map(function ($agent) {
                    return [
                        'agent_id' => $agent->id,
                    ];
                });
        }

        $newSet = collect($matchedAgents)->keyBy('agent_id');

        $existing = $this->newJob->matchedAgents()->with('quotes')->get();

        foreach ($existing as $ma) {
            $shouldIgnore = $this->matchedAgentIdToIgnore !== null && (int) $ma->id === (int) $this->matchedAgentIdToIgnore;
            if ($shouldIgnore) {
                continue;
            }

            $statusId = method_exists($ma, 'getStatusIdAttribute') ? $ma->status_id : null;
            $isAcceptedOrAssigned = in_array($statusId, [2, 4], true);
            $hasQuoted = $ma->quotes()->exists();

            $existsInNew = $newSet->has((int) $ma->agent_id);

            if (!$existsInNew && !$isAcceptedOrAssigned && !$hasQuoted) {
                $ma->delete();
            }
        }

        $existingByAgent = $existing->keyBy('agent_id');
        foreach ($newSet as $agentId => $payload) {
            $existingMa = $existingByAgent->get($agentId);
            if ($existingMa) {
                if (array_key_exists('distance', $payload)) {
                    $existingMa->distance = $payload['distance'];
                    $existingMa->save();
                }
            } else {
                $this->newJob->matchedAgents()->create([
                    'agent_id' => $payload['agent_id'],
                    'distance' => $payload['distance'] ?? null,
                ]);

                // Send in-app notification to newly matched agent
                $jobType = get_class($this->newJob) == 'App\InterpreterJob' ? 'interpreter' : 'translator';
                $agent = Agent::find($payload['agent_id']);
                if ($agent && $agent->user) {
                    NotificationHelper::send($agent->user, new JobAssignedNotification($this->newJob, $jobType));
                }
            }
        }

        // Send push notifications to newly matched agents
        $this->notifyMatchedAgents($newSet);
    }

    /**
     * Send push notifications to newly matched agents.
     */
    private function notifyMatchedAgents($matchedAgents)
    {
        try {
            $job = $this->newJob;
            $isInterpreter = get_class($job) === 'App\InterpreterJob';
            $jobType = $isInterpreter ? 'interpreting' : 'translation';

            // Extract language name safely (from_language can be a relation/object)
            $language = '';
            try {
                if ($isInterpreter) {
                    $language = optional($job->toLanguage)->name ?? optional($job->fromLanguage)->name ?? '';
                }
            } catch (\Throwable $e) {
            }

            // Format date nicely
            $dateStr = '';
            try {
                $rawDate = $isInterpreter ? $job->appointment_date : ($job->target_date ?? null);
                if ($rawDate) {
                    $dateStr = \Carbon\Carbon::parse($rawDate)->format('d M Y');
                }
            } catch (\Throwable $e) {
            }

            $title = 'New Job Match';
            $body = $language
                ? "You've been matched to a {$language} {$jobType} job"
                : "You've been matched to a new {$jobType} job";
            if ($dateStr) {
                $body .= " on {$dateStr}";
            }

            foreach ($matchedAgents as $matched) {
                $agentId = $matched['agent_id'] ?? null;
                if (!$agentId)
                    continue;

                $agent = Agent::find($agentId);
                if (!$agent || !$agent->user)
                    continue;

                PushNotificationService::notifyUser($agent->user->id, $title, $body, [
                    'type' => 'new_job_match',
                    'jobId' => $job->id,
                    'url' => '/jobs/' . ($isInterpreter ? 'interpreter' : 'translator') . '-' . $job->id,
                ]);
            }
        } catch (\Throwable $e) {
            error_log('Push notification to matched agents failed: ' . $e->getMessage());
        }
    }
}
