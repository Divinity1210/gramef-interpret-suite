<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Document;
use App\Http\Controllers\Controller;
use App\InterpreterJob;
use App\TranslatorJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Map numeric document types to human-readable labels (mirrors config/enums.php document_types)
     */
    private static $typeLabels = [
        0 => 'File to be Translated',
        1 => 'DBS Certificate',
        2 => 'Proof of Address',
        3 => 'Code of Conduct',
        4 => 'Interpreter Contract',
        5 => 'References',
        6 => 'Passport',
        7 => 'Affirmation',
        8 => 'Affidavit',
        9 => 'Translated File',
        10 => 'Induction',
    ];

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
            $interpreterJobIds = InterpreterJob::query()
                ->where(function ($q) use ($agent) {
                    $q->where('agent_id', $agent->id)
                        ->orWhere(function ($q) use ($agent) {
                            $q->whereNull('agent_id')
                                ->whereHas('matchedAgents', function ($mq) use ($agent) {
                                    $mq->where('agent_id', $agent->id)
                                        ->whereIn('status', [0, 2]);
                                });
                        });
                })
                ->pluck('id');

            $translatorJobIds = TranslatorJob::query()
                ->where(function ($q) use ($agent) {
                    $q->where('agent_id', $agent->id)
                        ->orWhere(function ($q) use ($agent) {
                            $q->whereNull('agent_id')
                                ->whereHas('matchedAgents', function ($mq) use ($agent) {
                                    $mq->where('agent_id', $agent->id)
                                        ->whereIn('status', [0, 2]);
                                });
                        });
                })
                ->pluck('id');

            $docs = Document::query()
                ->where(function ($q) use ($interpreterJobIds, $translatorJobIds, $agent) {
                    $q->where(function ($q) use ($interpreterJobIds) {
                        $q->where('documentable_type', InterpreterJob::class)
                            ->whereIn('documentable_id', $interpreterJobIds);
                    })->orWhere(function ($q) use ($translatorJobIds) {
                        $q->where('documentable_type', TranslatorJob::class)
                            ->whereIn('documentable_id', $translatorJobIds);
                    })->orWhere(function ($q) use ($agent) {
                        $q->where('documentable_type', \App\Agent::class)
                            ->where('documentable_id', $agent->id);
                    });
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage > 0 ? $perPage : 20);

            $docs->getCollection()->transform(function ($doc) {
                $documentable = $doc->documentable;
                $jobData = null;
                $category = 'other';

                // Check for agent profile documents FIRST
                // (Agent is a valid model, so it's truthy — must check before job branch)
                if ($doc->documentable_type === \App\Agent::class) {
                    $category = 'agent-profile';
                } elseif ($documentable) {
                    $isInterpreter = class_basename($documentable) === 'InterpreterJob';
                    $category = $isInterpreter ? 'job-interpreter' : 'job-translator';

                    $jobReference = null;
                    $jobLanguage = null;
                    $jobDate = null;

                    try {
                        $jobReference = $documentable->client_reference ?: ('Job #' . $documentable->id);
                        $jobDate = $isInterpreter
                            ? ($documentable->appointment_date ? $documentable->appointment_date->format('Y-m-d') : null)
                            : ($documentable->target_date ? $documentable->target_date->format('Y-m-d') : null);
                        if (method_exists($documentable, 'toLanguage') && $documentable->toLanguage) {
                            $jobLanguage = $documentable->toLanguage->name;
                        }
                    } catch (\Throwable $e) {
                        // Graceful fallback
                    }

                    $jobData = [
                        'id' => $documentable->id,
                        'type' => $isInterpreter ? 'interpreter' : 'translator',
                        'reference' => $jobReference,
                        'language' => $jobLanguage,
                        'appointment_date' => $jobDate,
                    ];
                }

                return [
                    'id' => $doc->id,
                    'name' => $doc->name,
                    'type' => $doc->type,
                    'type_label' => self::$typeLabels[$doc->type] ?? null,
                    'category' => $category,
                    'url' => $doc->url,
                    'full_url' => $doc->fullUrl,
                    'created_at' => $doc->created_at ? $doc->created_at->toIso8601String() : null,
                    'job' => $jobData,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $docs
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR documents.index ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Documents are unavailable'
            ], 503);
        }
    }

    /**
     * Upload a profile document (mirrors web AgentDocumentController@update)
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $agent = $user->agent ?? null;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'User is not an agent'
            ], 403);
        }

        if (!$request->hasFile('file')) {
            return response()->json([
                'success' => false,
                'message' => 'No file provided'
            ], 422);
        }

        $file = $request->file('file');
        $documentType = (int) $request->input('document_type', 0);

        try {
            // Delete existing document of this type if exists (updateOrCreate style)
            $existing = $agent->documents()->where('type', $documentType)->first();
            if ($existing && $existing->url) {
                Storage::disk('public')->delete($existing->url);
            }

            // Store the file under agents/ on the public disk
            $path = $file->storeAs(
                'agents',
                $file->getMimeType() === 'application/octet-stream'
                ? $file->hashName() . '.pdf'
                : $file->hashName(),
                'public'
            );

            // Create or update the document record
            $doc = $agent->documents()->updateOrCreate(
                ['type' => $documentType],
                [
                    'name' => $file->getClientOriginalName(),
                    'url' => $path,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'id' => $doc->id,
                    'name' => $doc->name,
                    'type' => $doc->type,
                    'type_label' => self::$typeLabels[$doc->type] ?? null,
                    'category' => 'agent-profile',
                    'url' => $doc->url,
                    'full_url' => $doc->fullUrl,
                    'created_at' => $doc->created_at ? $doc->created_at->toIso8601String() : null,
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR documents.store ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . substr((string) $e->getMessage(), 0, 100)
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $doc = $this->findAuthorizedDocument($request, $id);
            if (!$doc) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found'
                ], 404);
            }

            $job = $doc->documentable;
            $jobData = null;
            if ($job) {
                $jobData = [
                    'id' => $job->id,
                    'type' => class_basename($job) === 'InterpreterJob' ? 'interpreter' : 'translator',
                    'appointment_date' => class_basename($job) === 'InterpreterJob' ? ($job->appointment_date ? $job->appointment_date->format('Y-m-d') : null) : ($job->target_date ? $job->target_date->format('Y-m-d') : null),
                ];
            }

            $data = [
                'id' => $doc->id,
                'name' => $doc->name,
                'type' => $doc->type,
                'url' => $doc->url,
                'full_url' => $doc->fullUrl,
                'created_at' => $doc->created_at ? $doc->created_at->toIso8601String() : null,
                'job' => $jobData,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR documents.show ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Document is unavailable'
            ], 503);
        }
    }

    public function download(Request $request, $id)
    {
        try {
            $doc = $this->findAuthorizedDocument($request, $id);
            if (!$doc) {
                return response()->json([
                    'message' => 'Document not found'
                ], 404);
            }

            if ($doc->url) {
                if (Storage::disk('public')->exists($doc->url)) {
                    return Storage::disk('public')->download($doc->url, $doc->name);
                }

                // Fallback to redirect if streaming fails or file not found in public disk
                return redirect()->away($doc->fullUrl);
            }

            return response()->json([
                'message' => 'Document has no file'
            ], 404);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR documents.download ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Download is unavailable'
            ], 503);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $doc = $this->findAuthorizedDocument($request, $id);
            if (!$doc) {
                return response()->json([
                    'message' => 'Document not found'
                ], 404);
            }

            if ($doc->url) {
                Storage::disk('public')->delete($doc->url);
            }

            $doc->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted'
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR documents.destroy ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Delete failed'
            ], 503);
        }
    }

    private function findAuthorizedDocument(Request $request, $id)
    {
        $user = $request->user();
        $agent = $user ? $user->agent : null;
        if (!$agent) {
            return null;
        }

        $doc = Document::find($id);
        if (!$doc) {
            return null;
        }

        $documentable = $doc->documentable;

        if ($documentable instanceof InterpreterJob) {
            if ((int) $documentable->agent_id === (int) $agent->id) {
                return $doc;
            }
            $matched = $documentable->matchedAgents()->where('agent_id', $agent->id)->first();
            if ($matched) {
                return $doc;
            }
            return null;
        }

        if ($documentable instanceof TranslatorJob) {
            if ((int) $documentable->agent_id === (int) $agent->id) {
                return $doc;
            }
            $matched = $documentable->matchedAgents()->where('agent_id', $agent->id)->first();
            if ($matched) {
                return $doc;
            }
            return null;
        }

        // Allow agent to access their own profile documents
        if ($documentable instanceof \App\Agent) {
            if ((int) $documentable->id === (int) $agent->id) {
                return $doc;
            }
            return null;
        }

        return null;
    }
}
