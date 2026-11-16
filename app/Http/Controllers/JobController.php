<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Job;

class JobController extends Controller
{
    /**
     * Search for a job by ID, reference (e.g., 123/atoz), or client reference
     */
    public function search($identifier)
    {
        try {
            $job = null;

            if (is_numeric($identifier)) {
                $job = \App\InterpreterJob::find($identifier) ?? \App\TranslatorJob::find($identifier);
            } elseif (preg_match('/^(\d+)\/atoz$/i', $identifier, $m)) {
                $id = (int) $m[1];
                $job = \App\InterpreterJob::find($id) ?? \App\TranslatorJob::find($id);
            } else {
                // Search by client_reference across both job types
                $job = \App\InterpreterJob::where('client_reference', $identifier)->first();
                if (!$job) {
                    $job = \App\TranslatorJob::where('client_reference', $identifier)->first();
                }
            }

            if ($job) {
                $isInterpreter = $job instanceof \App\InterpreterJob;
                $clientName = optional(optional($job->client)->user)->getFullName();
                $language = $isInterpreter
                    ? optional($job->toLanguage)->name
                    : trim(sprintf('%s → %s', optional($job->fromLanguage)->name, optional($job->toLanguage)->name), ' → ');
                $date = $isInterpreter
                    ? optional($job->appointment_date)->toDateString()
                    : optional($job->target_date)->toDateString();
                $postal = $isInterpreter ? $job->postcode : optional($job->client)->client_postcode;

                // Attempt to get latest AgentJobEntry amount for this job
                $entry = \App\AgentJobEntry::where('job_type', get_class($job))
                    ->where('job_id', $job->id)
                    ->orderBy('id', 'desc')
                    ->first();
                $amount = $entry ? (float) $entry->total_amount : 0.0;

                return response()->json([
                    'success' => true,
                    'job' => [
                        'id' => $job->id,
                        'job_ref' => $job->reference,
                        'client_name' => $clientName,
                        'postal_code' => $postal,
                        'language' => $language,
                        'date' => $date,
                        'amount' => $amount,
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Job not found'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching for job: ' . $e->getMessage()
            ], 500);
        }
    }
}