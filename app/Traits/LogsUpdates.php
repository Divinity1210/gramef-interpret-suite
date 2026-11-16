<?php

namespace App\Traits;

use App\AllUpdates;
use App\Traits\DeterminesUserType;
use PhpParser\Node\Stmt\TryCatch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait LogsUpdates
{
    use DeterminesUserType;

    /**
     * Log a job update with richer context for admin visibility.
     * Backward compatible with existing calls.
     */
    public function logUpdate(
        $job_id,
        $job_type,
        $user_id,
        $agent_id,
        $new_status,
        $comment = null,
        $action = null,
        $old_status = null,
        $result = 'succeeded',
        array $context = []
    ) {
        $userId = Auth::id();
        $userType = $this->getUserType($userId);

        // Map status codes to labels (ensure values fit within DB column constraints)
        $code = '';
        if ($new_status == 1) { $code = 'Rejected'; }
        if ($new_status == 0) { $code = 'Matched'; }
        if ($new_status == 2) { $code = 'Accepted'; }
        if ($new_status == 3) { $code = 'Cancelled'; }
        // Status 4 is used both for assignment and completion in legacy flows;
        // keep a short, safe default and override via action below when needed
        if ($new_status == 4) { $code = 'Assigned'; }

        if (empty($agent_id)) { $agent_id = 0; }

        // Attempt to load job model to enrich context (appointment dates, etc.)
        $job = null;
        try {
            if ($job_type === 'interpreter-jobs') {
                $job = \App\InterpreterJob::find($job_id);
            } elseif ($job_type === 'translator-jobs') {
                $job = \App\TranslatorJob::find($job_id);
            }
        } catch (\Throwable $e) {}

        // Determine old status from model if not provided
        if ($old_status === null && $job) {
            $old_status = $job->status;
        }

        // Enrich context with request metadata
        $baseContext = [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'route' => optional(request()->route())->getName(),
            'performed_at' => now()->toDateTimeString(),
        ];

        // Appointment timing checks
        if ($job) {
            $appointmentDate = $job->appointment_date ?? null;
            $appointmentTime = $job->appointment_time ?? null;
            $baseContext['appointment_date'] = $appointmentDate;
            $baseContext['appointment_time'] = $appointmentTime;

            try {
                if ($appointmentDate) {
                    $appointmentDateTime = $appointmentDate;
                    if ($appointmentTime) {
                        $appointmentDateTime = \Carbon\Carbon::parse($appointmentDate.' '.$appointmentTime);
                    } else {
                        $appointmentDateTime = \Carbon\Carbon::parse($appointmentDate);
                    }
                    $baseContext['pre_appointment'] = now()->lt($appointmentDateTime);
                }
            } catch (\Throwable $e) {
                $baseContext['pre_appointment'] = null;
            }
            $baseContext['current_status'] = $job->status ?? null;
        }

        // Default action if not supplied
        if (!$action) {
            $action = match ((int)$new_status) {
                0 => 'status_matched',
                1 => 'status_rejected',
                2 => 'status_accepted',
                3 => 'status_cancelled',
                4 => 'status_completed_or_assigned',
                default => 'status_changed',
            };
        }

        // Override code label based on explicit action when provided
        // Keep labels short to satisfy the `code` column length (<= 15 chars)
        if ($action === 'complete') {
            $code = 'Completed';
        } elseif ($action === 'complete_attempt') {
            $code = 'Completion';
        } elseif ($action === 'soft_guard_blocked') {
            $code = 'Blocked';
        }

        // Final safety: ensure code string does not exceed DB column length
        $code = mb_substr($code, 0, 15);

        // Merge context arrays and ensure JSON encodable
        $contextPayload = array_merge($baseContext, $context);

        try {
            AllUpdates::create([
                'job_id' => $job_id,
                'job_type' => $job_type,
                'user_id' => $user_id,
                'user_type' => $userType,
                'agent_id' => $agent_id,
                'new_status' => $new_status,
                'old_status' => $old_status,
                'action' => $action,
                'result' => $result,
                'context' => json_encode($contextPayload),
                'code' => $code,
                'comment' => $comment,
                'update_time' => now(),
                'update_date' => now(),
                'deleted' => 'N',
            ]);
        } catch (\Exception $e) {
            // Don't break the user flow; log and continue
            Log::error('Failed to log update', [
                'error' => $e->getMessage(),
                'job_id' => $job_id,
                'job_type' => $job_type,
            ]);
            return $e->getMessage();
        }
    }
        function objectToArray(&$object)
{
    return @json_decode(json_encode($object), true);
}
}