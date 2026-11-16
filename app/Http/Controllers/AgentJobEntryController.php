<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\AgentJobEntry;
use App\MatchedAgent;
use App\Job;
use App\InterpreterJob;
use App\TranslatorJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\AgentJobEntryExpense;
use App\Timesheet;
use App\ClientInvoiceItem;
use App\AllUpdates;

class AgentJobEntryController extends Controller
{
    /**
     * Display the job entry form for a specific job
     */
    public function create($jobType, $jobId)
    {
        $user = Auth::user();
        $agent = $user->agent;
        
        if (!$agent) {
            abort(403, 'Access denied. Agent profile required.');
        }

        // Get the job based on type
        $job = $this->getJobByTypeAndId($jobType, $jobId);
        
        if (!$job) {
            abort(404, 'Job not found.');
        }

        // Check if agent is matched to this job
        $matchedAgent = MatchedAgent::where('agent_id', $agent->id)
            ->where('job_type', $this->getJobModelClass($jobType))
            ->where('job_id', $jobId)
            ->whereIn('status', [2, 4])
            ->first();

        if (!$matchedAgent) {
            $allowByCancelledSameDayTimesheet = false;
            if ($jobType === 'interpreter' && method_exists($job, 'isCancelledSameDay')) {
                $isEligibleCancelledSameDay = ($job->statusName === 'cancelled' && $job->isCancelledSameDay());
                if ($isEligibleCancelledSameDay) {
                    $hasTimesheet = Timesheet::where('job_id', $jobId)->where('agent_id', $agent->id)->exists();
                    $assignedMatched = $job->assignedMatched;
                    $hasAssignedMatch = $assignedMatched && (int) $assignedMatched->agent_id === (int) $agent->id;
                    $hasAssignedAudit = AllUpdates::where('job_id', $jobId)
                        ->where('job_type', 'interpreter-jobs')
                        ->where('agent_id', $agent->id)
                        ->where('deleted', 'N')
                        ->where('code', 'Assigned')
                        ->exists();
                    $hasAgentIdOnJob = ((int) $job->agent_id === (int) $agent->id);
                    $agentMatch = MatchedAgent::where('agent_id', $agent->id)
                        ->where('job_type', $this->getJobModelClass($jobType))
                        ->where('job_id', $jobId)
                        ->first();
                    if ($agentMatch && in_array($agentMatch->status, ['rejected', 'cancelled'])) {
                        $allowByCancelledSameDayTimesheet = false;
                    } else {
                        $allowByCancelledSameDayTimesheet = $hasTimesheet || $hasAssignedMatch || $hasAssignedAudit || $hasAgentIdOnJob;
                    }
                }
            }
            if (!$allowByCancelledSameDayTimesheet) {
                abort(403, 'You are not authorized to enter amounts for this job.');
            }
        }

        // Check if entry already exists
        $existingEntry = AgentJobEntry::where('agent_id', $agent->id)
            ->where('job_type', $this->getJobModelClass($jobType))
            ->where('job_id', $jobId)
            ->first();

        return view('agent.job-entry.create', compact('job', 'jobType', 'existingEntry'));
    }

