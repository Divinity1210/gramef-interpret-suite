@extends('layouts.app')

@section('content')

    <nav class="nav">
        <h1 class="nav__heading">Job Board</h1>
        <button type="button" class="btn btn-secondary">Total Jobs: <span
                class="badge bg-light text-dark text-bold">{{ $count }}</span></button>
        <ul class="links">
            <li class="links__item">
                <div class="links__text">Interpreter Jobs</div>
            </li>
            <li class="links__item links__item--last">
                <a href="{{ route('translator-jobs.index') }}" class="links__text links__text--link">Translation Jobs</a>
            </li>
        </ul>
        <section class="job__actions job__actions--inline">
            @role('admin')
                <a href="{{ route('interpreter-jobs.export', request()->query()) }}" class="btn btn--secondary nav__btn">Export CSV</a>
            @endrole
            @unlessrole('agent')
                <a href="{{ route('interpreter-jobs.create') }}" class="btn btn--primary nav__btn">+ Post Interpreter Job</a>
                <a href="{{ route('interpreter-jobs.createbulk') }}" class="btn btn--primary nav__btn">+ Post Bulk Interpreter Job</a>
            @endunlessrole
        </section>
    </nav>

    @include('partials.job-filter-form')

    {{-- Top pagination controls for better usability --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="pagination-info">
            Showing {{ $firstItem ?? 0 }} to {{ $lastItem ?? 0 }} of {{ $count ?? 0 }} results
        </div>
        <div class="per-page-selector">
            <form method="GET" action="{{ route('interpreter-jobs.index') }}" class="d-inline">
                @foreach(request()->except('per_page') as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <label for="per_page" class="form-label me-2">Show:</label>
                <select name="per_page" id="per_page" class="form-select d-inline" style="width: auto;" onchange="this.form.submit()">
                    <option value="20" {{ request('per_page', 20) == 20 ? 'selected' : '' }}>20</option>
                    <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                </select>
                <span class="ms-2">per page</span>
            </form>
        </div>
    </div>

    @php
        $hasTotal = method_exists($jobs, 'total');
    @endphp
    @if($hasTotal)
        {{ $jobs->appends(request()->all())->links('custom.pagination') }}
    @else
        {{ $jobs->appends(request()->all())->links('pagination::simple-bootstrap-4') }}
    @endif

    @role('agent')
        @if(isset($claimable_cancelled_today) && $claimable_cancelled_today->count() > 0)
            <section class="content__table mt-4">
                <h2 class="mb-2">Claimable (cancelled same-day)</h2>
                <table class="table">
                    <thead class="table__header">
                        <tr>
                            <th class="table__heading">Ref</th>
                            <th class="table__heading">Appt. Date</th>
                            <th class="table__heading">Appt. Time</th>
                            <th class="table__heading">Language</th>
                            <th class="table__heading">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($claimable_cancelled_today as $job)
                            <tr class="table__row">
                                <td class="table__data">{{ $job->reference }}</td>
                                <td class="table__data">{{ $job->appointment_date->format('d/m/Y') }}</td>
                                <td class="table__data">{{ $job->start_time }}</td>
                                <td class="table__data">{{ optional($job->toLanguage)->name }}</td>
                                <td class="table__data table__data--actions">
                                    @if (!empty($job->timesheet->job_id) && empty(optional($job->timesheet)->agent_signature))
                                        <a class="btn btn--secondary table__btn ml-1 my-1" href="{{ route('timesheet.edit', $job) }}">Sign Timesheet</a>
                                    @endif
                                    <a href="{{ route('agent.job-entry.create', ['interpreter', $job->id]) }}" class="btn btn--success table__btn" style="background-color: #28a745; color: white;">
                                        Add Amount
                                    </a>
                                    <a class="btn btn--secondary table__btn ml-1" href="{{ route('interpreter-jobs.show', $job) }}">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    @endrole

    <section class="content__table">
        <table class="table">
            <thead class="table__header">
                <tr>
                    <th class="table__heading">Ref</th>
                    <th class="table__heading">Submitted</th>
                    <th class="table__heading">Appt. Date</th>
                    <th class="table__heading">Appt. Time</th>
                    <th class="table__heading">Duration</th>
                    <th class="table__heading">Language</th>
                    {{-- <th class="table__heading">Bulk ID</th> --}}

                    @role('admin')
                        <th class="table__heading">Company Name</th>
                        <th class="table__heading">Posted By</th>
                    @endrole

                    @hasanyrole('admin|client')
                        <th class="table__heading">Agent Details</th>
                    @endhasanyrole

                    @role('agent')
                        <th class="table__heading">Amount</th>
                    @endrole

                    <th class="table__heading">Status</th>
                    <th class="table__heading">Action</th>
                </tr>
            </thead>
            <tbody>

                @forelse ($jobs as $job)
                    <tr class="table__row">
                        <td class="table__data">{{ $job->reference }}{{ $job->client->show_agents }}</td>
                        <td class="table__data">{{ $job->created_at->format('d/m/Y') }}</td>
                        <td class="table__data">{{ $job->appointment_date->format('d/m/Y') }}</td>
                        <td class="table__data">{{ $job->start_time }}</td>
                        <td class="table__data">{{ $job->formattedDuration }}</td>
                        <td class="table__data">
                            {{ $job->toLanguage->name }}
                            @role('admin')
                                @if ($job->toLanguage->trashed())
                                    <br /><small>Deleted</small>
                                @endif
                            @endrole
                        </td>
                        {{-- <td class="table__data" style="font-size: 0.7em">

                            @if ($job->bulk_id)
                                {{ $job->bulk_id }}
                            @else
                                --
                            @endif
                        </td> --}}
                        @role('admin')
                            <td class="table__data">{{ $job->client->organisation->company->name ?? 'N/A' }}</td>
                            <td class="table__data">{{ $job->client->user->getFullName() }}</td>
                        @endrole

                        @hasanyrole('admin|client')
                            <td class="table__data">
                                @php
                                    $isAdminUser = auth()->user() && method_exists(auth()->user(), 'isAdmin') && auth()->user()->isAdmin();
                                    $canShowAgentName = $isAdminUser ? true : (bool) ($job->client->show_agents ?? false);
                                    $jobIsCancelled = ($job->statusName ?? null) === 'cancelled';
                                    $isSameDayCancelled = $jobIsCancelled
                                        && is_object($job)
                                        && method_exists($job, 'isLateCancellation')
                                        && $job->isLateCancellation();

                                    $agentForDisplay = null;
                                    if ($jobIsCancelled) {
                                        $agentForDisplay = $isSameDayCancelled
                                            ? ($job->agent ?: optional($job->timesheet)->agentOne)
                                            : null;
                                    } else {
                                        $agentForDisplay = $job->agent;
                                    }
                                @endphp
                                @if ($agentForDisplay && $canShowAgentName)
                                    <button type="button" class="table__agent-name" data-toggle="modal"
                                        data-target="#agentCardModal" data-picture="{{ $agentForDisplay->getProfilePicture() }}"
                                        data-name="{{ $agentForDisplay->user ? $agentForDisplay->user->getFullName() : 'DELETED USER' }}"
                                        data-dbs-expiry-date="{{ optional($agentForDisplay->dbs_expiry_date)->format('d/m/Y') }}"
                                        data-dbs-number="{{ $agentForDisplay->dbs_number }}"
                                        data-induction-date="{{ optional($agentForDisplay->induction_date)->format('d/m/Y') }}"
                                        data-dbs-ref="{{ $agentForDisplay->dbs_update_reference_number ?? 'n/a' }}"
                                        data-tel="{{ ($job->skill_id == 3 || $job->skill_id == 4) && $agentForDisplay->contact_number
                                            ? $agentForDisplay->contact_number
                                            : 'n/a' }}"
                                        data-email="{{ $job->skill_id == 4 && $agentForDisplay->user && $agentForDisplay->user->email ? $agentForDisplay->user->email : 'n/a' }}">
                                        {{ $agentForDisplay->user ? $agentForDisplay->user->getFullName() : 'DELETED USER' }}
                                    </button>
                                @else
                                    —
                                @endif

                                {{-- {{ $job->agent->user->getFullName() }} --}}

                            </td>
                        @endhasanyrole

                        @role('agent')
                            <td class="table__data">
                                @if ($job->agentJobEntry)
                                    £{{ number_format($job->agentJobEntry->total_amount, 2) }}
                                @else
                                    NA
                                @endif
                            </td>
                        @endrole

                        <td class="table__data status status--{{ $job->getStatusForAgent() ?? $job->statusName }}">

                            @role('agent')
                                {{ $job->getStatusForAgent() ?? $job->statusName }}
                                @if (($job->statusName ?? null) === 'cancelled' && method_exists($job, 'isLateCancellation') && $job->isLateCancellation())
                                    <span class="badge badge-warning ml-2">Late cancellation</span>
                                @endif
                                @if ($job->dna)
                                    <span class="badge badge-danger ml-2">DNA</span>
                                @endif
                            @else
                                {{ $job->statusName }}
                                @if (($job->statusName ?? null) === 'cancelled' && method_exists($job, 'isLateCancellation') && $job->isLateCancellation())
                                    <span class="badge badge-warning ml-2">Late cancellation</span>
                                @endif
                                @if ($job->dna)
                                    <span class="badge badge-danger ml-2">DNA</span>
                                @endif

                                @if ($job->cancellation)
                                    <a class="table__agent-name align-bottom" data-toggle="modal" data-target="#messageModal"
                                        data-msg="{{ $job->cancellation->message }}">
                                        <img src="/img/cancellations.svg" alt="Agent Cancellation">
                                    </a>
                                @endif

                                {{-- Quick-scan badge: Paper timesheet verified --}}
                                @php
                                    $isPaper = ($job->signature_method ?? null) === 'paper';
                                    $tsStatus = $job->timesheet_status ?? null;
                                @endphp
                                @if ($isPaper && $tsStatus === 'verified')
                                    <span class="badge badge-success ml-2">Timesheet: Verified</span>
                                @endif
                                @if ($job->showQuoteRejectedFlagForUser(auth()->user()))
                                    <span class="badge badge-warning ml-2">Quote: Rejected</span>
                                @endif
                            @endrole

                        </td>
                        <td class="table__data table__data--actions">

                            @if ($job->canBeCompleted())
                                {{ Form::open([
                                    'method' => 'PUT',
                                    'route' => ['interpreter-jobs.complete', $job],
                                    'onsubmit' => 'return confirmCompletion(this)',
                                    'class' => 'd-inline-block',
                                    'data-appointment-date' => $job->appointment_date ?? $job->start_date ?? '',
                                    'data-appointment-time' => $job->appointment_time ?? $job->start_time ?? '',
                                    'data-job-type' => 'interpreter'
                                ]) }}
                                {{ Form::submit('Complete', ['class' => 'btn btn--primary table__btn']) }}
                                {{ Form::close() }}
                            @endif
                            @if ($job->canBeDna())
                                @role('admin')
                                    {{ Form::Open([
                                        'method' => 'PUT',
                                    
                                        'route' => ['interpreter-jobs.dna', $job],
                                        'onSubmit' => 'return confirm("Are you Sure you want to DNA this job?")',
                                        'class' => 'd-inline-block',
                                    ]) }}
                                    {{ Form::submit('DNA', ['class' => 'btn btn--cancel table__btn']) }}
                                    {{ Form::close() }}
                                @endrole
                            @endif


                            @if ($job->canBeRetrn())
                                @role('admin')
                                    {{ Form::Open([
                                        'method' => 'PUT',
                                    
                                        'route' => ['interpreter-jobs.retrn', $job],
                                        'onSubmit' => 'return confirm("Are you Sure you want to Return this job?")',
                                        'class' => 'd-inline-block',
                                    ]) }}
                                    {{ Form::submit('Return', ['class' => 'btn btn--cancel table__btn']) }}
                                    {{ Form::close() }}
                                @endrole
                            @endif
                            {{-- @if ($job->canBeCancelled()) --}}

                            @role('admin')
                                {{ Form::open([
                                    'route' => ['interpreter-jobs.cancel', $job],
                                    'onsubmit' => 'return confirm("Are you sure you want to cancel this job?")',
                                    'class' => 'd-inline-block',
                                ]) }}
                                {{ Form::submit('Cancel', ['class' => 'btn btn--cancel table__btn']) }}
                                {{ Form::close() }}
                            @elserole('client')
                                @includeWhen($job->canBeCancelled(), 'partials.cancel-button', [
                                    'isWithin24Hours' => json_encode($job->isWithin24Hours()),
                                    'route' => route('interpreter-jobs.cancel', $job),
                                    'class' => 'btn btn--cancel table__btn',
                                ])
                            @endrole
                            {{-- @endif --}}

                            @role('agent')
                                @if ($job->getStatusForAgent() == 'assigned' && $job->statusName != 'completed' && auth()->user()->can('unassign', $job))
                                    {{ Form::open([
                                        'method' => 'DELETE',
                                        'route' => ['interpreter-jobs.matched.unassign', $job->id, $job->matchedAgentId],
                                        'class' => 'd-inline-block',
                                    ]) }}
                                    {{ Form::submit('Unassign', [
                                        'class' => 'btn btn--delete btn--small',
                                        'onclick' => "return confirm('Are you sure you want to be unassigned from this job?')",
                                    ]) }}
                                    {{ Form::close() }}
                                @endif
                            @endrole

                            @hasanyrole('admin|client')
                                @php
                                    $hasDigitalTimesheet = !empty(optional($job->timesheet)->agent_signature) && !empty(optional($job->timesheet)->client_signature);
                                    $uploadedSigned = $job->documents->firstWhere('type', config('enums.document_types')['signed_timesheet']);
                                @endphp
                                @if ($hasDigitalTimesheet)
                                    <a href="{{ route('timesheet-pdf', ['id' => $job->timesheet->id]) }}"
                                        class="btn btn--primary table__btn my-1">Print Timesheet</a>
                                @endif
                                @if ($uploadedSigned)
                                    <a href="{{ $uploadedSigned->fullUrl }}"
                                        target="_blank" rel="noopener noreferrer"
                                        class="btn btn--secondary table__btn my-1">View uploaded timesheet</a>
                                @endif
                            @endhasanyrole

                            @role('agent')
                                @php $isPaid = \App\ClientInvoiceItem::where('job_id', $job->id)->whereHas('invoice', function($q){ $q->where('status','paid'); })->exists(); @endphp
                                @if (!empty($job->timesheet->job_id)
                                    && ($job->statusName == 'completed'
                                        || ($job->statusName == 'cancelled' && method_exists($job, 'isLateCancellation') && $job->isLateCancellation()))
                                    && empty(optional($job->timesheet)->agent_signature)
                                    && !$isPaid)
                                    <a class="btn btn--secondary table__btn ml-1 my-1" href="{{ route('timesheet.edit', $job) }}">Sign Timesheet</a>
                                @endif
                                @if ($job->getStatusForAgent() == 'matched')
                                    @if ($job->requiresQuote() && !auth()->user()->agent->is($job->agent))
                                        <a class="btn btn--primary table__btn"
                                            href="{{ route('interpreter-jobs.quotes.index', $job) }}">
                                            Accept
                                        </a>
                                    @else
                                        {{ Form::open(['route' => ['interpreter-jobs.matched.update', $job], 'method' => 'PUT', 'class' => 'd-inline-block']) }}
                                        <button type="submit" class="btn btn--primary table__btn">Accept</button>
                                        {{ Form::close() }}
                                    @endif

                                    {{ Form::open(['route' => ['interpreter-jobs.matched.reject', $job], 'class' => 'd-inline-block']) }}
                                    <button type="submit" class="btn btn--reject table__btn">Reject</button>
                                    {{ Form::close() }}
                                @else
                                    @if ($job->getStatusForAgent() == 'rejected')
                                        {{ Form::open(['route' => ['interpreter-jobs.matched.update', $job], 'method' => 'PUT', 'class' => 'd-inline-block']) }}
                                        <button type="submit" class="btn btn--primary table__btn">Accept</button>
                                        {{ Form::close() }}
                                    @endif
                                @endif
                            @endrole

                            @role('client')
                                @php $isPaid = \App\ClientInvoiceItem::where('job_id', $job->id)->whereHas('invoice', function($q){ $q->where('status','paid'); })->exists(); @endphp
                                @if (!empty($job->timesheet->job_id)
                                    && empty(optional($job->timesheet)->client_signature)
                                    && ($job->statusName == 'completed' || ($job->statusName == 'cancelled' && method_exists($job, 'isLateCancellation') && $job->isLateCancellation()))
                                    && !$isPaid)
                                    <a class="btn btn--secondary table__btn ml-1 my-1"
                                        href="{{ route('timesheet.edit', $job) }}">Sign Timesheet</a>
                                    @if (empty($job->feedback->job_id))
                                        <a class="btn btn--secondary table__btn ml-1 my-1"
                                            href="{{ route('feedback.edit', $job) }}">FeedBack</a>
                                    @endif
                                @endif
                            @endrole

                            @unlessrole('agent')
                                <a class="btn btn--secondary table__btn ml-1"
                                    href="{{ route('interpreter-jobs.edit', $job) }}">View</a>
                            @else
                                <a class="btn btn--secondary table__btn ml-1"
                                    href="{{ route('interpreter-jobs.show', $job) }}">View</a>
                                @php $agentStatus = $job->getStatusForAgent() ?? $job->statusName; @endphp
                                @if (!empty($job->timesheet->job_id)
                                    && empty(optional($job->timesheet)->agent_signature)
                                    && ($agentStatus === 'completed' || ($agentStatus === 'cancelled' && method_exists($job, 'isLateCancellation') && $job->isLateCancellation())))
                                    <a class="btn btn--secondary table__btn ml-1 my-1"
                                        href="{{ route('timesheet.edit', $job) }}">Sign Timesheet</a>
                                @endif
                                @if ((($job->statusName == 'completed') || ($job->statusName == 'cancelled' && method_exists($job, 'isLateCancellation') && $job->isLateCancellation())) && !$job->agentJobEntry)
                                    <a href="{{ route('agent.job-entry.create', ['interpreter', $job->id]) }}" class="btn btn--success table__btn" style="background-color: #28a745; color: white;">
                                        Add Amount
                                    </a>
                                @endif

                            @endunlessrole

                        </td>
                    </tr>
                @empty
                    <tr class="table__row">
                        <td class="table__data table__data--grey" colspan="10">
                            There are no interpreter jobs
                        </td>
                    </tr>
                @endforelse

            </tbody>
        </table>
    </section>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="pagination-info">
            Showing {{ $firstItem ?? 0 }} to {{ $lastItem ?? 0 }} of {{ $count ?? 0 }} results
        </div>
        <div class="per-page-selector">
            <form method="GET" action="{{ route('interpreter-jobs.index') }}" class="d-inline">
                @foreach(request()->except('per_page') as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <label for="per_page" class="form-label me-2">Show:</label>
                <select name="per_page" id="per_page" class="form-select d-inline" style="width: auto;" onchange="this.form.submit()">
                    <option value="20" {{ request('per_page', 20) == 20 ? 'selected' : '' }}>20</option>
                    <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                </select>
                <span class="ms-2">per page</span>
            </form>
        </div>
    </div>

    @php
        $hasTotal = method_exists($jobs, 'total');
    @endphp
    @if($hasTotal)
        {{ $jobs->appends(request()->all())->links('custom.pagination') }}
    @else
        {{ $jobs->appends(request()->all())->links('pagination::simple-bootstrap-4') }}
    @endif

    

@endsection
