<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\InterpreterJob;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use App\Mail\JobMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class InterpreterJobController extends Controller
{
    /**
     * Get list of interpreter jobs for the authenticated agent
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $agent = $user->agent;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'User is not an agent'
            ], 403);
        }

        $perPage = (int) $request->get('per_page', 20);

        try {
            $query = InterpreterJob::query()
                ->with(['to_language', 'client.user', 'agent.user', 'timesheet', 'skill'])
                ->where(function ($q) use ($agent) {
                    $q->where('agent_id', $agent->id)
                        ->orWhere(function ($q) use ($agent) {
                            $q->whereNull('agent_id')
                                ->whereHas('matchedAgents', function ($mq) use ($agent) {
                                    $mq->where('agent_id', $agent->id)
                                        ->whereNotIn('status', [1, 3]); // exclude rejected(1), cancelled(3)
                                });
                        });
                });

            // Search filter
            $search = trim((string) $request->get('q', ''));
            if ($search !== '') {
                $query->where(function ($outer) use ($search) {
                    $outer->where('id', 'like', '%' . $search . '%')
                        ->orWhere('client_reference', 'like', '%' . $search . '%')
                        ->orWhere('address_line_1', 'like', '%' . $search . '%')
                        ->orWhere('postcode', 'like', '%' . $search . '%')
                        ->orWhereHas('to_language', function ($lq) use ($search) {
                            $lq->where('name', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('client.user', function ($cq) use ($search) {
                            $cq->where('first_name', 'like', '%' . $search . '%')
                                ->orWhere('last_name', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%');
                        });
                });
            }

            // Status filter
            $status = $request->get('status');
            if ($status !== null && $status !== '') {
                if (is_numeric($status)) {
                    $query->where('status', (int) $status);
                }
            }

            $jobs = $query->orderBy('appointment_date', 'desc')
                ->paginate($perPage > 0 ? $perPage : 20);

            // Add can_complete, can_dna, can_return flags and other helpful data
            $jobs->getCollection()->transform(function ($job) {
                try {
                    return [
                        'id' => $job->id,
                        'status' => $job->status,
                        'status_name' => $job->status_name ?? 'unknown',
                        'appointment_date' => $job->appointment_date ? $job->appointment_date->format('Y-m-d') : null,
                        'start_time' => $job->start_time,
                        'end_time' => $job->end_time,
                        'duration_hours' => $job->duration_hours,
                        'duration_minutes' => $job->duration_minutes,
                        'formatted_duration' => $job->formattedDuration ?? null,
                        'address_line_1' => $job->address_line_1,
                        'address_line_2' => $job->address_line_2,
                        'city' => $job->city ?? null,
                        'postcode' => $job->postcode,
                        'skill' => $job->skill ? ['id' => $job->skill->id, 'name' => $job->skill->name ?? $job->skill->skill ?? null] : null,
                        'to_language' => $job->to_language ? ['id' => $job->to_language->id, 'name' => $job->to_language->name] : null,
                        'from_language' => $job->fromLanguage ? ['id' => $job->fromLanguage->id, 'name' => $job->fromLanguage->name] : null,
                        'client_reference' => $job->client_reference ?? null,
                        'client' => $job->client && $job->client->user ? ['id' => $job->client->id, 'name' => $job->client->user->fullName ?? null] : null,
                        'can_accept' => (int) ($job->agent_id ?? 0) === 0 && in_array((int) $job->status, [0, 5], true),
                        'can_complete' => (function () use ($job) {
                            try {
                                return $job->canBeCompleted(); } catch (\Throwable $e) {
                                return false; } })(),
                        'can_dna' => (function () use ($job) {
                            try {
                                return $job->canBeDna(); } catch (\Throwable $e) {
                                return false; } })(),
                        'can_return' => (function () use ($job) {
                            try {
                                return $job->canBeRetrn(); } catch (\Throwable $e) {
                                return false; } })(),
                        'has_timesheet' => (bool) $job->timesheet,
                    ];
                } catch (\Throwable $e) {
                    // If the entire transform crashes for a job, return minimal data
                    return [
                        'id' => $job->id ?? 0,
                        'status' => $job->status ?? 0,
                        'status_name' => 'unknown',
                        'appointment_date' => null,
                        'start_time' => null,
                        'end_time' => null,
                        'duration_hours' => 0,
                        'duration_minutes' => 0,
                        'formatted_duration' => null,
                        'address_line_1' => null,
                        'address_line_2' => null,
                        'city' => null,
                        'postcode' => null,
                        'skill' => null,
                        'to_language' => null,
                        'from_language' => null,
                        'client' => null,
                        'can_complete' => false,
                        'can_dna' => false,
                        'can_return' => false,
                        'has_timesheet' => false,
                    ];
                }
            });

            return response()->json([
                'success' => true,
                'data' => $jobs
            ]);
        } catch (QueryException $e) {
            $info = property_exists($e, 'errorInfo') ? $e->errorInfo : null;
            $sqlState = is_array($info) && isset($info[0]) ? (string) $info[0] : '';
            $driverCode = is_array($info) && isset($info[1]) ? (string) $info[1] : '';
            $driverMessage = is_array($info) && isset($info[2]) ? (string) $info[2] : '';
            $driverMessage = $driverMessage ? substr($driverMessage, 0, 300) : '';
            error_log('MOBILE_API_ERROR interpreter-jobs.index QueryException sqlstate=' . $sqlState . ' code=' . $driverCode . ' msg=' . $driverMessage);

            return response()->json([
                'success' => false,
                'message' => 'Jobs are unavailable'
            ], 503);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.index ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Jobs are unavailable'
            ], 503);
        }

    }

    /**
     * Get a specific interpreter job
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not an agent'
                ], 403);
            }

            $job = InterpreterJob::with([
                'to_language',
                'client.user',
                'client.organisation.company',
                'agent.user',
                'timesheet',
                'skill',
            ])->find($id);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            // Load optional relationships safely
            try {
                $job->load('documents');
            } catch (\Throwable $e) {
            }
            try {
                $job->load('feedback');
            } catch (\Throwable $e) {
            }
            try {
                $job->load('securityType');
            } catch (\Throwable $e) {
            }
            try {
                $job->load('interpreterType');
            } catch (\Throwable $e) {
            }

            // Check if user is authorized to view this job
            $isAssigned = (int) $job->agent_id === (int) $agent->id;
            $isMatched = $job->matchedAgents()->where('agent_id', $agent->id)->exists();

            if (!$isAssigned && !$isMatched) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Get matched agent status
            $matchedStatus = null;
            if ($isMatched) {
                $matchedRecord = $job->matchedAgents()->where('agent_id', $agent->id)->first();
                $matchedStatus = $matchedRecord ? $matchedRecord->status : null;
            }
            $requiresQuote = false;
            try {
                $requiresQuote = method_exists($job, 'requiresQuote') ? (bool) $job->requiresQuote() : false;
            } catch (\Throwable $e) {
                $requiresQuote = false;
            }
            $hasQuoted = false;
            try {
                $matched = $job->matchedAgents()->where('agent_id', $agent->id)->first();
                $hasQuoted = $matched && $matched->quotes()->exists();
            } catch (\Throwable $e) {
                $hasQuoted = false;
            }
            $canAccept = $isMatched
                && !$isAssigned
                && (int) ($job->agent_id ?? 0) === 0
                && in_array((int) $job->status, [0, 5], true)
                && !in_array($matchedStatus, [1, 3, '1', '3', 'rejected', 'cancelled'], true)
                && !($requiresQuote && $hasQuoted);

            // Transform for consistent mobile response
            $jobData = [
                'id' => $job->id,
                'reference' => $job->reference,
                'status' => $job->status,
                'status_name' => $job->status_name,
                'agent_status' => (function () use ($job) {
                    try {
                        return $job->getStatusForAgent() ?? $job->status_name;
                    } catch (\Throwable $e) {
                        return $job->status_name;
                    }
                })(),
                'is_matched' => $isMatched && !$isAssigned,
                'matched_status' => $matchedStatus,
                'requires_quote' => $requiresQuote,
                'has_quoted' => $hasQuoted,
                'can_accept' => $canAccept,
                'agent_quote' => (function () use ($job, $agent) {
                    try {
                        $matched = $job->matchedAgents()->where('agent_id', $agent->id)->first();
                        if (!$matched)
                            return null;
                        $quote = $matched->quotes()->latest()->first();
                        if (!$quote)
                            return null;
                        return [
                            'id' => $quote->id,
                            'interpreting_cost' => $quote->interpreting_cost,
                            'travel_time' => $quote->travel_time,
                            'travel_cost' => $quote->travel_cost,
                            'mileage_miles' => $quote->mileage_miles,
                            'mileage_cost' => $quote->mileage_cost,
                            'cost_description' => $quote->cost_description,
                            'cost' => $quote->cost,
                            'status' => $quote->status ?? null,
                        ];
                    } catch (\Throwable $e) {
                        return null; }
                })(),
                'appointment_date' => $job->appointment_date ? $job->appointment_date->format('Y-m-d') : null,
                'start_time' => $job->start_time,
                'end_time' => $job->end_time,
                'duration_hours' => $job->duration_hours,
                'duration_minutes' => $job->duration_minutes,
                'formatted_duration' => (function () use ($job) {
                    try {
                        return $job->formattedDuration;
                    } catch (\Throwable $e) {
                        return ($job->duration_hours ? $job->duration_hours . 'h' : '') . ($job->duration_minutes ? ' ' . $job->duration_minutes . 'm' : '');
                    }
                })(),
                'address_line_1' => $job->address_line_1,
                'address_line_2' => $job->address_line_2,
                'city' => $job->city,
                'county' => $job->county,
                'postcode' => $job->postcode,
                'latitude' => $job->latitude,
                'longitude' => $job->longitude,
                'to_language' => $job->to_language ? ['id' => $job->to_language->id, 'name' => $job->to_language->name] : null,
                'from_language' => ['id' => 37, 'name' => 'English'], // Interpreter jobs are always from English
                'company_name' => optional(optional(optional($job->client)->organisation)->company)->name ?? null,
                'client' => $job->client && $job->client->user ? ['id' => $job->client->id, 'name' => $job->client->user->fullName] : null,
                'agent' => $job->agent && $job->agent->user ? ['id' => $job->agent->id, 'name' => $job->agent->user->fullName] : null,
                'skill' => $job->skill ? ['id' => $job->skill->id, 'name' => $job->skill->skill ?? $job->skill->name ?? null] : null,
                'gender_preference' => $job->gender ?? null,
                'gender_name' => (function () use ($job) {
                    try {
                        return $job->getGenderName(); } catch (\Throwable $e) {
                        return null; }
                })(),
                'special_requirements' => $job->special_requirements ?? null,
                'client_reference' => $job->client_reference ?? null,
                'contact_information' => $job->contact_information ?? null,
                'contact_information_is_same_as_account' => (function () use ($job) {
                    try {
                        return (bool) $job->contact_information_is_same_as_account; } catch (\Throwable $e) {
                        return false; } })(),
                'department' => $job->department ?? null,
                'file_reference' => $job->file_reference ?? null,
                // Service user details
                'service_user' => (function () use ($job) {
                    try {
                        $dob = null;
                        if ($job->date_of_birth) {
                            try {
                                $dob = $job->date_of_birth instanceof \Carbon\Carbon ? $job->date_of_birth->format('Y-m-d') : (string) $job->date_of_birth; } catch (\Throwable $e) {
                                $dob = null; }
                        }
                        return [
                            'title' => $job->user_title ?? null,
                            'first_name' => $job->user_first_name ?? null,
                            'last_name' => $job->user_last_name ?? null,
                            'personal_identity_number' => $job->personal_identity_number ?? null,
                            'date_of_birth' => $dob,
                            'contact_person_details' => $job->contact_person_details ?? null,
                        ];
                    } catch (\Throwable $e) {
                        return null;
                    }
                })(),
                'service_user_required' => (function () use ($job) {
                    try {
                        return (bool) $job->service_user_required; } catch (\Throwable $e) {
                        return false; } })(),
                // Security and qualification requirements
                'require_qualified' => $job->require_qualified,
                'security_type' => (function () use ($job) {
                    try {
                        $st = $job->securityType;
                        return $st ? ['id' => $st->id, 'name' => $st->name] : null;
                    } catch (\Throwable $e) {
                        return null; }
                })(),
                'interpreter_type' => (function () use ($job) {
                    try {
                        $it = $job->interpreterType;
                        return $it ? ['id' => $it->id, 'name' => $it->name] : null;
                    } catch (\Throwable $e) {
                        return null; }
                })(),
                'requested_agent' => $job->requested_agent_id ? (function () use ($job) {
                    try {
                        $ra = \App\Agent::with('user')->find($job->requested_agent_id);
                        return $ra && $ra->user ? ['id' => $ra->id, 'name' => $ra->user->fullName] : null;
                    } catch (\Throwable $e) {
                        return null; }
                })() : null,
                'dna' => (function () use ($job) {
                    try {
                        return (bool) ($job->dna ?? false); } catch (\Throwable $e) {
                        return false; } })(),
                'retrn' => (function () use ($job) {
                    try {
                        return (bool) ($job->retrn ?? false); } catch (\Throwable $e) {
                        return false; } })(),
                'notes' => $job->notes ?? null,
                'created_at' => $job->created_at ? $job->created_at->toIso8601String() : null,
                'updated_at' => $job->updated_at ? $job->updated_at->toIso8601String() : null,
                'can_complete' => (function () use ($job) {
                    try {
                        return $job->canBeCompleted(); } catch (\Throwable $e) {
                        return false; }
                })(),
                'can_cancel' => (function () use ($job) {
                    try {
                        return $job->canBeCancelled(); } catch (\Throwable $e) {
                        return false; }
                })(),
                'can_dna' => (function () use ($job) {
                    try {
                        return $job->canBeDna(); } catch (\Throwable $e) {
                        return false; }
                })(),
                'can_return' => (function () use ($job) {
                    try {
                        return $job->canBeRetrn(); } catch (\Throwable $e) {
                        return false; }
                })(),
                'has_timesheet' => (bool) $job->timesheet,
                'timesheet' => $job->timesheet ? [
                    'id' => $job->timesheet->id,
                    'status' => $job->timesheet->status ?? null,
                    'signed_at' => $job->timesheet->signed_at ? $job->timesheet->signed_at->toIso8601String() : null,
                    'signature_method' => $job->timesheet->signature_method ?? null,
                ] : null,
                'documents' => (function () use ($job) {
                    try {
                        return $job->documents ? $job->documents->map(function ($doc) {
                            return [
                                'id' => $doc->id,
                                'name' => $doc->name ?? null,
                                'type' => $doc->type ?? null,
                                'url' => $doc->url ?? null,
                                'full_url' => (function () use ($doc) {
                                    try {
                                        return $doc->fullUrl; } catch (\Throwable $e) {
                                        return null; } })(),
                            ];
                        }) : [];
                    } catch (\Throwable $e) {
                        return [];
                    }
                })(),
                'feedback' => (function () use ($job) {
                    try {
                        return $job->feedback ? [
                            'rating' => $job->feedback->rating ?? null,
                            'comment' => $job->feedback->comment ?? null,
                        ] : null;
                    } catch (\Throwable $e) {
                        return null;
                    }
                })(),
            ];

            return response()->json([
                'success' => true,
                'data' => $jobData
            ]);
        } catch (QueryException $e) {
            $info = property_exists($e, 'errorInfo') ? $e->errorInfo : null;
            $sqlState = is_array($info) && isset($info[0]) ? (string) $info[0] : '';
            $driverCode = is_array($info) && isset($info[1]) ? (string) $info[1] : '';
            $driverMessage = is_array($info) && isset($info[2]) ? (string) $info[2] : '';
            $driverMessage = $driverMessage ? substr($driverMessage, 0, 300) : '';
            error_log('MOBILE_API_ERROR interpreter-jobs.show QueryException sqlstate=' . $sqlState . ' code=' . $driverCode . ' msg=' . $driverMessage);

            return response()->json([
                'success' => false,
                'message' => 'Job details are unavailable'
            ], 503);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.show ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Job details are unavailable'
            ], 503);
        }
    }

    /**
     * Accept an interpreter job
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function accept(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not an agent'
                ], 403);
            }

            $job = InterpreterJob::with('matchedAgents')->find($id);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            if ($job->agent_id && (int) $job->agent_id !== (int) $agent->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This assignment is no longer available!'
                ], 403);
            }

            $matched = $job->matchedAgents()->where('agent_id', $agent->id)->first();

            if (!$matched) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            // Only block cancelled agents (deliberate cancellation by admin).
            // Rejected agents should be allowed to re-accept if the job is still open.
            if ($matched->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'This job has been cancelled for you'
                ], 404);
            }

            // Guard: Verify that the agent satisfies security clearance & job requirements
            $canAccept = \App\Agent::where('id', $agent->id)
                ->canBeMatchedToInterpreterJobs($job)
                ->matchesInterpreterJob($job)
                ->exists();

            if (!$canAccept) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not meet the security clearance, DBS, or qualification requirements for this job.'
                ], 403);
            }

            // If job is already assigned to THIS agent, return success
            if ((int) $job->status === 1 && (int) $job->agent_id === (int) $agent->id) {
                return response()->json([
                    'success' => true,
                    'message' => 'Job is already accepted',
                    'data' => [
                        'id' => $job->id,
                        'status' => $job->status,
                        'status_name' => $job->status_name,
                    ]
                ]);
            }

            if ((int) $job->status !== 0 && (int) $job->status !== 5) {
                $statusName = config('enums.statuses')[$job->status] ?? 'unknown';
                return response()->json([
                    'success' => false,
                    'message' => "This job is currently '{$statusName}' and cannot be accepted"
                ], 400);
            }

            // Guard: if client requires a quote, block direct accept
            if (method_exists($job, 'requiresQuote') && $job->requiresQuote()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This client requires a quote before assignment. Please submit a quote instead.'
                ], 400);
            }

            // Reset any previously assigned agents back to rejected (status 1)
            // This matches the web admin assign flow behavior
            \App\MatchedAgent::where('job_id', $job->id)
                ->where('job_type', get_class($job))
                ->where('status', 4) // currently assigned
                ->where('agent_id', '!=', $agent->id)
                ->update(['status' => 1]);

            $matched->update(['status' => 4]);
            $job->update([
                'agent_id' => $agent->id,
                'status' => 1,
            ]);

            $this->notifyAdmins(
                'Job Accepted',
                $agent->user->full_name . ' accepted Job #' . $job->id,
                ['type' => 'job_accepted', 'jobId' => $job->id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Job accepted successfully',
                'data' => [
                    'id' => $job->id,
                    'status' => $job->status,
                    'status_name' => $job->status_name,
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.accept ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Unable to accept job'
            ], 503);
        }
    }

    /**
     * Complete an interpreter job
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function complete(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not an agent'
                ], 403);
            }

            $job = InterpreterJob::find($id);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            if ((int) $job->agent_id !== (int) $agent->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden'
                ], 403);
            }

            if (!$job->canBeCompleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job cannot be completed yet'
                ], 400);
            }

            $job->update(['status' => 4]);

            // Sync matched_agent status to "assigned" (4) to prevent inconsistency
            $job->matchedAgents()
                ->where('agent_id', $agent->id)
                ->whereIn('status', [0, 1, 2]) // matched, rejected, or accepted → assigned
                ->update(['status' => 4]);

            $this->notifyAdmins(
                'Job Completed',
                ($agent->user->full_name ?? 'Agent') . ' completed Job #' . $job->id,
                ['type' => 'job_completed', 'jobId' => $job->id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Job completed successfully',
                'data' => [
                    'id' => $job->id,
                    'status' => $job->status,
                    'status_name' => $job->status_name,
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.complete ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Unable to complete job'
            ], 503);
        }
    }

    /**
     * Mark job as DNA (Did Not Attend)
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function dna(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not an agent'
                ], 403);
            }

            $job = InterpreterJob::find($id);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            if ((int) $job->agent_id !== (int) $agent->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden'
                ], 403);
            }

            if (!$job->canBeDna()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot mark as DNA'
                ], 400);
            }

            $job->update(['status' => 6, 'dna' => true]);

            $this->notifyAdmins(
                'Job DNA',
                ($agent->user->full_name ?? 'Agent') . ' marked Job #' . $job->id . ' as Did Not Attend',
                ['type' => 'job_dna', 'jobId' => $job->id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Job marked as DNA',
                'data' => [
                    'id' => $job->id,
                    'status' => $job->status,
                    'status_name' => $job->status_name,
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.dna ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Unable to update job'
            ], 503);
        }
    }

    /**
     * Return job to the pool
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function return(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not an agent'
                ], 403);
            }

            $job = InterpreterJob::find($id);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            if ((int) $job->agent_id !== (int) $agent->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden'
                ], 403);
            }

            if (!$job->canBeRetrn()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot return this job'
                ], 400);
            }

            $matched = $job->matchedAgents()->where('agent_id', $agent->id)->first();
            if ($matched) {
                $matched->update(['status' => 3]);
            }

            $updateFields = ['status' => 0, 'agent_id' => null, 'retrn' => true];
            if ((int) $job->requested_agent_id === (int) $agent->id) {
                $updateFields['requested_agent_id'] = null;
            }
            $job->update($updateFields);

            \App\Timesheet::where('job_id', $job->id)
                ->where('agent_id', $agent->id)
                ->whereNull('agent_signature')
                ->update(['agent_id' => null]);

            \Illuminate\Support\Facades\DB::table('job_snapshots')->where('job_id', $job->id)->delete();

            // Re-dispatch matching so other agents can see it
            try {
                \App\Jobs\MatchAgents::dispatch($job, optional($matched)->id);
            } catch (\Throwable $e) {
                error_log('MatchAgents dispatch failed after return: ' . $e->getMessage());
            }

            // Send unassignment / return email to admin
            try {
                $adminEmail = config('app.to.address') ?: 'bookings@atozinterpreting.com';
                $agentName = optional($agent->user)->getFullName()
                    ?: optional($agent->user)->full_name
                    ?: 'an interpreter';

                Mail::to($adminEmail)->send(
                    (new JobMail(
                        $job,
                        'emails.matched-agents.cancelled',
                        'Agent unassigned from job #' . $job->reference,
                        'admin'
                    ))->withData([
                        'agentName' => $agentName,
                    ])
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send return/unassign email to admin from mobile: ' . $e->getMessage());
            }

            $this->notifyAdmins(
                'Job Returned',
                ($agent->user->full_name ?? 'Agent') . ' returned Job #' . $job->id . ' to the pool',
                ['type' => 'job_returned', 'jobId' => $job->id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Job returned successfully',
                'data' => [
                    'id' => $job->id,
                    'status' => $job->status,
                    'status_name' => $job->status_name,
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.return ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Unable to return job'
            ], 503);
        }
    }

    /**
     * Decline an interpreter job
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function decline(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not an agent'
                ], 403);
            }

            $job = InterpreterJob::with('matchedAgents')->find($id);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            $matched = $job->matchedAgents()->where('agent_id', $agent->id)->first();

            if (!$matched) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            if ((int) $job->agent_id === (int) $agent->id && !$user->can('unassign', $job)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot unassign from a job after the appointment time has started.'
                ], 400);
            }

            // Update the matched_agent pivot status to rejected (1)
            // Consistent with web reject() behavior
            $matched->update([
                'status' => 1, // rejected (matches web flow)
                'decline_reason' => $request->input('reason', ''),
            ]);

            // CRITICAL: If this agent was the assigned agent, reset the job
            // so it goes back to the pool (status 0 = open, agent_id = null)
            if ((int) $job->agent_id === (int) $agent->id) {
                $updateFields = [
                    'agent_id' => null,
                    'status' => 0, // open / unassigned
                ];
                if ((int) $job->requested_agent_id === (int) $agent->id) {
                    $updateFields['requested_agent_id'] = null;
                }
                $job->update($updateFields);

                \App\Timesheet::where('job_id', $job->id)
                    ->where('agent_id', $agent->id)
                    ->whereNull('agent_signature')
                    ->update(['agent_id' => null]);

                \Illuminate\Support\Facades\DB::table('job_snapshots')->where('job_id', $job->id)->delete();

                // Re-dispatch matching so other agents can see it
                try {
                    \App\Jobs\MatchAgents::dispatch($job, $matched->id);
                } catch (\Throwable $e) {
                    error_log('MatchAgents dispatch failed after decline: ' . $e->getMessage());
                }

                // Send email to admin that assigned agent unassigned/cancelled
                try {
                    $adminEmail = config('app.to.address') ?: 'bookings@atozinterpreting.com';
                    $agentName = optional($agent->user)->getFullName()
                        ?: optional($agent->user)->full_name
                        ?: 'an interpreter';

                    Mail::to($adminEmail)->send(
                        (new JobMail(
                            $job,
                            'emails.matched-agents.cancelled',
                            'Agent has unassigned from job #' . $job->reference,
                            'admin'
                        ))->withData([
                            'agentName' => $agentName,
                        ])
                    );
                } catch (\Throwable $e) {
                    Log::error('Failed to send unassigned email to admin from mobile decline: ' . $e->getMessage());
                }
            }

            // Notify admins
            $this->notifyAdmins(
                'Job Declined',
                ($agent->user->full_name ?? 'Agent') . ' declined Job #' . $job->id,
                ['type' => 'job_declined', 'jobId' => $job->id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Job declined successfully',
                'data' => [
                    'id' => $job->id,
                    'status' => $job->status,
                    'status_name' => $job->status_name,
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.decline ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Unable to decline job'
            ], 503);
        }
    }

    /**
     * Check in to an interpreter job with GPS location
     */
    public function checkIn(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json(['success' => false, 'message' => 'User is not an agent'], 403);
            }

            $job = InterpreterJob::find($id);

            if (!$job) {
                return response()->json(['success' => false, 'message' => 'Job not found'], 404);
            }

            if ((int) $job->agent_id !== (int) $agent->id) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }

            // Guard against missing columns (migration not yet run)
            if (Schema::hasColumn('interpreter_jobs', 'checked_in_at')) {
                if ($job->checked_in_at) {
                    return response()->json(['success' => false, 'message' => 'Already checked in'], 400);
                }

                $job->update([
                    'checked_in_at' => now(),
                    'checked_in_lat' => $request->input('latitude'),
                    'checked_in_lng' => $request->input('longitude'),
                ]);
            }

            $this->notifyAdmins(
                'Agent Checked In',
                ($agent->user->full_name ?? 'Agent') . ' checked in to Job #' . $job->id,
                ['type' => 'check_in', 'jobId' => $job->id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Checked in successfully',
                'data' => ['id' => $job->id, 'checked_in_at' => $job->checked_in_at ?? now()->toIso8601String()]
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.checkIn ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to check in: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Check out from an interpreter job with GPS location
     */
    public function checkOut(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json(['success' => false, 'message' => 'User is not an agent'], 403);
            }

            $job = InterpreterJob::find($id);

            if (!$job) {
                return response()->json(['success' => false, 'message' => 'Job not found'], 404);
            }

            if ((int) $job->agent_id !== (int) $agent->id) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }

            // Guard against missing columns (migration not yet run)
            if (Schema::hasColumn('interpreter_jobs', 'checked_out_at')) {
                if ($job->checked_out_at) {
                    return response()->json(['success' => false, 'message' => 'Already checked out'], 400);
                }

                $job->update([
                    'checked_out_at' => now(),
                    'checked_out_lat' => $request->input('latitude'),
                    'checked_out_lng' => $request->input('longitude'),
                ]);
            }

            $this->notifyAdmins(
                'Agent Checked Out',
                ($agent->user->full_name ?? 'Agent') . ' checked out from Job #' . $job->id,
                ['type' => 'check_out', 'jobId' => $job->id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Checked out successfully',
                'data' => ['id' => $job->id, 'checked_out_at' => $job->checked_out_at ?? now()->toIso8601String()]
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.checkOut ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to check out: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Notify all admin users via push notification
     */
    private function notifyAdmins(string $title, string $body, array $data = [])
    {
        try {
            $adminUserIds = \App\User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['admin', 'super-admin']);
            })->pluck('id')->toArray();

            PushNotificationService::notifyUsers($adminUserIds, $title, $body, $data);
        } catch (\Throwable $e) {
            error_log('Push notification to admins failed: ' . $e->getMessage());
        }
    }

    /**
     * Get the agent's amount entry for a job
     */
    public function getAmount(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json(['success' => false, 'message' => 'User is not an agent'], 403);
            }

            $job = InterpreterJob::find($id);
            if (!$job || (int) $job->agent_id !== (int) $agent->id) {
                return response()->json(['success' => false, 'message' => 'Job not found'], 404);
            }

            $entry = \App\AgentJobEntry::where('job_id', $id)
                ->where('job_type', 'App\\InterpreterJob')
                ->where('agent_id', $agent->id)
                ->first();

            if (!$entry) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No amount entry yet'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $entry->id,
                    'base_amount' => $entry->base_amount,
                    'expenses' => $entry->expenses,
                    'surcharge' => $entry->surcharge,
                    'other_charges' => $entry->other_charges,
                    'total_amount' => $entry->total_amount,
                    'notes' => $entry->notes,
                    'invoiced' => $entry->invoiced,
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.getAmount ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to load amount'], 500);
        }
    }

    /**
     * Set/update the agent's amount entry for a job
     */
    public function setAmount(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json(['success' => false, 'message' => 'User is not an agent'], 403);
            }

            $job = InterpreterJob::find($id);
            if (!$job || (int) $job->agent_id !== (int) $agent->id) {
                return response()->json(['success' => false, 'message' => 'Job not found'], 404);
            }

            $request->validate([
                'base_amount' => 'required|numeric|min:0',
                'expenses' => 'nullable|numeric|min:0',
                'surcharge' => 'nullable|numeric|min:0',
                'other_charges' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string|max:500',
            ]);

            $entry = \App\AgentJobEntry::updateOrCreate(
                [
                    'job_id' => $id,
                    'job_type' => 'App\\InterpreterJob',
                    'agent_id' => $agent->id,
                ],
                [
                    'base_amount' => $request->input('base_amount', 0),
                    'expenses' => $request->input('expenses', 0),
                    'surcharge' => $request->input('surcharge', 0),
                    'other_charges' => $request->input('other_charges', 0),
                    'notes' => $request->input('notes'),
                ]
            );

            $entry->calculateTotal();
            $entry->save();

            return response()->json([
                'success' => true,
                'message' => 'Amount saved successfully',
                'data' => [
                    'id' => $entry->id,
                    'base_amount' => $entry->base_amount,
                    'expenses' => $entry->expenses,
                    'surcharge' => $entry->surcharge,
                    'other_charges' => $entry->other_charges,
                    'total_amount' => $entry->total_amount,
                    'notes' => $entry->notes,
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.setAmount ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to save amount'], 500);
        }
    }

    /**
     * Upload a signed timesheet document for an interpreter job.
     * Replicates the web InterpreterJobDocumentController@store logic
     * inline to avoid S3 SDK autoloading issues on Vercel serverless.
     */
    public function uploadTimesheet(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json(['success' => false, 'message' => 'User is not an agent'], 403);
            }

            $job = \App\InterpreterJob::find($id);
            if (!$job) {
                return response()->json(['success' => false, 'message' => 'Job not found'], 404);
            }

            // Verify agent has access to this job
            if ((int) $job->agent_id !== (int) $agent->id) {
                $matched = $job->matchedAgents()->where('agent_id', $agent->id)->first();
                if (!$matched) {
                    return response()->json(['success' => false, 'message' => 'Not authorized'], 403);
                }
            }

            if (!$request->hasFile('file')) {
                return response()->json(['success' => false, 'message' => 'No file provided'], 422);
            }

            $file = $request->file('file');
            $documentName = $file->getClientOriginalName();

            // Validate file type (same as web controller)
            $allowedMimes = ['pdf', 'jpg', 'jpeg', 'png'];
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, $allowedMimes)) {
                return response()->json(['success' => false, 'message' => 'File type not allowed. Accepted: pdf, jpg, jpeg, png'], 422);
            }

            // Use public disk (safe on both Vercel and cPanel); override via env if desired
            $disk = env('INTERPRETER_DOCS_DISK') ?: 'public';
            $documentUrl = $file->store("interpreter-jobs/$id", $disk);

            // Get signed_timesheet document type from config
            $signedTimesheetType = config('enums.document_types')['signed_timesheet'] ?? 12;

            // Check for existing document of this type
            $existingDocument = $job->documents->firstWhere('type', $signedTimesheetType);
            if ($existingDocument) {
                $prevUrl = data_get($existingDocument, 'url');
                if (!empty($prevUrl)) {
                    \Illuminate\Support\Facades\Storage::disk($disk)->delete($prevUrl);
                }
            }

            // Create or update document record linked to this job
            $documentModel = $job->documents()->updateOrCreate(
                ['type' => $signedTimesheetType],
                ['url' => $documentUrl, 'name' => $documentName]
            );

            // Auto-create Timesheet record if missing (best effort — table/model may not exist)
            try {
                $timesheet = $job->timesheet;
                if (!$timesheet) {
                    $timesheet = new \App\Timesheet();
                    $timesheet->job_id = $job->id;
                    $timesheet->agent_id = $job->agent_id ?: $agent->id;
                    $timesheet->status = 'Y';
                    $timesheet->save();
                }
            } catch (\Throwable $tsErr) {
                \Illuminate\Support\Facades\Log::warning('Timesheet creation skipped: ' . $tsErr->getMessage());
            }

            // Update job paper workflow fields (best effort — columns may not exist yet)
            try {
                $job->signature_method = 'paper';
                $job->timesheet_status = 'signed_uploaded';
                $job->timesheet_document_id = $documentModel->id;
                $job->timesheet_uploaded_by = $user->id;
                $job->timesheet_uploaded_at = now();
                $job->save();
            } catch (\Throwable $jobErr) {
                \Illuminate\Support\Facades\Log::warning('Job timesheet fields update skipped: ' . $jobErr->getMessage());
            }

            // Send admin notification email (best effort)
            try {
                $to = config('app.to.address') ?: 'bookings@atozinterpreting.com';
                if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    \Illuminate\Support\Facades\Mail::to($to)->send(new \App\Mail\JobMail(
                        $job,
                        'emails.admin.job-timesheet-sign',
                        'Timesheet uploaded: pending verification',
                        'admin'
                    ));
                }
            } catch (\Throwable $mailErr) {
                \Illuminate\Support\Facades\Log::warning('Admin timesheet upload email failed: ' . $mailErr->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Signed timesheet uploaded successfully',
                'data' => [
                    'name' => $documentName,
                    'url' => optional($documentModel)->fullUrl ?: $documentUrl,
                    'file_name' => $documentName,
                    'file_path' => optional($documentModel)->fullUrl ?: $documentUrl,
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.uploadTimesheet ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . substr((string) $e->getMessage(), 0, 100)
            ], 500);
        }
    }

    /**
     * Submit a quote for an interpreter job (mirrors web InterpreterJobQuoteController@store)
     */
    public function submitQuote(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json(['success' => false, 'message' => 'User is not an agent'], 403);
            }

            $job = InterpreterJob::with('client')->find($id);

            if (!$job) {
                return response()->json(['success' => false, 'message' => 'Job not found'], 404);
            }

            $matched = $job->matchedAgents()->where('agent_id', $agent->id)->first();

            if (!$matched) {
                return response()->json(['success' => false, 'message' => 'You are not matched to this job'], 404);
            }

            if (in_array($matched->status, ['cancelled', 'rejected'])) {
                return response()->json(['success' => false, 'message' => 'Job not available'], 404);
            }

            $validated = $request->validate([
                'interpreting_cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'travel_time' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'travel_cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'mileage_miles' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'mileage_cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'cost_description' => ['required', 'string'],
                'cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            ]);

            $normalized = $validated;
            foreach (['interpreting_cost', 'travel_time', 'travel_cost', 'mileage_miles', 'mileage_cost', 'cost'] as $key) {
                $normalized[$key] = round((float) $normalized[$key], 2);
            }

            // Update matched status to 'quoted' (2)
            $matched->update(['status' => 2]);

            // Create quote on matched agent
            $quote = $matched->quotes()->create($normalized);

            return response()->json([
                'success' => true,
                'message' => 'Quote submitted successfully',
                'data' => [
                    'id' => $quote->id,
                    'interpreting_cost' => $quote->interpreting_cost,
                    'travel_time' => $quote->travel_time,
                    'travel_cost' => $quote->travel_cost,
                    'mileage_miles' => $quote->mileage_miles,
                    'mileage_cost' => $quote->mileage_cost,
                    'cost_description' => $quote->cost_description,
                    'cost' => $quote->cost,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.submitQuote ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json(['success' => false, 'message' => 'Unable to submit quote'], 503);
        }
    }

    /**
     * Update an existing quote for an interpreter job (mirrors web InterpreterJobQuoteController@update)
     */
    public function updateQuote(Request $request, $id, $quoteId)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json(['success' => false, 'message' => 'User is not an agent'], 403);
            }

            $job = InterpreterJob::find($id);

            if (!$job) {
                return response()->json(['success' => false, 'message' => 'Job not found'], 404);
            }

            $matched = $job->matchedAgents()->where('agent_id', $agent->id)->first();

            if (!$matched) {
                return response()->json(['success' => false, 'message' => 'You are not matched to this job'], 404);
            }

            $quote = $matched->quotes()->where('id', $quoteId)->firstOrFail();

            // Only quotes with status "quote sent" (statusId = 0) can be edited
            if (property_exists($quote, 'statusId') && $quote->statusId !== 0) {
                return response()->json(['success' => false, 'message' => 'Only quotes with status "quote sent" can be edited'], 422);
            }

            $validated = $request->validate([
                'interpreting_cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'travel_time' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'travel_cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'mileage_miles' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'mileage_cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
                'cost_description' => ['required', 'string'],
                'cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            ]);

            $normalized = $validated;
            foreach (['interpreting_cost', 'travel_time', 'travel_cost', 'mileage_miles', 'mileage_cost', 'cost'] as $key) {
                $normalized[$key] = round((float) $normalized[$key], 2);
            }

            $quote->update($normalized);

            return response()->json([
                'success' => true,
                'message' => 'Quote updated successfully',
                'data' => [
                    'id' => $quote->id,
                    'interpreting_cost' => $quote->interpreting_cost,
                    'travel_time' => $quote->travel_time,
                    'travel_cost' => $quote->travel_cost,
                    'mileage_miles' => $quote->mileage_miles,
                    'mileage_cost' => $quote->mileage_cost,
                    'cost_description' => $quote->cost_description,
                    'cost' => $quote->cost,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.updateQuote ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json(['success' => false, 'message' => 'Unable to update quote'], 503);
        }
    }

    /**
     * Get the agent's quotes for an interpreter job
     */
    public function getQuotes(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json(['success' => false, 'message' => 'User is not an agent'], 403);
            }

            $job = InterpreterJob::find($id);

            if (!$job) {
                return response()->json(['success' => false, 'message' => 'Job not found'], 404);
            }

            $matched = $job->matchedAgents()->where('agent_id', $agent->id)->first();
            $quotes = [];

            if ($matched) {
                $quotes = $matched->quotes()->latest()->get()->map(function ($q) {
                    return [
                        'id' => $q->id,
                        'interpreting_cost' => $q->interpreting_cost,
                        'travel_time' => $q->travel_time,
                        'travel_cost' => $q->travel_cost,
                        'mileage_miles' => $q->mileage_miles,
                        'mileage_cost' => $q->mileage_cost,
                        'cost_description' => $q->cost_description,
                        'cost' => $q->cost,
                        'status' => $q->status ?? null,
                        'total_amount' => (function () use ($q) {
                            try {
                                return $q->totalAmount; } catch (\Throwable $e) {
                                return null; }
                        })(),
                        'created_at' => $q->created_at ? $q->created_at->toIso8601String() : null,
                    ];
                });
            }

            return response()->json([
                'success' => true,
                'data' => $quotes
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.getQuotes ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json(['success' => false, 'message' => 'Unable to fetch quotes'], 503);
        }
    }

    /**
     * Submit or update travel expenses for an interpreter job
     */
    public function submitExpenses(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json(['success' => false, 'message' => 'User is not an agent'], 403);
            }

            $job = InterpreterJob::find($id);
            if (!$job) {
                return response()->json(['success' => false, 'message' => 'Job not found'], 404);
            }

            $request->validate([
                'base_amount' => 'required|numeric|min:0',
                'expenses_details' => 'nullable|array',
                'expenses_details.*.type' => 'nullable|string|max:100',
                'expenses_details.*.value' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string|max:500',
            ]);

            $baseAmount = floatval($request->input('base_amount', 0));
            $details = $request->input('expenses_details', []);
            $computedExpenses = 0.0;
            $normalizedDetails = [];

            if (is_array($details)) {
                foreach ($details as $item) {
                    $type = isset($item['type']) ? trim($item['type']) : null;
                    $value = isset($item['value']) ? floatval($item['value']) : 0.0;
                    if ($value > 0) {
                        $computedExpenses += $value;
                        $normalizedDetails[] = ['type' => $type, 'amount' => number_format($value, 2, '.', '')];
                    }
                }
            }

            $totalAmount = $baseAmount + $computedExpenses;

            $entry = \App\AgentJobEntry::updateOrCreate(
                [
                    'agent_id' => $agent->id,
                    'job_type' => 'App\\InterpreterJob',
                    'job_id' => $id,
                ],
                [
                    'base_amount' => $baseAmount,
                    'expenses' => $computedExpenses,
                    'surcharge' => 0,
                    'other_charges' => 0,
                    'total_amount' => $totalAmount,
                    'notes' => $request->input('notes'),
                    'invoiced' => false,
                ]
            );

            // Sync expense detail rows
            if (!empty($normalizedDetails)) {
                \App\AgentJobEntryExpense::where('agent_job_entry_id', $entry->id)->delete();
                foreach ($normalizedDetails as $detail) {
                    \App\AgentJobEntryExpense::create([
                        'agent_job_entry_id' => $entry->id,
                        'type' => $detail['type'],
                        'amount' => $detail['amount'],
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Expenses saved successfully',
                'data' => [
                    'id' => $entry->id,
                    'base_amount' => $entry->base_amount,
                    'expenses' => $entry->expenses,
                    'total_amount' => $entry->total_amount,
                    'notes' => $entry->notes,
                    'details' => $normalizedDetails,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.submitExpenses ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json(['success' => false, 'message' => 'Unable to save expenses'], 503);
        }
    }

    /**
     * Get the agent's expense entries for an interpreter job
     */
    public function getExpenses(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json(['success' => false, 'message' => 'User is not an agent'], 403);
            }

            $entry = \App\AgentJobEntry::where('agent_id', $agent->id)
                ->where('job_type', 'App\\InterpreterJob')
                ->where('job_id', $id)
                ->first();

            if (!$entry) {
                return response()->json(['success' => true, 'data' => null]);
            }

            $details = \App\AgentJobEntryExpense::where('agent_job_entry_id', $entry->id)->get()->map(function ($d) {
                return ['type' => $d->type, 'amount' => $d->amount];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $entry->id,
                    'base_amount' => $entry->base_amount,
                    'expenses' => $entry->expenses,
                    'surcharge' => $entry->surcharge,
                    'other_charges' => $entry->other_charges,
                    'total_amount' => $entry->total_amount,
                    'notes' => $entry->notes,
                    'details' => $details,
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR interpreter-jobs.getExpenses ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json(['success' => false, 'message' => 'Unable to fetch expenses'], 503);
        }
    }
}
