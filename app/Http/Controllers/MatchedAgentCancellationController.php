<?php

namespace App\Http\Controllers;

use App\Mail\JobMail;
use App\MatchedAgent;
use App\Jobs\MatchAgents;
use App\Http\Requests\CancelRequest;
use Illuminate\Support\Facades\Mail;
use App\Traits\LogsUpdates;

class MatchedAgentCancellationController extends Controller
{
    use LogsUpdates;
    public function store(CancelRequest $request, MatchedAgent $matchedAgent)
    {
        $route = get_class($matchedAgent->job) === 'App\InterpreterJob' ? 'interpreter-jobs.index' : 'translator-jobs.index';

        tap($matchedAgent)
            ->update(['status' => 3])
            ->cancellation()
            ->create(['message' => $request->message]);

        // Capture the agent_id before nullifying — needed for audit logging.
        // Use the raw column instead of the relationship to avoid null issues.
        $agentId = $matchedAgent->job->agent_id;

        // Only unassign and send notifications if there's actually an assigned agent
        if (!$agentId) {
            return redirect()->route($route)->with('success', 'Job Cancelled');
        }

        $matchedAgent->job->update([
            'agent_id' => null,
            'status' => 0,
        ]);

        try {
            $adminEmail = config('app.to.address') ?: 'bookings@atozinterpreting.com';
            $agentName = optional(optional($matchedAgent->agent)->user)->getFullName()
                ?: optional(optional($matchedAgent->agent)->user)->full_name
                ?: 'an agent';

            Mail::to($adminEmail)->send(
                (new JobMail(
                    $matchedAgent->job,
                    'emails.matched-agents.cancelled',
                    'Agent has cancelled their assigned job',
                    'admin'
                ))->withData(['agentName' => $agentName])
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send agent cancellation email to admin: ' . $e->getMessage());
        }

        try {
            $clientUser = optional($matchedAgent->job->client)->user;
            if ($clientUser && filter_var($clientUser->email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($clientUser)->send(new JobMail(
                    $matchedAgent->job,
                    'emails.matched-agents.agent-unavailable',
                    'Agent no longer available',
                    'client'
                ));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send agent unavailable email to client: ' . $e->getMessage());
        }

        $this->logUpdate(
            $matchedAgent->job->id,
            'interpreter-jobs',
            auth()->user()->id,
            $agentId,
            1,
            'Job Rejected by Agent'
        );
        MatchAgents::dispatch($matchedAgent->job, $matchedAgent->id);

        return redirect()->route($route)->with('success', 'Job Cancelled');
    }
}

