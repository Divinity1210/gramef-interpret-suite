<?php

namespace App\Http\Controllers;

use App\Agent;
use App\Mail\JobMail;
use App\MatchedAgent;
use App\InterpreterJob;
use App\Jobs\MatchAgents;
use App\Traits\LogsUpdates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Timesheet;
use Illuminate\Support\Facades\Schema;
use App\Services\JobSnapshotService;
use App\Helpers\RoleHelper;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use App\Services\PushNotificationService;
use App\Services\NotificationHelper;
use App\Notifications\JobAssignedNotification;

class InterpreterJobMatchedAgentController extends Controller
{
    use LogsUpdates;
    public function index(InterpreterJob $interpreterJob)
    {

        $matchedAgentsOptions = Agent::canBeMatchedToInterpreterJobs($interpreterJob)
            ->hasEnabledUser()
            ->matchesInterpreterJob($interpreterJob)
            ->with([
                'user' => function ($query) {
                    return $query->select('id', 'first_name', 'last_name');
                }
            ])
            ->select('id', 'user_id')
            ->get()
            ->mapWithKeys(function ($agent) {
                return [
                    $agent->id => $agent->user->getFullName() . ' (Available)',
                ];
            });

        #	dd( $matchedAgentsOptions );	


        // dd($interpreterJob->security_type_id);
        return view('interpreter-jobs.matched.index', [

            'interpreterJob' => $interpreterJob,
            'matchedAgents' => $interpreterJob->matchedAgents()
                ->with([
                    'agent.user',
                    'quotes',
                    'job.adminQuotes',
                ])
                ->get(),
            'matchedAgentsOptions' => $matchedAgentsOptions,
            'unmatchedAgents' => Agent::with('user')
                // ->canBeMatchedToJobs()
                ->canBeMatchedToInterpreterJobs($interpreterJob)
                ->hasEnabledUser()
                ->matchesInterpreterJob($interpreterJob)
                ->whereNotIn('id', $interpreterJob->matchedAgents()->pluck('agent_id')->toArray())
                ->get()
                ->mapWithKeys(function ($agent) {
                    return [
                        $agent->id => $agent->user->getFullName(),
                    ];
                })
        ]);
    }

