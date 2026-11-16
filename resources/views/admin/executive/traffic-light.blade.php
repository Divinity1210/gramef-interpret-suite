@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap:12px;">
        <div>
            <div style="font-family:var(--font-mono);font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#2563EB;">
                Deliverable 02 · Smart Margin &amp; Safety Guard
            </div>
            <h1 class="page-title mb-1">Side-by-Side Comparison &amp; Traffic-Light Checker</h1>
            <p class="page-subtitle">Compare Booked Time, GPS Check-In/Out, Signed Timesheet, Interpreter Pay, Client Invoice &amp; Net Margin on one row.</p>
        </div>

        <form method="POST" action="{{ route('admin.traffic-light.approve-all-green') }}">
            @csrf
            <button type="submit" class="btn btn-success font-weight-bold px-4 py-2" style="border-radius:999px;">
                <i class="fas fa-check-double mr-2"></i>1-Click Approve All GREEN ({{ $greenCount }})
            </button>
        </form>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="ms-kpi-card">
                <div style="font-family:var(--font-mono);font-size:11px;color:#059669;text-transform:uppercase;letter-spacing:0.1em;">GREEN · Ready for 1-Click Approval</div>
                <div class="tabular-nums mt-2" style="font-size:32px;font-weight:700;color:#0F172A;">{{ $greenCount }}</div>
                <div style="font-size:12.5px;color:#64748B;">Hours, GPS check-in, receipt photos &amp; rates align</div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="ms-kpi-card">
                <div style="font-family:var(--font-mono);font-size:11px;color:#D97706;text-transform:uppercase;letter-spacing:0.1em;">AMBER · Please Check Warnings</div>
                <div class="tabular-nums mt-2" style="font-size:32px;font-weight:700;color:#B45309;">{{ $amberCount }}</div>
                <div style="font-size:12.5px;color:#64748B;">Plain-English discrepancy alerts caught before payout</div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="ms-kpi-card">
                <div style="font-family:var(--font-mono);font-size:11px;color:#2563EB;text-transform:uppercase;letter-spacing:0.1em;">Tracked Net Agency Margin</div>
                <div class="tabular-nums mt-2" style="font-size:32px;font-weight:700;color:#0F172A;">{{ $tenantBrand->currency_symbol ?? '£' }}{{ number_format($totalMargin, 2) }}</div>
                <div style="font-size:12.5px;color:#64748B;">Calculated automatically via Price Book</div>
            </div>
        </div>
    </div>

    <div class="ms-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:13px;">
                <thead style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;">
                    <tr>
                        <th>Job / Client</th>
                        <th>Booked Time</th>
                        <th>GPS Check-In/Out</th>
                        <th>Timesheet Time</th>
                        <th>Interpreter Pay</th>
                        <th>Client Invoice</th>
                        <th>Net Margin</th>
                        <th>Traffic-Light Check</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $job = $row['job'];
                            $eval = $row['eval'];
                            $isGreen = $eval['status'] === 'green';
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('interpreter-jobs.show', $job->id) }}" class="font-weight-bold text-dark">
                                    #{{ $job->id }} · {{ $job->client_reference ?: 'JOB-' . $job->id }}
                                </a>
                                <div class="text-muted small">
                                    {{ optional(optional($job->client)->organisation)->organisation_company ?? optional(optional($job->client)->user)->getFullName() ?? 'Client' }}
                                    • {{ optional($job->toLanguage)->name }}
                                </div>
                            </td>
                            <td class="tabular-nums">{{ $eval['booked_display'] }}</td>
                            <td class="tabular-nums">
                                <span class="{{ $job->checked_in_at ? 'text-success font-weight-bold' : 'text-muted' }}">
                                    {{ $eval['gps_display'] }}
                                </span>
                            </td>
                            <td class="tabular-nums">{{ $eval['timesheet_display'] }}</td>
                            <td class="tabular-nums font-weight-bold">£{{ number_format($eval['interpreter_pay'], 2) }}</td>
                            <td class="tabular-nums font-weight-bold">£{{ number_format($eval['client_charge'], 2) }}</td>
                            <td class="tabular-nums font-weight-bold {{ $eval['margin'] >= 0 ? 'text-success' : 'text-danger' }}">
                                £{{ number_format($eval['margin'], 2) }}
                            </td>
                            <td style="max-width:300px;">
                                <span class="ms-traffic-badge {{ $isGreen ? 'ms-traffic-badge--green' : 'ms-traffic-badge--amber' }}">
                                    <i class="fas {{ $isGreen ? 'fa-check-circle' : 'fa-exclamation-triangle' }}"></i>
                                    {{ $eval['badge_label'] }}
                                </span>
                                @if(!$isGreen && !empty($eval['warnings']))
                                    <ul class="mb-0 mt-2 pl-3 text-danger small" style="line-height:1.45;">
                                        @foreach ($eval['warnings'] as $w)
                                            <li>{{ $w }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                            <td class="text-right" style="white-space:nowrap;">
                                @if($job->timesheet)
                                    <form method="POST" action="{{ route('admin.smart-reader.timesheet', $job->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary mb-1" title="Run Smart Paper Timesheet & Receipt Reader">
                                            <i class="fas fa-magic"></i> Smart OCR
                                        </button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.traffic-light.approve', $job->id) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="interpreter_pay" value="{{ $eval['interpreter_pay'] }}">
                                    <input type="hidden" name="client_charge" value="{{ $eval['client_charge'] }}">
                                    <button type="submit" class="btn btn-sm {{ $isGreen ? 'btn-success' : 'btn-warning' }} mb-1">
                                        <i class="fas fa-check"></i> {{ $isGreen ? 'Approve' : 'Override & Approve' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-5 text-muted">No jobs queued for Traffic-Light inspection.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
