<?php

namespace App\Policies;

use App\User;
use App\InterpreterJob;
use App\Agent;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Helpers\RoleHelper;
use Illuminate\Support\Facades\Log;

class InterpreterJobPolicy
{
    use HandlesAuthorization;

    public function view(User $user, InterpreterJob $interpreterJob)
    {
        if (RoleHelper::hasAnyRole($user, ['admin', 'super-admin'])) {
            return true;
        }

        // Allow client who owns the job to view it
        if ($user->client && $interpreterJob->client_id === $user->client->id) {
            return true;
        }

        if (!$user->agent) {
            Log::warning('InterpreterJobPolicy view denied: non-agent', [
                'user_id' => $user->id,
                'job_id' => $interpreterJob->id,
                'job_status' => $interpreterJob->statusName,
                'assigned_agent_id' => optional($interpreterJob->agent)->id,
            ]);
            return $this->deny('Only agents can view jobs');
        }
        if (in_array(optional($interpreterJob->matchedLoggedInAgent())->status, ['rejected', 'cancelled'])) {
            Log::warning('InterpreterJobPolicy view denied: matched status rejected/cancelled', [
                'user_id' => $user->id,
                'agent_id' => optional($user->agent)->id,
                'job_id' => $interpreterJob->id,
                'matched_agent_status' => optional($interpreterJob->matchedLoggedInAgent())->status,
            ]);
            return $this->deny('You previously rejected or cancelled this job');
        }


        if ($interpreterJob->statusName == 'cancelled') {
            $agentId = (int) optional($user->agent)->id;
            $isSameDayCancelled = is_object($interpreterJob)
                && method_exists($interpreterJob, 'isCancelledSameDay')
                && $interpreterJob->isCancelledSameDay();

            if ($agentId && $isSameDayCancelled) {
                $timesheetAgentId = (int) optional($interpreterJob->timesheet)->agent_id;
                if ($timesheetAgentId === $agentId) {
                    return true;
                }

                $assignedMatched = optional($interpreterJob->assignedMatched);
                if ((int) optional($assignedMatched)->agent_id === $agentId) {
                    return true;
                }

                if ($interpreterJob->matchedAgents()->where('agent_id', $agentId)->where('status', 4)->exists()) {
                    return true;
                }

                if ((int) $interpreterJob->agent_id === $agentId) {
                    return true;
                }
            }

            Log::warning('InterpreterJobPolicy view denied: job cancelled', [
                'user_id' => $user->id,
                'agent_id' => optional($user->agent)->id,
                'job_id' => $interpreterJob->id,
            ]);
            return $this->deny('This job has been cancelled');
        }

        if ($interpreterJob->statusName == 'dna') {
            Log::warning('InterpreterJobPolicy view denied: job dna', [
                'user_id' => $user->id,
                'agent_id' => optional($user->agent)->id,
                'job_id' => $interpreterJob->id,
            ]);
            return $this->deny('This job has been cancelled');
        }

        if ($user->agent->is($interpreterJob->agent)) {
            return true;
        }

        if ($interpreterJob->agent) {
            Log::info('InterpreterJobPolicy view denied: job already assigned', [
                'user_id' => $user->id,
                'agent_id' => optional($user->agent)->id,
                'job_id' => $interpreterJob->id,
                'assigned_agent_id' => optional($interpreterJob->agent)->id,
            ]);
            return $this->deny('This job has already been assigned to another agent.');
        }

        if (!$interpreterJob->agent && optional($user->agent)->id) {
            if (Agent::whereKey($user->agent->id)->matchesInterpreterJob($interpreterJob)->exists()) {
                return true;
            }
        }

        if ($user->agent->isMatchedToJob($interpreterJob)) {
            return true;
        }

        Log::info('InterpreterJobPolicy view denied: agent not matched to job', [
            'user_id' => $user->id,
            'agent_id' => optional($user->agent)->id,
            'job_id' => $interpreterJob->id,
        ]);
        return $this->deny('You are no longer matched to this job. It may have been taken.');
    }
    public function dna(User $user, InterpreterJob $interpreterJob)
    {
        if (RoleHelper::hasRole($user, 'admin')) {
            return true;
        }
        if ($interpreterJob->canBeDna) {
            return true;
        }
        return false;
    }
    public function retrn(User $user, InterpreterJob $interpreterJob)
    {
        if (RoleHelper::hasRole($user, 'admin')) {
            return true;
        }
        if ($interpreterJob->canBeRetrn) {
            return true;
        }
        return false;
    }
    public function edit(User $user, InterpreterJob $interpreterJob)
    {
        if (RoleHelper::hasRole($user, 'admin')) {
            return true;
        }

        if ($interpreterJob->client->is($user->client)) {
            return true;
        }

        return false;
    }

    public function update(User $user, InterpreterJob $interpreterJob)
    {
        if ($user->cannot('edit', $interpreterJob)) {
            return false;
        }

        // if ($interpreterJob->cannotBeCancelled()) {
        //     return false;
        // }

        $start = $interpreterJob->appointment_date->setTimeFromTimeString($interpreterJob->start_time);

        if (true) {
            return true;
        }

        return true;
    }

