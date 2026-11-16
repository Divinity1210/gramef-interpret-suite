<?php

namespace App\Http\Controllers;

use App\InterpreterJob;
use App\Timesheet;
use App\Mail\JobMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Traits\LogsUpdates;

class CompletedInterpreterJobController extends Controller
{
    use LogsUpdates;
    public function update(InterpreterJob $interpreterJob)
    {
        $this->authorize('complete', $interpreterJob);

        // Log the completion attempt with context (before status change)
        $this->logUpdate(
            $interpreterJob->id,
            'interpreter-jobs',
            auth()->user()->id,
            $interpreterJob->agent_id,
            4,
            'Completion triggered',
            'complete_attempt',
            $interpreterJob->status,
            'attempted'
        );

        // Soft guard: if before appointment, require explicit confirmation
        $preAppointment = false;
        try {
            if ($interpreterJob->appointment_date) {
                $timeStr = $interpreterJob->start_time ?? ($interpreterJob->appointment_time ?? '00:00:00');
                $dtStr = $interpreterJob->appointment_date . ' ' . $timeStr;
                $appointmentDateTime = \Carbon\Carbon::parse($dtStr, 'Europe/London');
                $preAppointment = \Carbon\Carbon::now('Europe/London')->lt($appointmentDateTime);
            }
        } catch (\Throwable $e) {
        }

        if ($preAppointment && request()->input('confirm') !== 'yes') {
            // Log that the soft guard blocked the completion
            $this->logUpdate(
                $interpreterJob->id,
                'interpreter-jobs',
                auth()->user()->id,
                $interpreterJob->agent_id,
                $interpreterJob->status,
                'Soft guard: pre-appointment completion blocked (no confirmation)',
                'soft_guard_blocked',
                $interpreterJob->status,
                'blocked'
            );
            return back()->with('failure', sprintf(
                'This job is scheduled for %s %s. Please confirm to complete it before the appointment.',
                (string) ($interpreterJob->appointment_date ?? ''),
                (string) ($interpreterJob->start_time ?? ($interpreterJob->appointment_time ?? ''))
            ));
        }

        $timesheet = Timesheet::where('job_id', $interpreterJob->id)->first();
        if (empty($timesheet)) {
            $timesheet = new Timesheet();
        }
        $timesheet->agent_id = $interpreterJob->agent_id;
        $timesheet->job_id = $interpreterJob->id;
        $timesheet->status = 'Y';
        $timesheet->save();

        // Try to send email notification, but don't fail the job completion if email fails
        try {
            Mail::to($interpreterJob->agent->user)->send(new JobMail(
                $interpreterJob,
                'emails.agents.job-timesheet-sign',
                'Timesheet needs to be signed',
                'agent'
            ));
        } catch (\Exception $e) {
            // Log the email error but continue with job completion
            Log::error('Failed to send job completion email: ' . $e->getMessage(), [
                'job_id' => $interpreterJob->id,
                'agent_id' => $interpreterJob->agent_id
            ]);
        }

        $oldStatus = $interpreterJob->status;
        $interpreterJob->update(['status' => 4]);

        // Ensure the assigned agent's matched_agent record is synced to "assigned" (4)
        // This prevents the inconsistency where a completed job shows the agent as "rejected"
        if ($interpreterJob->agent_id) {
            $interpreterJob->matchedAgents()
                ->where('agent_id', $interpreterJob->agent_id)
                ->whereIn('status', [0, 1, 2]) // matched, rejected, or accepted → assigned
                ->update(['status' => 4]);
        }

        // Log success after status update
        $this->logUpdate(
            $interpreterJob->id,
            'interpreter-jobs',
            auth()->user()->id,
            $interpreterJob->agent_id,
            4,
            'Job marked as completed',
            'complete',
            $oldStatus,
            'succeeded'
        );
        return back()->with('success', 'Completed job');
    }
}
