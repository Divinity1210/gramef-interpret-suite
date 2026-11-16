<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\TranslatorJob;

// Test route to simulate agent login and check pending jobs
Route::get('/test-pending-jobs/{agent_id}', function($agent_id) {
    $agent = \App\Agent::find($agent_id);
    if (!$agent) {
        return "Agent not found";
    }
    
    // Simulate login
    Auth::login($agent->user);
    
    // Get pending entries using the same logic as the controller
    $pendingEntries = \App\AgentJobEntry::where('agent_id', $agent->id)
        ->where('invoiced', false)
        ->with('job')
        ->get();
    
    $output = "<h2>Pending Job Entries for Agent {$agent->id} ({$agent->user->email})</h2>";
    $output .= "<p>Total entries: {$pendingEntries->count()}</p>";
    $output .= "<p>Total amount: £{$pendingEntries->sum('total_amount')}</p>";
    
    if ($pendingEntries->count() > 0) {
        $output .= "<ul>";
        foreach ($pendingEntries as $entry) {
            $output .= "<li>Entry ID: {$entry->id}, Job ID: {$entry->job_id}, Amount: £{$entry->total_amount}</li>";
        }
        $output .= "</ul>";
        $output .= "<p><a href='/agent/job-entry/pending'>Go to Pending Jobs Page</a></p>";
    } else {
        $output .= "<p>No pending entries found.</p>";
    }
    
    return $output;
});

// Local-only: quick seeding route to create a translator job without CSRF (for E2E/preview automation)
Route::match(['GET', 'POST'], '/dev/translator-jobs/seed', function (Request $request) {
    if (!app()->environment('local')) {
        abort(403, 'Forbidden');
    }

    // Allow overriding via query/body; otherwise use sensible defaults found on the create form
    $data = [
        'client_id' => (int) ($request->input('client_id', 440)),
        'from_language_id' => (int) ($request->input('from_language_id', 3)),
        'to_language_id' => (int) ($request->input('to_language_id', 3)),
        'skill_id' => (int) ($request->input('skill_id', 7)),
        'word_count' => (int) ($request->input('word_count', 654)),
        'notes' => (string) $request->input('notes', 'Seeded via /dev route'),
        'client_reference' => (string) $request->input('client_reference', 'E2E-DEV-SEED'),
        'affirmation' => (int) $request->input('affirmation', 0),
        'affidavit' => (int) $request->input('affidavit', 0),
        'target_date' => Carbon::parse($request->input('target_date', Carbon::now()->addWeek()->format('Y-m-d'))),
        'status' => (int) $request->input('status', 0),
    ];

    // Create job and optional document
    /** @var TranslatorJob $job */
    $job = TranslatorJob::create($data);

    $docName = $request->input('doc_name', 'seed-sample.txt');
    $docUrl = $request->input('doc_url', 'translator-jobs/dummy-'.time().'.txt');

    $job->documents()->create([
        'name' => $docName,
        'url' => $docUrl,
    ]);

    return response()->json([
        'ok' => true,
        'id' => $job->id,
        'client_reference' => $job->client_reference,
        'edit_url' => url("/translator-jobs/{$job->id}/edit"),
        'index_url' => route('translator-jobs.index'),
    ]);
});