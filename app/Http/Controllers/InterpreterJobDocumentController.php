<?php
#

namespace App\Http\Controllers;
use App\Helpers\RoleHelper;
use App\InterpreterJob;
use App\TravelExpences;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\User;
use App\Mail\JobMail;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class InterpreterJobDocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(InterpreterJob $interpreterJob)
    {
        $travel = TravelExpences::where('job_id',$interpreterJob->id)->first();
        return view('interpreter-jobs.documents.index', [
            'interpreterJob' => $interpreterJob,'travel' => $travel,
        ]);
    }

    /**
     * Dedicated Timesheets view to avoid showing uploaded timesheets under Travel Expenses
     * on the admin/client side.
     */
    public function timesheets(InterpreterJob $interpreterJob)
    {
        return view('interpreter-jobs.timesheets.index', [
            'interpreterJob' => $interpreterJob,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, InterpreterJob $interpreterJob)
    {
        $request->validate([
            'file' => [ 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480' ],
            'document_type' => ['required', Rule::in(array_values((array) config('enums.document_types')))],
        ]);

        $disk = env('INTERPRETER_DOCS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        $documentUrl = $request->file('file')->store("interpreter-jobs/$interpreterJob->id", $disk);
        $documentName = $request->file('file')->getClientOriginalName();

        $existingDocument = $interpreterJob->documents->firstWhere('type', $request->document_type);
        $multiFileTypes = [
            config('enums.document_types')['translated_file'],
            config('enums.document_types')['travel_expenses'],
        ];
        $isMultiFile = in_array((int)($existingDocument->type ?? $request->document_type), $multiFileTypes, true);

        if ($existingDocument && !$isMultiFile) {
            // Safely resolve previous file path from the existing document without assuming a property/method
            $prevUrl = data_get($existingDocument, 'url');
            if (!empty($prevUrl)) {
                Storage::disk($disk)->delete($prevUrl);
            }
        }

        // Persist the document and capture the model for linking to job
        $documentModel = null;
        if ($isMultiFile) {
            $documentModel = $interpreterJob->documents()->create([
                'type' => $request->document_type,
                'url' => $documentUrl,
                'name' => $documentName,
            ]);
        } else {
            $documentModel = $interpreterJob->documents()->updateOrCreate(
                [
                    'type' => $request->document_type
                ],
                [
                    'url' => $documentUrl,
                    'name' => $documentName,
                ]
            );
        }

        // If uploading a signed/scan timesheet, optionally persist provided metadata to the Timesheet
        try {
            $signedTimesheetType = config('enums.document_types')['signed_timesheet'] ?? null;
            if ((int)$request->document_type === (int)$signedTimesheetType) {
                $timesheet = $interpreterJob->timesheet;
                // Auto-create timesheet if missing
                if (!$timesheet) {
                    $timesheet = new \App\Timesheet();
                    $timesheet->job_id = $interpreterJob->id;
                    $timesheet->agent_id = $interpreterJob->agent_id ?: optional(auth()->user()->agent)->id;
                    $timesheet->status = 'Y';
                }

                if ($timesheet) {
                    $fields = ['agent_start_time','agent_end_time','client_name','client_phone','client_designation'];
                    $data = [];
                    foreach ($fields as $field) {
                        if ($request->filled($field)) {
                            $value = $request->input($field);
                            if (in_array($field, ['agent_start_time','agent_end_time'], true)) {
                                try {
                                    $value = Carbon::parse($value)->format('H:i');
                                } catch (\Exception $e) {
                                    // Ignore parsing errors and keep original value
                                }
                            }
                            $data[$field] = $value;
                        }
                    }
                    // Always save the timesheet (new or updated)
                    $timesheet->fill($data);
                    $timesheet->save();
                }

                // Update job paper workflow fields
                if ($documentModel) {
                    $interpreterJob->signature_method = 'paper';
                    $interpreterJob->timesheet_status = 'signed_uploaded';
                    $interpreterJob->timesheet_document_id = $documentModel->id;
                    $interpreterJob->timesheet_uploaded_by = Auth::id();
                    $interpreterJob->timesheet_uploaded_at = now();
                    $interpreterJob->save();

                    try {
                        $to = config('app.to.address') ?: 'bookings@atozinterpreting.com';
                        if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                            Mail::to($to)->send(new JobMail(
                                $interpreterJob,
                                'emails.admin.job-timesheet-sign',
                                'Timesheet uploaded: pending verification',
                                'admin'
                            ));
                        } else {
                            Log::warning('Timesheet upload notification skipped: invalid TO_EMAIL configuration');
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Admin timesheet upload email failed: '.$e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            // Do not interrupt upload response on metadata persistence issues
            // You may log this if desired: logger()->warning('Timesheet metadata persistence failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'fullUrl' => optional($documentModel)->fullUrl ?: $documentUrl,
            'name' => $documentName,
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
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function travel_details(Request $request){


        $validatedData = $request->validate([
            'start_time' => 'required',
            'end_time' => 'required',
            'date' => 'required',
            'file' => 'required'
        ]);

        $travelEx = TravelExpences::where('job_id', $request['job_id'])->first();
    
        if (!$travelEx) {
            // If no travelEx exists, create a new one
            $travelEx = new TravelExpences();
            $travelEx->job_id = $request['job_id'];
            $travelEx->agent_id = $request['agent_id'];
        }
    
        // Update or set the travelEx attributes
        $travelEx->travel_start_time = $validatedData['start_time'];
        $travelEx->travel_end_time = $validatedData['end_time'];
        $travelEx->travel_amount = $request['amount'];
        $travelEx->travel_date = $validatedData['date'];
    
        // Save the travelEx (new or updated)
        $travelEx->save();
    
        return redirect()->back()->with('success', 'Travel Details Updated successfully.');
    }

    /**
     * Admin-only: Verify an uploaded paper timesheet for a specific Interpreter Job.
     * Moves the job timesheet_status from 'signed_uploaded' to 'verified' and records verifier metadata.
     */
    public function verifyTimesheet(Request $request, InterpreterJob $interpreterJob)
    {
        $user = auth()->user();
        // Use RoleHelper checks to avoid static analyzer complaints about dynamic role methods
        $isAdmin = RoleHelper::hasRole($user, 'admin') || RoleHelper::hasRole($user, 'super-admin');
        if (!$user || !$isAdmin) {
            return back()->with('error', 'Only administrators can verify paper timesheets.');
        }

        // Ensure this is a paper workflow and a signed timesheet was uploaded
        if (($interpreterJob->signature_method ?? null) !== 'paper') {
            return back()->with('error', 'This job is not using the paper timesheet workflow.');
        }

        $signedTimesheetType = config('enums.document_types')['signed_timesheet'] ?? null;
        $uploadedSigned = $interpreterJob->documents->firstWhere('type', $signedTimesheetType);
        if (!$uploadedSigned) {
            return back()->with('error', 'No uploaded timesheet found to verify.');
        }

        // Only verify if currently in signed_uploaded state
        if (($interpreterJob->timesheet_status ?? null) !== 'signed_uploaded') {
            return back()->with('error', 'This timesheet cannot be verified in its current state.');
        }

        $interpreterJob->timesheet_status = 'verified';
        $interpreterJob->timesheet_verified_by = auth()->id();
        $interpreterJob->timesheet_verified_at = now();
        $interpreterJob->save();

        // Audit log (ignore failures)
        try {
            \App\AuditLog::create([
                'user_id' => optional($user)->id,
                'actor_id' => optional($user)->id,
                'action' => 'timesheet_verified',
                'entity_type' => 'InterpreterJob',
                'entity_id' => $interpreterJob->id,
                'metadata' => [
                    'signature_method' => 'paper',
                    'previous_status' => 'signed_uploaded',
                    'new_status' => 'verified',
                    'signed_document_id' => optional($uploadedSigned)->id,
                ],
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $e) { /* ignore audit failures */ }

        return back()->with('success', 'Paper timesheet verified successfully.');
    }

    public function cancelSignedTimesheetUpload(Request $request, InterpreterJob $interpreterJob)
    {
        $user = auth()->user();
        $isAgent = RoleHelper::hasRole($user, 'agent') || RoleHelper::hasRole($user, 'new-agent');
        if (!$user || !$isAgent) {
            $message = 'Only agents can remove uploaded timesheets.';
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 403);
            }
            return back()->with('error', $message);
        }

        $agent = optional($user)->agent;
        if (!$agent) {
            $message = 'Agent profile not found.';
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 403);
            }
            return back()->with('error', $message);
        }

        $isAssigned = (int) ($interpreterJob->agent_id ?? 0) === (int) $agent->id;
        $isMatched = $interpreterJob->matchedAgents()->where('agent_id', $agent->id)->exists();
        if (!$isAssigned && !$isMatched) {
            $message = 'You are not allowed to modify this job.';
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 403);
            }
            return back()->with('error', $message);
        }

        if (($interpreterJob->timesheet_status ?? null) === 'verified') {
            $message = 'Verified timesheets cannot be removed.';
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            return back()->with('error', $message);
        }

        $signedTimesheetType = config('enums.document_types')['signed_timesheet'] ?? null;
        $documentId = (int) $request->input('document_id');
        $query = $interpreterJob->documents()->where('type', $signedTimesheetType);
        if ($documentId > 0) {
            $query->where('id', $documentId);
        }
        $uploadedSigned = $query->latest('id')->first();
        if (!$uploadedSigned) {
            $message = 'No uploaded timesheet found to remove.';
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 404);
            }
            return back()->with('error', $message);
        }

        $disk = env('INTERPRETER_DOCS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        if (!empty($uploadedSigned->url)) {
            Storage::disk($disk)->delete($uploadedSigned->url);
        }

        $deletedId = (int) $uploadedSigned->id;
        $uploadedSigned->delete();

        $hasSignedTimesheet = $interpreterJob->documents()
            ->where('type', $signedTimesheetType)
            ->exists();

        if (!$hasSignedTimesheet) {
            if ((int) ($interpreterJob->timesheet_document_id ?? 0) === $deletedId) {
                $interpreterJob->timesheet_document_id = null;
            }
            if (($interpreterJob->signature_method ?? null) === 'paper') {
                $interpreterJob->signature_method = null;
            }
            $interpreterJob->timesheet_status = null;
            $interpreterJob->timesheet_uploaded_by = null;
            $interpreterJob->timesheet_uploaded_at = null;
            $interpreterJob->timesheet_verified_by = null;
            $interpreterJob->timesheet_verified_at = null;
            $interpreterJob->save();
        }

        $message = 'Uploaded timesheet removed.';
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }
        return back()->with('success', $message);
    }
}
