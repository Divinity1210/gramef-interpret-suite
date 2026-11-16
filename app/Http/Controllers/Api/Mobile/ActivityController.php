<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\AllUpdates;
use App\InterpreterJob;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ActivityController extends Controller
{
    /**
     * Return recent activities for the authenticated agent.
     *
     * Activities are derived from job status changes recorded in
     * the `allupdates` table, ordered by most recent first.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user || !$user->agent) {
                return response()->json(['success' => false, 'message' => 'Agent profile not found'], 404);
            }

            $agentId = $user->agent->id;
            $perPage = min((int) $request->get('per_page', 20), 50);

            // Fetch updates from the allupdates table for this agent
            $updates = AllUpdates::where('agent_id', $agentId)
                ->where(function ($q) {
                    $q->where('deleted', 'N')->orWhereNull('deleted');
                })
                ->orderByDesc('created_at')
                ->paginate($perPage);

            $items = collect();
            foreach ($updates->items() as $update) {
                $jobId = $update->job_id;
                $jobType = $update->job_type;
                $code = strtolower($update->code ?? $update->action ?? '');
                $description = $update->comment ?? $update->notes ?? '';
                $createdAt = optional($update->created_at)->toIso8601String();

                // Determine icon and category based on the code
                $icon = 'information-circle';
                $iconColor = '#E7F1FF';
                $iconColorText = '#0D6EFD';
                $category = 'info';

                if (str_contains($code, 'assign') || str_contains($code, 'accept')) {
                    $icon = 'calendar';
                    $iconColor = '#E7F1FF';
                    $iconColorText = '#0D6EFD';
                    $category = 'assigned';
                } elseif (str_contains($code, 'complet')) {
                    $icon = 'checkmark-circle';
                    $iconColor = '#D4EDDA';
                    $iconColorText = '#28A745';
                    $category = 'completed';
                } elseif (str_contains($code, 'cancel')) {
                    $icon = 'close-circle';
                    $iconColor = '#F8D7DA';
                    $iconColorText = '#DC3545';
                    $category = 'cancelled';
                } elseif (str_contains($code, 'dna')) {
                    $icon = 'alert-circle';
                    $iconColor = '#FFF3CD';
                    $iconColorText = '#856404';
                    $category = 'dna';
                } elseif (str_contains($code, 'return') || str_contains($code, 'retrn')) {
                    $icon = 'arrow-undo';
                    $iconColor = '#FFF3CD';
                    $iconColorText = '#856404';
                    $category = 'returned';
                } elseif (str_contains($code, 'timesheet') || str_contains($code, 'sign')) {
                    $icon = 'document-text';
                    $iconColor = '#E7F1FF';
                    $iconColorText = '#0D6EFD';
                    $category = 'timesheet';
                } elseif (str_contains($code, 'quote')) {
                    $icon = 'cash';
                    $iconColor = '#D4EDDA';
                    $iconColorText = '#28A745';
                    $category = 'quote';
                } elseif (str_contains($code, 'match') || str_contains($code, 'pending')) {
                    $icon = 'notifications';
                    $iconColor = '#FFF3CD';
                    $iconColorText = '#856404';
                    $category = 'matched';
                }

                // Build a human-readable activity text
                $text = $description;
                if (empty($text)) {
                    $text = ucfirst(str_replace('_', ' ', $code)) . ' - Job #' . $jobId;
                }

                // Try to get the language name for richer context
                $languageName = null;
                if ($jobType === 'interpreter-jobs' && $jobId) {
                    try {
                        $job = InterpreterJob::find($jobId, ['id', 'to_language_id']);
                        if ($job && $job->to_language_id) {
                            $lang = \App\Language::find($job->to_language_id, ['id', 'name']);
                            $languageName = $lang ? $lang->name : null;
                        }
                    } catch (\Throwable $e) {
                        // Language lookup failed, skip
                    }
                }

                $items->push([
                    'id' => $update->id,
                    'text' => $text,
                    'job_id' => $jobId,
                    'job_type' => $jobType,
                    'code' => $update->code ?? $update->action ?? null,
                    'category' => $category,
                    'icon' => $icon,
                    'icon_color' => $iconColor,
                    'icon_color_text' => $iconColorText,
                    'language' => $languageName,
                    'created_at' => $createdAt,
                    'time_ago' => $update->created_at
                        ? Carbon::parse($update->created_at)->diffForHumans()
                        : null,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'items' => $items->values(),
                    'current_page' => $updates->currentPage(),
                    'last_page' => $updates->lastPage(),
                    'total' => $updates->total(),
                    'per_page' => $updates->perPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR activities.index ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to load activities',
            ], 503);
        }
    }
}
