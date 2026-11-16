<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Schema;
use App\Helpers\RoleHelper;
use App\Skill;
use App\Client;
use App\Company;
use App\InterpreterType;
use App\Language;
use App\Mail\JobMail;
use App\TranslatorJob;
use App\Jobs\MatchAgents;
use Illuminate\Http\Request;
use App\AllUpdates;
use App\Traits\LogsUpdates;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Http\Requests\TranslatorJobRequest;
use App\Exports\TranslatorJobsExport;
use App\Exports\ExportData;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TranslatorJobController extends Controller
{
    use LogsUpdates;
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 20), 50);
        $user = auth()->user();
        $isAgent = $user ? RoleHelper::hasRole($user, 'agent') : false;
        $isAdmin = $user ? RoleHelper::hasRole($user, 'admin') : false;
        $agentId = optional(optional($user)->agent)->id;
        
        $baseQuery = TranslatorJob::hasEnabledUser()
            ->visibleToUser(auth()->user())
            ->filter($request->only(
                'date',
                'search',
                'language_id',
                'status',
                'company',
                'client'
                // 'require_qualified'
            ));

        $with = $isAgent
            ? [
                'fromLanguage:id,name,deleted_at',
                'toLanguage:id,name,deleted_at',
                'matchedAgents:id,job_id,agent_id,status',
                'agentJobEntry:id,job_id,job_type,agent_id,total_amount',
            ]
            : [
                'fromLanguage:id,name,deleted_at',
                'toLanguage:id,name,deleted_at',
                'matchedAgents:id,job_id,agent_id,status,distance',
                'matchedAgents.agent:id,user_id',
                'matchedAgents.agent.user:id,first_name,last_name',
                'client.user:id,first_name,last_name',
                'agent.user:id,first_name,last_name',
                'client.organisation.company:id,name',
                'cancellation:id,message',
                'agentJobEntry:id,job_id,job_type,agent_id,total_amount',
            ];

        $jobsQuery = $baseQuery
            ->with($with)
            ->orderBy('created_at', 'DESC');

        $forceSimple = (bool) env('PAGINATE_SIMPLE', false);
        $useSimple = ($isAgent || $forceSimple);
        if ($useSimple) {
            $count = (clone $baseQuery)->toBase()->getCountForPagination();
            $jobs = $jobsQuery->simplePaginate($perPage);
        } else {
            $jobs = $jobsQuery->paginate($perPage);
            $count = $jobs->total();
        }
        $firstItem = $jobs->count() > 0
            ? (($jobs->currentPage() - 1) * $jobs->perPage() + 1)
            : 0;
        $lastItem = $jobs->count() > 0
            ? (($jobs->currentPage() - 1) * $jobs->perPage() + $jobs->count())
            : 0;
        
        // Build claimable (cancelled today) list for agents
        $claimableCancelledToday = collect();
        if ($isAgent && $agentId) {
            $nowLondon = Carbon::now('Europe/London');
            $startUtc = $nowLondon->copy()->startOfDay()->timezone('UTC')->toDateTimeString();
            $endUtc = $nowLondon->copy()->endOfDay()->timezone('UTC')->toDateTimeString();
            $todayLondon = $nowLondon->toDateString();

            $alreadyClaimedJobIds = \App\AgentJobEntry::where('agent_id', $agentId)
                ->where('job_type', 'App\\TranslatorJob')
                ->pluck('job_id');

            $claimableCancelledToday = TranslatorJob::with([
                'fromLanguage:id,name,deleted_at',
                'toLanguage:id,name,deleted_at',
                'assignedMatched:id,job_id,job_type,agent_id,status',
            ])
                ->where('status', 2)
                ->where('target_date', $todayLondon)
                ->whereBetween('cancelled_at', [$startUtc, $endUtc])
                ->whereHas('assignedMatched', function ($q) use ($agentId) {
                    $q->where('agent_id', $agentId);
                })
                ->when($alreadyClaimedJobIds->count() > 0, function ($q) use ($alreadyClaimedJobIds) {
                    $q->whereNotIn('id', $alreadyClaimedJobIds);
                })
                ->orderByDesc('cancelled_at')
                ->get();
        }
            
        $languages = Cache::remember('languages_list', 600, function () {
            return Language::orderBy('name')->pluck('name', 'id');
        });
        $companies = $isAdmin
            ? Cache::remember('companies_list', 600, function () {
                return Company::orderBy('name')->pluck('name', 'id');
            })
            : collect([]);
        $clients = $isAdmin
            ? Cache::remember('clients_fullnames', 600, function () {
                return Client::fullNames();
            })
            : collect([]);

        return view('translator-jobs.index', [
            'jobs' => $jobs,
            'count' => $count,
            'firstItem' => $firstItem,
            'lastItem' => $lastItem,
            'languages' => $languages,
            'companies' => $companies,
            'clients' => $clients,
            'statuses' => config('enums.statuses'),
            // Additional data for agents: claimable same-day cancelled jobs
            'claimable_cancelled_today' => $claimableCancelledToday,
            //  'require_qualified' => InterpreterType::where('id','=','5')
            // ->orderBy('id')
            // ->pluck('name', 'id'),
        ]);
    }

    public function create()
    {
        return view('translator-jobs.create', [
            'languages' => Language::orderBy('name')->pluck('name', 'id'),
            'clients' => Client::fullNames(),
            'skills' => Skill::where('type', 1)->pluck('skill', 'id'),
        ]);
    }


    public function allupdates(TranslatorJob $translatorJob)
    {

        $job_id = $translatorJob->id;
        return view('translator-jobs.allupdates.index', [
                'translatorJob' => $translatorJob,
            'job_update' => AllUpdates::where([
                ['job_id', $job_id],
                ['job_type', 'translator-jobs'],
                ['deleted','N']])
            ->with(['user:id,first_name,last_name'])->get()


        ]);
    }
    public function store(TranslatorJobRequest $request)
    {
        $data = $request->validated();
        $identity = [
            'client_id' => $data['client_id'] ?? null,
            'target_date' => $data['target_date'] ?? null,
            'from_language_id' => $data['from_language_id'] ?? null,
            'to_language_id' => $data['to_language_id'] ?? null,
            'word_count' => $data['word_count'] ?? null,
            'client_reference' => $data['client_reference'] ?? null,
            'title' => $data['title'] ?? null,
        ];
        $hash = sha1(json_encode($identity));
        $cacheKey = 'tj_submit_' . $hash;
        if (!Cache::add($cacheKey, '1', 60)) {
            $existing = TranslatorJob::where('client_id', $identity['client_id'])
                ->whereDate('target_date', $identity['target_date'])
                ->where('from_language_id', $identity['from_language_id'])
                ->where('to_language_id', $identity['to_language_id'])
                ->where('word_count', $identity['word_count'])
                ->where('client_reference', $identity['client_reference'])
                ->where('title', $identity['title'])
                ->where('created_at', '>=', now()->subMinutes(1))
                ->latest('id')->first();
            if ($existing && !$request->boolean('allow_duplicate')) {
                return back()->with('failure', 'A similar translation job (#' . $existing->id . ') was created less than 1 minute ago. Tick "Create duplicate" to proceed.')->withInput();
            }
        }
        $job = TranslatorJob::create($data);
        Cache::put($cacheKey, (string) $job->id, 60);
        if ($request->filled('documents')) {
            $job->documents()->createMany($request->documents);
        }
        return redirect()->route('translator-jobs.index')->with('success', 'Created translator job');
    }

    public function show(TranslatorJob $translatorJob)
    {
        return view('translator-jobs.show', [
            'translatorJob' => $translatorJob,
        ]);
    }

    public function edit(TranslatorJob $translatorJob)
    {
        return view('translator-jobs.edit', [
            'translatorJob' => $translatorJob,
            'languages' => Language::orderBy('name')->pluck('name', 'id'),
            'clients' => Client::fullNames(),
            'skills' => Skill::where('type', 1)->pluck('skill', 'id'),
        ]);
    }

    public function update(TranslatorJobRequest $request, TranslatorJob $translatorJob)
    {
        // Safely attach any uploaded documents
        if ($request->filled('documents')) {
            try {
                $translatorJob->documents()->createMany($request->documents);
                $translatorJob->document_uploaded = true;
                $translatorJob->setUpdatedAt($translatorJob->freshTimestamp());
            } catch (\Throwable $e) {
                Log::error('Failed to attach translator documents for job '.$translatorJob->id.': '.$e->getMessage());
            }
        }

        $translatorJob->fill($request->validated());

        if (!$translatorJob->isDirty()) {
            return back()->with('success', 'Updated translator job');
        }

        if (!$translatorJob->agent) {
            $this->logUpdate(
                $translatorJob->id,
                'translator-jobs',
                auth()->user()->id,
                $translatorJob->agent_id,
                $translatorJob->status,
                'Job is updated'
            );

            $translatorJob->save();

            MatchAgents::dispatch($translatorJob);

            return back()->with('success', 'Updated translator job');
        }

        if (!$translatorJob->shouldBeRematched()) {

            $translatorJob->save();

            optional($translatorJob->assignedMatched)->update(['status' => 0]);
            $this->logUpdate(
                $translatorJob->id,
                'translator-jobs',
                auth()->user()->id,
                $translatorJob->agent_id,
                $translatorJob->status,
                'Should be Rematched'
            );

            return back()->with('success', 'Updated translator job');
        }

        $translatorJob->status = 0;

        // Wrap mail in try/catch to avoid 500 on mail transport failures
        try {
            Mail::to($translatorJob->agent->user)->send(new JobMail(
                $translatorJob,
                'emails.agents.translator-job-cancelled',
                'Job cancelled',
                'agent'
            ));
        } catch (\Exception $e) {
            Log::error('Failed to send translator job cancelled email to agent (job '.$translatorJob->id.'): '.$e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Failed to send translator job cancelled email to agent (job '.$translatorJob->id.'): '.$e->getMessage());
        }
        $this->logUpdate(
            $translatorJob->id,
            'translator-jobs',
            auth()->user()->id,
            $translatorJob->agent_id,
            $translatorJob->status,
            'translation job cancelled'
        );

        // Ensure matched record is cleaned up even if it does not exist
        optional($translatorJob->assignedMatched)->delete();
        $translatorJob->agent()->dissociate();
        $translatorJob->save();

        MatchAgents::dispatch($translatorJob);

        return back()->with('success', 'Updated translator job');
    }
    public function export(Request $request)
    {
        // $jobs = InterpreterJob::hasEnabledUser()
        //     ->with([
        //         'toLanguage',
        //         'matchedAgents',
        //         'client.user',
        //         'agent.user',
        //         'client.organisation.company',
        //         'cancellation',
        //     ])
        //     ->visibleToUser(auth()->user())
        //     ->filter($request->only(
        //         'date',
        //         'search',
        //         'language_id',
        //         'status',
        //         'company',
        //         'client',
        //         'require_qualified',
        //           'agents'
        //     ))
        //     ->orderBy('appointment_date', 'DESC')
        //     ->get();
        //     // dd($jobs);
        // return Excel::download(new InterpreterJobsExport($jobs), 'interpreter_jobs.xlsx');
        $filters = $request->only(
            'date',
            'search',
            'language_id',
            'status',
            'company',
            'client',
            'require_qualified',
            'agents'
        );
        $now = Carbon::now()->timezone('Europe/London');
        $date = $now->format('Y-m-d');
        return Excel::download(new TranslatorJobsExport($filters), "translator_jobs-$date.xls");


    }
}
