<?php

namespace App\Http\Controllers;

use App\Skill;
use App\User;
use App\Client;
use App\Agent;
use App\Company;
use App\Language;
use App\InterpreterType;
use App\Exports\ExportJobData;
use App\SecurityType;
use App\Mail\JobMail;
use App\AllUpdates;
use App\InterpreterJob;
use App\TranslatorJob;
use App\Jobs\MatchAgents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Http\Requests\InterpreterJobRequest;
use App\Traits\LogsUpdates;
use App\Exports\ExportData;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // Retrieve agent users for filters
        $users = User::role('agent')->get()->mapWithKeys(function ($user) {
            return [$user->id => $user->full_name];
        });

        // Build query for Interpreter Jobs
        $interpreterJobsQuery = InterpreterJob::hasEnabledUser()
            ->with([
                'toLanguage',
                'matchedAgents',
                'client.user',
                'agent.user',
                'client.organisation.company',
                'cancellation:id,job_id,job_type,message',
                'feedback',
                // Ensure timesheet relation is loaded for interpreter jobs
                'timesheet',
            ])
            ->showableToUser(auth()->user())
            ->filter($request->only(
                'date',
                'search',
                'language_id',
                'status',
                'company',
                'client',
                'require_qualified',
                'agents',
                // Additional filters
                'dna',
                'retrn',
                'bulk_id',
                'timesheet_verified',
            ))->select(
                'id',
                'skill_id',
                'client_id',
                'agent_id',
                'from_language_id',
                'to_language_id',
                DB::raw('NULL as word_count'),
                'appointment_date',
                DB::raw('NULL as target_date'),
                'duration_hours',
                'duration_minutes',
                'created_at',
                'start_time',
                'bulk_id',
                'status',
                // Paper timesheet workflow fields
                'signature_method',
                'timesheet_status',
                DB::raw('"interpreter" as job_type'),
            );

        // Build query for Translator Jobs
        $translatorJobsQuery = TranslatorJob::hasEnabledUser()
            ->with([
                'toLanguage',
                'matchedAgents',
                'client.user',
                'agent.user',
                'client.organisation.company',
            ])
            ->showableToUser(auth()->user())
            ->filter($request->only(
                'date',
                'search',
                'language_id',
                'status',
                'company',
                'client',
                'require_qualified',
                'agents',
                // Pass-through filters (timesheet filter will have no effect on translator jobs)
                'dna',
                'retrn',
                'bulk_id',
                'timesheet_verified'
            ))->select(
                'id',
                'skill_id',
                'client_id',
                'agent_id',
                'from_language_id',
                'to_language_id',
                'word_count',
                'target_date',
                DB::raw('NULL as duration_hours'),
                DB::raw('NULL as duration_minutes'),
                DB::raw('NULL as appointment_date'),
                'created_at',
                DB::raw('NULL as start_time'),
                DB::raw('NULL as bulk_id'),
                'status',
                // Align union columns for timesheet fields (not applicable to translator jobs)
                DB::raw('NULL as signature_method'),
                DB::raw('NULL as timesheet_status'),
                DB::raw('"translator" as job_type'),
            );
        $countInterpreterJob = $interpreterJobsQuery->count();
        $countTranslatorJob = $translatorJobsQuery->count();
        // Merge both queries using a union
        $combinedQuery = $interpreterJobsQuery->union($translatorJobsQuery);
        // Count combined results
        $count = $combinedQuery->count();


        // Get paginated results
        $jobs = $combinedQuery->orderBy('id', 'DESC')->paginate(10);
        //dd($jobs);
        // Return view with all necessary data
        return view('reports.index', [
            'jobs' => $jobs,
            'count' => $count,
            'countInterpreterJob' => $countInterpreterJob,
            'countTranslatorJob' => $countTranslatorJob,
            'languages' => Language::orderBy('name')->pluck('name', 'id'),
            'companies' => Company::orderBy('name')->pluck('name', 'id'),
            'clients' => Client::fullNames(),
            'statuses' => config('enums.statuses'),
            'require_qualified' => InterpreterType::orderBy('id')->pluck('name', 'id'),
            'bulk_ids' => InterpreterJob::whereNotNull('bulk_id')
                        ->select('bulk_id')
                        ->distinct()
                        ->orderBy('bulk_id')
                        ->pluck('bulk_id', 'bulk_id'),
            'agents' => $users,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $request->only(
            'date',
            'search',
            'language_id',
            'status',
            'company',
            'client',
            'require_qualified',
            'agents',
            'dna',
            'retrn',
            'timesheet_verified'
        );

        return Excel::download(new ExportJobData($filters), 'InterpreterAndTranslator_jobs.xlsx');
    }
}
