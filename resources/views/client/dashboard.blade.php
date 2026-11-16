@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12 d-flex flex-wrap justify-content-between align-items-center" style="gap:12px;">
            <div class="page-header mb-0">
                <div style="font-family:var(--font-mono);font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#2563EB;">
                    Peace-of-Mind Client Portal
                </div>
                <h1 class="page-title">Client Booking &amp; Live Tracking Desk</h1>
                <p class="page-subtitle">Real-time 5-stage interpreter tracking, 1-click follow-up bookings, and instant remittance uploads.</p>
            </div>
            <a href="{{ route('interpreter-jobs.create') }}" class="btn btn-primary px-4 font-weight-bold" style="border-radius:999px;">
                <i class="fas fa-plus mr-1"></i> New / Paste-an-Email Booking
            </a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="ms-kpi-card">
                <div class="small font-weight-bold text-primary">Interpreter Jobs (7d)</div>
                <div class="tabular-nums h2 font-weight-bold mt-2 mb-1">{{ $upcomingInterpreterJobs ?? 0 }}</div>
                <a href="{{ route('interpreter-jobs.index') }}" class="small font-weight-bold">Browse Bookings →</a>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="ms-kpi-card">
                <div class="small font-weight-bold text-info">Translation Jobs (7d)</div>
                <div class="tabular-nums h2 font-weight-bold mt-2 mb-1">{{ $upcomingTranslatorJobs ?? 0 }}</div>
                <a href="{{ route('translator-jobs.index') }}" class="small font-weight-bold">Browse Translations →</a>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="ms-kpi-card">
                <div class="small font-weight-bold text-warning">Invoices Open / Due</div>
                <div class="tabular-nums h2 font-weight-bold mt-2 mb-1">{{ $invoiceStats['sent'] ?? 0 }}</div>
                <a href="{{ route('invoices.client.index') }}" class="small font-weight-bold">View Invoices →</a>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="ms-kpi-card">
                <div class="small font-weight-bold text-success">Invoices Paid</div>
                <div class="tabular-nums h2 font-weight-bold mt-2 mb-1">{{ $invoiceStats['paid'] ?? 0 }}</div>
                <a href="{{ route('receipts.client.index') }}" class="small font-weight-bold">Download Receipts →</a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-7 mb-4">
            <div class="ms-card overflow-hidden h-100">
                <div class="p-3 d-flex justify-content-between align-items-center" style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;">
                    <h5 class="mb-0 font-weight-bold"><i class="fas fa-route text-primary mr-2"></i>6A. Live 5-Stage Appointment Tracker &amp; 1-Click Follow-Up</h5>
                </div>
                <div class="p-3">
                    @forelse($recentJobs as $job)
                        @php
                            $stage = 1; // Booked
                            if ($job->agent_id) $stage = 2; // Assigned
                            if ($job->client_confirmed_at) $stage = 3; // Confirmed
                            if ($job->checked_in_at) $stage = 4; // Checked In On-Site
                            if ((int)$job->status === 4) $stage = 5; // Completed
                            $steps = ['Booked', 'Assigned', 'Confirmed', 'Checked In On-Site', 'Completed'];
                        @endphp
                        <div class="p-3 mb-3 rounded" style="border:1px solid #E2E8F0;background:#FFFFFF;">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2" style="gap:8px;">
                                <div>
                                    <a href="{{ route('interpreter-jobs.show', $job->id) }}" class="font-weight-bold text-dark">
                                        #{{ $job->id }} • {{ optional($job->toLanguage)->name ?? 'Language' }}
                                    </a>
                                    <span class="small text-muted ml-2">
                                        {{ optional($job->appointment_date)->format('d M Y') }} {{ $job->start_time }} • {{ $job->department ?: $job->postcode }}
                                    </span>
                                </div>
                                <div class="d-flex align-items-center" style="gap:6px;">
                                    <form method="POST" action="{{ route('interpreter-jobs.follow-up', $job->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary" style="border-radius:999px;" title="Repeat booking keeping service user & requesting same interpreter">
                                            <i class="fas fa-redo mr-1"></i> Book Follow-Up
                                        </button>
                                    </form>
                                    <a href="{{ route('interpreter-jobs.show', $job->id) }}" class="btn btn-sm btn-outline-secondary" style="border-radius:999px;">View</a>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2" style="gap:4px;">
                                @foreach($steps as $idx => $label)
                                    @php $active = ($idx + 1) <= $stage; @endphp
                                    <div style="flex:1;text-align:center;">
                                        <div style="height:5px;border-radius:999px;background:{{ $active ? '#10B981' : '#E2E8F0' }};margin-bottom:4px;"></div>
                                        <div style="font-size:10.5px;font-weight:{{ $active ? '700' : '500' }};color:{{ $active ? '#065F46' : '#94A3B8' }};">{{ $label }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">No recent bookings found.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-5 mb-4">
            <div class="ms-card overflow-hidden h-100">
                <div class="p-3 d-flex justify-content-between align-items-center" style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;">
                    <h5 class="mb-0 font-weight-bold">Recent Invoices &amp; 1-Click Remittance</h5>
                    <a href="{{ route('invoices.client.index') }}" class="btn btn-sm btn-outline-primary" style="border-radius:999px;">All Invoices</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentInvoices as $inv)
                                <tr>
                                    <td class="tabular-nums font-weight-bold">{{ $inv->invoice_number }}</td>
                                    <td class="tabular-nums">£{{ number_format((float)($inv->total ?? 0), 2) }}</td>
                                    <td><span class="badge {{ $inv->status === 'paid' ? 'badge-success' : 'badge-warning' }}">{{ ucfirst($inv->status ?? 'draft') }}</span></td>
                                    <td class="text-right" style="white-space:nowrap;">
                                        @if($inv->remittance_magic_token && $inv->status !== 'paid')
                                            <a href="{{ route('magic.remittance.show', $inv->remittance_magic_token) }}" target="_blank" class="btn btn-sm btn-primary" style="border-radius:999px;">Confirm Paid</a>
                                        @endif
                                        <a href="{{ route('invoices.client.show', $inv->id) }}" class="btn btn-sm btn-outline-secondary" style="border-radius:999px;">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-4 text-muted">No recent invoices found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
