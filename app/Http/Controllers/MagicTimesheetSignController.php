<?php

namespace App\Http\Controllers;

use App\ClientInvoice;
use App\InterpreterJob;
use App\PaymentProof;
use App\Services\PriceBookService;
use App\Services\TrafficLightCheckerService;
use App\Timesheet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MagicTimesheetSignController extends Controller
{
    /**
     * Public, no-password mobile client signature screen (Deliverable 1A).
     */
    public function show(string $token)
    {
        $timesheet = Timesheet::with([
            'interpreterJob.client.user',
            'interpreterJob.client.organisation',
            'interpreterJob.toLanguage',
            'interpreterJob.skill',
            'agent.user',
        ])->where('magic_sign_token', $token)->firstOrFail();

        $job = $timesheet->interpreterJob;
        $expired = $timesheet->magic_sign_expires_at && now()->greaterThan($timesheet->magic_sign_expires_at);

        return view('timesheet.magic-sign', compact('timesheet', 'job', 'token', 'expired'));
    }

    /**
     * Save client signature without requiring login/password and trigger Price Book + Traffic Light check.
     */
    public function store(Request $request, string $token, PriceBookService $priceBook, TrafficLightCheckerService $checker)
    {
        $timesheet = Timesheet::with(['interpreterJob.skill', 'interpreterJob.agentJobEntry'])
            ->where('magic_sign_token', $token)
            ->firstOrFail();

        $validated = $request->validate([
            'client_name' => 'required|string|max:120',
            'client_designation' => 'required|string|max:120',
            'client_phone' => 'nullable|string|max:40',
            'actual_hours' => 'required|integer|min:0|max:24',
            'actual_minutes' => 'required|integer|min:0|max:59',
            'client_signature' => 'required|string',
        ]);

        $timesheet->client_name = $validated['client_name'];
        $timesheet->client_designation = $validated['client_designation'];
        $timesheet->client_phone = preg_replace('/\D+/', '', (string) ($validated['client_phone'] ?? '')) ?: $timesheet->client_phone;
        $timesheet->client_duration_hours = (int) $validated['actual_hours'];
        $timesheet->client_duration_minutes = (int) $validated['actual_minutes'];
        $timesheet->agent_duration_hours = (int) $validated['actual_hours'];
        $timesheet->agent_duration_minutes = (int) $validated['actual_minutes'];
        $timesheet->client_signature = $validated['client_signature'];
        $timesheet->client_status = 'Y';
        $timesheet->status = 'verified';
        $timesheet->signed_ip = $request->ip();
        $timesheet->save();

        if ($timesheet->interpreterJob) {
            $job = $timesheet->interpreterJob;
            $job->signature_method = 'digital_magic_link';
            $job->timesheet_status = 'verified';
            $job->timesheet_verified_at = now();
            $job->status = 4; // Completed

            $calc = $priceBook->calculateForJob($job);
            $job->calculated_interpreter_pay = $calc['interpreter_pay'];
            $job->calculated_client_charge = $calc['client_charge'];
            $job->calculated_margin = $calc['margin'];

            $eval = $checker->evaluate($job);
            $job->traffic_light_status = $eval['status'];
            $job->traffic_light_warnings = json_encode($eval['warnings']);
            $job->save();
        }

        return redirect()->route('magic.timesheet.show', $token)
            ->with('success', 'Thank you! Timesheet signed in under 10 seconds and verified automatically.');
    }

    /**
     * Generate or fetch a 1-click no-password signature link for a job/timesheet (for Agent or Admin).
     */
    public function generateLink(Request $request, int $jobId)
    {
        $job = InterpreterJob::with(['timesheet', 'toLanguage', 'client.user'])->findOrFail($jobId);
        $timesheet = $job->timesheet;

        if (!$timesheet) {
            $timesheet = Timesheet::create([
                'client_id' => $job->client_id,
                'agent_id' => $job->agent_id ?? 1,
                'job_id' => $job->id,
                'from_language_id' => $job->from_language_id ?? 37,
                'to_language_id' => $job->to_language_id ?? 1,
                'client_reference' => $job->client_reference ?: ('JOB-' . $job->id),
                'appointment_date' => $job->appointment_date ? $job->appointment_date->toDateString() : now()->toDateString(),
                'start_time' => $job->start_time ?: '09:00',
                'end_time' => $job->end_time ?: '10:30',
                'duration_hours' => $job->duration_hours ?? 1,
                'duration_minutes' => $job->duration_minutes ?? 30,
                'status' => 'pending',
                'agent_status' => 'Y',
                'client_status' => 'N',
            ]);
        }

        if (empty($timesheet->magic_sign_token)) {
            $timesheet->magic_sign_token = Str::random(32);
            $timesheet->magic_sign_expires_at = now()->addDays(14);
            $timesheet->save();
        }

        $url = route('magic.timesheet.show', $timesheet->magic_sign_token);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'url' => $url,
                'token' => $timesheet->magic_sign_token,
            ]);
        }

        return redirect()->route('magic.timesheet.show', $timesheet->magic_sign_token);
    }

    /**
     * Public, no-password 1-Click Remittance Upload & Payment Confirmation (Deliverable 4C + 4D Never Chase Twice).
     */
    public function showRemittance(string $token)
    {
        $invoice = ClientInvoice::with(['client.user', 'client.organisation', 'paymentProofs'])
            ->where('remittance_magic_token', $token)
            ->firstOrFail();

        $allClientUnpaid = ClientInvoice::where('client_id', $invoice->client_id)
            ->whereIn('status', ['sent', 'overdue', 'pending'])
            ->orderBy('invoice_date')
            ->get();

        return view('remittances.magic-upload', compact('invoice', 'allClientUnpaid', 'token'));
    }

    /**
     * Record 1-click client payment confirmation & immediately pause chasers (Smart Never Chase Twice Protection).
     */
    public function storeRemittance(Request $request, string $token)
    {
        $invoice = ClientInvoice::with(['client.user'])
            ->where('remittance_magic_token', $token)
            ->firstOrFail();

        $validated = $request->validate([
            'reference' => 'required|string|max:120',
            'amount' => 'required|numeric|min:0.01',
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        PaymentProof::create([
            'client_invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'submitted_by' => optional(optional($invoice->client)->user)->id ?? 1,
            'amount' => (float) $validated['amount'],
            'currency' => 'GBP',
            'paid_at' => $validated['paid_at'] ?? now(),
            'method' => 'Bank Transfer (1-Click Remittance)',
            'reference' => $validated['reference'],
            'notes' => $validated['notes'] ?? 'Confirmed via 1-Click No-Password Remittance Link',
            'status' => 'pending',
        ]);

        // Smart "Never Chase Twice" Protection: pause all reminders on this invoice immediately
        $invoice->chaser_paused = true;
        $invoice->save();

        return redirect()->route('magic.remittance.show', $token)
            ->with('success', 'Payment confirmation received! Automated payment reminders for ' . $invoice->invoice_number . ' have stopped immediately.');
    }
}
