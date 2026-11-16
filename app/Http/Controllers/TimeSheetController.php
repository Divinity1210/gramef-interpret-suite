<?php

namespace App\Http\Controllers;

use App\AdminInvoiceController;
use App\AgentJobEntry;
use App\AgentJobEntryExpense;
use App\AgentInvoiceItem;
use App\AuditLog;
use App\ClientInvoice;
use App\ClientInvoiceItem;
use App\InterpreterJob;
use App\Timesheet;
use App\Language;
use App\Feedback;
use App\Mail\JobMail;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\NotificationHelper;
use App\Notifications\TimesheetSignNotification;

class TimeSheetController extends Controller
{
    private function computeOutOfHoursSplitMinutes(?string $date, ?string $startTime, ?string $endTime, ?int $durationMinutes = null): array
    {
        $tz = 'Europe/London';

        if (empty($date) || empty($startTime)) {
            return ['total' => 0, 'normal' => 0, 'ooh' => 0, 'start' => null, 'end' => null];
        }

        try {
            $start = \Carbon\Carbon::parse(trim($date) . ' ' . trim($startTime), $tz);
        } catch (\Throwable $e) {
            return ['total' => 0, 'normal' => 0, 'ooh' => 0, 'start' => null, 'end' => null];
        }

        $end = null;
        if (!empty($endTime)) {
            try {
                $end = \Carbon\Carbon::parse(trim($date) . ' ' . trim($endTime), $tz);
            } catch (\Throwable $e) {
                $end = null;
            }
        }

        if (!$end && is_numeric($durationMinutes) && (int) $durationMinutes > 0) {
            $end = $start->copy()->addMinutes((int) $durationMinutes);
        }

        if (!$end) {
            return ['total' => 0, 'normal' => 0, 'ooh' => 0, 'start' => $start, 'end' => null];
        }

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->copy()->addDay();
        }

        $totalMinutes = (int) $start->diffInMinutes($end);
        if ($totalMinutes <= 0) {
            return ['total' => 0, 'normal' => 0, 'ooh' => 0, 'start' => $start, 'end' => $end];
        }

