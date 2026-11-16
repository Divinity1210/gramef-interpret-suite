@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12 d-flex flex-wrap justify-content-between align-items-center" style="gap:12px;">
            <div class="page-header mb-0">
                <div style="font-family:var(--font-mono);font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#2563EB;">
                    Interpreter Companion Workspace
                </div>
                <h1 class="page-title">Welcome back, {{ optional(auth()->user())->first_name }}</h1>
                <p class="page-subtitle">Your upcoming jobs, 10-second No-Password Client Signature links, and payslips.</p>
            </div>
            @if($agent)
                <div class="d-flex flex-wrap" style="gap:8px;">
                    <span class="ms-traffic-badge ms-traffic-badge--green">
                        <i class="fas fa-star"></i> {{ number_format($agent->reliability_score ?? 98.5, 1) }}% Reliability • {{ number_format($agent->on_time_rate ?? 99.0, 1) }}% On-Time
                    </span>
                    @if($agent->dbs_expiry_date)
                        @php $daysLeft = \Carbon\Carbon::today('Europe/London')->diffInDays(\Carbon\Carbon::parse($agent->dbs_expiry_date), false); @endphp
                        <span class="ms-traffic-badge {{ $daysLeft <= 30 ? 'ms-traffic-badge--amber' : 'ms-traffic-badge--green' }}">
                            <i class="fas fa-shield-alt"></i> DBS {{ $daysLeft >= 0 ? "valid ({$daysLeft}d left)" : 'EXPIRED' }}
                        </span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="ms-kpi-card">
                <div class="small font-weight-bold text-primary">Jobs in Next 7 Days</div>
                <div class="tabular-nums h2 font-weight-bold mt-2 mb-1">{{ $upcomingJobs ?? 0 }}</div>
                <a href="{{ route('interpreter-jobs.index') }}" class="small font-weight-bold">Open Job Board →</a>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="ms-kpi-card">
                <div class="small font-weight-bold text-warning">Starting Within 48 Hours</div>
                <div class="tabular-nums h2 font-weight-bold mt-2 mb-1">{{ $upcomingJobsSoon ?? 0 }}</div>
                <a href="{{ route('timesheet.index') }}" class="small font-weight-bold">My Timesheets →</a>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="ms-kpi-card">
                <div class="small font-weight-bold text-success">Earnings (Last 30 Days)</div>
                <div class="tabular-nums h2 font-weight-bold mt-2 mb-1">{{ $tenantBrand->currency_symbol ?? '£' }}{{ number_format($earnings30d ?? 0, 2) }}</div>
                <a href="{{ route('payslips.index') }}" class="small font-weight-bold">View Payslips →</a>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="ms-kpi-card">
                <div class="small font-weight-bold text-info">Interpreter ID</div>
                <div class="tabular-nums h2 font-weight-bold mt-2 mb-1">{{ optional($agent)->id ? '#' . optional($agent)->id : '—' }}</div>
                <a href="{{ route('agents.profile.edit') }}" class="small font-weight-bold">Update DBS &amp; Profile →</a>
            </div>
        </div>
    </div>

    <div class="ms-card overflow-hidden">
        <div class="p-3 d-flex justify-content-between align-items-center" style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;">
            <h5 class="mb-0 font-weight-bold">My Assigned Jobs &amp; 1-Click No-Password Client Sign-Off</h5>
            <a href="{{ route('interpreter-jobs.index') }}" class="btn btn-sm btn-outline-primary" style="border-radius:999px;">View All Jobs</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:13px;">
                <thead>
                    <tr>
                        <th>Job #</th>
                        <th>Client / Organisation</th>
                        <th>Language</th>
                        <th>Date &amp; Time</th>
                        <th>Status</th>
                        <th class="text-right">Instant Client Sign / Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentJobs as $job)
                        <tr>
                            <td class="tabular-nums font-weight-bold">#{{ $job->id }}</td>
                            <td>
                                {{ optional(optional($job->client)->organisation)->organisation_company ?? optional(optional($job->client)->user)->getFullName() ?? '—' }}
                                <div class="small text-muted">{{ $job->department }} {{ $job->postcode }}</div>
                            </td>
                            <td>{{ optional($job->toLanguage)->name ?? '—' }}</td>
                            <td class="tabular-nums">{{ optional($job->appointment_date)->format('d M Y') }} {{ $job->start_time }}</td>
                            <td><span class="badge badge-primary">{{ ucfirst($job->getStatusForAgent() ?? $job->statusName ?? 'active') }}</span></td>
                            <td class="text-right" style="white-space:nowrap;">
                                <form method="POST" action="{{ route('timesheet.magic-link', $job->id) }}" class="d-inline" target="_blank">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success" style="border-radius:999px;">
                                        <i class="fas fa-qrcode mr-1"></i> Client Sign (10s No-Login)
                                    </button>
                                </form>
                                <a href="{{ route('interpreter-jobs.show', $job->id) }}" class="btn btn-sm btn-outline-secondary" style="border-radius:999px;">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-4 text-muted">No recent jobs found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
