@extends('layouts.app')

@section('content')

    <nav class="nav">
        <h1 class="nav__heading">Job Board</h1>
        <button type="button" class="btn btn-secondary">Total Jobs: <span
                class="badge bg-light text-dark text-bold">{{ $count }}</span></button>
        <ul class="links">
            <li class="links__item">
                <a href="{{ route('interpreter-jobs.index') }}" class="links__text links__text--link">Interpreter Jobs</a>
            </li>
            <li class="links__item links__item--last">
                <div class="links__text">Translation Jobs</div>
            </li>
        </ul>
        <section class="job__actions ">
            @role('admin')
                <a href="{{ route('translator-jobs.export', request()->query()) }}" class="btn btn--secondary nav__btn">Export
                    CSV</a>
            @endrole
            @unlessrole('agent')
                <a href="{{ route('translator-jobs.create') }}" class="btn btn--primary nav__btn">+ Post Translation Job</a>
            @else
                <div></div>
            @endunlessrole
        </section>
    </nav>


    @include('partials.job-filter-translation-form')

    {{-- Top pagination controls for better usability --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="pagination-info">
            Showing {{ $firstItem ?? 0 }} to {{ $lastItem ?? 0 }} of {{ $count ?? 0 }} results
        </div>
        <div class="per-page-selector">
            <form method="GET" action="{{ route('translator-jobs.index') }}" class="d-inline">
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
                <h2 class="mb-2">Claimable (cancelled today)</h2>
                <table class="table">
                    <thead class="table__header">
                        <tr>
                            <th class="table__heading">Ref</th>
                            <th class="table__heading">Delivery Date</th>
                            <th class="table__heading">Languages</th>
                            <th class="table__heading">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($claimable_cancelled_today as $job)
                            <tr class="table__row">
                                <td class="table__data">{{ $job->reference }}</td>
                                <td class="table__data">{{ $job->target_date->format('d/m/Y') }}</td>
                                <td class="table__data">{{ optional($job->fromLanguage)->name }} - {{ optional($job->toLanguage)->name }}</td>
                                <td class="table__data table__data--actions">
                                    <a href="{{ route('agent.job-entry.create', ['translator', $job->id]) }}" class="btn btn--success table__btn" style="background-color: #28a745; color: white;">
                                        Add Amount
                                    </a>
                                    <a class="btn btn--secondary table__btn ml-1" href="{{ route('translator-jobs.show', $job) }}">View</a>
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
                    <th class="table__heading">Delivery Date</th>
                    <th class="table__heading">Word Count</th>
                    <th class="table__heading">Languages</th>
                    <th class="table__heading">Affirmation</th>
                    <th class="table__heading">Affidavit</th>

                    @role('admin')
                        <th class="table__heading">Company Name</th>
                        <th class="table__heading">Posted By</th>
                    @endrole

                    @role('agent')
                        <th class="table__heading">Amount</th>
                    @endrole

                    <th class="table__heading">Status</th>
                    <th class="table__heading"></th>
                </tr>
            </thead>
            <tbody>

                @forelse ($jobs as $job)
                    <tr class="table__row">
                        <td class="table__data">{{ $job->reference }}</td>
                        <td class="table__data">{{ $job->created_at->format('d/m/Y') }}</td>
                        <td class="table__data">{{ $job->target_date->format('d/m/Y') }}</td>
                        <td class="table__data">{{ $job->word_count }}</td>
                        <td class="table__data">
                            {{ $job->fromLanguage->name }}
                            @role('admin')
                                @if ($job->fromLanguage->trashed())
                                    <small>Deleted</small>
                                @endif
                            @endrole
                            - {{ $job->toLanguage->name }}
                            @role('admin')
                                @if ($job->toLanguage->trashed())
                                    <small>Deleted</small>
                                @endif
                            @endrole
                        </td>
                        <td class="table__data">{{ $job->affirmation ? 'Yes' : 'No' }}</td>
                        <td class="table__data">{{ $job->affidavit ? 'Yes' : 'No' }}</td>

                        @role('admin')
                            <td class="table__data">{{ $job->client->organisation->company->name ?? 'N/A' }}</td>
                            <td class="table__data">{{ $job->client->user->getFullName() }}</td>
                        @endrole

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
                                @if (($job->statusName ?? null) === 'cancelled' && method_exists($job, 'isCancelledSameDay') && $job->isCancelledSameDay())
                                    <span class="badge badge-warning ml-2">Same-day cancelled</span>
                                @endif
                            @else
                                {{ $job->statusName }}
                                @if (($job->statusName ?? null) === 'cancelled' && method_exists($job, 'isCancelledSameDay') && $job->isCancelledSameDay())
                                    <span class="badge badge-warning ml-2">Same-day cancelled</span>
                                @endif

                                @if ($job->cancellation)
                                    <a class="table__agent-name align-bottom" data-toggle="modal" data-target="#messageModal"
                                        data-msg="{{ $job->cancellation->message }}">
                                        <img src="/img/cancellations.svg" alt="Agent Cancellation">
                                    </a>
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
                                    'route' => ['translator-jobs.complete', $job],
                                    'onsubmit' => 'return confirmCompletion(this)',
                                    'class' => 'd-inline-block',
                                    'data-appointment-date' => $job->appointment_date ?? $job->target_date ?? '',
                                    'data-appointment-time' => $job->appointment_time ?? $job->start_time ?? '',
                                    'data-job-type' => 'translation'
                                ]) }}
                                {{ Form::submit('Complete', ['class' => 'btn btn--primary table__btn']) }}
                                {{ Form::close() }}
                            @elseif ($job->canBeCancelled())
                                @role('admin')
                                    {{ Form::open([
                                        'route' => ['translator-jobs.cancel', $job],
                                        'onsubmit' => 'return confirm("Are you sure you want to cancel this job?")',
                                        'class' => 'd-inline-block',
                                    ]) }}
                                    {{ Form::submit('Cancel', ['class' => 'btn btn--cancel table__btn']) }}
                                    {{ Form::close() }}
                                    @elserole('client')
                                    @includeWhen($job->canBeCancelled(), 'partials.cancel-button', [
                                        'isWithin24Hours' => json_encode($job->isWithin24Hours()),
                                        'route' => route('translator-jobs.cancel', $job),
                                        'class' => 'btn btn--cancel table__btn',
                                    ])
                                @endrole
                            @endif

                            @role('agent')
                                @if ($job->getStatusForAgent() == 'matched')
                                    @if (!auth()->user()->agent->is($job->agent))
                                        <a class="btn btn--primary table__btn"
                                            href="{{ route('translator-jobs.quotes.index', $job) }}">
                                            Accept
                                        </a>
                                    @else
                                        {{ Form::open(['route' => ['translator-jobs.matched.update', $job], 'method' => 'PUT', 'class' => 'd-inline-block']) }}
                                        <button type="submit" class="btn btn--primary table__btn">Accept</button>
                                        {{ Form::close() }}
                                    @endif

                                    {{ Form::open([
                                        'route' => ['translator-jobs.matched.reject', $job],
                                        'class' => 'd-inline-block',
                                    ]) }}
                                    <button type="submit" class="btn btn--reject table__btn">Reject</button>
                                    {{ Form::close() }}
                                @else
                                    @php
                                        $matched = $job->matchedLoggedInAgent();
                                    @endphp
                                    @if ($job->canBeCancelled() && $matched && $matched->canBeCancelled())
                                        @include('partials.cancel-button', [
                                            'isWithin24Hours' => json_encode($job->isWithin24Hours()),
                                            'route' => route('matched.cancel', $matched),
                                            'class' => 'btn btn--cancel table__btn',
                                        ])
                                    @endif
                                @endif

                                @if ($job->status == 'completed' && !$job->agentJobEntry)
                                    <a href="{{ route('agent.job-entry.create', ['translator', $job->id]) }}" class="btn btn--success table__btn" style="background-color: #28a745; color: white;">
                                        Add Amount
                                    </a>
                                @endif
                            @endrole

                            @unlessrole('agent')
                                <a class="btn btn--secondary table__btn"
                                    href="{{ route('translator-jobs.edit', $job) }}">View</a>
                            @else
                                <a class="btn btn--secondary table__btn"
                                    href="{{ route('translator-jobs.show', $job) }}">View</a>
                            @endunlessrole

                        </td>
                    </tr>
                @empty
                    <tr class="table__row">
                        <td class="table__data table__data--grey" colspan="10">
                            There are no translation jobs
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
            <form method="GET" action="{{ route('translator-jobs.index') }}" class="d-inline">
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
