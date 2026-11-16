@extends('layouts.app')

@section('content')
<div class="container-fluid" style="max-width: 1400px;">
    <div class="row mb-3">
        <div class="col-md-8">
            <h3>Create Payslip (By Appointment Date)</h3>
            <p class="text-muted">Select agent and date range; tick items to include.</p>
        </div>
        <div class="col-md-4 text-right">
            <a href="{{ route('payslips.index') }}" class="btn btn-outline-secondary">Back to Payslips</a>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('payslips.create') }}" class="mb-4">
        <div class="row g-3 filters">
            <div class="col-md-4">
                <label class="form-label">Agent</label>
                <select name="agent_id" id="agent_id_select" class="form-select select2">
                    <option value="">Select agent...</option>
                    @foreach($agents as $a)
                        <option value="{{ $a->id }}" @if($selected_agent_id == $a->id) selected @endif>
                            {{ data_get($a, 'user.full_name', 'Unknown') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                @include('partials.filters.date-range', [
                    'mode' => 'single',
                    'formId' => 'payslip-create-filters',
                    'inputId' => 'payslip-create-date',
                    'paramName' => 'date',
                    'startName' => 'start_date',
                    'endName' => 'end_date',
                    'startId' => 'start_date',
                    'endId' => 'end_date',
                    'mirrorHidden' => true,
                    'placeholder' => 'Filter By Appointment Date...'
                ])
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Update</button>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">Eligible Invoice Items</div>
        <div class="card-body p-0">
            <form method="POST" action="{{ route('payslips.store') }}">
                @csrf
                <input type="hidden" name="agent_id" value="{{ $selected_agent_id }}" />
                <input type="hidden" name="start_date" value="{{ $start_date }}" />
                <input type="hidden" name="end_date" value="{{ $end_date }}" />
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th style="width:32px;">
                                    <input type="checkbox" onclick="document.querySelectorAll('.item-select').forEach(cb=>cb.checked=this.checked)" />
                                </th>
                                <th>Date</th>
                                <th>Job ID</th>
                                <th>Language</th>
                                <th>Type</th>
                                <th>Duration</th>
                                <th>Description</th>
                                <th class="text-right">Base</th>
                                <th class="text-right">Expenses</th>
                                <th class="text-right">Parking/Travel</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $invItem)
                                @php
                                    $job = $invItem->job;
                                    $date = $job ? ($job->appointment_date ?? $job->target_date) : null;
                                    $dateStr = $date ? \Carbon\Carbon::parse($date)->format('Y-m-d') : 'N/A';
                                    $language = $job && $job->toLanguage ? $job->toLanguage->name : 'N/A';
                                @endphp
                                <tr>
                                    <td>
                                        <input type="checkbox" class="item-select" name="item_ids[]" value="{{ $invItem->id }}" />
                                    </td>
                                    <td>{{ $dateStr }}</td>
                                    <td>#{{ $invItem->job_id }}</td>
                                    <td>{{ $language }}</td>
                                <td>{{ $invItem->job_type }}</td>
                                <td>
                                    @php
                                        $durationText = 'N/A';
                                        if ($job && $invItem->job_type === 'App\\InterpreterJob') {
                                            $h = (int) ($job->duration_hours ?? 0);
                                            $m = (int) ($job->duration_minutes ?? 0);
                                            if ($h > 0 || $m > 0) {
                                                $durationText = $h . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
                                            } elseif (!empty($job->start_time) && !empty($job->end_time)) {
                                                try {
                                                    $start = \Carbon\Carbon::parse($job->start_time);
                                                    $end = \Carbon\Carbon::parse($job->end_time);
                                                    $mins = $end->diffInMinutes($start);
                                                    $durationText = floor($mins / 60) . ':' . str_pad((string)($mins % 60), 2, '0', STR_PAD_LEFT);
                                                } catch (\Throwable $e) {}
                                            }
                                        }
                                    @endphp
                                    {{ $durationText }}
                                </td>
                                    <td>{{ $invItem->description }}</td>
                                    <td class="text-right">£{{ number_format($invItem->base_amount, 2) }}</td>
                                    <td class="text-right">£{{ number_format($invItem->expenses_amount, 2) }}</td>
                                    <td class="text-right">£{{ number_format($invItem->surcharge, 2) }}</td>
                                    <td class="text-right"><strong>£{{ number_format($invItem->total_amount, 2) }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-center text-muted">No eligible items for the selected period</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- DNA Jobs Section --}}
                @if(isset($dnaJobs) && $dnaJobs->isNotEmpty())
                <div class="card-header bg-warning text-dark mt-2" style="border-top: 2px solid #dc3545;">
                    <strong>⚠️ DNA Jobs (Did Not Attend)</strong>
                    <small class="d-block text-muted">These jobs were marked as DNA. Select to include in payslip for deductions.</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th style="width:32px;">
                                    <input type="checkbox" onclick="document.querySelectorAll('.dna-select').forEach(cb=>cb.checked=this.checked)" />
                                </th>
                                <th>Date</th>
                                <th>Job ID</th>
                                <th>Language</th>
                                <th>Status</th>
                                <th>Duration</th>
                                <th>Client</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dnaJobs as $dnaJob)
                                @php
                                    $dnaDate = $dnaJob->appointment_date;
                                    $dnaDateStr = $dnaDate ? \Carbon\Carbon::parse($dnaDate)->format('Y-m-d') : 'N/A';
                                    $dnaLanguage = optional($dnaJob->toLanguage)->name ?? 'N/A';
                                    $dnaClient = $dnaJob->client ? optional($dnaJob->client->user)->full_name : 'N/A';
                                    $dnaDuration = 'N/A';
                                    $h = (int) ($dnaJob->duration_hours ?? 0);
                                    $m = (int) ($dnaJob->duration_minutes ?? 0);
                                    if ($h > 0 || $m > 0) {
                                        $dnaDuration = $h . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
                                    }
                                @endphp
                                <tr style="background-color: #fff3cd;">
                                    <td>
                                        <input type="checkbox" class="dna-select item-select" name="dna_job_ids[]" value="{{ $dnaJob->id }}" />
                                    </td>
                                    <td>{{ $dnaDateStr }}</td>
                                    <td>#{{ $dnaJob->id }}</td>
                                    <td>{{ $dnaLanguage }}</td>
                                    <td><span class="badge badge-danger" style="background:#dc3545;color:#fff;padding:3px 8px;border-radius:4px;">DNA</span></td>
                                    <td>{{ $dnaDuration }}</td>
                                    <td>{{ $dnaClient }}</td>
                                    <td class="text-right"><strong>£0.00</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                <div class="p-3 d-flex justify-content-end">
                    <button type="submit" class="btn btn-success" @if($items->isEmpty() && (!isset($dnaJobs) || $dnaJobs->isEmpty())) disabled @endif>
                        Create Payslip
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function(){
        var init = function(){
            var el = $('#agent_id_select');
            if (!el.length) return;
            if (typeof $.fn.select2 !== 'function') return;
            try {
                el.select2({ width: '100%', placeholder: 'Select agent...', allowClear: true });
            } catch(e) {}
        };
        if (typeof $.fn.select2 !== 'function') {
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js';
            s.async = true;
            s.onload = init;
            document.head.appendChild(s);
        } else {
            init();
        }
    })();
</script>
@endpush
