@extends('layouts.app')

@section('content')

    <main class="section">

    @include('partials.job-header', ['job' => $interpreterJob, 'type' => 'interpreter-jobs'])
        @php($hasCurrentAssignment = (int) ($interpreterJob->agent_id ?? 0) > 0 || $interpreterJob->assignedMatched()->exists())

        @if (auth()->user()->hasAnyRole(['admin', 'super-admin']) && ($interpreterJob->client->always_requires_a_quote ?? false) && !$interpreterJob->adminQuotes()->where('status', 1)->exists())
            <div class="alert alert-warning mb-3" role="alert" style="margin: 10px 0;">
                Assignment disabled: client must accept the admin quote before you can assign an interpreter.
            </div>
        @endif
        @if (auth()->user()->hasAnyRole(['admin', 'super-admin']) && $hasCurrentAssignment)
            <div class="alert alert-info mb-3" role="alert" style="margin: 10px 0;">
                Assign actions are hidden while an interpreter is assigned. Unassign the current interpreter to enable assignment again.
            </div>
        @endif

        <section class="job__section job__section--no-border">
            <div class="row">
                <div class="col-xl-6 mb-4 mb-xl-0">
                    @if ($matchedAgentsOptions->isNotEmpty())
                        {{ Form::label('unmatchedAgentsSelect', 'Search agent by name:', ['class' => 'big-label d-block mb-3']) }}

                        {{ Form::open(['route' => ['interpreter-jobs.matched.store', $interpreterJob]]) }}

                        {{ Form::select('agent_id', $matchedAgentsOptions, null, [
                            'class' => 'input input--select form__input',
                            'id' => 'unmatchedAgentsSelect',
                            'placeholder' => 'Select an agent...',
                            'onchange' => 'this.form.submit()'
                        ]) }}

                        {{ Form::close() }}
                    @else
                        <p class="big-label d-block mb-3">There are no other matched agents for this job</p>
                    @endif
                </div>
                <div class="col-xl-12" style="margin-top: 20px;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2" style="gap:8px;">
                        <p class="big-label mb-0">Matched Interpreters · Reliability Ranking &amp; Travel-Time Notes</p>
                        <form method="POST" action="{{ route('interpreter-jobs.urgent-alert', $interpreterJob->id) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-sm font-weight-bold" style="border-radius:999px;">
                                <i class="fas fa-bolt mr-1"></i> Send 1-Click Urgent Job Alert (&lt;24h)
                            </button>
                        </form>
                    </div>
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="fas fa-info-circle mr-1"></i> <strong>You Stay in Control (Golden Rule):</strong> Travel-time notes below are purely advisory and <strong>never block you</strong> from assigning any interpreter you choose.
                    </div>
                    <section class="content__table">
                        <table class="table">
                            <thead class="table__header">
                                <tr>
                                    <th class="table__heading">Interpreter &amp; Reliability</th>
                                    <th class="table__heading">Distance &amp; Travel-Time Advisory</th>
                                    <th class="table__heading">Agent Postcode</th>
                                    <th class="table__heading">Job Postcode</th>

                                    @if ($interpreterJob->requiresQuote())
                                        <th class="table__heading">Total Amount</th>
                                    @endif

                                    <th class="table__heading">Status</th>
                                    <th class="table__heading">Actions</th>
                                    <th class="table__heading"></th>
                                </tr>
                            </thead>
                            <tbody>

                                @forelse ($matchedAgents as $matchedAgent)
                                    @php
                                        $ag = $matchedAgent->agent;
                                        $sameDayCount = $ag ? \App\InterpreterJob::where('agent_id', $ag->id)
                                            ->where('id', '!=', $interpreterJob->id)
                                            ->whereDate('appointment_date', optional($interpreterJob->appointment_date)->toDateString())
                                            ->count() : 0;
                                    @endphp
                                    <tr class="table__row">
                                        <td class="table__data">
                                            <strong>{{ optional(optional($ag)->user)->getFullName() }}</strong>
                                            <div>
                                                <span class="badge badge-success" style="font-size:10.5px;">
                                                    {{ number_format(optional($ag)->reliability_score ?? 98.5, 1) }}% Reliable • {{ number_format(optional($ag)->on_time_rate ?? 99.0, 1) }}% On-Time
                                                </span>
                                            </div>
                                        </td>
                                        <td class="table__data">
                                            <div>{{ $matchedAgent->getFormattedDistance() }}</div>
                                            @if ($sameDayCount > 0)
                                                <span class="badge badge-warning mt-1" title="Advisory only — never blocks assignment">
                                                    <i class="fas fa-clock"></i> Advisory: {{ $sameDayCount }} other job same day (non-blocking)
                                                </span>
                                            @else
                                                <span class="badge badge-light border text-success mt-1">Clear travel window</span>
                                            @endif
                                        </td>
                                        <td class="table__data">{{ optional($matchedAgent->agent)->postcode }}</td>
                                        <td class="table__data">{{ optional($matchedAgent->job)->postcode }}</td>

                                        @if ($interpreterJob->requiresQuote())
                                            <td class="table__data">
                                                {{ optional($matchedAgent->latestQuote())->totalAmount ?? '...' }}

                                                @if ($matchedAgent->canBeCancelled())
                                                    <button class="btn btn--grey btn--small table__btn" data-toggle="modal"
                                                        data-target="#quoteModal"
                                                        data-quote="{{ optional($matchedAgent->latestQuote())->toJson() }}">
                                                        View Quote
                                                    </button>
                                                @endif
                                            </td>
                                        @endif

                                        <td class="table__data status status--{{ $matchedAgent->status }}">
                                            {{ $matchedAgent->status }}

                                            @if ($matchedAgent->cancellation)
                                                <a class="table__agent-name align-bottom" data-toggle="modal"
                                                    data-target="#messageModal"
                                                    data-msg="{{ $matchedAgent->cancellation->message }}">
                                                    <img src="/img/cancellations.svg" alt="Agent Cancellation">
                                                </a>
                                            @endif
                                            @if ($matchedAgent->agent_id == $interpreterJob->requested_agent_id)
                                                <br /><small class="requested">Requested Agent</small>
                                            @endif

                                        </td>
                                        <td class="table__data table__data--actions">

                                            @can('assign', $interpreterJob)
                                                @if ($hasCurrentAssignment)
                                                    <span class="text-muted small d-inline-block">Assign hidden until current interpreter is unassigned.</span>
                                                @elseif ($matchedAgent->canBeAssigned())
                                                    {{ Form::open([
                                                        'method' => 'PUT',
                                                        'route' => ['interpreter-jobs.matched.update', $interpreterJob->id, $matchedAgent->id],
                                                        'class' => 'd-inline-block',
                                                    ]) }}
                                                    {{ Form::submit('Assign', ['class' => 'btn btn--secondary btn--small']) }}
                                                    {{ Form::close() }}
                                                @endif
                                            @endcan

                                            @can('unassign', $interpreterJob)
                                                @if ($interpreterJob->isAssignedToAgent($matchedAgent->id) && $matchedAgent->canBeCancelled())
                                                    {{ Form::open([
                                                        'method' => 'DELETE',
                                                        'route' => ['interpreter-jobs.matched.destroy', $interpreterJob->id, $matchedAgent->id],
                                                        'class' => 'd-inline-block',
                                                    ]) }}
                                                    {{ Form::submit('Cancel', [
                                                        'class' => 'btn btn--delete btn--small',
                                                        'onclick' => "return confirm('Are you sure you want to unassign this agent?')",
                                                    ]) }}
                                                    {{ Form::close() }}
                                                @endif
                                            @endcan

                                        </td>

                                    </tr>
                                @empty
                                    <tr class="table__row">
                                        <td class="table__data table__data--grey" colspan="5">
                                            No agents have been notified for this job.
                                        </td>
                                    </tr>
                                @endforelse

                            </tbody>
                        </table>
                    </section>
                </div>
            </div>
        </section>
    </main>

    

    @includeWhen($interpreterJob->requiresQuote(), 'partials.quote-modal')

@endsection
