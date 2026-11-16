@extends('layouts.app')

@section('content')
    <main class="section">
        <div class="section__top d-block d-lg-flex">
            <div class="section__heading">
                Pending Jobs
                @if($pendingEntries->count() > 0)
                    <span class="badge badge-warning ml-2">{{ $pendingEntries->count() }}</span>
                @endif
            </div>
            <div class="d-flex">
                <a href="{{ route('invoices.agent.index') }}" class="btn btn--cancel az-btn az-btn-secondary" title="Go to your invoices">
                    View Invoices
                </a>
                <a href="{{ route('invoices.agent.create') }}" class="btn btn--primary ml-2 az-btn az-btn-primary" title="Create a new agent invoice">
                    Create Invoice
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class=" btn-close close" data-dismiss="alert" data-bs-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if($pendingEntries->count() > 0)
            <div class="row">
                <div class="col-12">
                    <!-- Filters -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <form id="appointment-filters" method="GET" action="{{ route('agent.job-entry.pending') }}" class="mb-2">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        @include('partials.filters.date-range', [
                                            'mode' => 'single',
                                            'formId' => 'appointment-filters',
                                            'inputId' => 'appointment-date-range',
                                            'paramName' => 'date',
                                            'startName' => 'start_date',
                                            'endName' => 'end_date',
                                            'startId' => 'start_date',
                                            'endId' => 'end_date',
                                            'mirrorHidden' => true,
                                            'placeholder' => 'Filter by appointment date...'
                                        ])
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary">Update Range</button>
                                    </div>
                                </div>
                            </form>
                            <small class="text-muted">Filtering by job appointment date (interpreting) or target date (translation). Generating an invoice will use this period.</small>
                        </div>
                    </div>
                    <!-- Summary Card -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <h4 class="text-primary">{{ $pendingEntries->count() }}</h4>
                                    <p class="mb-0">Pending Entries</p>
                                </div>
                                <div class="col-md-3">
                                    <h4 class="text-success">£{{ number_format($pendingEntries->sum('total_amount'), 2) }}</h4>
                                    <p class="mb-0">Total Amount</p>
                                </div>
                                <div class="col-md-3">
                                    <h4 class="text-info">£{{ number_format($pendingEntries->sum('base_amount'), 2) }}</h4>
                                    <p class="mb-0">Base Amount</p>
                                </div>
                                <div class="col-md-3">
                                    <h4 class="text-warning">£{{ number_format($pendingEntries->sum('expenses'), 2) }}</h4>
                                    <p class="mb-0">Total Expenses</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Jobs Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Job Entries Awaiting Invoice</h5>
                            <small class="text-muted">These entries will be included in your next monthly invoice</small>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <form method="POST" action="{{ route('invoices.agent.generate') }}">
                                    @csrf
                                    <input type="hidden" name="start_date" value="{{ $start_date }}" />
                                    <input type="hidden" name="end_date" value="{{ $end_date }}" />
                                <table class="table table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width:32px;">
                                                <input type="checkbox" id="select-all-pending" onclick="document.querySelectorAll('.entry-select').forEach(cb=>cb.checked=this.checked)" />
                                            </th>
                                            <th>Job ID</th>
                                            <th>Type</th>
                                            <th>Client</th>
                                            <th>Date</th>
                                            <th>Language</th>
                                            <th>Duration</th>
                                            <th>Base Amount</th>
                                            <th>Expenses</th>
                                            <th>Parking/Travel</th>
                                            <th>Total</th>
                                            <th>Entry Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pendingEntries as $entry)
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="entry-select" name="entry_ids[]" value="{{ $entry->id }}" />
                                                </td>
                                                <td>
                                                    <strong>{{ $entry->job_id }}/atoz</strong>
                                                </td>
                                                <td>
                                                    <span class="badge badge-{{ $entry->job_type == 'App\InterpreterJob' ? 'primary' : 'info' }}">
                                                        {{ $entry->job_type == 'App\InterpreterJob' ? 'Interpreter' : 'Translator' }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if($entry->jobDetails && $entry->jobDetails->client)
                                                        {{ $entry->jobDetails->client->user->getFullName() }}
                                                    @else
                                                        <span class="text-muted">N/A</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @php
                                                        $isInterpreter = ($entry->job_type == 'App\\InterpreterJob');
                                                        $dateRaw = null;
                                                        if ($entry->jobDetails) {
                                                            $dateRaw = $isInterpreter ? ($entry->jobDetails->appointment_date ?? null) : ($entry->jobDetails->target_date ?? null);
                                                        }
                                                    @endphp
                                                    {{ $dateRaw ? \Carbon\Carbon::parse($dateRaw)->format('d/m/Y') : 'N/A' }}
                                                </td>
                                                <td>
                                                    @php
                                                        $langText = 'N/A';
                                                        if ($entry->jobDetails) {
                                                            if ($isInterpreter && $entry->jobDetails->toLanguage) {
                                                                $langText = $entry->jobDetails->toLanguage->name;
                                                            } elseif (!$isInterpreter && $entry->jobDetails->fromLanguage && $entry->jobDetails->toLanguage) {
                                                                $langText = $entry->jobDetails->fromLanguage->name . ' → ' . $entry->jobDetails->toLanguage->name;
                                                            }
                                                        }
                                                    @endphp
                                                    {{ $langText }}
                                                </td>
                                                <td>
                                                    @php
                                                        $durationText = 'N/A';
                                                        if ($isInterpreter && $entry->jobDetails) {
                                                            $h = (int) ($entry->jobDetails->duration_hours ?? 0);
                                                            $m = (int) ($entry->jobDetails->duration_minutes ?? 0);
                                                            if ($h > 0 || $m > 0) {
                                                                $durationText = $h . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
                                                            } elseif (!empty($entry->jobDetails->start_time) && !empty($entry->jobDetails->end_time)) {
                                                                try {
                                                                    $start = \Carbon\Carbon::parse($entry->jobDetails->start_time);
                                                                    $end = \Carbon\Carbon::parse($entry->jobDetails->end_time);
                                                                    $mins = $end->diffInMinutes($start);
                                                                    $durationText = floor($mins / 60) . ':' . str_pad((string)($mins % 60), 2, '0', STR_PAD_LEFT);
                                                                } catch (\Throwable $e) {}
                                                            }
                                                        }
                                                    @endphp
                                                    {{ $durationText }}
                                                </td>
                                                <td>£{{ number_format($entry->base_amount, 2) }}</td>
                                                <td>£{{ number_format($entry->expenses, 2) }}</td>
                                                <td>£{{ number_format($entry->surcharge, 2) }}</td>
                                                <td>
                                                    <strong class="text-success">£{{ number_format($entry->total_amount, 2) }}</strong>
                                                </td>
                                                <td>{{ $entry->created_at->format('d/m/Y') }}</td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="{{ route('agent.job-entry.edit', $entry->id) }}" 
                                                           class="btn btn-sm btn-outline-primary az-btn az-btn-outline" 
                                                           title="Edit this entry">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        @php
                                                            $jobShowUrl = $entry->job_type == 'App\\InterpreterJob'
                                                                ? route('interpreter-jobs.show', $entry->job_id)
                                                                : route('translator-jobs.show', $entry->job_id);
                                                        @endphp
                                                        <a href="{{ $jobShowUrl }}"
                                                           class="btn btn-sm btn-outline-info az-btn az-btn-secondary"
                                                           title="Open job details">
                                                            <i class="fas fa-external-link-alt"></i> View Job
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-danger az-btn az-btn-danger" 
                                                                title="Delete this entry"
                                                                onclick="confirmDelete('{{ $entry->id }}')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            @if($entry->notes)
                                                <tr class="table-light">
                                                    <td colspan="11">
                                                        <small class="text-muted">
                                                            <strong>Notes:</strong> {{ $entry->notes }}
                                                        </small>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="p-3 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-success" @if($pendingEntries->isEmpty()) disabled @endif>
                                        Create Invoice (Selected)
                                    </button>
                                </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- Empty State -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <h4>No Pending Job Entries</h4>
                            <p class="text-muted mb-4">
                                You don't have any pending job entries at the moment. 
                                Job entries will appear here after you submit them and before they are included in an invoice.
                            </p>
                            <div class="row justify-content-center">
                                <div class="col-md-8">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6>How to add job entries:</h6>
                                            <ol class="text-left mb-0">
                                                <li>Navigate to your assigned jobs</li>
                                                <li>Click on a job to enter amounts</li>
                                                <li>Fill in base amount, expenses, and other charges</li>
                                                <li>Submit the entry - it will appear in this pending list</li>
                                                <li>Entries will be included in your next monthly invoice</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </main>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class=" btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this job entry? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Delete Entry</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function confirmDelete(entryId) {
            const form = document.getElementById('deleteForm');
            form.action = `/agent/job-entry/${entryId}`;
            $('#deleteModal').modal('show');
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    if (alert.classList.contains('show')) {
                        alert.classList.remove('show');
                        alert.classList.add('fade');
                        setTimeout(function() {
                            alert.remove();
                        }, 150);
                    }
                });
            }, 5000);
        });
    </script>
    @endpush
@endsection
