{{-- # --}}
<header class="job__header">
    <h1 class="job__heading">

        @if ($type == 'interpreter-jobs')
            Interpreter job: {{ $job->toLanguage->name }}
        @else
            Translator job: {{ $job->toLanguage->name }} - {{ $job->fromLanguage->name }}
        @endif

        <div class="job__reference">{{ $job->reference }}</div>
    </h1>

    @unlessrole('agent')
        <section class="job__actions {{ $job->cannotBeCancelled() ? 'job__actions--short' : '' }}">
            <div class="job__wrapper">
                <label class="job__label job__label--big">Status:</label>
                <span class="job__status status status--{{ $job->statusName }}">
                    {{ $job->statusName }}
                    @if ($job->showQuoteRejectedFlagForUser(auth()->user()))
                        <span class="badge badge-warning ml-2">Quote: Rejected</span>
                    @endif

                    @if ($job->cancellation)
                        <a class="table__agent-name align-bottom" data-toggle="modal" data-target="#messageModal"
                            data-msg="{{ $job->cancellation->message }}">
                            <img src="/img/cancellations.svg" alt="Agent Cancellation">
                        </a>
                    @endif
                </span>
            </div>

            @if ($type == 'interpreter-jobs')
                @php
                    $timesheet = optional($job->timesheet);
                    $hasElectronic = !empty(optional($timesheet)->agent_signature) && !empty(optional($timesheet)->client_signature);
                    $isPaper = ($job->signature_method ?? null) === 'paper';
                    $tsStatus = $job->timesheet_status ?? null;
                    $signedDoc = $job->documents->firstWhere('type', config('enums.document_types')['signed_timesheet'] ?? null);
                @endphp
                <div class="job__wrapper mt-2">
                    <label class="job__label job__label--big">Timesheet:</label>
                    @if ($hasElectronic)
                        <span class="badge badge-success">Electronic signatures captured</span>
                    @elseif($isPaper && $tsStatus === 'verified')
                        <span class="badge badge-success">Paper timesheet verified</span>
                        @if($signedDoc)
                            <a href="{{ $signedDoc->fullUrl }}" target="_blank" rel="noopener" class="ml-2">View</a>
                            <a href="{{ $signedDoc->fullUrl }}" target="_blank" rel="noopener" class="ml-2">Print</a>
                        @endif
                    @elseif($isPaper && $tsStatus === 'signed_uploaded')
                        <span class="badge badge-warning">Paper timesheet uploaded (pending verification)</span>
                        @role('admin')
                            <a href="{{ route('interpreter-jobs.timesheets.index', $job->id) }}" class="ml-2">Verify now</a>
                        @endrole
                        @if($signedDoc)
                            <a href="{{ $signedDoc->fullUrl }}" target="_blank" rel="noopener" class="ml-2">View</a>
                            <a href="{{ $signedDoc->fullUrl }}" target="_blank" rel="noopener" class="ml-2">Print</a>
                        @endif
                    @else
                        <span class="badge badge-secondary">Pending signature</span>
                    @endif
                </div>
            @endif

            <div class="job__actions--buttons d-flex flex-column w-100">
            @if ($job->canBeCompleted())
                @can('complete', $job)
                {{ Form::open([
                    'method' => 'PUT',
                    'route' => ["$type.complete", $job],
                    'onsubmit' => 'return confirmCompletion(this)',
                    'class' => 'd-block w-100',
                    'data-appointment-date' => $job->appointment_date ?? $job->target_date ?? $job->start_date ?? '',
                    'data-appointment-time' => $job->appointment_time ?? $job->start_time ?? '',
                    'data-job-type' => ($type == 'interpreter-jobs') ? 'interpreter' : 'translation',
                ]) }}
                {{ Form::submit('Complete', ['class' => 'btn btn--primary w-100']) }}
                {{ Form::close() }}
                @endcan
                {{-- @elseif ($job->canBeCancelled()) --}}
                @role('client')
                    @includeWhen($job->canBeCancelled(), 'partials.cancel-button', [
                        'isWithin24Hours' => json_encode($job->isWithin24Hours()),
                        'route' => route("$type.cancel", $job->id),
                        'class' => 'btn btn--cancel w-100 mt-2',
                    ])
                @else
                    {{ Form::open(['route' => ["$type.cancel", $job->id], 'class' => 'd-block w-100 mt-2']) }}
                    {{ Form::submit('Cancel', [
                        'class' => 'btn btn--cancel w-100',
                        'onclick' => "return confirm('Are you sure you want to cancel this job?')",
                    ]) }}
                    {{ Form::close() }}
                @endrole
            @endif
</div>

        </section>
    @else
        <section class="job__actions {{ $job->cannotBeCancelled() ? 'job__actions--short' : '' }}">
            <div class="job__wrapper">
                <label class="job__label job__label--big">Status:</label>
                <span class="job__status status status--{{ $job->getStatusForAgent() ?? $job->statusName }}">
                    {{ $job->getStatusForAgent() ?? $job->statusName }}

                    @if ($job->cancellation)
                        <a class="table__agent-name align-bottom" data-toggle="modal" data-target="#messageModal"
                            data-msg="{{ $job->cancellation->message }}">
                            <img src="/img/cancellations.svg" alt="Agent Cancellation">
                        </a>
                    @endif

                </span>
            </div>

            @if ($type == 'interpreter-jobs')
                @php
                    $timesheet = optional($job->timesheet);
                    $hasElectronic = !empty(optional($timesheet)->agent_signature) && !empty(optional($timesheet)->client_signature);
                    $isPaper = ($job->signature_method ?? null) === 'paper';
                    $tsStatus = $job->timesheet_status ?? null;
                    $signedDoc = $job->documents->firstWhere('type', config('enums.document_types')['signed_timesheet'] ?? null);
                @endphp
                <div class="job__wrapper mt-2">
                    <label class="job__label job__label--big">Timesheet:</label>
                    @if ($hasElectronic)
                        <span class="badge badge-success">Electronic signatures captured</span>
                    @elseif($isPaper && $tsStatus === 'verified')
                        <span class="badge badge-success">Paper timesheet verified</span>
                        @if($signedDoc)
                            <a href="{{ $signedDoc->fullUrl }}" target="_blank" rel="noopener" class="ml-2">View</a>
                        @endif
                    @elseif($isPaper && $tsStatus === 'signed_uploaded')
                        <span class="badge badge-warning">Paper timesheet uploaded (pending verification)</span>
                        @if($signedDoc)
                            <a href="{{ $signedDoc->fullUrl }}" target="_blank" rel="noopener" class="ml-2">View</a>
                        @endif
                    @else
                        <span class="badge badge-secondary">Pending signature</span>
                    @endif
                </div>
            @endif

            @if (optional($job->matchedLoggedInAgent())->status == 'matched')
                @if ($type == 'translator-jobs' || ($type == 'interpreter-jobs' && $job->requiresQuote()))
                    <a class="btn btn--primary w-50" href="{{ route("$type.quotes.index", $job->id) }}">
                        Quote Job
                    </a>
                @else
                    {{ Form::open(['route' => ['interpreter-jobs.matched.update', $job->id], 'method' => 'PUT', 'class' => 'd-inline-block w-50']) }}
                    <button type="submit" class="btn btn--primary">Accept</button>
                    {{ Form::close() }}
                @endif
            @endif

        </section>
    @endunlessrole

</header>

@if ($type == 'interpreter-jobs')
    @php
        $liveStage = 1;
        if (!empty($job->agent_id)) $liveStage = 2;
        if (!empty($job->client_confirmed_at)) $liveStage = 3;
        if (!empty($job->checked_in_at)) $liveStage = 4;
        if ((int)$job->status === 4) $liveStage = 5;
        $liveSteps = ['Booked', 'Interpreter Assigned', 'Confirmed', 'Checked In On-Site', 'Completed'];
    @endphp
    <div class="ms-card p-3 mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2" style="gap:8px;">
            <div style="font-family:var(--font-mono);font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#2563EB;font-weight:600;">
                Live Appointment Progress Tracker
            </div>
            <div class="d-flex flex-wrap" style="gap:6px;">
                @hasanyrole('admin|super-admin|client')
                    <form method="POST" action="{{ route('interpreter-jobs.follow-up', $job->id) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary" style="border-radius:999px;">
                            <i class="fas fa-redo mr-1"></i> 1-Click Book Follow-Up
                        </button>
                    </form>
                @endhasanyrole
                @hasanyrole('admin|super-admin|agent')
                    <form method="POST" action="{{ route('timesheet.magic-link', $job->id) }}" target="_blank" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success" style="border-radius:999px;">
                            <i class="fas fa-qrcode mr-1"></i> No-Password Client Sign (10s)
                        </button>
                    </form>
                @endhasanyrole
                @hasanyrole('admin|super-admin')
                    @if((int)$job->status === 0)
                        <form method="POST" action="{{ route('interpreter-jobs.urgent-alert', $job->id) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-warning font-weight-bold" style="border-radius:999px;">
                                <i class="fas fa-bolt mr-1"></i> 1-Click Urgent Alert (&lt;24h)
                            </button>
                        </form>
                    @endif
                @endhasanyrole
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center" style="gap:6px;">
            @foreach($liveSteps as $idx => $stepName)
                @php $done = ($idx + 1) <= $liveStage; @endphp
                <div style="flex:1;text-align:center;">
                    <div style="height:6px;border-radius:999px;background:{{ $done ? '#10B981' : '#E2E8F0' }};margin-bottom:4px;"></div>
                    <div style="font-size:11px;font-weight:{{ $done ? '700' : '500' }};color:{{ $done ? '#065F46' : '#94A3B8' }};">{{ $stepName }}</div>
                </div>
            @endforeach
        </div>
    </div>
@endif

<ul class="tabs">
    <li class="tabs__tab">

        @if (in_array(Route::current()->getName(), ["$type.show", "$type.edit"]))
            <div class="tabs__text">Job details</div>
        @else
            @hasanyrole('admin|client')
                <a href="{{ route("$type.edit", $job->id) }}" class="tabs__text tabs__text--link">Job details</a>
            @else
                <a href="{{ route("$type.show", $job->id) }}" class="tabs__text tabs__text--link">Job details</a>
            @endhasanyrole
        @endif

    </li>

    @hasanyrole('admin|client')
        @if ($job->agent && $type == 'interpreter-jobs' && $job->client->show_agents)
            <li class="tabs__tab">
                @if (Route::current()->getName() == "$type.agent.index")
                    <div class="tabs__text">Agent ID</div>
                @else
                    <a href="{{ route("$type.agent.index", $job->id) }}" class="tabs__text tabs__text--link">Agent ID</a>
                @endif
            </li>
        @endif
        @if ($type == 'interpreter-jobs')
            <li class="tabs__tab">
                @if (Route::current()->getName() == "$type.timesheets.index")
                    <div class="tabs__text">Timesheet</div>
                @else
                    <a href="{{ route("$type.timesheets.index", $job->id) }}" class="tabs__text tabs__text--link">Timesheet</a>
                @endif
            </li>
        @endif
    @endhasanyrole

    @role('admin')
        <li class="tabs__tab">
            @if (Route::current()->getName() == "$type.matched.index")
                <div class="tabs__text">Assign</div>
            @else
                <a href="{{ route("$type.matched.index", $job->id) }}" class="tabs__text tabs__text--link">Assign</a>
            @endif
        </li>
    @endrole
    @role('admin')
        <li class="tabs__tab">
            @if (Route::current()->getName() == "$type.allupdates.index")
                <div class="tabs__text">Job Updates</div>
            @else
                <a href="{{ route("$type.allupdates.index", $job->id) }}" class="tabs__text tabs__text--link">Job
                    Updates</a>
            @endif
        </li>

        <li class="tabs__tab">
            @if (Route::current()->getName() == "$type.documents.index")
                <div class="tabs__text">Travel Expenses</div>
            @else
                <a href="{{ route("$type.documents.index", $job->id) }}" class="tabs__text tabs__text--link">Travel
                    Expenses</a>
            @endif
        </li>



    @endrole
    @role('agent')
        <li class="tabs__tab">
            @if (Route::current()->getName() == "$type.documents.index")
                <div class="tabs__text">Travel Expenses Receipt</div>
            @else
                <a href="{{ route("$type.documents.index", $job->id) }}" class="tabs__text tabs__text--link">Travel
                    Expenses Receipt</a>
            @endif
        </li>
        @if ($type == 'interpreter-jobs')
        <li class="tabs__tab">
            @if (Route::current()->getName() == "$type.timesheets.index")
                <div class="tabs__text">Timesheet Upload</div>
            @else
                <a href="{{ route("$type.timesheets.index", $job->id) }}" class="tabs__text tabs__text--link">Timesheet Upload</a>
            @endif
        </li>
        @endif
    @endrole
    @can('viewQuotes', $job)
        <li class="tabs__tab">
            @if (Route::current()->getName() == "$type.quotes.index")
                <div class="tabs__text">Quotes</div>
            @else
                <a href="{{ route("$type.quotes.index", $job->id) }}" class="tabs__text tabs__text--link">Quotes</a>
            @endif
        </li>
    @endcan

    @can('viewDocuments', $job)
        <li class="tabs__tab">
            @if (Route::current()->getName() == "$type.documents.index")
                <div class="tabs__text">Translation Files</div>
            @else
                <a href="{{ route("$type.documents.index", $job->id) }}" class="tabs__text tabs__text--link">Translation
                    Files</a>
            @endif
        </li>
    @endcan

</ul>

{{-- Modals are now provided globally via partials/global-modals in the app layout --}}
