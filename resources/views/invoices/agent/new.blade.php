@extends('layouts.app')

@section('content')
<div class="container-fluid" style="max-width: 1400px;">
    <div class="row mb-3">
        <div class="col-md-8">
            <h3>Create Agent Invoice</h3>
            <p class="text-muted">Select a date range to include pending job entries and generate an invoice.</p>
        </div>
        <div class="col-md-4 text-right">
            <a href="{{ route('invoices.agent.index') }}" class="btn btn-outline-secondary">Back to Invoices</a>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form id="agent-invoice-filters" method="GET" action="{{ route('invoices.agent.create') }}" class="mb-4">
        <div class="row g-3 filters">
            <div class="col-md-6">
                @include('partials.filters.date-range', [
                    'mode' => 'single',
                    'formId' => 'agent-invoice-filters',
                    'inputId' => 'agent-invoice-date',
                    'paramName' => 'date',
                    'startName' => 'start_date',
                    'endName' => 'end_date',
                    'startId' => 'start_date',
                    'endId' => 'end_date',
                    'mirrorHidden' => true,
                    'placeholder' => 'Filter By Date...'
                ])
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">Update Range</button>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            Pending job entries in selected period
            <div class="text-muted" style="font-size: 0.9rem; margin-top: 4px;">
                This range filters by entry date (when you logged the amounts), not the job date. Use selection to include only the jobs you wish to invoice.
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <form method="POST" action="{{ route('invoices.agent.generate') }}">
                    @csrf
                    <input type="hidden" name="start_date" value="{{ $start_date }}" />
                    <input type="hidden" name="end_date" value="{{ $end_date }}" />
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th style="width:32px;">
                                    <input type="checkbox" id="select-all" onclick="document.querySelectorAll('.entry-select').forEach(cb=>cb.checked=this.checked)" />
                                </th>
                                <th>Job ID</th>
                                <th>Job Date</th>
                                <th>Type</th>
                                <th>Language</th>
                                <th>Duration</th>
                                <th>Notes</th>
                                <th class="text-right">Base</th>
                                <th class="text-right">Expenses</th>
                                <th class="text-right">Parking/Travel</th>
                                <th class="text-right">Total</th>
                        </tr>
                        </thead>
                        <tbody>
                            @forelse($entries as $e)
                                <tr>
                                    <td>
                                        <input type="checkbox" class="entry-select" name="entry_ids[]" value="{{ $e->id }}" />
                                    </td>
                                    <td>
                                        @if($e->job_type === 'App\\InterpreterJob')
                                            <a href="{{ route('interpreter-jobs.show', $e->job_id) }}" target="_blank">#{{ $e->job_id }}</a>
                                        @elseif($e->job_type === 'App\\TranslatorJob')
                                            <a href="{{ route('translator-jobs.show', $e->job_id) }}" target="_blank">#{{ $e->job_id }}</a>
                                        @else
                                            #{{ $e->job_id }}
                                        @endif
                                    </td>
                                    @php
                                        $job = $e->job;
                                        $jobDate = $job ? ($job->appointment_date ?? $job->target_date) : null;
                                        $jobDateStr = $jobDate ? \Carbon\Carbon::parse($jobDate)->format('Y-m-d') : 'N/A';
                                        $language = $job && $job->toLanguage ? $job->toLanguage->name : 'N/A';
                                        $duration = '-';
                                        if ($job && $e->job_type === 'App\\InterpreterJob') {
                                            $duration = ($job->duration_hours ?? 0) . 'h ' . ($job->duration_minutes ?? 0) . 'm';
                                        }
                                    @endphp
                                    <td>{{ $jobDateStr }}</td>
                                    <td>{{ $e->job_type }}</td>
                                    <td>{{ $language }}</td>
                                    <td>{{ $duration }}</td>
                                    <td>{{ $e->notes }}</td>
                                    <td class="text-right">£{{ number_format($e->base_amount, 2) }}</td>
                                    <td class="text-right">£{{ number_format($e->expenses, 2) }}</td>
                                    <td class="text-right">£{{ number_format($e->surcharge, 2) }}</td>
                                    <td class="text-right">£{{ number_format($e->total_amount, 2) }}</td>
                                </tr>
                            @empty
                            <tr><td colspan="12" class="text-center text-muted">No pending entries in this period</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="p-3 d-flex justify-content-between align-items-center">
                        @php
                            $subtotal = $entries->sum('base_amount');
                            $expensesTotal = $entries->sum(function($i){ return $i->expenses + $i->surcharge + $i->other_charges; });
                            $vatAmount = 0.00; $grandTotal = $subtotal + $expensesTotal + $vatAmount;
                        @endphp
                        <div>
                            <strong>Subtotal:</strong> £{{ number_format($subtotal, 2) }}
                            <strong class="ml-3">Expenses:</strong> £{{ number_format($expensesTotal, 2) }}
                            <strong class="ml-3">Total:</strong> £{{ number_format($grandTotal, 2) }}
                        </div>
                        <div>
                            <button type="submit" class="btn btn-success" @if($entries->isEmpty()) disabled @endif>
                                Generate Invoice (Selected)
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card-footer">
            <div class="text-muted">You can still adjust which jobs to include by ticking/unticking rows above.</div>
        </div>
    </div>
</div>
@endsection