    /**
     * Store the job entry
     */
    public function store(Request $request, $jobType, $jobId)
    {
        $user = Auth::user();
        $agent = $user->agent;
        
        if (!$agent) {
            abort(403, 'Access denied. Agent profile required.');
        }

        $request->validate([
            'base_amount' => 'required|numeric|min:0',
            'expenses_amount' => 'nullable|numeric|min:0',
            'expenses_details' => 'nullable|array',
            'expenses_details.*.type' => 'nullable|string|max:100',
            'expenses_details.*.value' => 'nullable|numeric|min:0',
            // Surcharge is controlled by admin, agents cannot set it here
            'surcharge' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        // Get the job to validate it exists
        $job = $this->getJobByTypeAndId($jobType, $jobId);
        
        if (!$job) {
            abort(404, 'Job not found.');
        }

        // Check if agent is matched to this job
        $matchedAgent = MatchedAgent::where('agent_id', $agent->id)
            ->where('job_type', $this->getJobModelClass($jobType))
            ->where('job_id', $jobId)
            ->whereIn('status', [2, 4]) // accepted or assigned
            ->first();

        if (!$matchedAgent) {
            $allowByCancelledSameDayTimesheet = false;
            if ($jobType === 'interpreter' && method_exists($job, 'isCancelledSameDay')) {
                $isEligibleCancelledSameDay = ($job->statusName === 'cancelled' && $job->isCancelledSameDay());
                if ($isEligibleCancelledSameDay) {
                    $hasTimesheet = Timesheet::where('job_id', $jobId)->where('agent_id', $agent->id)->exists();
                    $assignedMatched = $job->assignedMatched;
                    $hasAssignedMatch = $assignedMatched && (int) $assignedMatched->agent_id === (int) $agent->id;
                    $hasAssignedAudit = AllUpdates::where('job_id', $jobId)
                        ->where('job_type', 'interpreter-jobs')
                        ->where('agent_id', $agent->id)
                        ->where('deleted', 'N')
                        ->where('code', 'Assigned')
                        ->exists();
                    $hasAgentIdOnJob = ((int) $job->agent_id === (int) $agent->id);
                    $agentMatch = MatchedAgent::where('agent_id', $agent->id)
                        ->where('job_type', $this->getJobModelClass($jobType))
                        ->where('job_id', $jobId)
                        ->first();
                    if ($agentMatch && in_array($agentMatch->status, ['rejected', 'cancelled'])) {
                        $allowByCancelledSameDayTimesheet = false;
                    } else {
                        $allowByCancelledSameDayTimesheet = $hasTimesheet || $hasAssignedMatch || $hasAssignedAudit || $hasAgentIdOnJob;
                    }
                }
            }
            if (!$allowByCancelledSameDayTimesheet) {
                abort(403, 'You are not authorized to enter amounts for this job.');
            }
        }

        // Normalize numeric inputs and coerce null/empty to 0
        $baseAmount = floatval($request->input('base_amount') ?? 0);
        // Compute expenses from details if provided; fallback to hidden total
        $details = $request->input('expenses_details', []);
        $computedExpenses = 0.0;
        $normalizedDetails = [];
        if (is_array($details)) {
            foreach ($details as $item) {
                $type = isset($item['type']) ? trim($item['type']) : null;
                $value = isset($item['value']) ? floatval($item['value']) : 0.0;
                if ($value > 0) {
                    $computedExpenses += $value;
                    $normalizedDetails[] = [
                        'type' => $type,
                        'amount' => number_format($value, 2, '.', ''),
                    ];
                }
            }
        }
        $expensesAmount = $computedExpenses > 0
            ? floatval(number_format($computedExpenses, 2, '.', ''))
            : floatval($request->input('expenses_amount') ?? 0);
        // Force surcharge to 0 for agent submissions; admins can add later in invoice review
        $surcharge = 0;
        $otherCharges = floatval($request->input('other_charges') ?? 0);
        
        // Calculate total amount
        $totalAmount = $baseAmount + $expensesAmount + $surcharge + $otherCharges;

        // Block changes to entries already bundled into an invoice to prevent
        // the same job being re-invoiced while it still sits on the original invoice
        $existingEntry = AgentJobEntry::where('agent_id', $agent->id)
            ->where('job_type', $this->getJobModelClass($jobType))
            ->where('job_id', $jobId)
            ->first();
        if ($existingEntry && $existingEntry->invoiced) {
            return back()->with('failure', 'This job has already been included in an invoice. Delete the draft invoice first if you need to amend it.');
        }

        // Create or update the entry
        try {
            $entry = AgentJobEntry::updateOrCreate(
                [
                    'agent_id' => $agent->id,
                    'job_type' => $this->getJobModelClass($jobType),
                    'job_id' => $jobId,
                ],
                [
                    'base_amount' => $baseAmount,
                    'expenses' => $expensesAmount,
                    'surcharge' => $surcharge,
                    'other_charges' => $otherCharges,
                    'total_amount' => $totalAmount,
                    'notes' => $request->input('notes'),
                    // Ensure newly saved entries appear in the pending list until invoiced
                    'invoiced' => false,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('AgentJobEntryController@store: failed to create entry', [
                'error' => $e->getMessage(),
                'agent_id' => $agent->id,
                'job_id' => $jobId
            ]);
            return back()->with('failure', 'Failed to save job entry. Please try again or contact support.');
        }

        try {
            if ($jobType === 'interpreter' && method_exists($job, 'isCancelledSameDay')) {
                if ($job->statusName === 'cancelled' && $job->isCancelledSameDay()) {
                    \Illuminate\Support\Facades\Mail::to(config('app.to.address'))
                        ->send(new \App\Mail\JobMail(
                            $job,
                            'emails.admin.agent-claim-cancelled-same-day',
                            'Same-day cancelled job claim submitted',
                            'admin'
                        ));
                }
            }
        } catch (\Throwable $e) { }

        // Persist expense breakdown items
        if (!empty($normalizedDetails)) {
            AgentJobEntryExpense::where('agent_job_entry_id', $entry->id)->delete();
            foreach ($normalizedDetails as $row) {
                AgentJobEntryExpense::create([
                    'agent_job_entry_id' => $entry->id,
                    'type' => $row['type'],
                    'amount' => $row['amount'],
                ]);
            }
        }

        return redirect()->route('agent.job-entry.pending')
            ->with('success', 'Job entry saved successfully. It has been added to your pending jobs list.');
    }

    /**
     * Display the pending jobs list for the agent
     */
    public function pendingJobs()
    {
        $user = Auth::user();
        $agent = $user->agent;
        
        if (!$agent) {
            abort(403, 'Access denied. Agent profile required.');
        }

        $start = request()->query('start_date') ?? now()->startOfMonth()->toDateString();
        $end = request()->query('end_date') ?? now()->endOfMonth()->toDateString();

        Log::info('AgentJobEntryController@pendingJobs: fetching pending entries', [
            'userId' => $user->id,
            'agentId' => $agent->id,
            'start' => $start,
            'end' => $end,
        ]);

        $pendingEntries = AgentJobEntry::query()
            ->where('agent_id', $agent->id)
            ->where('invoiced', false)
            ->orderBy('created_at', 'desc')
            ->get();

        // Load job details for each entry
        foreach ($pendingEntries as $entry) {
            Log::info('AgentJobEntryController@pendingJobs: entry hydrate start', [
                'entryId' => $entry->id,
                'job_type' => $entry->job_type,
                'job_id' => $entry->job_id,
            ]);
            $entry->jobDetails = $this->getJobByTypeAndId(
                $this->getJobTypeFromModel($entry->job_type), 
                $entry->job_id
            );
            Log::info('AgentJobEntryController@pendingJobs: entry hydrate done', [
                'entryId' => $entry->id,
                'job_found' => (bool) $entry->jobDetails,
            ]);
        }

        // Apply in-memory appointment date filter after hydrating jobs (robust across drivers)
        $filtered = $pendingEntries->filter(function($entry) use ($start, $end){
            $type = $entry->job_type;
            $job = $entry->jobDetails ?? null;
            if (!$job) { return false; }
            $date = null;
            if ($type === 'App\\InterpreterJob') { $date = $job->appointment_date ?? null; }
            elseif ($type === 'App\\TranslatorJob') { $date = $job->target_date ?? null; }
            if (!$date) { return false; }
            try {
                $d = \Carbon\Carbon::parse($date)->toDateString();
                return ($d >= $start) && ($d <= $end);
            } catch (\Throwable $e) { return false; }
        })->values();

        Log::info('AgentJobEntryController@pendingJobs: returning view', [
            'pending_count' => $filtered->count(),
        ]);
        return view('agent.job-entry.pending', [
            'pendingEntries' => $filtered,
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    /**
     * Edit a pending job entry
     */
    public function edit($entryId)
    {
        $user = Auth::user();
        $agent = $user->agent;
        
        if (!$agent) {
            abort(403, 'Access denied. Agent profile required.');
        }

        $entry = AgentJobEntry::where('agent_id', $agent->id)
            ->where('id', $entryId)
            ->firstOrFail();

        if ($entry->invoiced) {
            return back()->with('failure', 'This entry has already been included in an invoice and can no longer be edited.');
        }

        $jobType = $this->getJobTypeFromModel($entry->job_type);
        $job = $this->getJobByTypeAndId($jobType, $entry->job_id);

        $isPaid = ClientInvoiceItem::where('job_id', $entry->job_id)
            ->where(function ($q) use ($entry) {
                $q->where('job_type', $entry->job_type)->orWhereNull('job_type');
            })
            ->whereHas('invoice', function ($q) { $q->where('status', 'paid'); })
            ->exists();
        if ($isPaid) {
            return back()->with('failure', 'This entry cannot be edited after payment has been recorded.');
        }

        return view('agent.job-entry.edit', compact('entry', 'job', 'jobType'));
    }

    /**
     * Update a pending job entry
     */
    public function update(Request $request, $entryId)
    {
        $user = Auth::user();
        $agent = $user->agent;
        
        if (!$agent) {
            abort(403, 'Access denied. Agent profile required.');
        }

        $request->validate([
            'base_amount' => 'required|numeric|min:0',
            'expenses_amount' => 'nullable|numeric|min:0',
            'expenses_details' => 'nullable|array',
            'expenses_details.*.type' => 'nullable|string|max:100',
            'expenses_details.*.value' => 'nullable|numeric|min:0',
            'surcharge' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $entry = AgentJobEntry::where('agent_id', $agent->id)
            ->where('id', $entryId)
            ->firstOrFail();

        if ($entry->invoiced) {
            return back()->with('failure', 'This entry has already been included in an invoice and can no longer be edited.');
        }

        $isPaid = ClientInvoiceItem::where('job_id', $entry->job_id)
            ->where(function ($q) use ($entry) {
                $q->where('job_type', $entry->job_type)->orWhereNull('job_type');
            })
            ->whereHas('invoice', function ($q) { $q->where('status', 'paid'); })
            ->exists();
        if ($isPaid) {
            return back()->with('failure', 'This entry cannot be edited after payment has been recorded.');
        }

        // Normalize numeric inputs and coerce null/empty to 0
        $baseAmount = floatval($request->input('base_amount') ?? 0);
        // Compute expenses from details if provided; fallback to hidden total
        $details = $request->input('expenses_details', []);
        $computedExpenses = 0.0;
        $normalizedDetails = [];
        if (is_array($details)) {
            foreach ($details as $item) {
                $type = isset($item['type']) ? trim($item['type']) : null;
                $value = isset($item['value']) ? floatval($item['value']) : 0.0;
                if ($value > 0) {
                    $computedExpenses += $value;
                    $normalizedDetails[] = [
                        'type' => $type,
                        'amount' => number_format($value, 2, '.', ''),
                    ];
                }
            }
        }
        $expensesAmount = $computedExpenses > 0
            ? floatval(number_format($computedExpenses, 2, '.', ''))
            : floatval($request->input('expenses_amount') ?? 0);
        // Force surcharge to 0 for agent submissions; admins can add later in invoice review
        $surcharge = 0;
        $otherCharges = floatval($request->input('other_charges') ?? 0);
        
        // Calculate total amount
        $totalAmount = $baseAmount + $expensesAmount + $surcharge + $otherCharges;

        $entry->update([
            'base_amount' => $baseAmount,
            'expenses' => $expensesAmount,
            'surcharge' => $surcharge,
            'other_charges' => $otherCharges,
            'total_amount' => $totalAmount,
            'notes' => $request->input('notes'),
        ]);

        try {
            $jobType = $this->getJobTypeFromModel($entry->job_type);
            $job = $this->getJobByTypeAndId($jobType, $entry->job_id);
            if ($jobType === 'interpreter' && method_exists($job, 'isCancelledSameDay')) {
                if ($job->statusName === 'cancelled' && $job->isCancelledSameDay()) {
                    \Illuminate\Support\Facades\Mail::to(config('app.to.address'))
                        ->send(new \App\Mail\JobMail(
                            $job,
                            'emails.admin.agent-claim-cancelled-same-day',
                            'Same-day cancelled job claim updated',
                            'admin'
                        ));
                }
            }
        } catch (\Throwable $e) { }

        // Persist expense breakdown items
        AgentJobEntryExpense::where('agent_job_entry_id', $entry->id)->delete();
        if (!empty($normalizedDetails)) {
            foreach ($normalizedDetails as $row) {
                AgentJobEntryExpense::create([
                    'agent_job_entry_id' => $entry->id,
                    'type' => $row['type'],
                    'amount' => $row['amount'],
                ]);
            }
        }

        return redirect()->route('agent.job-entry.pending')
            ->with('success', 'Job entry updated successfully.');
    }

    /**
     * Delete a pending job entry
     */
    public function destroy($entryId)
    {
        $user = Auth::user();
        $agent = $user->agent;
        
        if (!$agent) {
            abort(403, 'Access denied. Agent profile required.');
        }

        $entry = AgentJobEntry::where('agent_id', $agent->id)
            ->where('id', $entryId)
            ->where('invoiced', false)
            ->firstOrFail();

        $entry->delete();

        return redirect()->route('agent.job-entry.pending')
            ->with('success', 'Job entry deleted successfully.');
    }

    /**
     * Get job by type and ID
     */
    private function getJobByTypeAndId($jobType, $jobId)
    {
        switch ($jobType) {
            case 'interpreter':
                return InterpreterJob::find($jobId);
            case 'translator':
                return TranslatorJob::find($jobId);
            default:
                return null;
        }
    }

    /**
     * Get job model class from job type
     */
    private function getJobModelClass($jobType)
    {
        switch ($jobType) {
            case 'interpreter':
                return 'App\InterpreterJob';
            case 'translator':
                return 'App\TranslatorJob';
            default:
                return null;
        }
    }

    /**
     * Get job type from model class
     */
    private function getJobTypeFromModel($modelClass)
    {
        switch ($modelClass) {
            case 'App\InterpreterJob':
                return 'interpreter';
            case 'App\TranslatorJob':
                return 'translator';
            default:
                return null;
        }
    }
}