    public function store(Request $request, InterpreterJob $interpreterJob)
    {
        // Validate that agent_id is provided
        $request->validate([
            'agent_id' => 'required|exists:agents,id'
        ]);

        // Check if agent can be matched to this job
        $agent = Agent::where('id', $request->agent_id)
            ->canBeMatchedToInterpreterJobs($interpreterJob)
            ->matchesInterpreterJob($interpreterJob)
            ->first();

        if (!$agent) {
            return back()->with('failure', 'Selected agent cannot be matched to this job or does not meet the requirements.');
        }

        // Check if agent can be matched to this type of job
        if (!$agent->user || !RoleHelper::hasRole($agent->user, 'agent')) {
            return redirect()->back()->with('error', 'Selected agent is not eligible for job matching.');
        }

        // Check if agent is already matched to this job
        $existingMatch = $interpreterJob->matchedAgents()->where('agent_id', $request->agent_id)->first();
        if ($existingMatch) {
            return back()->with('failure', 'Agent is already notified for this job.');
        }

        // Calculate distance safely
        $distance = null;
        if ($interpreterJob->latitude && $interpreterJob->longitude) {
            $agentWithDistance = Agent::where('id', $request->agent_id)
                ->withDistanceFromInterpreterJob($interpreterJob)
                ->first();
            $distance = $agentWithDistance->distance ?? null;
        }

        // Create the matched agent record
        $interpreterJob->matchedAgents()->create([
            'agent_id' => $request->agent_id,
            'distance' => $distance
        ]);

        // Notify the agent about the new match
        try {
            $agent = Agent::with('user')->find($request->agent_id);
            if ($agent && $agent->user) {
                PushNotificationService::notifyUser($agent->user->id, 'New Job Available', 'You have been matched to Job #' . $interpreterJob->id, [
                    'type' => 'new_job_match',
                    'jobId' => $interpreterJob->id,
                    'url' => '/jobs/interpreter-' . $interpreterJob->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Push notify on manual match failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Agent notified successfully.');
    }

    public function update(InterpreterJob $interpreterJob, ?MatchedAgent $matchedAgent = null)
    {
        if (RoleHelper::hasAnyRole(auth()->user(), ['admin', 'super-admin'])) {
            $this->authorize('assign', $interpreterJob);
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
            // Ensure the matched agent belongs to this interpreter job
            if ((int) optional($matchedAgent)->job_id !== (int) $interpreterJob->id) {
                return back()->with('failure', 'Selected agent does not belong to this job.');
            }
            if ((int) ($interpreterJob->agent_id ?? 0) > 0 || $interpreterJob->assignedMatched()->exists()) {
                return back()->with('failure', 'This job is already assigned. Unassign the current agent before reassigning.');
            }
            $canBeMatched = \App\Agent::where('id', $matchedAgent->agent->id)
                ->canBeMatchedToInterpreterJobs($interpreterJob)
                ->matchesInterpreterJob($interpreterJob)
                ->exists();
            $notDoubleBooked = \App\InterpreterJob::whereKey($interpreterJob->id)
                ->excludeDoubleBookings($matchedAgent->agent)
                ->exists();
            if ($canBeMatched && $notDoubleBooked) {

                // Ensure there is a timesheet record upon assignment
                $timesheet = \App\Timesheet::where('job_id', $interpreterJob->id)->first();
                if (!$timesheet) {
                    $timesheet = new \App\Timesheet();
                }
                // Populate mandatory columns defensively depending on schema
                $timesheet->agent_id = $matchedAgent->agent->id;

                if (Schema::hasColumn('timesheets', 'job_id')) {
                    $timesheet->job_id = $interpreterJob->id;
                }

                if (Schema::hasColumn('timesheets', 'client_id')) {
                    $timesheet->client_id = $interpreterJob->client_id ?? 0;
                }
                if (Schema::hasColumn('timesheets', 'from_language_id')) {
                    $timesheet->from_language_id = $interpreterJob->from_language_id ?? 37; // default English id if unset
                }
                if (Schema::hasColumn('timesheets', 'to_language_id')) {
                    $timesheet->to_language_id = $interpreterJob->to_language_id ?? 0;
                }
                if (Schema::hasColumn('timesheets', 'client_reference')) {
                    $timesheet->client_reference = $interpreterJob->client_reference ?? '';
                }
                if (Schema::hasColumn('timesheets', 'appointment_date')) {
                    $timesheet->appointment_date = is_string($interpreterJob->appointment_date)
                        ? $interpreterJob->appointment_date
                        : (optional($interpreterJob->appointment_date)->toDateString() ?: date('Y-m-d'));
                }
                if (Schema::hasColumn('timesheets', 'start_time')) {
                    $timesheet->start_time = $interpreterJob->start_time ?: '00:00:00';
                }
                if (Schema::hasColumn('timesheets', 'end_time')) {
                    $timesheet->end_time = $interpreterJob->end_time ?: '00:00:00';
                }
                if (Schema::hasColumn('timesheets', 'duration_hours')) {
                    $timesheet->duration_hours = $interpreterJob->duration_hours ?? 0;
                }
                if (Schema::hasColumn('timesheets', 'duration_minutes')) {
                    $timesheet->duration_minutes = $interpreterJob->duration_minutes ?? 0;
                }
                if (Schema::hasColumn('timesheets', 'status')) {
                    $timesheet->status = 'Y';
                }
                try {
                    $timesheet->save();
                } catch (\Throwable $e) {
                    Log::error('Failed to save timesheet on assignment: ' . $e->getMessage());
                    return back()->with('failure', 'Failed to save timesheet. Please try again or contact support.');
                }
                // Create canonical appointment snapshot at assignment time (versioned)
                try {
                    JobSnapshotService::createSnapshot($interpreterJob, $timesheet, 'assignment', auth()->id());
                } catch (\Throwable $e) {
                    Log::warning('Failed to create job snapshot on assignment: ' . $e->getMessage());
                }
                // Reset any previously assigned agents back to matched (status 1)
                \App\MatchedAgent::where('job_id', $interpreterJob->id)
                    ->where('job_type', get_class($interpreterJob))
                    ->where('status', 4)
                    ->where('id', '!=', $matchedAgent->id)
                    ->update(['status' => 1]);

                $matchedAgent->update(['status' => 4]);

                optional($matchedAgent->latestQuote())->update(['status' => 1]);

                $interpreterJob->update([
                    'agent_id' => $matchedAgent->agent->id,
                    'status' => 1,
                ]);

                $this->logUpdate(
                    $interpreterJob->id,
                    'interpreter-jobs',
                    auth()->user()->id,
                    $interpreterJob->agent_id,
                    '4',
                    'Interpreter is assigned to this job'
                );
                // Client email is handled automatically by InterpreterJobObserver when status changes to 'assigned'

                // Notify agent: You've been assigned to this job
                NotificationHelper::send($matchedAgent->agent->user, new JobAssignedNotification($interpreterJob, 'interpreter'));

                return back()->with('success', 'Agent assigned');
            } else {
                $errorMessage = 'That agent cannot be assigned to this job.';
                if (!$canBeMatched) {
                    $agent = $matchedAgent->agent;
                    $requiresDbs = ((int) $interpreterJob->security_type_id !== 4);

                    // Check each condition separately to provide an accurate error message
                    $passesDbsDocs = \App\Agent::where('id', $agent->id)
                        ->canBeMatchedToInterpreterJobs($interpreterJob)
                        ->exists();
                    $passesBookingCheck = \App\Agent::where('id', $agent->id)
                        ->hasNotBookedDate($interpreterJob)
                        ->exists();

                    if ($agent && $agent->restrict_job_notifications) {
                        $errorMessage .= ' Agent has restricted job notifications.';
                    } elseif (!$passesBookingCheck) {
                        // Agent is already booked at this date/time
                        $conflicting = \App\InterpreterJob::where('agent_id', $agent->id)
                            ->where('appointment_date', $interpreterJob->appointment_date)
                            ->where('start_time', $interpreterJob->start_time)
                            ->where('status', 1)
                            ->first();
                        $conflictMsg = $conflicting ? ' (already assigned to Job #' . $conflicting->id . ')' : '';
                        $errorMessage .= ' Agent is double-booked at this date and time' . $conflictMsg . '.';
                    } elseif ($requiresDbs && (!$agent || !$agent->dbs_expiry_date || $agent->dbs_expiry_date < today() || !$agent->hasDbsFields())) {
                        $errorMessage .= ' Agent\'s DBS check has expired or is missing.';
                    } elseif ($requiresDbs && !$passesDbsDocs) {
                        $secId = (int) $interpreterJob->security_type_id;
                        if ($agent && empty($agent->dbs_level_id)) {
                            $errorMessage .= ' Agent does not have a verified DBS level set on their profile.';
                        } elseif ($agent && $secId === 1 && !in_array((int) $agent->dbs_level_id, [1, 3], true)) {
                            $errorMessage .= ' Agent does not have the required Enhanced DBS clearance for this job.';
                        } elseif ($agent && $secId === 3 && (int) $agent->dbs_level_id !== 3) {
                            $errorMessage .= ' Agent does not have the required National Security Vetting clearance for this job.';
                        } else {
                            $errorMessage .= ' Agent does not meet the DBS or document requirements for this job.';
                        }
                    } else {
                        $errorMessage .= ' Agent does not meet the job requirements (language, skills, qualifications, or availability).';
                    }
                } else {
                    $errorMessage = 'That agent cannot be assigned to this job because they are double-booked at this time.';
                }
                return back()->with('failure', $errorMessage);
            }
        }

        if (auth()->user()) {
            $response = Gate::inspect('accept', $interpreterJob);
            if ($response->denied()) {
                return back()->with('failure', $response->message() ?? 'You cannot accept this job');
            }
            if ($interpreterJob->isVisible(auth()->user())) {
                tap($interpreterJob)
                    ->update([
                        'agent_id' => auth()->user()->agent->id,
                        'status' => 1,
                    ])
                    ->matchedLoggedInAgent();

                // Reset any previously assigned agents back to matched (status 1)
                \App\MatchedAgent::where('job_id', $interpreterJob->id)
                    ->where('job_type', get_class($interpreterJob))
                    ->where('status', 4)
                    ->where('agent_id', '!=', auth()->user()->agent->id)
                    ->update(['status' => 1]);

                optional($interpreterJob->matchedLoggedInAgent())->update(['status' => 4]);

                // Ensure there is a timesheet record upon agent acceptance
                $timesheet = \App\Timesheet::where('job_id', $interpreterJob->id)->first();
                if (!$timesheet) {
                    $timesheet = new \App\Timesheet();
                }
                // Populate mandatory columns defensively depending on schema
                $timesheet->agent_id = auth()->user()->agent->id;
                $timesheet->job_id = $interpreterJob->id;
                if (Schema::hasColumn('timesheets', 'client_id')) {
                    $timesheet->client_id = $interpreterJob->client_id;
                }
                if (Schema::hasColumn('timesheets', 'from_language_id')) {
                    $timesheet->from_language_id = $interpreterJob->from_language_id ?? 37; // default English id if unset
                }
                if (Schema::hasColumn('timesheets', 'to_language_id')) {
                    $timesheet->to_language_id = $interpreterJob->to_language_id;
                }
                if (Schema::hasColumn('timesheets', 'client_reference')) {
                    $timesheet->client_reference = $interpreterJob->client_reference ?? '';
                }
                if (Schema::hasColumn('timesheets', 'appointment_date')) {
                    $timesheet->appointment_date = optional($interpreterJob->appointment_date)->toDateString() ?: date('Y-m-d');
                }
                if (Schema::hasColumn('timesheets', 'start_time')) {
                    $timesheet->start_time = $interpreterJob->start_time ?: '00:00:00';
                }
                if (Schema::hasColumn('timesheets', 'end_time')) {
                    $timesheet->end_time = $interpreterJob->end_time ?: '00:00:00';
                }
                if (Schema::hasColumn('timesheets', 'duration_hours')) {
                    $timesheet->duration_hours = $interpreterJob->duration_hours ?? 0;
                }
                if (Schema::hasColumn('timesheets', 'duration_minutes')) {
                    $timesheet->duration_minutes = $interpreterJob->duration_minutes ?? 0;
                }
                if (Schema::hasColumn('timesheets', 'status')) {
                    $timesheet->status = 'Y';
                }
                try {
                    $timesheet->save();
                } catch (\Throwable $e) {
                    Log::error('Failed to save timesheet on agent acceptance: ' . $e->getMessage());
                    return back()->with('failure', 'Failed to save timesheet. Please try again or contact support.');
                }
                // Create canonical appointment snapshot on agent acceptance (if not already created)
                try {
                    JobSnapshotService::createSnapshot($interpreterJob, $timesheet, 'accept', auth()->id());
                } catch (\Throwable $e) {
                    Log::warning('Failed to create job snapshot on acceptance: ' . $e->getMessage());
                }

                //Log update start agent assign
                $this->logUpdate(
                    $interpreterJob->id,
                    'interpreter-jobs',
                    auth()->user()->id,
                    $interpreterJob->agent_id,
                    '4',
                    'Agent is assigned to this job'
                );
                //Log update and agent assign


                // Client email is handled automatically by InterpreterJobObserver when status changes to 'assigned'
                return back()->with('success', 'Job accepted');
            } else {
                $canBeMatched = \App\Agent::where('id', auth()->user()->agent->id)
                    ->canBeMatchedToInterpreterJobs($interpreterJob)
                    ->matchesInterpreterJob($interpreterJob)
                    ->exists();
                $errorMessage = 'You cannot accept this job';
                if (!$canBeMatched) {
                    $agent = auth()->user()->agent;
                    $requiresDbs = ((int) $interpreterJob->security_type_id !== 4);

                    // Check each condition separately to provide an accurate error message
                    $passesDbsDocs = \App\Agent::where('id', $agent->id)
                        ->canBeMatchedToInterpreterJobs($interpreterJob)
                        ->exists();
                    $passesBookingCheck = \App\Agent::where('id', $agent->id)
                        ->hasNotBookedDate($interpreterJob)
                        ->exists();

                    if ($agent && $agent->restrict_job_notifications) {
                        $errorMessage .= ' Agent has restricted job notifications.';
                    } elseif (!$passesBookingCheck) {
                        $conflicting = \App\InterpreterJob::where('agent_id', $agent->id)
                            ->where('appointment_date', $interpreterJob->appointment_date)
                            ->where('start_time', $interpreterJob->start_time)
                            ->where('status', 1)
                            ->first();
                        $conflictMsg = $conflicting ? ' (already assigned to Job #' . $conflicting->id . ')' : '';
                        $errorMessage .= ' You are double-booked at this date and time' . $conflictMsg . '.';
                    } elseif ($requiresDbs && (!$agent || !$agent->dbs_expiry_date || $agent->dbs_expiry_date < today() || !$agent->hasDbsFields())) {
                        $errorMessage .= ' Agent\'s DBS check has expired or is missing.';
                    } elseif ($requiresDbs && !$passesDbsDocs) {
                        $secId = (int) $interpreterJob->security_type_id;
                        if ($agent && empty($agent->dbs_level_id)) {
                            $errorMessage .= ' You do not have a verified DBS level set on your profile.';
                        } elseif ($agent && $secId === 1 && !in_array((int) $agent->dbs_level_id, [1, 3], true)) {
                            $errorMessage .= ' You do not have the required Enhanced DBS clearance for this job.';
                        } elseif ($agent && $secId === 3 && (int) $agent->dbs_level_id !== 3) {
                            $errorMessage .= ' You do not have the required National Security Vetting clearance for this job.';
                        } else {
                            $errorMessage .= ' You do not meet the DBS or document requirements for this job.';
                        }
                    } else {
                        $errorMessage .= ' Agent does not meet the job requirements (language, skills, qualifications, or availability).';
                    }
                } else {
                    // If they meet all requirements but isVisible() failed, it's usually because 
                    // the job is already assigned, or they previously rejected it.
                    if ($interpreterJob->agent_id) {
                        $errorMessage = 'This job has already been assigned to another agent.';
                    } else {
                        $matchedStatus = optional($interpreterJob->matchedAgents()->where('agent_id', auth()->user()->agent->id)->first())->status;
                        if ($matchedStatus === 'rejected' || $matchedStatus === 'cancelled') {
                            $errorMessage = 'You have previously rejected or cancelled this job, or you were unassigned.';
                        } else {
                            $errorMessage = 'This job is no longer available to accept.';
                        }
                    }
                }
                return back()->with('failure', $errorMessage);
            }
        }
    }

    public function destroy(InterpreterJob $interpreterJob, MatchedAgent $matchedAgent)
    {
        return $this->performUnassignment($interpreterJob, $matchedAgent);
    }
    public function unAssignForAgents(InterpreterJob $interpreterJob, MatchedAgent $matchedAgent)
    {
        return $this->performUnassignment($interpreterJob, $matchedAgent);
    }

    private function performUnassignment(InterpreterJob $interpreterJob, MatchedAgent $matchedAgent)
    {
        $this->authorize('unassign', $interpreterJob);

        $actorId = (int) auth()->id();

        // Capture agent details BEFORE the transaction nullifies agent_id
        $preloadedAgent = Agent::with('user')->find($matchedAgent->agent_id);
        $agentName = ($preloadedAgent && $preloadedAgent->user)
            ? $preloadedAgent->user->getFullName()
            : null;

        $state = DB::transaction(function () use ($interpreterJob, $matchedAgent, $actorId) {
            $job = InterpreterJob::query()->whereKey($interpreterJob->id)->lockForUpdate()->first();
            if (!$job) {
                return ['ok' => false, 'message' => 'Job not found.'];
            }

            $lockedMatched = MatchedAgent::query()
                ->whereKey($matchedAgent->id)
                ->where('job_id', $job->id)
                ->where('job_type', InterpreterJob::class)
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

            // Use updateQuietly to prevent the observer from firing a misleading
            // "Job Updated" email — the controller sends the correct unassignment
            // notifications explicitly below.
            $jobFill = ['agent_id' => null, 'status' => 0];
            if ((int) $job->requested_agent_id === $assignedAgentId) {
                $jobFill['requested_agent_id'] = null;
            }
            $job->fill($jobFill);
            $job->saveQuietly();

            $lockedMatched->update(['status' => 3]);

            Timesheet::where('job_id', $job->id)
                ->where('agent_id', $assignedAgentId)
                ->whereNull('agent_signature')
                ->update(['agent_id' => null]);

            DB::table('job_snapshots')->where('job_id', $job->id)->delete();

            $this->logUpdate(
                $job->id,
                'interpreter-jobs',
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

        // Resolve the agent's user for the agent notification email.
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

        return back()->with('success', 'Agent unassigned');
    }

    public function reject(InterpreterJob $interpreterJob)
    {
        $matched = $interpreterJob->matchedLoggedInAgent();
        if ($matched) {
            $matched->update([
                'status' => 1
            ]);
        }

        // If this job is currently assigned (has an agent_id), reset it to open
        // NOTE: Previously used `$interpreterJob->agent` (Eloquent relationship) which
        // can return null if the Agent model fails to load. Using raw column check instead.
        if ($interpreterJob->agent_id) {
            $previousAgentId = (int) $interpreterJob->agent_id;
            $updateFields = [
                'agent_id' => null,
                'status' => 0
            ];
            if ((int) $interpreterJob->requested_agent_id === $previousAgentId) {
                $updateFields['requested_agent_id'] = null;
            }

            $interpreterJob->update($updateFields);

            Timesheet::where('job_id', $interpreterJob->id)
                ->where('agent_id', $previousAgentId)
                ->whereNull('agent_signature')
                ->update(['agent_id' => null]);

            DB::table('job_snapshots')->where('job_id', $interpreterJob->id)->delete();

            try {
                $adminEmail = config('app.to.address') ?: 'bookings@atozinterpreting.com';
                Mail::to($adminEmail)->send(new JobMail(
                    $interpreterJob,
                    'emails.matched-agents.rejected-changes',
                    'Agent rejected client\'s changes',
                    'admin'
                ));
            } catch (\Throwable $e) {
                Log::error('Failed to send rejected changes email to admin: ' . $e->getMessage());
            }

            //Log update start agent assign
            $this->logUpdate(
                $interpreterJob->id,
                'interpreter-jobs',
                auth()->user()->id,
                $previousAgentId,
                '0',
                'Agent Rejected this job'
            );
            //Log update end agent assign

            try {
                $clientUser = optional($interpreterJob->client)->user;
                if ($clientUser && filter_var($clientUser->email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($clientUser)->send(new JobMail(
                        $interpreterJob,
                        'emails.matched-agents.agent-unavailable',
                        'Agent no longer available',
                        'client'
                    ));
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send agent unavailable email to client: ' . $e->getMessage());
            }

            MatchAgents::dispatch($interpreterJob, optional($matched)->id);
        }

        return redirect()->route('interpreter-jobs.index')->with('success', 'Job rejected');
    }
}
