<?php

namespace App\Http\Controllers;

use App\InterpreterJob;
use App\Timesheet;
use App\Mail\JobMail;
use App\Agent;
use App\Http\Requests\CancelRequest;
use App\Traits\LogsUpdates;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Services\NotificationHelper;
use App\Notifications\JobCancelledNotification;

class InterpreterJobCancellationController extends Controller
{
    use LogsUpdates;
    public function store(CancelRequest $request, InterpreterJob $interpreterJob)
    {
        // Capture the currently assigned agent before cancellation nullifies it
        $assignedAgentId = $interpreterJob->agent_id;

        $this->logUpdate(
            $interpreterJob->id,
            'interpreter-jobs',
            auth()->user()->id,
            $interpreterJob->agent_id,
            '3',
            'Job cancelled'
        );
        $interpreterJob->cancel($request->message);

        // If cancellation is on the same day as appointment, create a timesheet
        // so the admin can invoice the client for late cancellation.
        // If an agent was assigned, also notify them to sign.
        try {
            $isSameDayCancelled = is_object($interpreterJob)
                && method_exists($interpreterJob, 'isCancelledSameDay')
                && $interpreterJob->statusName === 'cancelled'
                && $interpreterJob->isCancelledSameDay();

            if ($isSameDayCancelled) {
                $timesheet = Timesheet::firstOrNew(['job_id' => $interpreterJob->id]);
                $timesheet->agent_id = $assignedAgentId ?: null;
                $timesheet->job_id = $interpreterJob->id;
                if (Schema::hasColumn('timesheets', 'client_id')) {
                    $timesheet->client_id = $interpreterJob->client_id;
                }
                if (Schema::hasColumn('timesheets', 'from_language_id')) {
                    $timesheet->from_language_id = $interpreterJob->from_language_id ?? 37;
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
                $timesheet->status = 'Y';
                $timesheet->save();

                // Notify the agent to sign the timesheet (only if one was assigned)
                if ($assignedAgentId) {
                    $agent = Agent::find($assignedAgentId);
                    if ($agent && $agent->user) {
                        Mail::to($agent->user)->send(new JobMail(
                            $interpreterJob,
                            'emails.agents.job-timesheet-sign',
                            'Timesheet needs to be signed',
                            'agent'
                        ));
                    }
                }
            }

            $isWithin24Cancelled = is_object($interpreterJob)
                && method_exists($interpreterJob, 'isCancelledWithin24Hours')
                && $interpreterJob->statusName === 'cancelled'
                && $interpreterJob->isCancelledWithin24Hours();

            if (!$isSameDayCancelled && $isWithin24Cancelled) {
                $timesheet = Timesheet::firstOrNew(['job_id' => $interpreterJob->id]);
                $timesheet->agent_id = $assignedAgentId ?: null;
                $timesheet->job_id = $interpreterJob->id;
                if (Schema::hasColumn('timesheets', 'client_id')) {
                    $timesheet->client_id = $interpreterJob->client_id;
                }
                if (Schema::hasColumn('timesheets', 'from_language_id')) {
                    $timesheet->from_language_id = $interpreterJob->from_language_id ?? 37;
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
                $timesheet->status = 'Y';
                $timesheet->save();
            }
        } catch (\Throwable $e) {
            Log::error('Failed to prepare timesheet for cancelled job', [
                'job_id' => $interpreterJob->id,
                'agent_id' => $assignedAgentId,
                'error' => $e->getMessage(),
            ]);
        }

        // Notify all previously matched agents about cancellation
        // GUARD: Only send cancellation notifications for future/current jobs
        $isPastJob = $interpreterJob->appointment_date
            && \Carbon\Carbon::parse($interpreterJob->appointment_date)->endOfDay()->lt(\Carbon\Carbon::now('Europe/London'));
        if (!$isPastJob) {
            try {
                $matchedAgents = $interpreterJob->matchedAgents()->with('agent.user')->get();
                foreach ($matchedAgents as $ma) {
                    if ($ma->agent && $ma->agent->user) {
                        NotificationHelper::send($ma->agent->user, new JobCancelledNotification($interpreterJob, 'interpreter'));
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to send cancellation notifications: ' . $e->getMessage());
            }
        } else {
            Log::info('Skipping cancellation notifications for past-date job', [
                'job_id' => $interpreterJob->id,
                'appointment_date' => optional($interpreterJob->appointment_date)->toDateString(),
            ]);
        }

        return redirect()->route('interpreter-jobs.index')->with('success', 'Job Cancelled');
    }
}
