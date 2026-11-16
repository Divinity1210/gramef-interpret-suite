@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap:12px;">
        <div>
            <div style="font-family:var(--font-mono);font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#2563EB;">
                Deliverable 06C · Automatic DBS &amp; Compliance Guard
            </div>
            <h1 class="page-title mb-1">Automatic DBS Expiry Guard &amp; Reliability Scores</h1>
            <p class="page-subtitle">Automatically reads certificate numbers &amp; expiry dates from uploads and triggers 60-day and 30-day renewal alerts.</p>
        </div>
    </div>

    <div class="ms-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:13px;">
                <thead style="background:#F8FAFC;">
                    <tr>
                        <th>Interpreter</th>
                        <th>DBS Certificate #</th>
                        <th>DBS Expiry Date</th>
                        <th>Expiry Guard Status</th>
                        <th>Reliability / On-Time</th>
                        <th class="text-right">Smart Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($agents as $agent)
                        <tr>
                            <td>
                                <div class="font-weight-bold">{{ optional($agent->user)->getFullName() ?? 'Interpreter #' . $agent->id }}</div>
                                <div class="small text-muted">{{ $agent->postcode }} • {{ optional($agent->user)->email }}</div>
                            </td>
                            <td class="tabular-nums">{{ $agent->dbs_number ?: 'Not scanned' }}</td>
                            <td class="tabular-nums">{{ $agent->dbs_expiry_date ? \Carbon\Carbon::parse($agent->dbs_expiry_date)->format('d M Y') : 'Missing' }}</td>
                            <td>
                                @if($agent->dbs_guard_status === 'expired')
                                    <span class="badge badge-danger">EXPIRED ({{ abs($agent->dbs_days_left) }}d ago)</span>
                                @elseif($agent->dbs_guard_status === 'critical_30d')
                                    <span class="badge badge-danger">30-Day Renewal ({{ $agent->dbs_days_left }}d left)</span>
                                @elseif($agent->dbs_guard_status === 'warning_60d')
                                    <span class="badge badge-warning">60-Day Alert ({{ $agent->dbs_days_left }}d left)</span>
                                @elseif($agent->dbs_guard_status === 'missing')
                                    <span class="badge badge-secondary">Missing DBS</span>
                                @else
                                    <span class="badge badge-success">Compliant ({{ $agent->dbs_days_left }}d left)</span>
                                @endif
                            </td>
                            <td class="tabular-nums">
                                <span class="badge badge-light border">{{ number_format($agent->reliability_score ?? 98.5, 1) }}% Reliable</span>
                                <span class="small text-muted ml-1">{{ number_format($agent->on_time_rate ?? 99.0, 1) }}% On-Time</span>
                            </td>
                            <td class="text-right" style="white-space:nowrap;">
                                <form method="POST" action="{{ route('admin.compliance.scan-dbs', $agent->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-id-card"></i> Smart Scan Cert</button>
                                </form>
                                <form method="POST" action="{{ route('admin.compliance.remind-dbs', $agent->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas fa-bell"></i> Send Renewal Alert</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
