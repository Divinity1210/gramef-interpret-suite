@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-flex flex-wrap justify-content-between align-items-center" style="gap:12px;">
                <div>
                    <div style="font-family:var(--font-mono);font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#2563EB;">
                        {{ $tenantBrand->brand_name ?? 'GRAMEF InterpretFlow' }} • Executive Command Center
                    </div>
                    <h1 class="page-title">Admin Dashboard</h1>
                    <p class="page-subtitle">Same familiar routine — now backed by quiet Smart Helpers that cut ~45 admin hours every month.</p>
                </div>
                <div class="d-flex flex-wrap" style="gap:8px;">
                    <a href="{{ route('interpreter-jobs.create') }}" class="btn btn-primary btn-sm px-3" style="border-radius:999px;font-weight:600;">
                        <i class="fas fa-magic mr-1"></i> Paste-an-Email Booking
                    </a>
                    <a href="{{ route('admin.traffic-light.index') }}" class="btn btn-success btn-sm px-3" style="border-radius:999px;font-weight:600;">
                        <i class="fas fa-traffic-light mr-1"></i> Traffic-Light Board
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Executive Proposal Deliverables Quick Launchpad -->
    <div class="row mb-4">
        <div class="col-md-4 col-xl-2 mb-3">
            <a href="{{ route('admin.traffic-light.index') }}" class="ms-kpi-card d-block text-decoration-none h-100">
                <div class="small font-weight-bold text-success"><i class="fas fa-traffic-light mr-1"></i> Traffic-Light</div>
                <div class="small text-muted mt-1">Side-by-side margin &amp; receipt checks</div>
            </a>
        </div>
        <div class="col-md-4 col-xl-2 mb-3">
            <a href="{{ route('admin.price-book.index') }}" class="ms-kpi-card d-block text-decoration-none h-100">
                <div class="small font-weight-bold text-primary"><i class="fas fa-calculator mr-1"></i> Price Book</div>
                <div class="small text-muted mt-1">Auto daytime &amp; OOH rate cards</div>
            </a>
        </div>
        <div class="col-md-4 col-xl-2 mb-3">
            <a href="{{ route('admin.credit-control.index') }}" class="ms-kpi-card d-block text-decoration-none h-100">
                <div class="small font-weight-bold text-warning"><i class="fas fa-hand-holding-usd mr-1"></i> Credit Control</div>
                <div class="small text-muted mt-1">7/14/30/60+ day polite chaser</div>
            </a>
        </div>
        <div class="col-md-4 col-xl-2 mb-3">
            <a href="{{ route('admin.reconciliation.index') }}" class="ms-kpi-card d-block text-decoration-none h-100">
                <div class="small font-weight-bold text-info"><i class="fas fa-file-invoice-dollar mr-1"></i> Reconcile &amp; BACS</div>
                <div class="small text-muted mt-1">Smart remittance &amp; bank sheet</div>
            </a>
        </div>
        <div class="col-md-4 col-xl-2 mb-3">
            <a href="{{ route('admin.compliance.index') }}" class="ms-kpi-card d-block text-decoration-none h-100">
                <div class="small font-weight-bold text-danger"><i class="fas fa-shield-alt mr-1"></i> DBS Guard</div>
                <div class="small text-muted mt-1">60/30-day auto expiry alerts</div>
            </a>
        </div>
        <div class="col-md-4 col-xl-2 mb-3">
            <a href="{{ route('admin.white-label.index') }}" class="ms-kpi-card d-block text-decoration-none h-100">
                <div class="small font-weight-bold text-dark"><i class="fas fa-palette mr-1"></i> White-Label</div>
                <div class="small text-muted mt-1">Customise org brand &amp; stages</div>
            </a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title">{{ $pendingPayslips ?? 0 }}</h4>
                            <p class="card-text">Pending Payslips</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-file-invoice fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="{{ route('payslips.index', ['status' => 'pending']) }}" class="text-white">
                        <small>View All <i class="fas fa-arrow-right"></i></small>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title">{{ $pendingRemittances ?? 0 }}</h4>
                            <p class="card-text">Pending Remittances</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-upload fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="{{ route('remittances.index', ['status' => 'pending']) }}" class="text-white">
                        <small>View All <i class="fas fa-arrow-right"></i></small>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title">{{ $approvedToday ?? 0 }}</h4>
                            <p class="card-text">Approved Today</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title">{{ $totalProcessed ?? 0 }}</h4>
                            <p class="card-text">Total Processed</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Payslips Requiring Approval</h5>
                </div>
                <div class="card-body">
                    @if(isset($recentPayslips) && $recentPayslips->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Number</th>
                                        <th>Agent</th>
                                        <th>Amount</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentPayslips as $payslip)
                                    <tr>
                                        <td>{{ $payslip->payslip_number }}</td>
                                        <td>{{ optional(optional($payslip->agent)->user)->getFullName() ?? 'N/A' }}</td>
                                        <td class="tabular-nums">{{ $tenantBrand->currency_symbol ?? '£' }}{{ number_format((float)($payslip->total ?? 0), 2) }}</td>
                                        <td>{{ $payslip->created_at->format('M d, Y') }}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('payslips.show', $payslip) }}" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <form method="POST" action="{{ route('payslips.approve', $payslip) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this payslip?')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted">No pending payslips</p>
                    @endif
                </div>
                <div class="card-footer">
                    <a href="{{ route('payslips.index') }}" class="btn btn-primary btn-sm">View All Payslips</a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Remittances Requiring Approval</h5>
                </div>
                <div class="card-body">
                    @if(isset($recentRemittances) && $recentRemittances->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Number</th>
                                        <th>Client</th>
                                        <th>Amount</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentRemittances as $remittance)
                                    <tr>
                                        <td>{{ $remittance->remittance_number }}</td>
                                        <td>{{ optional(optional($remittance->clientInvoice)->client)->user->getFullName() ?? 'N/A' }}</td>
                                        <td>£{{ number_format($remittance->amount, 2) }}</td>
                                        <td>{{ $remittance->created_at->format('M d, Y') }}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('remittances.show', $remittance) }}" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <form method="POST" action="{{ route('remittances.approve', $remittance) }}" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this remittance?')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted">No pending remittances</p>
                    @endif
                </div>
                <div class="card-footer">
                    <a href="{{ route('remittances.index') }}" class="btn btn-primary btn-sm">View All Remittances</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="{{ route('payslips.create') }}" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-plus"></i> Create Payslip
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('payslips.index', ['status' => 'pending']) }}" class="btn btn-outline-warning btn-block">
                                <i class="fas fa-clock"></i> Review Payslips
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('remittances.index', ['status' => 'pending']) }}" class="btn btn-outline-info btn-block">
                                <i class="fas fa-upload"></i> Review Remittances
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('report.index') }}" class="btn btn-outline-success btn-block">
                                <i class="fas fa-chart-bar"></i> View Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Jobs Overview -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Interpreter Jobs Overview</h5>
                    <a href="{{ route('interpreter-jobs.index') }}" class="btn btn-sm btn-outline-primary">Go to Job Board</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="card bg-secondary text-white">
                                <div class="card-body d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">{{ $interpreterJobStats['pending'] ?? 0 }}</h4>
                                        <p class="card-text">Pending</p>
                                    </div>
                                    <div class="align-self-center"><i class="fas fa-hourglass-half fa-2x"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-primary text-white">
                                <div class="card-body d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">{{ $interpreterJobStats['assigned'] ?? 0 }}</h4>
                                        <p class="card-text">Assigned</p>
                                    </div>
                                    <div class="align-self-center"><i class="fas fa-user-check fa-2x"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-success text-white">
                                <div class="card-body d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">{{ $interpreterJobStats['completed'] ?? 0 }}</h4>
                                        <p class="card-text">Completed</p>
                                    </div>
                                    <div class="align-self-center"><i class="fas fa-check fa-2x"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-warning text-white">
                                <div class="card-body d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">{{ $interpreterJobStats['quoted'] ?? 0 }}</h4>
                                        <p class="card-text">Quoted</p>
                                    </div>
                                    <div class="align-self-center"><i class="fas fa-file-signature fa-2x"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-danger text-white">
                                <div class="card-body d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">{{ $urgentUnassigned ?? 0 }}</h4>
                                        <p class="card-text">Starting < 48h</p>
                                    </div>
                                    <div class="align-self-center"><i class="fas fa-exclamation-circle fa-2x"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-dark text-white">
                                <div class="card-body d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">{{ $interpreterJobStats['cancelled'] ?? 0 }}</h4>
                                        <p class="card-text">Cancelled</p>
                                    </div>
                                    <div class="align-self-center"><i class="fas fa-ban fa-2x"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Jobs Needing Assignment -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Jobs Needing Assignment</h5>
                    <a href="{{ route('interpreter-jobs.index', ['status' => 0]) }}" class="btn btn-sm btn-outline-primary">View All Pending Jobs</a>
                </div>
                <div class="card-body">
                    @if(isset($jobsNeedingAssignment) && $jobsNeedingAssignment->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Ref</th>
                                        <th>Client</th>
                                        <th>Language</th>
                                        <th>Appt. Date</th>
                                        <th>Appt. Time</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($jobsNeedingAssignment as $job)
                                        <tr>
                                            <td>{{ $job->reference }}</td>
                                            <td>{{ $job->client->user->getFullName() ?? 'N/A' }}</td>
                                            <td>{{ $job->toLanguage->name ?? 'N/A' }}</td>
                                            <td>{{ $job->appointment_date ? $job->appointment_date->format('d/m/Y') : 'N/A' }}</td>
                                            <td>{{ $job->start_time ?? 'N/A' }}</td>
                                            <td>
                                                <span class="badge badge-secondary text-uppercase">{{ $job->statusName }}</span>
                                                @if ($job->showQuoteRejectedFlagForUser(auth()->user()))
                                                    <span class="badge badge-warning ml-2">Quote: Rejected</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('interpreter-jobs.matched.index', $job) }}" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-user-plus"></i> Assign
                                                    </a>
                                                    <a href="{{ route('interpreter-jobs.show', $job) }}" class="btn btn-outline-secondary btn-sm">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No jobs require assignment right now.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Interpreter Jobs -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Interpreter Jobs</h5>
                    <a href="{{ route('interpreter-jobs.index') }}" class="btn btn-sm btn-outline-primary">Browse Job Board</a>
                </div>
                <div class="card-body">
                    @if(isset($recentInterpreterJobs) && $recentInterpreterJobs->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Ref</th>
                                        <th>Client</th>
                                        <th>Language</th>
                                        <th>Submitted</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentInterpreterJobs as $job)
                                        <tr>
                                            <td>{{ $job->reference }}</td>
                                            <td>{{ $job->client->user->getFullName() ?? 'N/A' }}</td>
                                            <td>{{ $job->toLanguage->name ?? 'N/A' }}</td>
                                            <td>{{ $job->created_at->format('d/m/Y') }}</td>
                                            <td>
                                                <span class="badge badge-light text-uppercase">{{ $job->statusName }}</span>
                                                @php
                                                    $isVerifiedTs = optional($job->timesheet)->signature_method === 'paper' && optional($job->timesheet)->status === 'verified';
                                                @endphp
                                                @if($isVerifiedTs)
                                                    <span class="badge badge-success ml-2">Timesheet: Verified</span>
                                                @endif
                                                @if ($job->showQuoteRejectedFlagForUser(auth()->user()))
                                                    <span class="badge badge-warning ml-2">Quote: Rejected</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('interpreter-jobs.show', $job) }}" class="btn btn-outline-secondary btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No recent jobs found.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Overview -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Financial Overview</h5>
                    <a href="{{ route('invoices.admin.index') }}" class="btn btn-sm btn-outline-primary">Manage Invoices</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">{{ $clientInvoiceStats['sent'] ?? 0 }}</h4>
                                        <p class="card-text">Client Invoices Sent</p>
                                    </div>
                                    <div class="align-self-center"><i class="fas fa-file-invoice fa-2x"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">{{ $clientInvoiceStats['paid'] ?? 0 }}</h4>
                                        <p class="card-text">Client Invoices Paid</p>
                                    </div>
                                    <div class="align-self-center"><i class="fas fa-check-circle fa-2x"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">{{ $clientInvoiceStats['overdue'] ?? 0 }}</h4>
                                        <p class="card-text">Overdue Client Invoices</p>
                                    </div>
                                    <div class="align-self-center"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">{{ $pendingPaymentProofs ?? 0 }}</h4>
                                        <p class="card-text">Pending Payment Proofs</p>
                                    </div>
                                    <div class="align-self-center"><i class="fas fa-receipt fa-2x"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <div class="card bg-secondary text-white">
                                <div class="card-body d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-1">Agent Invoices</h6>
                                        <div class="small">Total: {{ $agentInvoiceStats['total'] ?? 0 }}</div>
                                        <div class="small">Sent: {{ $agentInvoiceStats['sent'] ?? 0 }}</div>
                                        <div class="small">Paid: {{ $agentInvoiceStats['paid'] ?? 0 }}</div>
                                    </div>
                                    <div class="align-self-center"><i class="fas fa-user-tag fa-2x"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Log -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Activity</h5>
                    <a href="{{ route('admin.activity.index') }}" class="btn btn-sm btn-outline-secondary">View Full Activity</a>
                </div>
                <div class="card-body">
                    @if(isset($recentActivity) && $recentActivity->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Actor</th>
                                        <th>Action</th>
                                        <th>Entity</th>
                                        <th>Metadata</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentActivity as $log)
                                        <tr>
                                            <td>{{ optional($log->created_at)->format('d/m/Y H:i') }}</td>
                                            <td>
                                                {{ optional($log->actor)->getFullName() ?? 'System' }}
                                                @if(optional($log->actor)->email)
                                                    <div class="small text-muted">{{ optional($log->actor)->email }}</div>
                                                @endif
                                            </td>
                                            <td><span class="badge badge-light text-uppercase">{{ $log->action }}</span></td>
                                            <td>
                                                {{ $log->entity_type ?? '—' }}
                                                @if($log->entity_id)
                                                    <div class="small text-muted">#{{ $log->entity_id }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @php $md = is_array($log->metadata) ? $log->metadata : []; @endphp
                                                @if(!empty($md))
                                                    <div class="small text-muted">
                                                    @foreach(array_slice($md, 0, 4) as $k => $v)
                                                        <span class="mr-2">
                                                            <strong>{{ is_string($k) ? $k : 'meta' }}</strong>:
                                                            {{ is_array($v) ? json_encode($v) : (string)$v }}
                                                        </span>
                                                    @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $log->ip_address ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No recent activity logged.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
