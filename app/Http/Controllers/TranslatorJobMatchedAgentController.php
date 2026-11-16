<?php

namespace App\Http\Controllers;

use App\Agent;
use App\Mail\JobMail;
use App\MatchedAgent;
use App\TranslatorJob;
use App\Jobs\MatchAgents;
use App\User;
use App\Traits\LogsUpdates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Helpers\RoleHelper;
use App\Services\NotificationHelper;
use App\Notifications\JobAssignedNotification;

class TranslatorJobMatchedAgentController extends Controller
{
    use LogsUpdates;

    public function index(TranslatorJob $translatorJob)
    {
        return view('translator-jobs.matched.index', [
            'translatorJob' => $translatorJob,
            'matchedAgents' => $translatorJob->matchedAgents()
                ->with([
                    'agent.user',
                    'quotes',
                    'job.adminQuotes',
                ])->get(),
            'unmatchedAgents' => Agent::with('user')
                ->canBeMatchedToTranslatorJobs()
                ->hasEnabledUser()
                ->matchesTranslatorJob($translatorJob)
                ->whereNotIn('id', $translatorJob->matchedAgents->pluck('agent_id')->all())
                ->get()
                ->mapWithKeys(function ($agent) {
                    return [
                        $agent->id => $agent->user->getFullName(),
                    ];
                })
        ]);
    }

    public function store(Request $request, TranslatorJob $translatorJob)
    {
        $request->validate([
            'agent_id' => 'required|exists:agents,id',
        ]);

        $agent = Agent::where('id', $request->agent_id)
            ->canBeMatchedToTranslatorJobs()
            ->hasEnabledUser()
            ->matchesTranslatorJob($translatorJob)
            ->first();

        if (!$agent) {
            return back()->with('failure', 'Selected agent cannot be matched to this job or does not meet the requirements.');
        }

        if (!$agent->user || !RoleHelper::hasRole($agent->user, 'agent')) {
            return back()->with('failure', 'Selected agent is not eligible for job matching.');
        }

        $existingMatch = $translatorJob->matchedAgents()->where('agent_id', $request->agent_id)->first();
        if ($existingMatch) {
            return back()->with('failure', 'Agent is already notified for this job.');
        }

        $translatorJob->matchedAgents()->create([
            'agent_id' => $request->agent_id,
        ]);

        MatchAgents::dispatch($translatorJob);

        return back()->with('success', 'Agent notified successfully.');
    }

    public function update(TranslatorJob $translatorJob, ?MatchedAgent $matchedAgent = null)
    {
        if (RoleHelper::hasAnyRole(auth()->user(), ['admin', 'super-admin'])) {
            $this->authorize('assign', $translatorJob);
            if (!$matchedAgent) {
                $routeMatched = request()->route('matchedAgent');
                if ($routeMatched instanceof \App\MatchedAgent) {
                    $matchedAgent = $routeMatched;
                } elseif (is_numeric($routeMatched)) {
                    $matchedAgent = \App\MatchedAgent::with('agent.user')->find($routeMatched);
                }
            }
            if (!$matchedAgent || !$matchedAgent->agent || !$matchedAgent->agent->user) {
                return back()->with('failure', 'Matched agent not found or invalid.');
            }
            if ((int) optional($matchedAgent)->job_id !== (int) $translatorJob->id) {
                return back()->with('failure', 'Selected agent does not belong to this job.');
            }
            if ($translatorJob->agent_id && (int) $translatorJob->agent_id !== (int) $matchedAgent->agent->id) {
                return back()->with('failure', 'This job is already assigned. Unassign the current agent before reassigning.');
            }
            $eligible = Agent::where('id', $matchedAgent->agent->id)
                ->hasEnabledUser()
                ->matchesTranslatorJob($translatorJob)
                ->exists();
            if (!$eligible) {
                return redirect()->back()->with('failure', 'Selected agent does not meet the job requirements.');
            }
            \App\MatchedAgent::where('job_id', $translatorJob->id)
                ->where('job_type', get_class($translatorJob))
                ->where('status', 4)
                ->where('id', '!=', $matchedAgent->id)
                ->update(['status' => 1]);
            $matchedAgent->update(['status' => 4]);
            optional($matchedAgent->latestQuote())->update(['status' => 1]);
            $translatorJob->update([
                'agent_id' => $matchedAgent->agent->id,
                'status' => 1,
            ]);

            // Notify agent: assigned to translator job
            if ($matchedAgent->agent && $matchedAgent->agent->user) {
                NotificationHelper::send($matchedAgent->agent->user, new JobAssignedNotification($translatorJob, 'translator'));
            }

            return redirect()->back()->with('success', 'Agent assigned');
        }

        $this->authorize('accept', $translatorJob);

        tap($translatorJob)
            ->update([
                'agent_id' => auth()->user()->agent->id,
                'status' => 1,
            ])
            ->matchedLoggedInAgent()
            ->update(['status' => 4]);

        return redirect()->back()->with('success', 'Job accepted');
    }