    public function viewQuotes(User $user, InterpreterJob $interpreterJob)
    {
        if (!$interpreterJob->requiresQuote()) {
            return false;
        }

        if ($user->can('view', $interpreterJob)) {
            return true;
        }

        if ($user->can('edit', $interpreterJob)) {
            return true;
        }

        return false;
    }

    public function accept(User $user, InterpreterJob $interpreterJob)
    {
        if (!$interpreterJob->isActive()) {
            return $this->deny('This job is no longer available to accept.');
        }

        if (RoleHelper::hasRole($user, 'admin')) {
            return true;
        }

        if (!RoleHelper::hasRole($user, 'agent')) {
            return $this->deny('Only agents can accept jobs.');
        }

        // Check if this agent is matched to the job (regardless of matched status)
        // The controller will handle proper status validation
        $agentId = optional($user->agent)->id;
        if (!$agentId) {
            return $this->deny('You are not allowed to accept this job.');
        }

        // Allow if agent is matched to this job (any status) and meets security requirements
        $isMatched = $interpreterJob->matchedAgents()
            ->where('agent_id', $agentId)
            ->exists();

        if ($isMatched) {
            if (Agent::whereKey($agentId)->canBeMatchedToInterpreterJobs($interpreterJob)->exists()) {
                return true;
            }
            return $this->deny('You do not meet the security clearance or DBS requirements for this job.');
        }

        // Allow if agent meets the job requirements
        if (Agent::whereKey($agentId)->canBeMatchedToInterpreterJobs($interpreterJob)->matchesInterpreterJob($interpreterJob)->exists()) {
            return true;
        }

        return $this->deny('You are not allowed to accept this job.');
    }

    public function assign(User $user, InterpreterJob $interpreterJob)
    {
        if (!RoleHelper::hasAnyRole($user, ['admin', 'super-admin'])) {
            return $this->deny('Only admins and super-admins can assign agents to jobs.');
        }

        if (!$interpreterJob->isActive()) {
            return $this->deny('This job is no longer available to assign.');
        }

        if ((int) ($interpreterJob->agent_id ?? 0) > 0 || $interpreterJob->assignedMatched()->exists()) {
            return $this->deny('This job is already assigned. Unassign the current agent before reassigning.');
        }

        // If this client does not require quotes, allow assignment (defensively handle missing client)
        if (!data_get($interpreterJob, 'client.always_requires_a_quote', false)) {
            return true;
        }

        // For clients that require quotes, prefer an accepted ADMIN quote
        // BaseQuote::STATUSES => [0 => 'quote sent', 1 => 'quote accepted', 2 => 'quote rejected']
        $clientHasAcceptedAdminQuote = $interpreterJob
            ->adminQuotes()
            ->where('status', 1)
            ->exists();

        if ($clientHasAcceptedAdminQuote) {
            return true;
        }

        // Exception: if the job had previously been assigned and then the agent cancelled/unassigned,
        // allow admin to reassign without requiring the client to accept a new admin quote.
        $wasPreviouslyAssigned = \App\Timesheet::where('job_id', $interpreterJob->id)->exists();
        if ($wasPreviouslyAssigned) {
            return true;
        }

        return $this->deny('Client must accept the admin quote before assigning an interpreter.');
    }
    public function unassign(User $user, InterpreterJob $interpreterJob)
    {
        if (RoleHelper::hasAnyRole($user, ['admin', 'super-admin'])) {
            return true;
        }

        if (RoleHelper::hasRole($user, 'agent')) {
            $agentId = (int) optional($user->agent)->id;
            if ($agentId <= 0 || (int) $interpreterJob->agent_id !== $agentId || (int) $interpreterJob->status !== 1) {
                return false;
            }

            try {
                if ($interpreterJob->appointment_date) {
                    $timeStr = $interpreterJob->start_time ?? ($interpreterJob->appointment_time ?? '23:59:59');
                    $dtStr = $interpreterJob->appointment_date->format('Y-m-d') . ' ' . $timeStr;
                    $appointmentDateTime = \Carbon\Carbon::parse($dtStr, 'Europe/London');

                    if (\Carbon\Carbon::now('Europe/London')->gte($appointmentDateTime)) {
                        return $this->deny('You cannot unassign from a job after the appointment time has started.');
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("InterpreterJobPolicy::unassign date parse failed for job {$interpreterJob->id}: " . $e->getMessage());
            }

            return true;
        }

        return false;
    }
    public function complete(User $user, InterpreterJob $interpreterJob)
    {
        if (!$interpreterJob->canBeCompleted()) {
            return false;
        }

        if (RoleHelper::hasAnyRole($user, ['admin', 'super-admin'])) {
            return true;
        }

        if (RoleHelper::hasAnyRole($user, ['agent', 'new-agent'])) {
            return $user->agent && $interpreterJob->agent && $user->agent->is($interpreterJob->agent);
        }

        if (RoleHelper::hasRole($user, 'client')) {
            return $user->client && $interpreterJob->client && $user->client->is($interpreterJob->client);
        }

        return false;
    }


}