(function() {
    const dataEndpoint = "{{ route('admin.dashboard.jobs-needing-assignment.data') }}";
    const refreshIntervalMs = 90 * 1000; // 90 seconds

    function findJobsTableBody() {
        // Locate the 'Jobs Needing Assignment' card by title text, then find its first table tbody
        const titles = Array.from(document.querySelectorAll('.card-title'));
        const target = titles.find(el => el && el.textContent && el.textContent.trim() === 'Jobs Needing Assignment');
        if (!target) return null;
        const card = target.closest('.card');
        if (!card) return null;
        const tbody = card.querySelector('table tbody');
        return tbody;
    }

    async function refreshJobs() {
        try {
            const res = await fetch(dataEndpoint, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const payload = await res.json();
            if (!payload || !Array.isArray(payload.jobs)) return;
            const tbody = findJobsTableBody();
            if (!tbody) return;
            const rows = payload.jobs.map(j => `
                <tr>
                    <td>${escapeHtml(j.reference || 'N/A')}</td>
                    <td>${escapeHtml(j.client_name || 'N/A')}</td>
                    <td>${escapeHtml(j.language || 'N/A')}</td>
                    <td>${escapeHtml(j.appointment_date || 'N/A')}</td>
                    <td>${escapeHtml(j.start_time || 'N/A')}</td>
                    <td><span class="badge badge-secondary text-uppercase">${escapeHtml(j.status || 'Pending')}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="${j.assign_url}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-user-plus"></i> Assign
                            </a>
                            <a href="${j.view_url}" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </td>
                </tr>
            `).join('');
            tbody.innerHTML = rows;
        } catch (e) {
            // Silently ignore errors
        }
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    window.addEventListener('load', () => {
        setTimeout(refreshJobs, 1000);
        setInterval(refreshJobs, refreshIntervalMs);
    });
})();
</script>
@endpush
@endsection

@push('styles')
<style>
.page-header {
    margin-bottom: 2rem;
}

.page-title {
    font-size: 2rem;
    font-weight: 600;
    color: #333;
}

.page-subtitle {
    color: #666;
    margin-bottom: 0;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border-radius: 0.5rem;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.btn-group-sm > .btn, .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.table-sm th, .table-sm td {
    padding: 0.5rem;
}

.badge {
    padding: 0.45rem 0.6rem;
    font-size: 0.75rem;
}
</style>
@endpush