        $normalMinutes = 0;
        $cursor = $start->copy()->startOfDay();
        $lastDay = $end->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($lastDay)) {
            $segmentStart = $cursor->copy();
            if ($segmentStart->lessThan($start)) {
                $segmentStart = $start->copy();
            }

            $segmentEnd = $cursor->copy()->endOfDay();
            if ($segmentEnd->greaterThan($end)) {
                $segmentEnd = $end->copy();
            }

            if ($segmentEnd->greaterThan($segmentStart)) {
                $normalStart = $cursor->copy()->setTime(8, 0, 0);
                $normalEnd = $cursor->copy()->setTime(18, 0, 0);

                $overlapStart = $segmentStart->copy();
                if ($overlapStart->lessThan($normalStart)) {
                    $overlapStart = $normalStart->copy();
                }

                $overlapEnd = $segmentEnd->copy();
                if ($overlapEnd->greaterThan($normalEnd)) {
                    $overlapEnd = $normalEnd->copy();
                }

                if ($overlapEnd->greaterThan($overlapStart)) {
                    $normalMinutes += (int) $overlapStart->diffInMinutes($overlapEnd);
                }
            }

            $cursor->addDay();
        }

        if ($normalMinutes < 0) {
            $normalMinutes = 0;
        }
        if ($normalMinutes > $totalMinutes) {
            $normalMinutes = $totalMinutes;
        }

        return [
            'total' => $totalMinutes,
            'normal' => $normalMinutes,
            'ooh' => $totalMinutes - $normalMinutes,
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = Auth::user();


        $query = Timesheet::with(['interpreter.client.userSheet', 'interpreterJob.client.userSheet', 'agentOne.user']);

        // Filter by agent if the user has the "agent" or "new-agent" role
        if ($user->roles->contains('name', 'agent') || $user->roles->contains('name', 'new-agent')) {
            $query->where('agent_id', $user->agent->id);
        }

        // Filter by client if the user has the "client" role
        if ($user->roles->contains('name', 'client')) {
            $query->whereHas('interpreter.client', function ($query) use ($user) {
                $query->where('client_id', $user->client->id);
            });
        }

        // Apply client_name filter using the scope
        if ($request->has('client_name') && $request->client_name != '') {
            $query->filterByClientName($request->client_name);
        }

        // Apply agent_name filter using the scope
        if ($request->has('agent_name') && $request->agent_name != '') {
            $query->filterByAgentName($request->agent_name);
        }

        // Apply ref (job_id) filter using the scope
        if ($request->has('ref') && $request->ref != '') {
            $query->filterByRef($request->ref);
        }

        $companyId = (int) $request->query('company_id');
        if ($companyId) {
            $query->filterByCompanyId($companyId);
        }

        // Apply date range filter on InterpreterJob.appointment_date
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        if ($startDate && $endDate) {
            $query->whereHas('interpreterJob', function ($q) use ($startDate, $endDate) {
                $q->whereDate('appointment_date', '>=', $startDate)
                    ->whereDate('appointment_date', '<=', $endDate);
            });
        } else if ($startDate) {
            $query->whereHas('interpreterJob', function ($q) use ($startDate) {
                $q->whereDate('appointment_date', '>=', $startDate);
            });
        } else if ($endDate) {
            $query->whereHas('interpreterJob', function ($q) use ($endDate) {
                $q->whereDate('appointment_date', '<=', $endDate);
            });
        }


        // Paginate the results
        $timesheets = $query->orderByDesc('created_at')->paginate(10);

        return view('timesheet.index', [
            'timesheets' => $timesheets,
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // Mirror behaviour of interpreter job show page by redirecting
        // the timesheet "show" route to the corresponding interpreter job.
        $timesheet = \App\Timesheet::with(['interpreter'])->find($id);
        if (!$timesheet) {
            return redirect()->route('timesheet.index')
                ->with('failure', 'Timesheet not found.');
        }

        $jobId = optional($timesheet->interpreter)->id ?? $timesheet->job_id;
        if (!$jobId) {
            return redirect()->route('timesheet.index')
                ->with('failure', 'Linked interpreter job could not be resolved for this timesheet.');
        }

        // Redirect to the Interpreter Job details page
        return redirect()->route('interpreter-jobs.show', ['interpreter_job' => $jobId]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $job = InterpreterJob::find($id);
        if ($job && $job->statusName === 'cancelled' && !$job->isCancelledSameDay()) {
            return back()->with('failure', 'This timesheet cannot be edited because the job has been cancelled.');
        }
        $isPaid = \App\ClientInvoiceItem::where('job_id', $id)
            ->whereHas('invoice', function ($q) {
                $q->where('status', 'paid'); })
            ->exists();
        if ($isPaid) {
            return back()->with('failure', 'This timesheet cannot be edited after payment has been recorded.');
        }
        $timesheets = Timesheet::with(['interpreter.client.userSheet', 'agentOne.user'])
            ->where('job_id', $id)
            ->get();

        $feedback = Feedback::where('job_id', $id)->first();

        return view('timesheet.agentSignature', ['timesheets' => $timesheets, 'feedback' => $feedback]);

        // dd($id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        $rules = [
            'signature' => 'required',
            'duration_hours' => 'required|integer',
            'duration_minutes' => 'required|integer',
        ];

        if (isset(auth()->user()->agent->id)) {
            $rules['agent_start_time'] = 'required';
            $rules['agent_end_time'] = 'required';
        } else {
            $rules['client_name'] = 'required';
            $rules['client_phone'] = 'required';
            $rules['client_designation'] = 'required';
        }

        $validatedData = $request->validate($rules);


        // Find the timesheet by ID
        $timesheet = Timesheet::findOrFail($id);
        $interpreterJob = InterpreterJob::findOrFail($timesheet->job_id);
        if ($interpreterJob->statusName === 'cancelled' && !$interpreterJob->isCancelledSameDay()) {
            return back()->with('failure', 'This timesheet cannot be updated because the job has been cancelled.');
        }
        //  dd($request->client_name);

        // Extract validated data
        // dd($validatedData);
        $signatureData = $validatedData['signature'];
        $duration_hours = $validatedData['duration_hours'];
        $duration_minutes = $validatedData['duration_minutes'];
        $agent_start_time = $request->agent_start_time;
        $agent_end_time = $request->agent_end_time;


        // Check if the authenticated user is an agent
        if (isset(auth()->user()->agent->id)) {
            // Update the timesheet data for an agent
            $updateData = [
                'agent_signature' => $signatureData,
                'agent_duration_hours' => $duration_hours,
                'agent_duration_minutes' => $duration_minutes,
                'agent_start_time' => $agent_start_time,
                'agent_end_time' => $agent_end_time,
                'client_name' => $request->client_name ?? $timesheet->client_name,
            ];

            try {
                Mail::to($interpreterJob->client->user)->send(new JobMail(
                    $interpreterJob,
                    'emails.clients.job-timesheet-sign',
                    'Timesheet needs to be signed',
                    'client'
                ));
                // Notify client: timesheet needs your signature
                NotificationHelper::send($interpreterJob->client->user, new TimesheetSignNotification($interpreterJob, $timesheet, 'agent'));
            } catch (\Exception $e) {
                Log::error('Failed to send timesheet timesheet signing email to client: ' . $e->getMessage());
            }
        } else {
            // Update the timesheet data for the client
            $updateData = [
                'client_signature' => $signatureData,
                'client_name' => $request->client_name ?? $timesheet->client_name,
                'client_phone' => $request->client_phone ?? $timesheet->client_phone,
                'client_designation' => $request->client_designation ?? $timesheet->client_designation,
            ];

            try {
                Mail::to($interpreterJob->agent->user)->send(new JobMail(
                    $interpreterJob,
                    'emails.agents.job-timesheet-sign',
                    'Timesheet needs to be signed',
                    'agent'
                ));
                // Notify agent: timesheet signed by client
                NotificationHelper::send($interpreterJob->agent->user, new TimesheetSignNotification($interpreterJob, $timesheet, 'client'));
            } catch (\Exception $e) {
                Log::error('Failed to send timesheet timesheet signing email to agent: ' . $e->getMessage());
            }
        }

        $timesheet->update($updateData);

        // Send admin notification: Timesheet Complete or Timesheet Signed by Interpreter
        try {
            $freshTimesheet = $timesheet->fresh() ?: $timesheet;
            $adminEmail = config('app.to.address') ?: 'bookings@atozinterpreting.com';
            $isBothSigned = !empty($freshTimesheet->agent_signature) && !empty($freshTimesheet->client_signature);
            $isAgentSignedOnly = !empty($freshTimesheet->agent_signature) && empty($freshTimesheet->client_signature);

            if ($isBothSigned) {
                Mail::to($adminEmail)->send(
                    (new JobMail(
                        $interpreterJob,
                        'emails.admin.job-timesheet-sign',
                        'Timesheet Complete - Job #' . $interpreterJob->reference,
                        'admin'
                    ))->withData([
                        'heading' => 'The Timesheet for job ' . $interpreterJob->reference . ' is Complete',
                        'bodyMessage' => 'This is an email notification to inform you that both the interpreter and client have signed the timesheet for job <span style="font-weight: bold">' . e($interpreterJob->reference) . '</span>.',
                    ])
                );
            } elseif ($isAgentSignedOnly && isset(auth()->user()->agent->id)) {
                Mail::to($adminEmail)->send(
                    (new JobMail(
                        $interpreterJob,
                        'emails.admin.job-timesheet-sign',
                        'Timesheet Signed by Interpreter - Job #' . $interpreterJob->reference,
                        'admin'
                    ))->withData([
                        'heading' => 'The Timesheet for job ' . $interpreterJob->reference . ' has been signed by the Interpreter',
                        'bodyMessage' => 'This is an email notification to inform you that the interpreter has signed off on the timesheet for job <span style="font-weight: bold">' . e($interpreterJob->reference) . '</span>. Awaiting client signature.',
                    ])
                );
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send admin timesheet notification email: ' . $e->getMessage());
        }

        // Redirect back to the Timesheet edit page for live preview and possible re-sign
        // Historically, the edit page shows the saved signature on the right and allows overriding by signing again
        return redirect()->route('timesheet.edit', $interpreterJob->id)
            ->with('success', 'Timesheet submitted successfully!');
    }

    public function generateTimesheet($id)
    {
        $timesheet = Timesheet::with([
            'interpreterJob.client.userSheet',
            'interpreterJob.toLanguage',
            'interpreterJob.agent.user',
            'agentOne.user',
        ])
            ->where('id', $id)
            ->firstOrFail();
        $this->authorize('view', $timesheet);

        $disk = env('TIMESHEETS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        $relPath = 'timesheets/timesheet-' . $timesheet->id . '.pdf';
        $force = (bool) (request()->query('force') ?? request()->query('regen') ?? false);
        $tplPath = resource_path('views/timesheet/timesheet_pdf.blade.php');
        $tplUpdated = is_file($tplPath) ? ((int) @filemtime($tplPath)) : 0;
        $needsRegeneration = $force || !\Illuminate\Support\Facades\Storage::disk($disk)->exists($relPath);
        if (!$needsRegeneration) {
            try {
                $last = \Illuminate\Support\Facades\Storage::disk($disk)->lastModified($relPath) ?: 0;
                $tsUpdated = $timesheet->updated_at ? $timesheet->updated_at->getTimestamp() : time();
                $needsRegeneration = $last < max($tsUpdated, $tplUpdated);
            } catch (\Throwable $e) {
                $needsRegeneration = true;
            }
        }

        if ($needsRegeneration) {
            $html = view('timesheet/timesheet_pdf', compact('timesheet'))->render();

            $tempDir = storage_path('app/mpdf-temp');
            if (!is_dir($tempDir)) {
                @mkdir($tempDir, 0775, true);
            }

            try {
                $mpdf = new \Mpdf\Mpdf([
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'orientation' => 'P',
                    'margin_top' => 5,
                    'margin_left' => 5,
                    'margin_right' => 5,
                    'margin_bottom' => 5,
                    'tempDir' => $tempDir,
                ]);
                $mpdf->WriteHTML($html);
                $content = $mpdf->Output('', 'S');
                \Illuminate\Support\Facades\Storage::disk($disk)->put($relPath, $content);
                try {
                    foreach ((array) glob($tempDir . '/*') as $f) {
                        if (is_file($f) && @filemtime($f) < (time() - 86400)) {
                            @unlink($f);
                        }
                    }
                } catch (\Throwable $e) {
                }
                try {
                    foreach ((array) glob($tempDir . '/*') as $f) {
                        if (is_file($f) && @filemtime($f) < (time() - 86400)) {
                            @unlink($f);
                        }
                    }
                } catch (\Throwable $e) {
                }
            } catch (\Throwable $e) {
                try {
                    $dompdf = new \Dompdf\Dompdf();
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    $output = $dompdf->output();
                    \Illuminate\Support\Facades\Storage::disk($disk)->put($relPath, $output);
                    try {
                        foreach ((array) glob($tempDir . '/*') as $f) {
                            if (is_file($f) && @filemtime($f) < (time() - 86400)) {
                                @unlink($f);
                            }
                        }
                    } catch (\Throwable $e) {
                    }
                    try {
                        foreach ((array) glob($tempDir . '/*') as $f) {
                            if (is_file($f) && @filemtime($f) < (time() - 86400)) {
                                @unlink($f);
                            }
                        }
                    } catch (\Throwable $e) {
                    }
                } catch (\Throwable $e2) {
                    $minimal = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R>>endobj\n4 0 obj<</Length 44>>stream\nBT /F1 12 Tf 72 700 Td (Timesheet) Tj ET\nendstream endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
                    \Illuminate\Support\Facades\Storage::disk($disk)->put($relPath, $minimal);
                    try {
                        foreach ((array) glob($tempDir . '/*') as $f) {
                            if (is_file($f) && @filemtime($f) < (time() - 86400)) {
                                @unlink($f);
                            }
                        }
                    } catch (\Throwable $e) {
                    }
                    try {
                        foreach ((array) glob($tempDir . '/*') as $f) {
                            if (is_file($f) && @filemtime($f) < (time() - 86400)) {
                                @unlink($f);
                            }
                        }
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

        if ($disk === 's3') {
            try {
                $client = new \Aws\S3\S3Client([
                    'version' => 'latest',
                    'region' => env('AWS_DEFAULT_REGION'),
                    'credentials' => [
                        'key' => env('AWS_ACCESS_KEY_ID'),
                        'secret' => env('AWS_SECRET_ACCESS_KEY'),
                    ],
                ]);
                $key = trim(env('AWS_PREFIX', ''), '/') ? (trim(env('AWS_PREFIX', ''), '/') . '/' . $relPath) : $relPath;
                $cmd = $client->getCommand('GetObject', [
                    'Bucket' => env('AWS_BUCKET'),
                    'Key' => $key,
                    'ResponseContentDisposition' => 'inline; filename="timesheet-' . $timesheet->id . '.pdf"',
                    'ResponseContentType' => 'application/pdf',
                ]);
                $req = $client->createPresignedRequest($cmd, '+15 minutes');
                return redirect()->away((string) $req->getUri());
            } catch (\Throwable $e) {
            }
        }
        $abs = storage_path('app/public/' . $relPath);
        return response()->file($abs, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Generate Timesheet PDF by InterpreterJob (implicit route-model binding).
     * Enables printing directly from the job page and enforces TimesheetPolicy@view.
     */
    public function generateTimesheetByJob(\App\InterpreterJob $interpreterJob)
    {
        // Find the timesheet associated with this job
        $timesheet = Timesheet::with([
            'interpreterJob.client.userSheet',
            'interpreterJob.toLanguage',
            'interpreterJob.agent.user',
            'agentOne.user',
        ])
            ->where('job_id', $interpreterJob->id)
            ->firstOrFail();

        // Enforce authorization on the Timesheet entity
        $this->authorize('view', $timesheet);

        $disk = env('TIMESHEETS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        $relPath = 'timesheets/timesheet-' . $timesheet->id . '.pdf';
        $force = (bool) (request()->query('force') ?? request()->query('regen') ?? false);
        $tplPath = resource_path('views/timesheet/timesheet_pdf.blade.php');
        $tplUpdated = is_file($tplPath) ? ((int) @filemtime($tplPath)) : 0;
        $needsRegeneration = $force || !\Illuminate\Support\Facades\Storage::disk($disk)->exists($relPath);
        if (!$needsRegeneration) {
            try {
                $last = \Illuminate\Support\Facades\Storage::disk($disk)->lastModified($relPath) ?: 0;
                $tsUpdated = $timesheet->updated_at ? $timesheet->updated_at->getTimestamp() : time();
                $needsRegeneration = $last < max($tsUpdated, $tplUpdated);
            } catch (\Throwable $e) {
                $needsRegeneration = true;
            }
        }

        if ($needsRegeneration) {
            $html = view('timesheet/timesheet_pdf', compact('timesheet'))->render();

            $tempDir = storage_path('app/mpdf-temp');
            if (!is_dir($tempDir)) {
                @mkdir($tempDir, 0775, true);
            }

            try {
                $mpdf = new \Mpdf\Mpdf([
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'orientation' => 'P',
                    'margin_top' => 5,
                    'margin_left' => 5,
                    'margin_right' => 5,
                    'margin_bottom' => 5,
                    'tempDir' => $tempDir,
                ]);
                $mpdf->WriteHTML($html);
                $content = $mpdf->Output('', 'S');
                \Illuminate\Support\Facades\Storage::disk($disk)->put($relPath, $content);
            } catch (\Throwable $e) {
                try {
                    $dompdf = new \Dompdf\Dompdf();
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    $output = $dompdf->output();
                    \Illuminate\Support\Facades\Storage::disk($disk)->put($relPath, $output);
                } catch (\Throwable $e2) {
                    $minimal = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R>>endobj\n4 0 obj<</Length 44>>stream\nBT /F1 12 Tf 72 700 Td (Timesheet) Tj ET\nendstream endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
                    \Illuminate\Support\Facades\Storage::disk($disk)->put($relPath, $minimal);
                }
            }
        }

        if ($disk === 's3') {
            try {
                $client = new \Aws\S3\S3Client([
                    'version' => 'latest',
                    'region' => env('AWS_DEFAULT_REGION'),
                    'credentials' => [
                        'key' => env('AWS_ACCESS_KEY_ID'),
                        'secret' => env('AWS_SECRET_ACCESS_KEY'),
                    ],
                ]);
                $key = trim(env('AWS_PREFIX', ''), '/') ? (trim(env('AWS_PREFIX', ''), '/') . '/' . $relPath) : $relPath;
                $cmd = $client->getCommand('GetObject', [
                    'Bucket' => env('AWS_BUCKET'),
                    'Key' => $key,
                    'ResponseContentDisposition' => 'inline; filename="timesheet-' . $timesheet->id . '.pdf"',
                    'ResponseContentType' => 'application/pdf',
                ]);
                $req = $client->createPresignedRequest($cmd, '+15 minutes');
                return redirect()->away((string) $req->getUri());
            } catch (\Throwable $e) {
            }
        }
        $abs = storage_path('app/public/' . $relPath);
        return response()->file($abs, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }


    // AJAX data endpoint for collective builder filters
    public function collectiveData(Request $request)
    {
        $user = Auth::user();
        $query = Timesheet::with(['interpreter.client', 'interpreter.to_language', 'agentOne.user']);
        if ($user->roles->contains('name', 'agent')) {
            $query->where('agent_id', optional($user->agent)->id);
        }
        if ($user->roles->contains('name', 'client')) {
            $query->whereHas('interpreter.client', function ($q) use ($user) {
                $q->where('client_id', optional($user->client)->id);
            });
        }

        // Filters
        $clientName = $request->query('client_name');
        $agentName = $request->query('agent_name');
        $ref = $request->query('ref');
        $companyId = (int) $request->query('company_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if (!empty($clientName)) {
            $query->filterByClientName($clientName);
        }
        if (!empty($agentName)) {
            $query->filterByAgentName($agentName);
        }
        if (!empty($ref)) {
            $query->filterByRef($ref);
        }
        if ($companyId) {
            $query->filterByCompanyId($companyId);
        }
        if ($startDate && $endDate) {
            $query->whereHas('interpreterJob', function ($q) use ($startDate, $endDate) {
                $q->whereDate('appointment_date', '>=', $startDate)
                    ->whereDate('appointment_date', '<=', $endDate);
            });
        } elseif ($startDate) {
            $query->whereHas('interpreterJob', function ($q) use ($startDate) {
                $q->whereDate('appointment_date', '>=', $startDate);
            });
        } elseif ($endDate) {
            $query->whereHas('interpreterJob', function ($q) use ($endDate) {
                $q->whereDate('appointment_date', '<=', $endDate);
            });
        }

        // Pagination parameters
        $perPage = (int) ($request->query('per_page') ?? 25);
        if ($perPage < 5) {
            $perPage = 5;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }
        $page = (int) ($request->query('page') ?? 1);
        if ($page < 1) {
            $page = 1;
        }

        // Total count and page slice
        $totalCount = (clone $query)->count();
        $timesheets = $query->orderByDesc('id')->forPage($page, $perPage)->get();
        $capped = false; // pagination replaces capping UX here

        $filterSummaryParts = [];
        if ($clientName) {
            $filterSummaryParts[] = 'Client: ' . $clientName;
        }
        if ($agentName) {
            $filterSummaryParts[] = 'Agent: ' . $agentName;
        }
        if ($ref) {
            $filterSummaryParts[] = 'Ref: ' . $ref;
        }
        if ($startDate || $endDate) {
            $filterSummaryParts[] = 'Date: ' . ($startDate ?: '—') . ' → ' . ($endDate ?: '—');
        }
        if ($companyId) {
            $filterSummaryParts[] = 'Company';
        }
        if ($companyId) {
            $filterSummaryParts[] = 'Company';
        }
        if ($companyId) {
            $filterSummaryParts[] = 'Company';
        }
        $filterSummary = implode(' • ', $filterSummaryParts);

        $rows = $timesheets->map(function ($ts) {
            $job = $ts->interpreter;
            $agentName = optional(optional($ts->agentOne)->user)->getFullName();
            // Compute amounts (payslip + agent entry)
            $jobId = optional($job)->id;
            $payslipItem = \App\AgentInvoiceItem::where('job_id', $jobId)->orderByDesc('id')->first();
            $clientItem = ClientInvoiceItem::where('job_id', $jobId)->orderByDesc('id')->first();
            $agentEntry = \App\AgentJobEntry::where('job_id', $jobId)->where('agent_id', $ts->agent_id)->first();
            $agentEntryBreakdown = [
                'base' => (float) optional($agentEntry)->base_amount,
                'expenses' => (float) optional($agentEntry)->expenses,
                'surcharge' => (float) optional($agentEntry)->surcharge,
                'other' => (float) optional($agentEntry)->other_charges,
            ];
            $hasTsAmount = is_numeric($ts->base_amount) || is_numeric($ts->other_charges) || is_numeric($ts->expenses_amount) || is_numeric($ts->surcharge);
            $tsAmount = (float) ((($ts->base_amount ?? 0) + ($ts->expenses_amount ?? 0) + ($ts->surcharge ?? 0) + ($ts->other_charges ?? 0)));
            $aeTotal = $agentEntry ? (float) $agentEntry->total_amount : null;
            $payslipTotal = $payslipItem ? (float) $payslipItem->total_amount : null;
            $clientItemTotal = $clientItem ? ((float) $clientItem->amount + (float) $clientItem->surcharge + (float) $clientItem->other_charges) : null;
            $row = [
                'timesheet_id' => $ts->id,
                'job_id' => optional($job)->id,
                'client_id' => optional(optional($job)->client)->id,
                'ref' => optional($job)->reference,
                'date' => optional($job->appointment_date)->toDateString(),
                'start_time' => ($ts->agent_start_time ?: ($ts->start_time ?: (optional($job)->start_time ?: null))),
                'end_time' => ($ts->agent_end_time ?: ($ts->end_time ?: (optional($job)->end_time ?: null))),
                'client_name' => optional(optional($job)->client)->user ? optional(optional($job)->client)->user->getFullName() : null,
                'agent_name' => $agentName,
                'language' => optional($job->to_language)->name,
                'amount' => (is_numeric($clientItemTotal) && $clientItemTotal > 0)
                    ? $clientItemTotal
                    : ($hasTsAmount ? $tsAmount : ($aeTotal ?? ($payslipTotal ?? 0.0))),
                'expenses' => (float) ($ts->expenses_amount ?? 0),
                'surcharge' => (float) ($ts->surcharge ?? 0),
                'other' => (float) ($ts->other_charges ?? 0),
                'description' => $ts->client_name ? ('Interpreting for ' . $ts->client_name) : ('Interpreting for Job #' . optional($job)->id),
                // New info columns
                'payslip_amount' => $payslipItem ? (float) $payslipItem->total_amount : null,
                'agent_entry_total' => $agentEntry ? (float) $agentEntry->total_amount : null,
                'agent_entry_breakdown' => $agentEntry ? $agentEntryBreakdown : null,
            ];
            $row['has_amount'] = (is_numeric($clientItemTotal) && $clientItemTotal > 0)
                || $hasTsAmount
                || (is_numeric($aeTotal) && $aeTotal > 0)
                || (is_numeric($payslipTotal) && $payslipTotal > 0);
            $row['already_invoiced'] = ClientInvoiceItem::where('job_id', optional($job)->id)
                ->whereHas('invoice', function ($q) {
                    $q->whereIn('status', ['sent', 'paid']); })
                ->exists();
            return $row;
        })->values();

        $payload = [
            'meta' => [
                'cap' => $perPage,
                'capped' => $capped,
                'total' => $totalCount,
                'displayed' => (int) $rows->count(),
                'filter_summary' => $filterSummary,
                // pagination meta
                'current_page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil(max(1, $totalCount) / max(1, $perPage)),
                'suggestions' => [
                    'Narrow the date range',
                    'Add or refine Client/Agent name',
                    'Filter by Job Ref',
                ],
            ],
            'rows' => $rows,
        ];

        $etag = md5(json_encode([$payload['meta'], $request->query()]));
        return response()->json($payload, 200, [
            'Cache-Control' => 'private, max-age=60',
            'ETag' => $etag,
        ]);
    }
    public function collectiveStore(Request $request)
    {
        $payload = $request->validate([
            'client_id' => 'required|integer',
            'items' => 'required|array|min:1',
            'items.*.timesheet_id' => 'required|integer',
            'items.*.job_id' => 'required|integer',
            'items.*.amount' => 'required|numeric|min:0.01',
            'items.*.description' => 'nullable|string|max:500',
            'items.*.interpreting_time_minutes' => 'nullable|numeric|min:0',
            'items.*.interpreting_rate_hour' => 'nullable|numeric|min:0',
            'items.*.interpreting_ooh_rate_hour' => 'nullable|numeric|min:0',
            'items.*.interpreting_normal_minutes' => 'nullable|numeric|min:0',
            'items.*.interpreting_ooh_minutes' => 'nullable|numeric|min:0',
            'include_vat' => 'nullable|boolean',
            'vat_rate' => 'nullable|numeric',
        ]);

        $includeVat = (bool) ($payload['include_vat'] ?? true);
        $vatRate = (float) ($payload['vat_rate'] ?? 20.0);

        // Load timesheets and ensure same client
        $timesheetIds = collect($payload['items'])->pluck('timesheet_id')->unique()->values()->all();
        $timesheets = Timesheet::with(['interpreter.client'])->whereIn('id', $timesheetIds)->get();
        if ($timesheets->isEmpty()) {
            return back()->with('failure', 'No valid timesheets selected.');
        }
        $clientIds = $timesheets->pluck('interpreter.client.id')->filter()->unique();
        if ($clientIds->count() !== 1 || (int) $clientIds->first() !== (int) $payload['client_id']) {
            return back()->with('failure', 'Selected rows must belong to a single client.');
        }
        $client = optional($timesheets->first())->interpreter->client;

        // Prevent duplicate invoices only if parent invoice is sent or paid
        $jobIds = collect($payload['items'])->pluck('job_id')->unique();
        $already = ClientInvoiceItem::whereIn('job_id', $jobIds)
            ->whereHas('invoice', function ($q) {
                $q->whereIn('status', ['sent', 'paid']); })
            ->pluck('job_id')->unique();
        if ($already->count() > 0) {
            return back()->with('failure', 'One or more selected jobs are already invoiced: ' . $already->implode(', '));
        }

        // Compute date range for invoice
        $dates = $timesheets->map(function ($ts) {
            return optional($ts->interpreter->appointment_date)->toDateString(); })->filter();
        $start = $dates->min();
        $end = $dates->max();

        // Map per-timesheet service dates for item metadata
        $tsServiceDateById = $timesheets->mapWithKeys(function ($ts) {
            $d = optional($ts->interpreter->appointment_date)->toDateString();
            return [$ts->id => $d];
        });

        return DB::transaction(function () use ($payload, $includeVat, $vatRate, $client, $start, $end, $tsServiceDateById) {
            $subtotal = 0.0;
            foreach ($payload['items'] as $item) {
                $subtotal += (float) $item['amount'];
            }
            $vatAmount = $includeVat ? round($subtotal * ($vatRate / 100), 2) : 0.0;
            $total = round($subtotal + $vatAmount, 2);

            $firstJobId = isset($payload['items'][0]['job_id']) ? (int) $payload['items'][0]['job_id'] : null;
            $firstJob = $firstJobId ? \App\InterpreterJob::find($firstJobId) : null;

            $invoice = ClientInvoice::create([
                'client_id' => $client->id,
                'company_id' => optional(optional($client)->organisation)->company_id,
                'invoice_number' => 'CI-TMP-' . uniqid(),
                'invoice_date' => now()->toDateString(),
                'date_range_start' => $start,
                'date_range_end' => $end,
                'template' => 'v2',
                'include_vat' => $includeVat,
                'vat_rate' => $vatRate,
                'subtotal' => round($subtotal, 2),
                'vat_amount' => $vatAmount,
                'total' => $total,
                'status' => 'draft',
                'file_reference' => optional($firstJob)->file_reference,
                'client_reference' => optional($firstJob)->client_reference,
                'patient_number' => optional($firstJob)->patient_number,
                'pid' => optional($firstJob)->personal_identity_number,
            ]);
            $invoice->invoice_number = 'CI-' . str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT);
            $invoice->save();

            foreach ($payload['items'] as $item) {
                $serviceDate = $tsServiceDateById->get((int) $item['timesheet_id']);
                $jobForItem = \App\InterpreterJob::find((int) $item['job_id']);
                $itemAmount = (float) $item['amount'];

                $intMinutes = isset($item['interpreting_time_minutes']) && is_numeric($item['interpreting_time_minutes'])
                    ? (float) $item['interpreting_time_minutes']
                    : null;
                $intRateHour = isset($item['interpreting_rate_hour']) && is_numeric($item['interpreting_rate_hour'])
                    ? (float) $item['interpreting_rate_hour']
                    : null;
                $intOohRateHour = isset($item['interpreting_ooh_rate_hour']) && is_numeric($item['interpreting_ooh_rate_hour'])
                    ? (float) $item['interpreting_ooh_rate_hour']
                    : null;
                $intNormalMinutes = isset($item['interpreting_normal_minutes']) && is_numeric($item['interpreting_normal_minutes'])
                    ? (float) $item['interpreting_normal_minutes']
                    : null;
                $intOohMinutes = isset($item['interpreting_ooh_minutes']) && is_numeric($item['interpreting_ooh_minutes'])
                    ? (float) $item['interpreting_ooh_minutes']
                    : null;

                if (is_numeric($intRateHour) && is_numeric($intMinutes) && $intMinutes > 0) {
                    if (is_numeric($intOohRateHour) && $intOohRateHour >= 0 && is_numeric($intNormalMinutes) && is_numeric($intOohMinutes) && ($intNormalMinutes + $intOohMinutes) > 0) {
                        $itemAmount = round(($intRateHour * ($intNormalMinutes / 60)) + ($intOohRateHour * ($intOohMinutes / 60)), 2);
                    } else {
                        $itemAmount = round($intRateHour * ($intMinutes / 60), 2);
                    }
                }

                ClientInvoiceItem::create([
                    'client_invoice_id' => $invoice->id,
                    'job_id' => (int) $item['job_id'],
                    'description' => $item['description'] ?: ('Interpreting service for Job #' . $item['job_id']),
                    'amount' => (float) $itemAmount,
                    'surcharge' => 0.0,
                    'other_charges' => 0.0,
                    'vat_applicable' => $includeVat,
                    'metadata' => [
                        'timesheet_id' => (int) $item['timesheet_id'],
                        'source' => 'timesheet_collective_builder',
                        // Service date for filtering and PDF display
                        'date' => $serviceDate,
                        // Explicit job type for downstream logic
                        'job_type' => 'App\\InterpreterJob',
                        'interpreting_time_minutes' => $intMinutes,
                        'interpreting_rate_hour' => $intRateHour,
                        'interpreting_ooh_rate_hour' => $intOohRateHour,
                        'interpreting_normal_minutes' => $intNormalMinutes,
                        'interpreting_ooh_minutes' => $intOohMinutes,
                        'client_reference' => optional($jobForItem)->client_reference,
                        'file_reference' => optional($jobForItem)->file_reference,
                        'patient_number' => optional($jobForItem)->patient_number,
                        'pid' => optional($jobForItem)->personal_identity_number,
                    ],
                ]);
            }

            try {
                AuditLog::create([
                    'user_id' => optional(\Illuminate\Support\Facades\Auth::user())->id,
                    'actor_id' => optional(\Illuminate\Support\Facades\Auth::user())->id,
                    'action' => 'collective_client_invoice_created',
                    'entity_type' => 'ClientInvoice',
                    'entity_id' => $invoice->id,
                    'metadata' => [
                        'client_id' => $client->id,
                        'invoice_id' => $invoice->id,
                        'items_count' => count($payload['items']),
                        'subtotal' => round($subtotal, 2),
                        'vat' => $vatAmount,
                        'total' => $total,
                    ],
                    'ip_address' => request()->ip(),
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create audit log for collective invoice: ' . $e->getMessage());
            }

            return redirect()->route('invoices.admin.client.preview', ['id' => $invoice->id])
                ->with('success', 'Client invoice created successfully.');
        });
    }

    // Add collective invoice builder (row-based UI)
    public function collectiveCreate(Request $request)
    {
        $user = Auth::user();
        $query = Timesheet::with(['interpreter.client', 'interpreter.to_language', 'agentOne.user']);
        if ($user->roles->contains('name', 'agent')) {
            $query->where('agent_id', optional($user->agent)->id);
        }
        if ($user->roles->contains('name', 'client')) {
            $query->whereHas('interpreter.client', function ($q) use ($user) {
                $q->where('client_id', optional($user->client)->id);
            });
        }

        $clientName = $request->query('client_name');
        $agentName = $request->query('agent_name');
        $ref = $request->query('ref');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if (!empty($clientName)) {
            $query->filterByClientName($clientName);
        }
        if (!empty($agentName)) {
            $query->filterByAgentName($agentName);
        }
        if (!empty($ref)) {
            $query->filterByRef($ref);
        }
        if ($startDate && $endDate) {
            $query->whereHas('interpreterJob', function ($q) use ($startDate, $endDate) {
                $q->whereDate('appointment_date', '>=', $startDate)
                    ->whereDate('appointment_date', '<=', $endDate);
            });
        } elseif ($startDate) {
            $query->whereHas('interpreterJob', function ($q) use ($startDate) {
                $q->whereDate('appointment_date', '>=', $startDate);
            });
        } elseif ($endDate) {
            $query->whereHas('interpreterJob', function ($q) use ($endDate) {
                $q->whereDate('appointment_date', '<=', $endDate);
            });
        }

        // Initial page defaults (server-rendered)
        $perPage = (int) ($request->query('per_page') ?? 25);
        if ($perPage < 5) {
            $perPage = 5;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }
        $page = (int) ($request->query('page') ?? 1);
        if ($page < 1) {
            $page = 1;
        }
        $totalCount = (clone $query)->count();
        $timesheets = $query->orderByDesc('id')->forPage($page, $perPage)->get();
        $capped = false;

        $filterSummaryParts = [];
        if ($clientName) {
            $filterSummaryParts[] = 'Client: ' . $clientName;
        }
        if ($agentName) {
            $filterSummaryParts[] = 'Agent: ' . $agentName;
        }
        if ($ref) {
            $filterSummaryParts[] = 'Ref: ' . $ref;
        }
        if ($startDate || $endDate) {
            $filterSummaryParts[] = 'Date: ' . ($startDate ?: '—') . ' → ' . ($endDate ?: '—');
        }
        $filterSummary = implode(' • ', $filterSummaryParts);
        $resultMeta = [
            'cap' => $perPage,
            'capped' => $capped,
            'total' => $totalCount,
            'displayed' => (int) $timesheets->count(),
            'filter_summary' => $filterSummary,
            'current_page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil(max(1, $totalCount) / max(1, $perPage)),
            'suggestions' => [
                'Narrow the date range',
                'Add or refine Client/Agent name',
                'Filter by Job Ref',
            ],
        ];

        $client = optional(optional($timesheets->first())->interpreter)->client;
        $rows = $timesheets->map(function ($ts) {
            $job = $ts->interpreter;
            $hasAmount = is_numeric($ts->base_amount) || is_numeric($ts->other_charges) || is_numeric($ts->expenses_amount) || is_numeric($ts->surcharge);
            $alreadyInvoiced = ClientInvoiceItem::where('job_id', optional($job)->id)
                ->whereHas('invoice', function ($q) {
                    $q->whereIn('status', ['sent', 'paid']); })
                ->exists();
            $agentName = optional(optional($ts->agentOne)->user)->getFullName();
            // amounts from agent payslip and entry
            $jobId = optional($job)->id;
            $payslipItem = \App\AgentInvoiceItem::where('job_id', $jobId)->orderByDesc('id')->first();
            $clientItem = ClientInvoiceItem::where('job_id', $jobId)->orderByDesc('id')->first();
            $agentEntry = \App\AgentJobEntry::where('job_id', $jobId)->where('agent_id', $ts->agent_id)->first();
            $agentEntryBreakdown = [
                'base' => (float) optional($agentEntry)->base_amount,
                'expenses' => (float) optional($agentEntry)->expenses,
                'surcharge' => (float) optional($agentEntry)->surcharge,
                'other' => (float) optional($agentEntry)->other_charges,
            ];
            $hasTsAmount = is_numeric($ts->base_amount) || is_numeric($ts->other_charges) || is_numeric($ts->expenses_amount) || is_numeric($ts->surcharge);
            $tsAmount = (float) ((($ts->base_amount ?? 0) + ($ts->expenses_amount ?? 0) + ($ts->surcharge ?? 0) + ($ts->other_charges ?? 0)));
            $aeTotal = $agentEntry ? (float) $agentEntry->total_amount : null;
            $payslipTotal = $payslipItem ? (float) $payslipItem->total_amount : null;
            $clientItemTotal = $clientItem ? ((float) $clientItem->amount + (float) $clientItem->surcharge + (float) $clientItem->other_charges) : null;
            return [
                'timesheet_id' => $ts->id,
                'job_id' => optional($job)->id,
                'client_id' => optional(optional($job)->client)->id,
                'ref' => optional($job)->reference,
                'date' => optional($job->appointment_date)->toDateString(),
                'start_time' => ($ts->agent_start_time ?: ($ts->start_time ?: (optional($job)->start_time ?: null))),
                'end_time' => ($ts->agent_end_time ?: ($ts->end_time ?: (optional($job)->end_time ?: null))),
                'client_name' => optional(optional($job)->client)->user ? optional(optional($job)->client)->user->getFullName() : null,
                'agent_name' => $agentName,
                'language' => optional($job->to_language)->name,
                'amount' => (is_numeric($clientItemTotal) && $clientItemTotal > 0)
                    ? $clientItemTotal
                    : ($hasTsAmount ? $tsAmount : ($aeTotal ?? ($payslipTotal ?? 0.0))),
                'expenses' => (float) ($ts->expenses_amount ?? 0),
                'surcharge' => (float) ($ts->surcharge ?? 0),
                'other' => (float) ($ts->other_charges ?? 0),
                'has_amount' => (is_numeric($clientItemTotal) && $clientItemTotal > 0)
                    || $hasTsAmount
                    || (is_numeric($aeTotal) && $aeTotal > 0)
                    || (is_numeric($payslipTotal) && $payslipTotal > 0),
                'already_invoiced' => $alreadyInvoiced,
                'description' => $ts->client_name ? ('Interpreting for ' . $ts->client_name) : ('Interpreting for Job #' . optional($job)->id),
                'payslip_amount' => $payslipItem ? (float) $payslipItem->total_amount : null,
                'agent_entry_total' => $agentEntry ? (float) $agentEntry->total_amount : null,
                'agent_entry_breakdown' => $agentEntry ? $agentEntryBreakdown : null,
            ];
        });

        $builderErrors = [];
        $missing = $rows->filter(function ($r) {
            return !$r['has_amount']; });
        if ($missing->count() > 0) {
            $builderErrors[] = 'Missing amounts for Jobs: ' . $missing->pluck('job_id')->implode(', ');
        }
        $invoiced = $rows->filter(function ($r) {
            return $r['already_invoiced']; });
        if ($invoiced->count() > 0) {
            $builderErrors[] = 'Already invoiced Jobs: ' . $invoiced->pluck('job_id')->implode(', ');
        }

        // Relaxed policy: do not block invoice creation based on missing client signature

        return view('timesheet.collective.create', [
            'client' => $client,
            'rows' => $rows,
            'builderErrors' => $builderErrors,
            'filters' => [
                'client_name' => $clientName,
                'agent_name' => $agentName,
                'ref' => $ref,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'resultMeta' => $resultMeta,
        ]);
    }

    /**
     * Create a client invoice for a single timesheet row.
     * Supports admin override with extra charges from the modal.
     */
    public function sendInvoice(Request $request, $id)
    {
        $timesheet = Timesheet::with(['interpreter.client.organisation', 'agentOne.user'])->findOrFail($id);
        $job = $timesheet->interpreter;
        $client = optional($job)->client;

        if (!$job || !$client) {
            return back()->with('failure', 'Unable to resolve job/client for this timesheet.');
        }

        // Prevent duplicate invoicing of the same job (allow admin edit/update instead of hard stop)
        $existingItem = ClientInvoiceItem::where('job_id', optional($job)->id)->first();

        $hasAgentSignature = !empty($timesheet->agent_signature);
        $hasVerifiedPaper = $job && ($job->signature_method === 'paper') && ($job->timesheet_status === 'verified');
        $isSameDayCancelled = $job
            && method_exists($job, 'isCancelledSameDay')
            && ($job->statusName === 'cancelled')
            && $job->isCancelledSameDay();

        $isWithin24HoursCancelled = $job
            && method_exists($job, 'isCancelledWithin24Hours')
            && ($job->statusName === 'cancelled')
            && $job->isCancelledWithin24Hours();

        if (!$hasAgentSignature && !$hasVerifiedPaper && !$isSameDayCancelled && !$isWithin24HoursCancelled) {
            return back()->with('failure', 'This timesheet is not ready for invoicing. Please ensure the agent signature is captured or the uploaded paper timesheet is verified.');
        }

        // Validate incoming override fields (all optional)
        $data = $request->validate([
            'admin_override' => ['nullable'],
            'base_amount' => ['nullable', 'numeric', 'min:0'],
            'include_vat' => ['nullable'], // 0/1
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'parking_amount' => ['nullable', 'numeric', 'min:0'],
            'mileage_miles' => ['nullable', 'numeric', 'min:0'],
            'mileage_rate' => ['nullable', 'numeric', 'min:0'],
            // Structured breakdown fields
            'interpreting_minutes' => ['nullable', 'numeric', 'min:0'],
            'interpreting_rate_hour' => ['nullable', 'numeric', 'min:0'],
            'interpreting_ooh_rate_hour' => ['nullable', 'numeric', 'min:0'],
            'use_interpreting_as_base' => ['nullable'],
            'travel_minutes' => ['nullable', 'numeric', 'min:0'],
            'travel_rate_hour' => ['nullable', 'numeric', 'min:0'],
            'travel_from_postcode' => ['nullable', 'string', 'max:255'],
            'travel_to_postcode' => ['nullable', 'string', 'max:255'],
            'charges' => ['nullable', 'array'],
            'charges.*' => ['nullable'],
            'charge_labels' => ['nullable', 'array'],
            'charge_labels.*' => ['nullable'],
        ]);

        // Extract charges from modal hidden inputs (JSON stored in first element)
        $chargeValuesRaw = (array) ($request->input('charges', []));
        $chargeLabelsRaw = (array) ($request->input('charge_labels', []));
        $chargeValues = [];
        $chargeLabels = [];
        if (!empty($chargeValuesRaw)) {
            $first = $chargeValuesRaw[0];
            if (is_string($first)) {
                try {
                    $decoded = json_decode($first, true);
                    if (is_array($decoded)) {
                        $chargeValues = $decoded;
                    }
                } catch (\Throwable $e) {
                }
            } elseif (is_array($first)) {
                $chargeValues = $first;
            }
        }
        if (!empty($chargeLabelsRaw)) {
            $firstL = $chargeLabelsRaw[0];
            if (is_string($firstL)) {
                try {
                    $decodedL = json_decode($firstL, true);
                    if (is_array($decodedL)) {
                        $chargeLabels = $decodedL;
                    }
                } catch (\Throwable $e) {
                }
            } elseif (is_array($firstL)) {
                $chargeLabels = $firstL;
            }
        }

        // Auto-add Travel Time charge if provided via structured fields and not already included
        $travelMinutes = isset($data['travel_minutes']) ? (float) $data['travel_minutes'] : null;
        $travelRateHour = isset($data['travel_rate_hour']) ? (float) $data['travel_rate_hour'] : null;
        $travelCost = null;
        if (is_numeric($travelMinutes) && is_numeric($travelRateHour)) {
            $travelCost = round($travelRateHour * ($travelMinutes / 60), 2);
        }
        $hasTravelLabel = false;
        foreach ($chargeLabels as $lbl) {
            if (strtolower(trim((string) $lbl)) === 'travel time') {
                $hasTravelLabel = true;
                break;
            }
        }
        if (!$hasTravelLabel && $travelCost && $travelCost > 0) {
            $chargeLabels[] = 'Travel Time';
            $chargeValues[] = $travelCost;
        }

        $parkingFromField = (float) ($data['parking_amount'] ?? 0.0);

        $adminOverride = (bool) ($data['admin_override'] ?? false);
        $includeVat = array_key_exists('include_vat', $data)
            ? ((string) $data['include_vat'] === '1' || (bool) $data['include_vat'])
            : true;
        $vatRate = (float) ($data['vat_rate'] ?? 20.0);

        $usedAgentEntryDefaults = false;
        $agentEntryDefaultBase = null;
        if (!$adminOverride && $job && method_exists($job, 'isCancelledSameDay') && $job->statusName === 'cancelled' && $job->isCancelledSameDay()) {
            $agentEntry = AgentJobEntry::where('agent_id', $timesheet->agent_id)
                ->where('job_type', InterpreterJob::class)
                ->where('job_id', (int) $job->id)
                ->orderByDesc('id')
                ->first();

            if ($agentEntry) {
                $agentEntryDefaultBase = (float) ($agentEntry->base_amount ?? 0.0);

                if (empty($chargeLabels) && empty($chargeValues)) {
                    $breakdown = AgentJobEntryExpense::where('agent_job_entry_id', $agentEntry->id)
                        ->orderBy('id', 'asc')
                        ->get();

                    foreach ($breakdown as $row) {
                        $label = trim((string) ($row->type ?? ''));
                        $val = (float) ($row->amount ?? 0);
                        if ($val <= 0) {
                            continue;
                        }
                        $chargeLabels[] = $label !== '' ? $label : 'Expense';
                        $chargeValues[] = $val;
                    }

                    if (empty($chargeLabels) && (float) ($agentEntry->expenses ?? 0) > 0) {
                        $chargeLabels[] = 'Expenses';
                        $chargeValues[] = (float) $agentEntry->expenses;
                    }

                    if ((float) ($agentEntry->other_charges ?? 0) > 0) {
                        $chargeLabels[] = 'Other Charges';
                        $chargeValues[] = (float) $agentEntry->other_charges;
                    }
                }

                $usedAgentEntryDefaults = true;
            }
        }

        $parkingFromLabels = 0.0;
        foreach ($chargeLabels as $idx => $label) {
            $labelNorm = strtolower(trim((string) $label));
            if ($labelNorm === 'parking') {
                $v = isset($chargeValues[$idx]) ? (float) $chargeValues[$idx] : 0.0;
                if ($v >= 0) {
                    $parkingFromLabels = $v;
                }
            }
        }
        // Resolve parking amount from admin field or agent entry labels
        $parkingAmount = (float) max((float) $parkingFromField, (float) $parkingFromLabels);

        $otherChargesTotal = 0.0;
        foreach ($chargeValues as $idx => $v) {
            $labelNorm = strtolower(trim((string) ($chargeLabels[$idx] ?? '')));
            $val = (float) ($v ?? 0);
            if ($val >= 0 && $labelNorm !== 'parking') {
                $otherChargesTotal += $val;
            }
        }

        // If no admin override values provided, default to agent entry totals from the timesheet
        $baseAmount = isset($data['base_amount'])
            ? (float) $data['base_amount']
            : (
                (is_numeric($timesheet->base_amount) && (float) $timesheet->base_amount > 0)
                ? (float) $timesheet->base_amount
                : (float) ($agentEntryDefaultBase ?? 0.0)
            );
        // Optionally compute base amount from interpreting breakdown
        $interpretingMinutes = isset($data['interpreting_minutes']) ? (float) $data['interpreting_minutes'] : null;
        $interpretingRateHour = isset($data['interpreting_rate_hour']) ? (float) $data['interpreting_rate_hour'] : null;
        $interpretingOohRateHour = isset($data['interpreting_ooh_rate_hour']) ? (float) $data['interpreting_ooh_rate_hour'] : null;
        $useInterpretingAsBase = array_key_exists('use_interpreting_as_base', $data)
            ? ((string) $data['use_interpreting_as_base'] === '1' || (bool) $data['use_interpreting_as_base'])
            : false;
        $interpretingNormalMinutes = null;
        $interpretingOohMinutes = null;
        if ($useInterpretingAsBase && is_numeric($interpretingRateHour)) {
            $dateForSplit = null;
            try {
                $dateForSplit = optional($job->appointment_date) ? \Carbon\Carbon::parse($job->appointment_date)->toDateString() : null;
            } catch (\Throwable $e) {
                $dateForSplit = null;
            }

            $startTimeForSplit = $timesheet->agent_start_time ?: ($timesheet->start_time ?: (optional($job)->start_time ?: null));
            $endTimeForSplit = $timesheet->agent_end_time ?: ($timesheet->end_time ?: (optional($job)->end_time ?: null));

            $durationMinutesForSplit = null;
            if (is_numeric($timesheet->agent_duration_hours) || is_numeric($timesheet->agent_duration_minutes)) {
                $durationMinutesForSplit = ((int) ($timesheet->agent_duration_hours ?? 0) * 60) + (int) ($timesheet->agent_duration_minutes ?? 0);
            } elseif (is_numeric($timesheet->duration_hours) || is_numeric($timesheet->duration_minutes)) {
                $durationMinutesForSplit = ((int) ($timesheet->duration_hours ?? 0) * 60) + (int) ($timesheet->duration_minutes ?? 0);
            } elseif ($job) {
                $durationMinutesForSplit = ((int) ($job->duration_hours ?? 0) * 60) + (int) ($job->duration_minutes ?? 0);
            }

            $split = $this->computeOutOfHoursSplitMinutes($dateForSplit, $startTimeForSplit, $endTimeForSplit, $durationMinutesForSplit);
            $splitTotal = (int) ($split['total'] ?? 0);
            $splitNormal = (int) ($split['normal'] ?? 0);
            $splitOoh = (int) ($split['ooh'] ?? 0);

            $minutesForCalc = is_numeric($interpretingMinutes) ? (int) round((float) $interpretingMinutes) : $splitTotal;
            if ($minutesForCalc < 0) {
                $minutesForCalc = 0;
            }
            if (!is_numeric($interpretingMinutes) && $minutesForCalc > 0) {
                $interpretingMinutes = (float) $minutesForCalc;
            }

            $factor = ($splitTotal > 0 && $minutesForCalc > 0) ? ($minutesForCalc / $splitTotal) : 0.0;
            if ($factor > 0) {
                $interpretingNormalMinutes = (int) round($splitNormal * $factor);
                if ($interpretingNormalMinutes < 0) {
                    $interpretingNormalMinutes = 0;
                }
                if ($interpretingNormalMinutes > $minutesForCalc) {
                    $interpretingNormalMinutes = $minutesForCalc;
                }
                $interpretingOohMinutes = $minutesForCalc - $interpretingNormalMinutes;
            } else {
                $interpretingNormalMinutes = $minutesForCalc;
                $interpretingOohMinutes = 0;
            }

            $computedBase = null;
            if (is_numeric($interpretingOohRateHour) && $interpretingOohRateHour >= 0 && ($interpretingOohMinutes ?? 0) > 0) {
                $computedBase = round(
                    ($interpretingRateHour * (($interpretingNormalMinutes ?? 0) / 60))
                    + ($interpretingOohRateHour * (($interpretingOohMinutes ?? 0) / 60)),
                    2
                );
            } elseif ($minutesForCalc > 0) {
                $computedBase = round($interpretingRateHour * ($minutesForCalc / 60), 2);
            }

            if (is_numeric($computedBase) && $computedBase > 0) {
                $baseAmount = (float) $computedBase;
            }
        }
        if (!$adminOverride && empty($chargeLabels)) {
            // Only fall back to timesheet-level expenses when no structured
            // charge_labels exist. When charge_labels are present they already
            // contain the itemised breakdown (travel, parking, etc.) and adding
            // timesheet totals on top would double-count.
            $otherChargesTotal += (float) ($timesheet->expenses_amount ?? 0);
            $otherChargesTotal += (float) ($timesheet->surcharge ?? 0);
            $otherChargesTotal += (float) ($timesheet->other_charges ?? 0);
        }

        // Compute dates
        $appointmentDateStr = null;
        try {
            $appointmentDateStr = optional($job->appointment_date) ? \Carbon\Carbon::parse($job->appointment_date)->toDateString() : now()->toDateString();
        } catch (\Throwable $e) {
            $appointmentDateStr = now()->toDateString();
        }

        // Totals
        $subtotal = round($baseAmount + $otherChargesTotal + $parkingAmount, 2);
        $vatAmount = $includeVat ? round($subtotal * ($vatRate / 100), 2) : 0.0;
        $total = round($subtotal + $vatAmount, 2);

        $mileageMiles = isset($data['mileage_miles']) ? (float) $data['mileage_miles'] : null;
        $mileageRate = isset($data['mileage_rate']) ? (float) $data['mileage_rate'] : null;
        $travelFromPostcode = $data['travel_from_postcode'] ?? null;
        $travelToPostcode = $data['travel_to_postcode'] ?? null;

        $metaSource = $adminOverride ? 'timesheet_admin_override' : ($usedAgentEntryDefaults ? 'timesheet_agent_entry' : 'timesheet_default');

        return DB::transaction(function () use ($client, $job, $timesheet, $appointmentDateStr, $includeVat, $vatRate, $subtotal, $vatAmount, $total, $baseAmount, $otherChargesTotal, $parkingAmount, $chargeLabels, $chargeValues, $mileageMiles, $mileageRate, $interpretingMinutes, $interpretingRateHour, $interpretingOohRateHour, $interpretingNormalMinutes, $interpretingOohMinutes, $useInterpretingAsBase, $travelMinutes, $travelRateHour, $travelFromPostcode, $travelToPostcode, $existingItem, $metaSource, $parkingFromField, $parkingFromLabels) {
            if ($existingItem) {
                $invoice = $existingItem->invoice;
                if (!$invoice) {
                    throw new \RuntimeException('Existing invoice item found but parent invoice missing.');
                }
                if (strtolower((string) $invoice->status) === 'paid') {
                    return redirect()->route('invoices.admin.client.preview', ['id' => $invoice->id])
                        ->with('error', 'Cannot edit a paid invoice.');
                }

                // Merge metadata so admins don't need to re-enter all summary items on edits
                $prevMd = is_array($existingItem->metadata ?? null) ? $existingItem->metadata : [];
                $mergedMd = array_merge($prevMd, [
                    'timesheet_id' => $timesheet->id,
                    'source' => $metaSource,
                    'date' => $appointmentDateStr ?: ($prevMd['date'] ?? null),
                    'job_type' => 'App\\InterpreterJob',
                    'charge_labels' => !empty($chargeLabels) ? $chargeLabels : ($prevMd['charge_labels'] ?? []),
                    'charge_values' => !empty($chargeValues) ? $chargeValues : ($prevMd['charge_values'] ?? []),
                    'mileage_miles' => $mileageMiles ?? ($prevMd['mileage_miles'] ?? null),
                    'mileage_rate' => $mileageRate ?? ($prevMd['mileage_rate'] ?? null),
                    'interpreting_time_minutes' => $interpretingMinutes ?? ($prevMd['interpreting_time_minutes'] ?? null),
                    'interpreting_rate_hour' => $interpretingRateHour ?? ($prevMd['interpreting_rate_hour'] ?? null),
                    'interpreting_ooh_rate_hour' => $interpretingOohRateHour ?? ($prevMd['interpreting_ooh_rate_hour'] ?? null),
                    'interpreting_normal_minutes' => $interpretingNormalMinutes ?? ($prevMd['interpreting_normal_minutes'] ?? null),
                    'interpreting_ooh_minutes' => $interpretingOohMinutes ?? ($prevMd['interpreting_ooh_minutes'] ?? null),
                    'use_interpreting_as_base' => $useInterpretingAsBase ?? ($prevMd['use_interpreting_as_base'] ?? false),
                    'travel_time_minutes' => $travelMinutes ?? ($prevMd['travel_time_minutes'] ?? null),
                    'travel_rate_hour' => $travelRateHour ?? ($prevMd['travel_rate_hour'] ?? null),
                    'travel_from_postcode' => $travelFromPostcode ?? ($prevMd['travel_from_postcode'] ?? null),
                    'travel_to_postcode' => $travelToPostcode ?? ($prevMd['travel_to_postcode'] ?? null),
                    // Preserve any prior explicit parking metadata for display only
                    'parking_amount' => $prevMd['parking_amount'] ?? null,
                    // Preserve arbitrary header summary items and billing overrides
                    'header_items' => isset($prevMd['header_items']) && is_array($prevMd['header_items']) ? $prevMd['header_items'] : [],
                    'bill_to_override' => $prevMd['bill_to_override'] ?? null,
                    // Preserve per-item identifiers that may have been set in admin modal
                    'client_reference' => $prevMd['client_reference'] ?? null,
                    'file_reference' => $prevMd['file_reference'] ?? null,
                    'patient_number' => $prevMd['patient_number'] ?? null,
                    'pid' => $prevMd['pid'] ?? null,
                ]);

                $existingItem->update([
                    'amount' => (float) $baseAmount,
                    'surcharge' => 0.0,
                    'other_charges' => (float) $otherChargesTotal,
                    'vat_applicable' => $includeVat,
                    'metadata' => $mergedMd,
                ]);

                $invoice->include_vat = array_key_exists('include_vat', request()->all()) ? $includeVat : (bool) $invoice->include_vat;
                $invoice->vat_rate = array_key_exists('vat_rate', request()->all()) ? $vatRate : (float) $invoice->vat_rate;
                $invoice->date_range_start = $appointmentDateStr;
                $invoice->date_range_end = $appointmentDateStr;
                // Include parking in invoice-level totals and store for display
                $invoice->parking_amount = (float) $parkingAmount;

                $items = $invoice->items()->get();
                $itemsSubtotal = 0.0;
                foreach ($items as $it) {
                    $itemsSubtotal += (float) $it->amount + (float) $it->other_charges + (float) $it->surcharge;
                }
                $invoiceSubtotal = round($itemsSubtotal + (float) $parkingAmount, 2);
                $invoiceVat = $includeVat ? round($invoiceSubtotal * ($vatRate / 100), 2) : 0.0;
                $invoiceTotal = round($invoiceSubtotal + $invoiceVat, 2);
                $invoice->subtotal = $invoiceSubtotal;
                $invoice->vat_amount = $invoiceVat;
                $invoice->total = $invoiceTotal;
                $invoice->save();

                try {
                    AuditLog::create([
                        'user_id' => optional(\Illuminate\Support\Facades\Auth::user())->id,
                        'actor_id' => optional(\Illuminate\Support\Facades\Auth::user())->id,
                        'action' => 'timesheet_client_invoice_updated',
                        'entity_type' => 'ClientInvoice',
                        'entity_id' => $invoice->id,
                        'metadata' => [
                            'timesheet_id' => $timesheet->id,
                            'job_id' => optional($job)->id,
                            'invoice_id' => $invoice->id,
                            'subtotal' => $invoiceSubtotal,
                            'vat' => $invoiceVat,
                            'total' => $invoiceTotal,
                        ],
                        'ip_address' => request()->ip(),
                    ]);
                } catch (\Throwable $e) {
                }

                return redirect()->route('invoices.admin.client.preview', ['id' => $invoice->id])
                    ->with('success', 'Client invoice amount updated successfully.');
            }

            $invoice = ClientInvoice::create([
                'client_id' => optional($client)->id,
                'company_id' => optional(optional($client)->organisation)->company_id,
                'invoice_number' => 'CI-TMP-' . uniqid(),
                'invoice_date' => now()->toDateString(),
                'date_range_start' => $appointmentDateStr,
                'date_range_end' => $appointmentDateStr,
                'template' => 'v2',
                'include_vat' => $includeVat,
                'vat_rate' => $vatRate,
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total' => $total,
                'status' => 'draft',
                // Persist invoice-level parking amount for display and totals
                'parking_amount' => (float) $parkingAmount,
                'file_reference' => optional($job)->file_reference,
                'client_reference' => optional($job)->client_reference,
                'patient_number' => optional($job)->patient_number,
                'pid' => optional($job)->personal_identity_number,
            ]);

            $invoice->invoice_number = 'CI-' . str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT);
            $invoice->save();

            $clientName = optional(optional($job)->client)->user ? optional(optional($job)->client)->user->getFullName() : null;
            $desc = $clientName ? ('Interpreting for ' . $clientName) : ('Interpreting service for Job #' . optional($job)->id);
            ClientInvoiceItem::create([
                'client_invoice_id' => $invoice->id,
                'job_id' => optional($job)->id,
                'description' => $desc,
                'amount' => (float) $baseAmount,
                'surcharge' => 0.0,
                'other_charges' => (float) $otherChargesTotal,
                'vat_applicable' => $includeVat,
                'metadata' => [
                    'timesheet_id' => $timesheet->id,
                    'source' => $metaSource,
                    'date' => $appointmentDateStr,
                    'job_type' => 'App\\InterpreterJob',
                    'charge_labels' => $chargeLabels,
                    'charge_values' => $chargeValues,
                    'mileage_miles' => $mileageMiles,
                    'mileage_rate' => $mileageRate,
                    'interpreting_time_minutes' => $interpretingMinutes,
                    'interpreting_rate_hour' => $interpretingRateHour,
                    'interpreting_ooh_rate_hour' => $interpretingOohRateHour,
                    'interpreting_normal_minutes' => $interpretingNormalMinutes,
                    'interpreting_ooh_minutes' => $interpretingOohMinutes,
                    'use_interpreting_as_base' => $useInterpretingAsBase,
                    'travel_time_minutes' => $travelMinutes,
                    'travel_rate_hour' => $travelRateHour,
                    'travel_from_postcode' => $travelFromPostcode,
                    'travel_to_postcode' => $travelToPostcode,
                    // Persist per-item parking display value for templates
                    'parking_amount' => ($parkingFromField > 0 ? (float) $parkingFromField : ($parkingFromLabels > 0 ? (float) $parkingFromLabels : null)),
                    'client_reference' => optional($job)->client_reference,
                    'file_reference' => optional($job)->file_reference,
                    'patient_number' => optional($job)->patient_number,
                    'pid' => optional($job)->personal_identity_number,
                ],
            ]);

            try {
                AuditLog::create([
                    'user_id' => optional(\Illuminate\Support\Facades\Auth::user())->id,
                    'actor_id' => optional(\Illuminate\Support\Facades\Auth::user())->id,
                    'action' => 'timesheet_client_invoice_created',
                    'entity_type' => 'ClientInvoice',
                    'entity_id' => $invoice->id,
                    'metadata' => [
                        'timesheet_id' => $timesheet->id,
                        'job_id' => optional($job)->id,
                        'invoice_id' => $invoice->id,
                        'subtotal' => $subtotal,
                        'vat' => $vatAmount,
                        'total' => $total,
                    ],
                    'ip_address' => request()->ip(),
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create audit log for timesheet invoice: ' . $e->getMessage());
            }

            return redirect()->route('invoices.admin.client.preview', ['id' => $invoice->id])
                ->with('success', 'Client invoice created successfully.');
        });
    }

    /**
     * Redirect to collective builder with current filters for batch invoicing.
     */
    public function sendInvoiceBatch(Request $request)
    {
        $query = array_filter([
            'client_name' => $request->input('client_name'),
            'agent_name' => $request->input('agent_name'),
            'ref' => $request->input('ref'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'company_id' => $request->input('company_id'),
        ], function ($v) {
            return !empty($v); });

        return redirect()->route('timesheet.collective.create', $query);
    }
}
