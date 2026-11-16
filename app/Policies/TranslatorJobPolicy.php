<?php

namespace App\Policies;

use App\User;
use App\TranslatorJob;
use App\Agent;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Helpers\RoleHelper;
use Illuminate\Support\Facades\Log;

class TranslatorJobPolicy
{
    use HandlesAuthorization;

    public function view(User $user, TranslatorJob $translatorJob)
    {
        // Allow administrators to view any translator job
        if (RoleHelper::hasAnyRole($user, ['admin', 'super-admin'])) {
            return true;
        }

        if (!$user->agent) {
            Log::warning('TranslatorJobPolicy view denied: non-agent', [
                'user_id' => $user->id,
                'job_id' => $translatorJob->id,
                'job_status' => $translatorJob->statusName,
                'assigned_agent_id' => optional($translatorJob->agent)->id,
            ]);
            return $this->deny('Only agents can view jobs');
        }

        if (in_array(optional($translatorJob->matchedLoggedInAgent())->status, ['rejected', 'cancelled'])) {
            Log::warning('TranslatorJobPolicy view denied: matched status rejected/cancelled', [
                'user_id' => $user->id,
                'agent_id' => optional($user->agent)->id,
                'job_id' => $translatorJob->id,
                'matched_agent_status' => optional($translatorJob->matchedLoggedInAgent())->status,
            ]);
            return $this->deny('You previously rejected or cancelled this job');
        }

        if ($translatorJob->statusName == 'cancelled') {
            $agentId = (int) optional($user->agent)->id;
            $isSameDayCancelled = is_object($translatorJob)
                && method_exists($translatorJob, 'isCancelledSameDay')
                && $translatorJob->isCancelledSameDay();

            if ($agentId && $isSameDayCancelled) {
                $assignedMatched = optional($translatorJob->assignedMatched);
                if ((int) optional($assignedMatched)->agent_id === $agentId) {
                    return true;
                }
            }

            Log::warning('TranslatorJobPolicy view denied: job cancelled', [
                'user_id' => $user->id,
                'agent_id' => optional($user->agent)->id,
                'job_id' => $translatorJob->id,
            ]);
            return $this->deny('This job has been cancelled');
        }

        if ($user->agent->is($translatorJob->agent)) {
            return true;
        }

        // Agents cannot view jobs that are already assigned to another agent,
        // but admins may still view them (handled above)
        if ($translatorJob->agent()->exists()) {
            Log::info('TranslatorJobPolicy view denied: job already assigned', [
                'user_id' => $user->id,
                'agent_id' => optional($user->agent)->id,
                'job_id' => $translatorJob->id,
                'assigned_agent_id' => optional($translatorJob->agent)->id,
            ]);
            return $this->deny('This job has already been assigned to another agent.');
        }

        if (! $translatorJob->agent && optional($user->agent)->id) {
            if (Agent::whereKey($user->agent->id)->matchesTranslatorJob($translatorJob)->exists()) {
                return true;
            }
        }

        if ($user->agent->isMatchedToJob($translatorJob)) {
            return true;
        }

        Log::info('TranslatorJobPolicy view denied: agent not matched to job', [
            'user_id' => $user->id,
            'agent_id' => optional($user->agent)->id,
            'job_id' => $translatorJob->id,
        ]);
        return $this->deny('You are no longer matched to this job. It may have been taken.');
    }

    public function edit(User $user, TranslatorJob $translatorJob)
    {
        if (RoleHelper::hasAnyRole($user, ['admin', 'super-admin'])) {
            return true;
        }

        if ($translatorJob->client->is($user->client)) {
            return true;
        }

        return false;
    }

    public function update(User $user, TranslatorJob $translatorJob)
    {
        if ($user->cannot('edit', $translatorJob)) {
            return false;
        }
        /*
                if ($translatorJob->cannotBeCancelled()) {
                    return false;
                }

                        if ($translatorJob->target_date->isFuture()) {
                            return true;
                        }

                        return false;
                        */
        return true;
    }


    public function viewQuotes(User $user, TranslatorJob $translatorJob)
    {
        if ($user->can('view', $translatorJob)) {
            return true;
        }

        if ($user->can('edit', $translatorJob)) {
            return true;
        }

        return false;
    }

    public function accept(User $user, TranslatorJob $translatorJob)
    {
        if (!$translatorJob->isActive()) {
            return $this->deny('This job is no longer available to accept.');
        }

        if (RoleHelper::hasRole($user, 'admin')) {
            return true;
        }

        // If this client requires quotes, ensure the client has accepted an ADMIN quote
        if (($translatorJob->client->always_requires_a_quote ?? false)) {
            $clientHasAcceptedAdminQuote = $translatorJob
                ->adminQuotes()
                ->where('status', 1)
                ->exists();

            if (!$clientHasAcceptedAdminQuote) {
                return $this->deny('Client must accept the admin quote before you can accept this job.');
            }
        }

        if ($user->can('view', $translatorJob)) {
            return true;
        }

        return $this->deny('You are not allowed to accept this job.');
    }

    public function assign(User $user, TranslatorJob $translatorJob)
    {
        if (!RoleHelper::hasAnyRole($user, ['admin', 'super-admin'])) {
            return $this->deny('Only admins and super-admins can assign agents to jobs.');
        }

        if (!$translatorJob->isActive()) {
            return $this->deny('This job is no longer available to assign.');
        }
        if ($translatorJob->agent_id) {
            return $this->deny('This job is already assigned. Unassign the current agent before reassigning.');
        }
        // If this client does not require quotes, allow assignment
        if (!$translatorJob->client->always_requires_a_quote) {
            return true;
        }

        // For clients that require quotes, ensure there is an ADMIN quote that the client has ACCEPTED
        $clientHasAcceptedAdminQuote = $translatorJob
            ->adminQuotes()
            ->where('status', 1) // numeric status id for 'quote accepted'
            ->exists();

        if ($clientHasAcceptedAdminQuote) {
            return true;
        }

        return $this->deny('Client must accept the admin quote before assigning an interpreter.');
    }

    public function unassign(User $user, TranslatorJob $translatorJob)
    {
        if (RoleHelper::hasAnyRole($user, ['admin', 'super-admin'])) {
            return true;
        }

        if (RoleHelper::hasRole($user, 'agent')) {
            $agentId = (int) optional($user->agent)->id;
            return $agentId > 0 && (int) $translatorJob->agent_id === $agentId;
        }

        return false;
    }

    public function viewDocuments(User $user, TranslatorJob $translatorJob)
    {
        if (RoleHelper::hasAnyRole($user, ['admin', 'super admin'])) {
            return true;
        }
        if (!$translatorJob->agent) {
            return false;
        }

        if ($user->can('edit', $translatorJob)) {
            return true;
        }

        if (optional($user->agent)->is($translatorJob->agent)) {
            return true;
        }

        return false;
    }

    public function complete(User $user, TranslatorJob $translatorJob)
    {
        if ($user->cannot('edit', $translatorJob)) {
            return false;
        }

        if ($translatorJob->canBeCompleted()) {
            return true;
        }

        return false;
    }
}
