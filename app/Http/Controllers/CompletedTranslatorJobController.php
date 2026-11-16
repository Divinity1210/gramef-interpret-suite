<?php

namespace App\Http\Controllers;

use App\TranslatorJob;
use App\Traits\LogsUpdates;

class CompletedTranslatorJobController extends Controller
{
    use LogsUpdates;
    public function update(TranslatorJob $translatorJob)
    {
        // Log completion attempt prior to status change
        $this->logUpdate(
            $translatorJob->id,
            'translator-jobs',
            auth()->user()->id,
            $translatorJob->agent_id ?? 0,
            4,
            'Completion triggered',
            'complete_attempt',
            $translatorJob->status,
            'attempted'
        );

        // Soft guard: if before appointment, require explicit confirmation
        $preAppointment = false;
        try {
            if ($translatorJob->appointment_date) {
                $dtStr = $translatorJob->appointment_date . ' ' . ($translatorJob->appointment_time ?? '00:00:00');
                $appointmentDateTime = \Carbon\Carbon::parse($dtStr);
                $preAppointment = now()->lt($appointmentDateTime);
            }
        } catch (\Throwable $e) {}

        if ($preAppointment && request()->input('confirm') !== 'yes') {
            // Log that the soft guard blocked the completion
            $this->logUpdate(
                $translatorJob->id,
                'translator-jobs',
                auth()->user()->id,
                $translatorJob->agent_id ?? 0,
                $translatorJob->status,
                'Soft guard: pre-appointment completion blocked (no confirmation)',
                'soft_guard_blocked',
                $translatorJob->status,
                'blocked'
            );
            return back()->with('failure', sprintf(
                'This job is scheduled for %s %s. Please confirm to complete it before the appointment.',
                (string)($translatorJob->appointment_date ?? ''),
                (string)($translatorJob->appointment_time ?? '')
            ));
        }

        $oldStatus = $translatorJob->status;
        $translatorJob->update(['status' => 4]);

        // Log success after status update
        $this->logUpdate(
            $translatorJob->id,
            'translator-jobs',
            auth()->user()->id,
            $translatorJob->agent_id ?? 0,
            4,
            'Job marked as completed',
            'complete',
            $oldStatus,
            'succeeded'
        );
        return back()->with('success', 'Completed job');
    }
}
