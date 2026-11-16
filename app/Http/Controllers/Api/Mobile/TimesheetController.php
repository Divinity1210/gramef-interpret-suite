<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Timesheet;
use App\InterpreterJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Mpdf\Mpdf;
use App\Mail\JobMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TimesheetController extends Controller
{
    private function canViewAllTimesheets($user): bool
    {
        try {
            if (!$user) {
                return false;
            }
            if (!property_exists($user, 'roles') && !method_exists($user, 'roles')) {
                return false;
            }
            $roles = $user->roles;
            if (!$roles) {
                return false;
            }

            return $roles->contains('name', 'admin')
                || $roles->contains('name', 'super-admin')
                || $roles->contains('name', 'office')
                || $roles->contains('name', 'manager');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function applyTimesheetFilters(Request $request, $query)
    {
        $status = $request->get('status');
        if (is_string($status) && $status !== '') {
            // Map human-readable status names to DB field queries
            switch (strtolower($status)) {
                case 'pending':
                    // Timesheets where agent hasn't signed yet
                    $query->where('agent_status', '!=', 'signed');
                    break;
                case 'signed':
                case 'submitted':
                    // Agent signed but client hasn't
                    $query->where('agent_status', 'signed')
                        ->where('client_status', '!=', 'signed');
                    break;
                case 'completed':
                case 'approved':
                    // Both agent and client have signed
                    $query->where('agent_status', 'signed')
                        ->where('client_status', 'signed');
                    break;
                default:
                    // Fall back to raw status field query
                    $query->where('status', $status);
                    break;
            }
        } elseif (is_array($status) && count($status) > 0) {
            $query->whereIn('status', $status);
        }

        $fromDate = trim((string) $request->get('from_date', ''));
        $toDate = trim((string) $request->get('to_date', ''));

        if ($fromDate !== '' || $toDate !== '') {
            $query->whereHas('interpreterJob', function ($q) use ($fromDate, $toDate) {
                if ($fromDate !== '') {
                    $q->whereDate('appointment_date', '>=', $fromDate);
                }
                if ($toDate !== '') {
                    $q->whereDate('appointment_date', '<=', $toDate);
                }
            });
        }

        $project = trim((string) $request->get('project', ''));
        if ($project !== '') {
            $query->whereHas('interpreterJob', function ($q) use ($project) {
                $q->where('client_reference', 'like', '%' . $project . '%')
                    ->orWhere('id', 'like', '%' . $project . '%');
            });
        }

        $ref = trim((string) $request->get('ref', ''));
        if ($ref !== '') {
            $query->filterByRef($ref);
        }

        $employeeName = trim((string) $request->get('employee_name', ''));
        if ($employeeName !== '') {
            $query->filterByAgentName($employeeName);
        }

        $clientName = trim((string) $request->get('client_name', ''));
        if ($clientName !== '') {
            $query->filterByClientName($clientName);
        }

        $search = trim((string) $request->get('q', ''));
        if ($search !== '') {
            $query->where(function ($outer) use ($search) {
                $outer->where('status', 'like', '%' . $search . '%')
                    ->orWhere('client_name', 'like', '%' . $search . '%')
                    ->orWhere('client_phone', 'like', '%' . $search . '%')
                    ->orWhere('client_designation', 'like', '%' . $search . '%')
                    ->orWhereHas('agentOne.user', function ($q) use ($search) {
                        $q->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('interpreterJob', function ($q) use ($search) {
                        $q->where('id', 'like', '%' . $search . '%')
                            ->orWhere('client_reference', 'like', '%' . $search . '%')
                            ->orWhereHas('to_language', function ($l) use ($search) {
                                $l->where('name', 'like', '%' . $search . '%');
                            });
                    });
            });
        }

        return $query;
    }

    /**
     * Get list of timesheets for the authenticated agent
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $agent = $user->agent;

        $canViewAll = false;
        if (!$agent) {
            $canViewAll = $this->canViewAllTimesheets($user);
            if (!$canViewAll) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden'
                ], 403);
            }
        }

        $perPage = (int) $request->get('per_page', 20);
        $employee = $request->get('employee');

        try {
            $query = Timesheet::query()
                ->with(['interpreterJob.to_language', 'agentOne.user'])
                ->orderBy('created_at', 'desc');

            if ($agent) {
                $query->where('agent_id', $agent->id);
            } elseif ($employee !== null && $employee !== '') {
                $query->where('agent_id', (int) $employee);
            }

            $this->applyTimesheetFilters($request, $query);

            $timesheets = $query->paginate($perPage > 0 ? $perPage : 20);

            $timesheets->getCollection()->transform(function ($t) {
                $date = null;
                $project = null;
                $task = null;
                if ($t->interpreterJob) {
                    $date = $t->interpreterJob->appointment_date ? $t->interpreterJob->appointment_date->format('Y-m-d') : null;
                    $project = $t->interpreterJob->client_reference ?: ('Job #' . $t->interpreterJob->id);
                    $task = $t->interpreterJob->to_language ? $t->interpreterJob->to_language->name : null;
                }

                $hoursFloat = 0;
                try {
                    $hoursFloat = ((int) $t->agent_duration_hours) + (((int) $t->agent_duration_minutes) / 60);
                } catch (\Throwable $e) {
                    $hoursFloat = 0;
                }

                // Compute a human-readable display status
                $displayStatus = 'pending';
                if ($t->agent_status === 'signed' && $t->client_status === 'signed') {
                    $displayStatus = 'completed';
                } elseif ($t->agent_status === 'signed') {
                    $displayStatus = 'signed';
                }

                // Agent photo URL (best effort)
                $agentPhotoUrl = null;
                try {
                    if ($t->agentOne) {
                        $agentPhotoUrl = $t->agentOne->getProfilePicture();
                    }
                } catch (\Throwable $e) {}

                return [
                    'id' => $t->id,
                    'job_id' => $t->job_id,
                    'status' => $displayStatus,
                    'agent_status' => $t->agent_status,
                    'client_status' => $t->client_status,
                    'agent_start_time' => $t->agent_start_time,
                    'agent_end_time' => $t->agent_end_time,
                    'duration_hours' => $t->agent_duration_hours,
                    'duration_minutes' => $t->agent_duration_minutes,
                    'hours' => round($hoursFloat, 2),
                    'client_name' => $t->client_name,
                    'client_phone' => $t->client_phone,
                    'client_designation' => $t->client_designation,
                    'has_agent_signature' => !empty($t->agent_signature),
                    'has_client_signature' => !empty($t->client_signature),
                    'created_at' => $t->created_at ? $t->created_at->toIso8601String() : null,
                    'date' => $date,
                    'project' => $project,
                    'task' => $task,
                    'description' => $t->client_name,
                    'agent_photo_url' => $agentPhotoUrl,
                    'employee' => $t->agentOne && $t->agentOne->user ? [
                        'id' => $t->agentOne->user->id,
                        'name' => trim((string) ($t->agentOne->user->first_name . ' ' . $t->agentOne->user->last_name)),
                    ] : null,
                    'job' => $t->interpreterJob ? [
                        'id' => $t->interpreterJob->id,
                        'appointment_date' => $t->interpreterJob->appointment_date ? $t->interpreterJob->appointment_date->format('Y-m-d') : null,
                        'client_reference' => $t->interpreterJob->client_reference,
                        'status' => $t->interpreterJob->status,
                        'status_name' => $t->interpreterJob->status_name,
                        'to_language' => $t->interpreterJob->to_language ? ['id' => $t->interpreterJob->to_language->id, 'name' => $t->interpreterJob->to_language->name] : null,
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $timesheets
            ]);
        } catch (QueryException $e) {
            $info = property_exists($e, 'errorInfo') ? $e->errorInfo : null;
            $sqlState = is_array($info) && isset($info[0]) ? (string) $info[0] : '';
            $driverCode = is_array($info) && isset($info[1]) ? (string) $info[1] : '';
            $driverMessage = is_array($info) && isset($info[2]) ? (string) $info[2] : '';
            $driverMessage = $driverMessage ? substr($driverMessage, 0, 300) : '';
            error_log('MOBILE_API_ERROR timesheets.index QueryException sqlstate=' . $sqlState . ' code=' . $driverCode . ' msg=' . $driverMessage);

            return response()->json([
                'success' => false,
                'message' => 'Timesheets are unavailable'
            ], 503);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR timesheets.index ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Timesheets are unavailable'
            ], 503);
        }
    }

    public function export(Request $request)
    {
        $user = $request->user();
        $agent = $user->agent;

        $canViewAll = false;
        if (!$agent) {
            $canViewAll = $this->canViewAllTimesheets($user);
            if (!$canViewAll) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden'
                ], 403);
            }
        }

        $format = strtolower(trim((string) $request->get('format', 'csv')));
        if (!in_array($format, ['csv', 'pdf'], true)) {
            $format = 'csv';
        }

        $limit = (int) $request->get('limit', 1000);
        if ($limit <= 0) {
            $limit = 1000;
        }
        if ($limit > 5000) {
            $limit = 5000;
        }

        $employee = $request->get('employee');

        try {
            $query = Timesheet::query()
                ->with(['interpreterJob.to_language', 'agentOne.user'])
                ->orderBy('created_at', 'desc');

            if ($agent) {
                $query->where('agent_id', $agent->id);
            } elseif ($employee !== null && $employee !== '') {
                $query->where('agent_id', (int) $employee);
            }

            $this->applyTimesheetFilters($request, $query);

            $timesheets = $query->limit($limit)->get();
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR timesheets.export ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Export unavailable'
            ], 503);
        }

        $rows = $timesheets->map(function ($t) {
            $date = $t->interpreterJob && $t->interpreterJob->appointment_date ? $t->interpreterJob->appointment_date->format('Y-m-d') : '';
            $project = $t->interpreterJob ? ($t->interpreterJob->client_reference ?: ('Job #' . $t->interpreterJob->id)) : ('Job #' . $t->job_id);
            $task = $t->interpreterJob && $t->interpreterJob->to_language ? $t->interpreterJob->to_language->name : '';
            $hours = '';
            try {
                $hours = number_format(((int) $t->agent_duration_hours) + (((int) $t->agent_duration_minutes) / 60), 2, '.', '');
            } catch (\Throwable $e) {
                $hours = '0.00';
            }
            $description = (string) ($t->client_name ?: '');
            $status = (string) ($t->status ?: '');

            return [$date, $project, $task, $hours, $description, $status];
        })->values()->all();

        if ($format === 'pdf') {
            $title = 'Timesheets';
            $generatedAt = Carbon::now()->toDateTimeString();

            $html = '<html><head><meta charset="utf-8" /><style>'
                . 'body{font-family: sans-serif; font-size: 12px; color:#111;}'
                . 'h1{font-size: 16px; margin: 0 0 8px 0;}'
                . '.meta{font-size: 10px; color:#555; margin-bottom: 10px;}'
                . 'table{width:100%; border-collapse: collapse;}'
                . 'th,td{border:1px solid #ddd; padding:6px; vertical-align: top;}'
                . 'th{background:#f5f5f5;}'
                . '</style></head><body>'
                . '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
                . '<div class="meta">Generated: ' . htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') . '</div>'
                . '<table><thead><tr>'
                . '<th>Date</th><th>Project</th><th>Task</th><th>Hours</th><th>Description</th><th>Status</th>'
                . '</tr></thead><tbody>';

            foreach ($rows as $r) {
                $html .= '<tr>'
                    . '<td>' . htmlspecialchars((string) $r[0], ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars((string) $r[1], ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars((string) $r[2], ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars((string) $r[3], ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars((string) $r[4], ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars((string) $r[5], ENT_QUOTES, 'UTF-8') . '</td>'
                    . '</tr>';
            }

            $html .= '</tbody></table></body></html>';

            try {
                $mpdf = new Mpdf([
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'orientation' => 'P',
                    'margin_top' => 8,
                    'margin_left' => 8,
                    'margin_right' => 8,
                    'margin_bottom' => 8,
                ]);
                $mpdf->WriteHTML($html);
                $pdf = $mpdf->Output('', 'S');
            } catch (\Throwable $e) {
                error_log('MOBILE_API_ERROR timesheets.export.pdf ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
                return response()->json([
                    'success' => false,
                    'message' => 'Export unavailable'
                ], 503);
            }

            $filename = 'timesheets-' . date('Y-m-d') . '.pdf';

            return response($pdf, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }

        $filename = 'timesheets-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'project', 'task', 'hours', 'description', 'status']);
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(Request $request, $id)
    {
        // Support authentication via ?token= query param for browser-based PDF viewing
        $user = $request->user();
        if (!$user && $request->has('token')) {
            try {
                $token = $request->get('token');
                // Same auth mechanism as MobileTokenAuth middleware
                $hashedToken = hash('sha256', $token);
                $user = \App\User::where('remember_token', $hashedToken)->first();
            } catch (\Throwable $e) {
                // Fall through — no user
            }
        }
        // Also try Authorization header if present (for AJAX calls)
        if (!$user && $request->bearerToken()) {
            try {
                $hashedToken = hash('sha256', $request->bearerToken());
                $user = \App\User::where('remember_token', $hashedToken)->first();
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $agent = $user->agent;

        $canViewAll = false;
        if (!$agent) {
            $canViewAll = $this->canViewAllTimesheets($user);
            if (!$canViewAll) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden'
                ], 403);
            }
        }


        try {
            $query = Timesheet::with(['interpreter.client.userSheet', 'agentOne.user']);

            if ($agent) {
                $query->where('agent_id', $agent->id);
            }

            $timesheet = $query->where('id', $id)->firstOrFail();

            $html = view('timesheet/timesheet_pdf', compact('timesheet'))->render();
            $base = rtrim((string) public_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $html = str_replace('src="img/', 'src="' . $base . 'img/', $html);
            $html = str_replace("src='img/", "src='" . $base . "img/", $html);

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_top' => 5,
                'margin_left' => 5,
                'margin_right' => 5,
                'margin_bottom' => 5,
            ]);
            $mpdf->WriteHTML($html);
            $pdf = $mpdf->Output('', 'S');
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR timesheets.pdf ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'PDF unavailable'
            ], 503);
        }

        $filename = 'timesheet-' . (int) $id . '.pdf';

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Get a specific timesheet
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $agent = $user->agent;

        $canViewAll = false;
        if (!$agent) {
            $canViewAll = $this->canViewAllTimesheets($user);
            if (!$canViewAll) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden'
                ], 403);
            }
        }

        try {
            $query = Timesheet::with([
                'interpreterJob.to_language',
                'agentOne.user',
            ]);

            if ($agent) {
                $query->where('agent_id', $agent->id);
            }

            $timesheet = $query->where('id', $id)->first();
        } catch (QueryException $e) {
            $info = property_exists($e, 'errorInfo') ? $e->errorInfo : null;
            $sqlState = is_array($info) && isset($info[0]) ? (string) $info[0] : '';
            $driverCode = is_array($info) && isset($info[1]) ? (string) $info[1] : '';
            $driverMessage = is_array($info) && isset($info[2]) ? (string) $info[2] : '';
            $driverMessage = $driverMessage ? substr($driverMessage, 0, 300) : '';
            error_log('MOBILE_API_ERROR timesheets.show QueryException sqlstate=' . $sqlState . ' code=' . $driverCode . ' msg=' . $driverMessage);

            return response()->json([
                'success' => false,
                'message' => 'Timesheets are unavailable',
                'debug_error' => "QueryException: {$sqlState} {$driverCode} {$driverMessage}",
            ], 503);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR timesheets.show ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Timesheets are unavailable',
                'debug_error' => get_class($e) . ': ' . substr((string) $e->getMessage(), 0, 300),
            ], 503);
        }

        if (!$timesheet) {
            return response()->json([
                'success' => false,
                'message' => 'Timesheet not found'
            ], 404);
        }

        // Compute a human-readable display status
        $displayStatus = 'pending';
        if ($timesheet->agent_status === 'signed' && $timesheet->client_status === 'signed') {
            $displayStatus = 'completed';
        } elseif ($timesheet->agent_status === 'signed') {
            $displayStatus = 'signed';
        }

        // Agent photo URL
        $agentPhotoUrl = null;
        $agentName = null;
        try {
            if ($timesheet->agentOne) {
                $agentPhotoUrl = $timesheet->agentOne->getProfilePicture();
                if ($timesheet->agentOne->user) {
                    $agentName = trim($timesheet->agentOne->user->first_name . ' ' . $timesheet->agentOne->user->last_name);
                }
            }
        } catch (\Throwable $e) {}

        // Check-in/out times from the job (best effort)
        $checkedInAt = null;
        $checkedOutAt = null;
        try {
            if ($timesheet->interpreterJob) {
                $checkedInAt = $timesheet->interpreterJob->checked_in_at ?? null;
                $checkedOutAt = $timesheet->interpreterJob->checked_out_at ?? null;
            }
        } catch (\Throwable $e) {}

        // Client company name
        $clientCompanyName = null;
        try {
            $clientCompanyName = optional(optional(optional($timesheet->interpreterJob)->client)->userSheet)->first_name
                . ' ' . optional(optional(optional($timesheet->interpreterJob)->client)->userSheet)->last_name;
            $clientCompanyName = trim((string) $clientCompanyName) ?: null;
        } catch (\Throwable $e) {}

        $job = $timesheet->interpreterJob;

        $data = [
            'id' => $timesheet->id,
            'job_id' => $timesheet->job_id,
            'agent_id' => $timesheet->agent_id,
            'status' => $displayStatus,
            'agent_status' => $timesheet->agent_status,
            'client_status' => $timesheet->client_status,
            'agent_start_time' => $timesheet->agent_start_time,
            'agent_end_time' => $timesheet->agent_end_time,
            'duration_hours' => $timesheet->agent_duration_hours,
            'duration_minutes' => $timesheet->agent_duration_minutes,
            'client_name' => $timesheet->client_name,
            'client_phone' => $timesheet->client_phone,
            'client_designation' => $timesheet->client_designation,
            'has_agent_signature' => !empty($timesheet->agent_signature),
            'has_client_signature' => !empty($timesheet->client_signature),
            'agent_signature' => $timesheet->agent_signature ?? null,
            'client_signature' => $timesheet->client_signature ?? null,
            'agent_photo_url' => $agentPhotoUrl,
            'agent_name' => $agentName,
            'notes' => $timesheet->notes ?? null,
            'checked_in_at' => $checkedInAt ? (string) $checkedInAt : null,
            'checked_out_at' => $checkedOutAt ? (string) $checkedOutAt : null,
            'client_company_name' => $clientCompanyName,
            'created_at' => $timesheet->created_at ? $timesheet->created_at->toIso8601String() : null,
            'updated_at' => $timesheet->updated_at ? $timesheet->updated_at->toIso8601String() : null,
            'job' => $job ? [
                'id' => $job->id,
                'appointment_date' => $job->appointment_date ? $job->appointment_date->format('Y-m-d') : null,
                'client_reference' => $job->client_reference,
                'start_time' => $job->start_time,
                'end_time' => $job->end_time,
                'duration_hours' => $job->duration_hours,
                'duration_minutes' => $job->duration_minutes,
                'address_line_1' => $job->address_line_1 ?? null,
                'address_line_2' => $job->address_line_2 ?? null,
                'county' => $job->county ?? null,
                'postcode' => $job->postcode ?? null,
                'contact_information' => $job->contact_information ?? null,
                'to_language' => $job->to_language ? ['id' => $job->to_language->id, 'name' => $job->to_language->name] : null,
                'from_language' => ['id' => 37, 'name' => 'English'],
            ] : null,
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Create a new timesheet
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $agent = $user->agent;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'User is not an agent'
            ], 403);
        }

        try {
            $request->validate([
                'job_id' => 'required|exists:interpreter_jobs,id',
                'agent_start_time' => 'required',
                'agent_end_time' => 'required',
                'agent_duration_hours' => 'nullable|integer|min:0',
                'agent_duration_minutes' => 'nullable|integer|min:0|max:59',
                'client_name' => 'nullable|string',
                'client_phone' => 'nullable|string',
                'client_designation' => 'nullable|string',
            ]);

            $job = InterpreterJob::where('id', $request->job_id)
                ->where('agent_id', $agent->id)
                ->first();

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found or not assigned to you'
                ], 404);
            }

            // Check if timesheet already exists for this job
            $existingTimesheet = Timesheet::where('job_id', $request->job_id)
                ->where('agent_id', $agent->id)
                ->first();

            if ($existingTimesheet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Timesheet already exists for this job'
                ], 400);
            }

            $durationHours = $request->agent_duration_hours;
            $durationMinutes = $request->agent_duration_minutes;

            // Auto-populate start/end times from check-in/check-out if not provided
            $startTime = $request->agent_start_time;
            $endTime = $request->agent_end_time;
            try {
                if ((!$startTime || !$endTime) && $job) {
                    $checkedIn = $job->checked_in_at ?? null;
                    $checkedOut = $job->checked_out_at ?? null;
                    if (!$startTime && $checkedIn) {
                        $startTime = Carbon::parse($checkedIn)->format('H:i');
                    }
                    if (!$endTime && $checkedOut) {
                        $endTime = Carbon::parse($checkedOut)->format('H:i');
                    }
                }
            } catch (\Throwable $e) {}

            if ($durationHours === null || $durationMinutes === null) {
                try {
                    $start = Carbon::parse($startTime);
                    $end = Carbon::parse($endTime);
                    $diffMinutes = $end->diffInMinutes($start, false);
                    if ($diffMinutes < 0) {
                        $diffMinutes = 0;
                    }
                    $durationHours = (int) floor($diffMinutes / 60);
                    $durationMinutes = (int) ($diffMinutes % 60);
                } catch (\Throwable $e) {
                    $durationHours = 0;
                    $durationMinutes = 0;
                }
            }

            $timesheet = Timesheet::create([
                'agent_id' => $agent->id,
                'job_id' => $request->job_id,
                'status' => 'pending',
                'agent_start_time' => $startTime,
                'agent_end_time' => $endTime,
                'agent_duration_hours' => $durationHours,
                'agent_duration_minutes' => $durationMinutes,
                'client_name' => $request->client_name,
                'client_phone' => $request->client_phone,
                'client_designation' => $request->client_designation,
            ]);

            $data = [
                'id' => $timesheet->id,
                'status' => $timesheet->status,
                'agent_start_time' => $timesheet->agent_start_time,
                'agent_end_time' => $timesheet->agent_end_time,
                'duration_hours' => $timesheet->agent_duration_hours,
                'duration_minutes' => $timesheet->agent_duration_minutes,
                'client_name' => $timesheet->client_name,
                'client_phone' => $timesheet->client_phone,
                'client_designation' => $timesheet->client_designation,
                'created_at' => $timesheet->created_at ? $timesheet->created_at->toIso8601String() : null,
                'job' => [
                    'id' => $job->id,
                    'appointment_date' => $job->appointment_date ? $job->appointment_date->format('Y-m-d') : null,
                    'to_language' => $job->to_language ? ['id' => $job->to_language->id, 'name' => $job->to_language->name] : null,
                ],
            ];

            return response()->json([
                'success' => true,
                'message' => 'Timesheet created successfully',
                'data' => $data
            ], 201);
        } catch (QueryException $e) {
            $info = property_exists($e, 'errorInfo') ? $e->errorInfo : null;
            $sqlState = is_array($info) && isset($info[0]) ? (string) $info[0] : '';
            $driverCode = is_array($info) && isset($info[1]) ? (string) $info[1] : '';
            $driverMessage = is_array($info) && isset($info[2]) ? (string) $info[2] : '';
            $driverMessage = $driverMessage ? substr($driverMessage, 0, 300) : '';
            error_log('MOBILE_API_ERROR timesheets.store QueryException sqlstate=' . $sqlState . ' code=' . $driverCode . ' msg=' . $driverMessage);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create timesheet'
            ], 503);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR timesheets.store ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Failed to create timesheet'
            ], 503);
        }
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $agent = $user->agent;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'User is not an agent'
            ], 403);
        }

        try {
            $timesheet = Timesheet::where('id', $id)
                ->where('agent_id', $agent->id)
                ->first();

            if (!$timesheet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Timesheet not found'
                ], 404);
            }

            if (strtolower((string) $timesheet->status) !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending timesheets can be deleted'
                ], 400);
            }

            $timesheet->delete();

            return response()->json([
                'success' => true,
                'message' => 'Timesheet deleted'
            ]);
        } catch (QueryException $e) {
            $info = property_exists($e, 'errorInfo') ? $e->errorInfo : null;
            $sqlState = is_array($info) && isset($info[0]) ? (string) $info[0] : '';
            $driverCode = is_array($info) && isset($info[1]) ? (string) $info[1] : '';
            $driverMessage = is_array($info) && isset($info[2]) ? (string) $info[2] : '';
            $driverMessage = $driverMessage ? substr($driverMessage, 0, 300) : '';
            error_log('MOBILE_API_ERROR timesheets.destroy QueryException sqlstate=' . $sqlState . ' code=' . $driverCode . ' msg=' . $driverMessage);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete timesheet'
            ], 503);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR timesheets.destroy ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete timesheet'
            ], 503);
        }
    }

    /**
     * Sign a timesheet
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function sign(Request $request, $id)
    {
        $user = $request->user();
        $agent = $user->agent;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'User is not an agent'
            ], 403);
        }

        try {
            $timesheet = Timesheet::where('id', $id)
                ->where('agent_id', $agent->id)
                ->first();

            if (!$timesheet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Timesheet not found'
                ], 404);
            }

            $request->validate([
                'agent_signature' => 'nullable|string',
                'client_signature' => 'nullable|string',
                'client_name' => 'nullable|string',
                'client_phone' => 'nullable|string',
                'client_designation' => 'nullable|string',
            ]);

            // Agent signature (only set if provided and non-empty)
            if ($request->filled('agent_signature')) {
                $timesheet->agent_signature = $request->agent_signature;
                $timesheet->agent_status = 'signed';
            }

            // Client signature (for the client review & sign flow)
            if ($request->filled('client_signature')) {
                $timesheet->client_signature = $request->client_signature;
                $timesheet->client_status = 'signed';
            }

            if ($request->has('client_name') && $request->client_name) {
                $timesheet->client_name = $request->client_name;
            }
            if ($request->has('client_phone') && $request->client_phone) {
                $timesheet->client_phone = $request->client_phone;
            }
            if ($request->has('client_designation') && $request->client_designation) {
                $timesheet->client_designation = $request->client_designation;
            }

            $timesheet->save();

            // Send admin & client notifications upon timesheet sign
            try {
                $job = $timesheet->interpreterJob;
                if ($job) {
                    $adminEmail = config('app.to.address') ?: 'bookings@atozinterpreting.com';
                    $isBothSigned = !empty($timesheet->agent_signature) && !empty($timesheet->client_signature);
                    $isAgentSigned = !empty($timesheet->agent_signature);

                    // If interpreter signed and client signature is still pending, notify client
                    if ($isAgentSigned && empty($timesheet->client_signature) && optional(optional($job->client)->user)->email) {
                        try {
                            Mail::to($job->client->user)->send(new JobMail(
                                $job,
                                'emails.clients.job-timesheet-sign',
                                'Timesheet needs to be signed',
                                'client'
                            ));
                            if (class_exists(\App\Helpers\NotificationHelper::class) && class_exists(\App\Notifications\TimesheetSignNotification::class)) {
                                \App\Helpers\NotificationHelper::send($job->client->user, new \App\Notifications\TimesheetSignNotification($job, $timesheet, 'agent'));
                            }
                        } catch (\Throwable $clientErr) {
                            Log::error('Failed to notify client of timesheet signature from mobile: ' . $clientErr->getMessage());
                        }
                    }

                    // Send admin notification: Complete or Interpreter Signed
                    if ($isBothSigned) {
                        Mail::to($adminEmail)->send(
                            (new JobMail(
                                $job,
                                'emails.admin.job-timesheet-sign',
                                'Timesheet Complete - Job #' . $job->reference,
                                'admin'
                            ))->withData([
                                'heading' => 'The Timesheet for job ' . $job->reference . ' is Complete',
                                'bodyMessage' => 'This is an email notification to inform you that both the interpreter and client have signed the timesheet for job <span style="font-weight: bold">' . e($job->reference) . '</span>.',
                            ])
                        );
                    } elseif ($isAgentSigned) {
                        Mail::to($adminEmail)->send(
                            (new JobMail(
                                $job,
                                'emails.admin.job-timesheet-sign',
                                'Timesheet Signed by Interpreter - Job #' . $job->reference,
                                'admin'
                            ))->withData([
                                'heading' => 'The Timesheet for job ' . $job->reference . ' has been signed by the Interpreter',
                                'bodyMessage' => 'This is an email notification to inform you that the interpreter has signed off on the timesheet for job <span style="font-weight: bold">' . e($job->reference) . '</span>. Awaiting client signature.',
                            ])
                        );
                    }
                }
            } catch (\Throwable $adminErr) {
                Log::error('Failed to send admin timesheet email from mobile: ' . $adminErr->getMessage());
            }

            $data = [
                'id' => $timesheet->id,
                'status' => $timesheet->status,
                'agent_status' => $timesheet->agent_status,
                'client_status' => $timesheet->client_status,
                'agent_start_time' => $timesheet->agent_start_time,
                'agent_end_time' => $timesheet->agent_end_time,
                'duration_hours' => $timesheet->agent_duration_hours,
                'duration_minutes' => $timesheet->agent_duration_minutes,
                'client_name' => $timesheet->client_name,
                'client_phone' => $timesheet->client_phone,
                'client_designation' => $timesheet->client_designation,
                'agent_signature' => $timesheet->agent_signature,
                'client_signature' => $timesheet->client_signature,
                'created_at' => $timesheet->created_at ? $timesheet->created_at->toIso8601String() : null,
                'job' => $timesheet->interpreterJob ? [
                    'id' => $timesheet->interpreterJob->id,
                    'appointment_date' => $timesheet->interpreterJob->appointment_date ? $timesheet->interpreterJob->appointment_date->format('Y-m-d') : null,
                    'to_language' => $timesheet->interpreterJob->to_language ? ['id' => $timesheet->interpreterJob->to_language->id, 'name' => $timesheet->interpreterJob->to_language->name] : null,
                ] : null,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Timesheet signed successfully',
                'data' => $data
            ]);
        } catch (QueryException $e) {
            $info = property_exists($e, 'errorInfo') ? $e->errorInfo : null;
            $sqlState = is_array($info) && isset($info[0]) ? (string) $info[0] : '';
            $driverCode = is_array($info) && isset($info[1]) ? (string) $info[1] : '';
            $driverMessage = is_array($info) && isset($info[2]) ? (string) $info[2] : '';
            $driverMessage = $driverMessage ? substr($driverMessage, 0, 300) : '';
            error_log('MOBILE_API_ERROR timesheets.sign QueryException sqlstate=' . $sqlState . ' code=' . $driverCode . ' msg=' . $driverMessage);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sign timesheet'
            ], 503);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR timesheets.sign ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Failed to sign timesheet'
            ], 503);
        }
    }

    /**
     * Update a timesheet
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $agent = $user->agent;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'User is not an agent'
            ], 403);
        }

        try {
            $timesheet = Timesheet::where('id', $id)
                ->where('agent_id', $agent->id)
                ->first();

            if (!$timesheet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Timesheet not found'
                ], 404);
            }

            // Allow editing timesheets that have been signed
            // Both parties signed = completed — still allow agent edits but invalidate client sig
            $hadClientSignature = !empty($timesheet->client_signature);

            $request->validate([
                'agent_start_time' => 'sometimes',
                'agent_end_time' => 'sometimes',
                'notes' => 'nullable|string',
                'client_name' => 'nullable|string',
            ]);

            $dataChanged = false;

            if ($request->has('agent_start_time') && $request->agent_start_time != $timesheet->agent_start_time) {
                $timesheet->agent_start_time = $request->agent_start_time;
                $dataChanged = true;
            }
            if ($request->has('agent_end_time') && $request->agent_end_time != $timesheet->agent_end_time) {
                $timesheet->agent_end_time = $request->agent_end_time;
                $dataChanged = true;
            }
            if ($request->has('notes')) {
                $timesheet->notes = $request->notes;
            }
            if ($request->has('client_name')) {
                $timesheet->client_name = $request->client_name;
            }

            // Recalculate duration if times changed
            if ($dataChanged) {
                try {
                    $start = Carbon::parse($timesheet->agent_start_time);
                    $end = Carbon::parse($timesheet->agent_end_time);
                    $diffMinutes = $end->diffInMinutes($start, false);
                    if ($diffMinutes < 0) {
                        $diffMinutes = 0;
                    }
                    $timesheet->agent_duration_hours = (int) floor($diffMinutes / 60);
                    $timesheet->agent_duration_minutes = (int) ($diffMinutes % 60);
                } catch (\Throwable $e) {
                    // Keep existing duration if parsing fails
                }

                // If client had signed, clear their signature (forces re-sign)
                if ($hadClientSignature) {
                    $timesheet->client_signature = null;
                    $timesheet->client_status = null;
                }
            }

            $timesheet->save();

            // Recompute display status
            $displayStatus = 'pending';
            if ($timesheet->agent_status === 'signed' && $timesheet->client_status === 'signed') {
                $displayStatus = 'completed';
            } elseif ($timesheet->agent_status === 'signed') {
                $displayStatus = 'signed';
            }

            $data = [
                'id' => $timesheet->id,
                'status' => $displayStatus,
                'agent_status' => $timesheet->agent_status,
                'client_status' => $timesheet->client_status,
                'agent_start_time' => $timesheet->agent_start_time,
                'agent_end_time' => $timesheet->agent_end_time,
                'duration_hours' => $timesheet->agent_duration_hours,
                'duration_minutes' => $timesheet->agent_duration_minutes,
                'has_client_signature' => !empty($timesheet->client_signature),
                'client_signature_cleared' => $hadClientSignature && empty($timesheet->client_signature),
                'created_at' => $timesheet->created_at ? $timesheet->created_at->toIso8601String() : null,
            ];

            return response()->json([
                'success' => true,
                'message' => $hadClientSignature && $dataChanged
                    ? 'Timesheet updated. Client signature has been cleared — please get the client to re-sign.'
                    : 'Timesheet updated',
                'data' => $data
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR timesheets.update ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Failed to update timesheet'
            ], 503);
        }
    }

    /**
     * Upload evidence for a travel expense
     */
    public function uploadExpenseEvidence(Request $request, $id)
    {
        $user = $request->user();
        $agent = $user->agent;

        if (!$agent) {
            return response()->json(['success' => false, 'message' => 'User is not an agent'], 403);
        }

        try {
            // Find the agent job entry for this job
            $entry = \App\AgentJobEntry::where('job_id', $id)
                ->where('job_type', 'App\\InterpreterJob')
                ->where('agent_id', $agent->id)
                ->first();

            if (!$entry) {
                return response()->json(['success' => false, 'message' => 'No expense entry found for this job'], 404);
            }

            if (!$request->hasFile('file')) {
                return response()->json(['success' => false, 'message' => 'No file provided'], 422);
            }

            $request->validate([
                'expense_id' => 'nullable|integer',
                'expense_type' => 'nullable|string',
            ]);

            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());
            $allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
            if (!in_array($ext, $allowedExts)) {
                return response()->json(['success' => false, 'message' => 'File type not allowed. Accepted: jpg, jpeg, png, pdf, webp'], 422);
            }

            $disk = env('AGENT_DOCS_DISK') ?: 'public';
            $path = $file->store('expense-evidence/' . $entry->id, $disk);

            // If a specific expense_id was provided, update that expense row
            if ($request->expense_id) {
                $expense = \App\AgentJobEntryExpense::where('id', $request->expense_id)
                    ->where('agent_job_entry_id', $entry->id)
                    ->first();
                if ($expense) {
                    $expense->evidence_path = $path;
                    $expense->save();
                }
            } elseif ($request->expense_type) {
                // Find or create the expense by type
                $expense = \App\AgentJobEntryExpense::firstOrCreate(
                    ['agent_job_entry_id' => $entry->id, 'type' => $request->expense_type],
                    ['amount' => 0]
                );
                $expense->evidence_path = $path;
                $expense->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Evidence uploaded successfully',
                'data' => [
                    'path' => $path,
                    'url' => asset('storage/' . $path),
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR timesheets.uploadExpenseEvidence ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Upload failed'], 500);
        }
    }
}