    public function destroy(TranslatorJob $translatorJob, MatchedAgent $matchedAgent)
    {
        $this->authorize('unassign', $translatorJob);

        $actorId = (int) auth()->id();

        // Capture agent details BEFORE the transaction nullifies agent_id
        $preloadedAgent = Agent::with('user')->find($matchedAgent->agent_id);
        $agentName = ($preloadedAgent && $preloadedAgent->user)
            ? $preloadedAgent->user->getFullName()
            : null;

        $state = DB::transaction(function () use ($translatorJob, $matchedAgent, $actorId) {
            $job = TranslatorJob::query()->whereKey($translatorJob->id)->lockForUpdate()->first();
            if (!$job) {
                return ['ok' => false, 'message' => 'Job not found.'];
            }

            $lockedMatched = MatchedAgent::query()
                ->whereKey($matchedAgent->id)
                ->where('job_id', $job->id)
                ->where('job_type', TranslatorJob::class)
                ->lockForUpdate()
                ->first();

            if (!$lockedMatched) {
                return ['ok' => false, 'message' => 'Selected agent does not belong to this job.'];
            }

            $assignedAgentId = (int) $job->agent_id;
            if ($assignedAgentId <= 0 || (int) $job->status !== 1) {
                return ['ok' => false, 'message' => 'This job is not currently assigned.'];
            }

            if ((int) $lockedMatched->agent_id !== $assignedAgentId || (int) $lockedMatched->status_id !== 4) {
                return ['ok' => false, 'message' => 'Selected agent is not the currently assigned agent for this job.'];
            }

            $oldStatus = (int) $job->status;

            // Use saveQuietly to prevent the observer from firing a misleading
            // "Job Updated" email — the controller sends the correct unassignment
            // notifications explicitly below.
            $job->fill(['agent_id' => null, 'status' => 0]);
            $job->saveQuietly();

            $lockedMatched->update(['status' => 3]);

            $this->logUpdate(
                $job->id,
                'translator-jobs',
                $actorId,
                $assignedAgentId,
                '0',
                'Agent unassigned from the job',
                'unassign',
                $oldStatus,
                'succeeded',
                ['matched_agent_id' => (int) $lockedMatched->id]
            );

            return [
                'ok' => true,
                'job' => $job->fresh(['client.user']),
                'assigned_agent_id' => $assignedAgentId,
                'matched_agent_id' => (int) $lockedMatched->id,
            ];
        });

        if (!($state['ok'] ?? false)) {
            return back()->with('failure', $state['message'] ?? 'Unable to unassign agent.');
        }

        $job = $state['job'];
        $assignedAgentId = (int) $state['assigned_agent_id'];
        $matchedAgentId = (int) $state['matched_agent_id'];

        // Use the pre-loaded agent from before the transaction rather than
        // re-querying (agent_id on the job is now null).
        $agentUser = ($preloadedAgent && $preloadedAgent->user) ? $preloadedAgent->user : null;

        MatchAgents::dispatch($job, $matchedAgentId);

        try {
            if (optional($job->client)->user) {
                Mail::to($job->client->user)->send(new JobMail(
                    $job,
                    'emails.matched-agents.agent-unavailable',
                    'Agent no longer available',
                    'client'
                ));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send agent unavailable email to client: ' . $e->getMessage());
        }

        try {
            $adminEmail = config('app.to.address') ?: 'bookings@atozinterpreting.com';
            Mail::to($adminEmail)->send(
                (new JobMail(
                    $job,
                    'emails.matched-agents.cancelled',
                    'Agent unassigned from job',
                    'admin'
                ))->withData(['agentName' => $agentName ?? 'an agent'])
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send unassigned email to admin: ' . $e->getMessage());
        }

        try {
            if ($agentUser) {
                Mail::to($agentUser)->send(new JobMail(
                    $job,
                    'emails.agents.unassigned',
                    'Unassigned from job',
                    'agent'
                ));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send unassigned email to agent: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Agent unassigned');
    }

    public function reject(TranslatorJob $translatorJob)
    {
        /** agent rejected client's changes */
        if ($translatorJob->agent_id) {

            $translatorJob->update([
                'agent_id' => null,
                'status' => 0
            ]);

            try {
                $adminEmail = config('app.to.address') ?: 'bookings@atozinterpreting.com';
                Mail::to($adminEmail)->send(new JobMail(
                    $translatorJob,
                    'emails.matched-agents.rejected-changes',
                    'Agent rejected client\'s changes',
                    'admin'
                ));
            } catch (\Throwable $e) {
                Log::error('Failed to send rejected changes email to admin: ' . $e->getMessage());
            }

            try {
                $clientUser = optional($translatorJob->client)->user;
                if ($clientUser && filter_var($clientUser->email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($clientUser)->send(new JobMail(
                        $translatorJob,
                        'emails.matched-agents.agent-unavailable',
                        'Agent no longer available',
                        'client'
                    ));
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send agent unavailable email to client: ' . $e->getMessage());
            }

            MatchAgents::dispatch($translatorJob);
        }

        $translatorJob
            ->matchedLoggedInAgent()
            ->update([
                'status' => 1
            ]);

        return redirect()->route('translator-jobs.index')->with('success', 'Job rejected');
    }
}
