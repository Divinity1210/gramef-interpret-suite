<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\TranslatorJob;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use App\Mail\JobMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TranslatorJobController extends Controller
{
    /**
     * Get list of translator jobs for the authenticated agent
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
            $jobs = TranslatorJob::query()
                ->with(['toLanguage', 'fromLanguage', 'client.user', 'agent.user', 'skill'])
                ->where(function ($q) use ($agent) {
                    $q->where('agent_id', $agent->id)
                        ->orWhere(function ($q) use ($agent) {
                            $q->whereNull('agent_id')
                                ->whereHas('matchedAgents', function ($mq) use ($agent) {
                                    $mq->where('agent_id', $agent->id)
                                        ->whereNotIn('status', [1, 3]); // exclude rejected(1), cancelled(3)
                                });
                        });
                })
                ->orderBy('target_date', 'desc')
                ->paginate($perPage > 0 ? $perPage : 20);

            // Add helpful data for the mobile app
            $jobs->getCollection()->transform(function ($job) {
                return [
                    'id' => $job->id,
                    'status' => $job->status,
                    'status_name' => $job->status_name,
                    'target_date' => $job->target_date ? $job->target_date->format('Y-m-d') : null,
                    'word_count' => $job->word_count,
                    'client_reference' => $job->client_reference,
                    'source_type' => $job->source_type,
                    'skill' => $job->skill ? ['id' => $job->skill->id, 'name' => $job->skill->name] : null,
                    'to_language' => $job->toLanguage ? ['id' => $job->toLanguage->id, 'name' => $job->toLanguage->name] : null,
                    'from_language' => $job->fromLanguage ? ['id' => $job->fromLanguage->id, 'name' => $job->fromLanguage->name] : null,
                    'client' => $job->client && $job->client->user ? ['id' => $job->client->id, 'name' => $job->client->user->fullName] : null,
                    'can_accept' => (int) ($job->agent_id ?? 0) === 0 && in_array((int) $job->status, [0, 5], true),
                    'can_complete' => $job->canBeCompleted(),
                ];
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
            error_log('MOBILE_API_ERROR translator-jobs.index QueryException sqlstate=' . $sqlState . ' code=' . $driverCode . ' msg=' . $driverMessage);

            return response()->json([
                'success' => false,
                'message' => 'Jobs are unavailable'
            ], 503);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR translator-jobs.index ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Jobs are unavailable'
            ], 503);
        }

    }

    /**
     * Get a specific translator job
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

            $job = TranslatorJob::with([
                'toLanguage',
                'fromLanguage',
                'client.user',
                'agent.user',
                'documents',
            ])->find($id);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found'
                ], 404);
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
                $requiresQuote = (bool) data_get($job, 'client.always_requires_a_quote', false);
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
                'status' => $job->status,
                'status_name' => $job->status_name,
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
                            'cost_description' => $quote->cost_description,
                            'cost' => $quote->cost,
                            'status' => $quote->status ?? null,
                        ];
                    } catch (\Throwable $e) {
                        return null; }
                })(),
                'target_date' => $job->target_date ? $job->target_date->format('Y-m-d') : null,
                'word_count' => $job->word_count,
                'client_reference' => $job->client_reference,
                'to_language' => $job->toLanguage ? ['id' => $job->toLanguage->id, 'name' => $job->toLanguage->name] : null,
                'from_language' => $job->fromLanguage ? ['id' => $job->fromLanguage->id, 'name' => $job->fromLanguage->name] : null,
                'client' => $job->client && $job->client->user ? ['id' => $job->client->id, 'name' => $job->client->user->fullName] : null,
                'agent' => $job->agent && $job->agent->user ? ['id' => $job->agent->id, 'name' => $job->agent->user->fullName] : null,
                'can_complete' => $job->canBeCompleted(),
                'documents' => $job->documents->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'name' => $doc->name,
                        'type' => $doc->type,
                        'url' => $doc->url,
                        'full_url' => $doc->fullUrl,
                    ];
                }),
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
            error_log('MOBILE_API_ERROR translator-jobs.show QueryException sqlstate=' . $sqlState . ' code=' . $driverCode . ' msg=' . $driverMessage);

            return response()->json([
                'success' => false,
                'message' => 'Job details are unavailable'
            ], 503);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR translator-jobs.show ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Job details are unavailable'
            ], 503);
        }
    }

    /**
     * Accept a translator job
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

            $job = TranslatorJob::with('matchedAgents')->find($id);

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

            if (in_array($matched->status, ['cancelled', 'rejected'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            if ((int) $job->status !== 0 && (int) $job->status !== 5) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job cannot be accepted in its current state'
                ], 400);
            }

            // Guard: if client requires a quote, block direct accept
            $clientRequiresQuote = false;
            try {
                $clientRequiresQuote = (bool) data_get($job, 'client.always_requires_a_quote', false);
            } catch (\Throwable $e) {
            }
            if ($clientRequiresQuote) {
                return response()->json([
                    'success' => false,
                    'message' => 'This client requires a quote before assignment. Please submit a quote instead.'
                ], 400);
            }

            $matched->update(['status' => 4]);
            $job->update([
                'agent_id' => $agent->id,
                'status' => 1,
            ]);

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
            error_log('MOBILE_API_ERROR translator-jobs.accept ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Unable to accept job'
            ], 503);
        }
    }

    /**
     * Decline a translator job
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

            $job = TranslatorJob::with('matchedAgents')->find($id);

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

            $matched->update([
                'status' => 3, // rejected
                'decline_reason' => $request->input('reason', ''),
            ]);

            // If this agent was the assigned agent, reset the job
            // so it goes back to open/unassigned and notify admin
            if ((int) $job->agent_id === (int) $agent->id) {
                $job->update([
                    'agent_id' => null,
                    'status' => 0, // open / unassigned
                ]);

                // Re-dispatch matching so other agents can see it
                try {
                    \App\Jobs\MatchAgents::dispatch($job);
                } catch (\Throwable $e) {
                    error_log('MatchAgents dispatch failed after translator decline: ' . $e->getMessage());
                }

                try {
                    $adminEmail = config('app.to.address') ?: 'bookings@atozinterpreting.com';
                    $agentName = optional($agent->user)->getFullName()
                        ?: optional($agent->user)->full_name
                        ?: 'a translator';

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
                    Log::error('Failed to send translator unassigned email to admin from mobile: ' . $e->getMessage());
                }
            }

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
            error_log('MOBILE_API_ERROR translator-jobs.decline ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Unable to decline job'
            ], 503);
        }
    }

    /**
     * Submit a quote for a translator job (mirrors web TranslatorJobQuoteController@store)
     * Translator quotes only need cost_description + cost (simpler than interpreter quotes)
     */
    public function submitQuote(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json(['success' => false, 'message' => 'User is not an agent'], 403);
            }

            $job = TranslatorJob::with('client')->find($id);

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
                'cost_description' => ['required', 'string'],
                'cost' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            ]);

            $validated['cost'] = round((float) $validated['cost'], 2);

            // Update matched status to 'quoted' (2)
            $matched->update(['status' => 2]);

            // Create quote on matched agent
            $quote = $matched->quotes()->create($validated);

            // Send notification email
            try {
                \Illuminate\Support\Facades\Mail::to(config('app.to.address'))->send(new \App\Mail\QuoteMail(
                    $job,
                    $quote,
                    'emails.translator-jobs.quote',
                    'Job Quoted',
                    'admin'
                ));
            } catch (\Throwable $mailErr) {
                // Don't fail the request if email fails
            }

            return response()->json([
                'success' => true,
                'message' => 'Quote submitted successfully',
                'data' => [
                    'id' => $quote->id,
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
            error_log('MOBILE_API_ERROR translator-jobs.submitQuote ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json(['success' => false, 'message' => 'Unable to submit quote'], 503);
        }
    }

    /**
     * Get the agent's quotes for a translator job
     */
    public function getQuotes(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = $user->agent;

            if (!$agent) {
                return response()->json(['success' => false, 'message' => 'User is not an agent'], 403);
            }

            $job = TranslatorJob::find($id);

            if (!$job) {
                return response()->json(['success' => false, 'message' => 'Job not found'], 404);
            }

            $matched = $job->matchedAgents()->where('agent_id', $agent->id)->first();
            $quotes = [];

            if ($matched) {
                $quotes = $matched->quotes()->latest()->get()->map(function ($q) {
                    return [
                        'id' => $q->id,
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
            error_log('MOBILE_API_ERROR translator-jobs.getQuotes ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json(['success' => false, 'message' => 'Unable to fetch quotes'], 503);
        }
    }
}
