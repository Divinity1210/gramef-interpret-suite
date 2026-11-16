@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap:12px;">
        <div>
            <div style="font-family:var(--font-mono);font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#2563EB;">
                Deliverable 04 · Polite Credit Control &amp; Never Chase Twice
            </div>
            <h1 class="page-title mb-1">Overdue Invoice Tracker &amp; Polite Chaser</h1>
            <p class="page-subtitle">Colour-coded aging board attaching original PDF invoice, Statement of Account &amp; a No-Password 1-Click Remittance Link.</p>
        </div>

        <form method="POST" action="{{ route('admin.credit-control.toggle-mode') }}">
            @csrf
            <button type="submit" class="btn {{ ($tenant->chaser_mode ?? 'review') === 'review' ? 'btn-dark' : 'btn-success' }} px-4 py-2" style="border-radius:999px;font-weight:600;">
                <i class="fas fa-sliders-h mr-2"></i>
                Mode: {{ ($tenant->chaser_mode ?? 'review') === 'review' ? '1 · Review Before Sending (Admin Control)' : '2 · Automatic Scheduled Chaser' }}
            </button>
        </form>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 col-6 mb-3">
            <div class="ms-kpi-card" style="border-left:4px solid #10B981;">
                <div class="small font-weight-bold text-success">Due Soon / &lt;7 Days</div>
                <div class="tabular-nums h3 font-weight-bold mt-1 mb-0">{{ $buckets['current']->count() }}</div>
                <div class="small text-muted tabular-nums">£{{ number_format($buckets['current']->sum('total'), 2) }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="ms-kpi-card" style="border-left:4px solid #3B82F6;">
                <div class="small font-weight-bold text-primary">7–13 Days Overdue</div>
                <div class="tabular-nums h3 font-weight-bold mt-1 mb-0">{{ $buckets['days_7']->count() }}</div>
                <div class="small text-muted tabular-nums">£{{ number_format($buckets['days_7']->sum('total'), 2) }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="ms-kpi-card" style="border-left:4px solid #F59E0B;">
                <div class="small font-weight-bold text-warning">14–29 Days Overdue</div>
                <div class="tabular-nums h3 font-weight-bold mt-1 mb-0">{{ $buckets['days_14']->count() }}</div>
                <div class="small text-muted tabular-nums">£{{ number_format($buckets['days_14']->sum('total'), 2) }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="ms-kpi-card" style="border-left:4px solid #EF4444;">
                <div class="small font-weight-bold text-danger">30–60+ Days Overdue</div>
                <div class="tabular-nums h3 font-weight-bold mt-1 mb-0">{{ $buckets['days_30']->count() + $buckets['days_60_plus']->count() }}</div>
                <div class="small text-muted tabular-nums">£{{ number_format($buckets['days_30']->sum('total') + $buckets['days_60_plus']->sum('total'), 2) }}</div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.credit-control.send-reminders') }}">
        @csrf
        <div class="ms-card overflow-hidden">
            <div class="p-3 d-flex flex-wrap justify-content-between align-items-center" style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;gap:10px;">
                <div class="font-weight-bold"><i class="fas fa-envelope-open-text text-primary mr-2"></i>Review Unpaid Invoices Before Chasing</div>
                <button type="submit" class="btn btn-primary btn-sm px-4" style="border-radius:999px;font-weight:600;">
                    <i class="fas fa-paper-plane mr-1"></i> Send Polite Reminders to Ticked Invoices
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:13px;">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" checked onclick="document.querySelectorAll('.chase-chk').forEach(c => { if(!c.disabled) c.checked = this.checked; })"></th>
                            <th>Invoice #</th>
                            <th>Organisation / Client</th>
                            <th>Invoice Date</th>
                            <th>Overdue Aging</th>
                            <th>Amount</th>
                            <th>Chased</th>
                            <th>1-Click Remittance Link</th>
                            <th class="text-right">Never Chase Twice</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($unpaid as $inv)
                            @php $d = $inv->computed_days_overdue ?? 0; @endphp
                            <tr>
                                <td>
                                    <input type="checkbox" name="invoice_ids[]" value="{{ $inv->id }}" class="chase-chk"
                                        {{ $inv->chaser_paused ? 'disabled' : 'checked' }}>
                                </td>
                                <td class="tabular-nums font-weight-bold">{{ $inv->invoice_number }}</td>
                                <td>
                                    {{ optional(optional($inv->client)->organisation)->organisation_company ?? optional(optional($inv->client)->user)->getFullName() }}
                                </td>
                                <td class="tabular-nums">{{ optional($inv->invoice_date)->format('d M Y') }}</td>
                                <td>
                                    @if($d >= 60)
                                        <span class="badge badge-danger">60+ Days ({{ $d }}d)</span>
                                    @elseif($d >= 30)
                                        <span class="badge badge-danger">30+ Days ({{ $d }}d)</span>
                                    @elseif($d >= 14)
                                        <span class="badge badge-warning">14+ Days ({{ $d }}d)</span>
                                    @elseif($d >= 7)
                                        <span class="badge badge-info">7+ Days ({{ $d }}d)</span>
                                    @else
                                        <span class="badge badge-success">Current</span>
                                    @endif
                                </td>
                                <td class="tabular-nums font-weight-bold">£{{ number_format((float)$inv->total, 2) }}</td>
                                <td class="tabular-nums small text-muted">
                                    {{ $inv->chase_count }}x
                                    @if($inv->last_chased_at)
                                        ({{ \Carbon\Carbon::parse($inv->last_chased_at)->diffForHumans() }})
                                    @endif
                                </td>
                                <td>
                                    @if($inv->remittance_magic_token)
                                        <a href="{{ route('magic.remittance.show', $inv->remittance_magic_token) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-external-link-alt"></i> Open Client Link
                                        </a>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <button type="submit" form="pause-form-{{ $inv->id }}" class="btn btn-sm {{ $inv->chaser_paused ? 'btn-secondary' : 'btn-outline-danger' }}">
                                        {{ $inv->chaser_paused ? 'Paused (Resume)' : 'Pause Chaser' }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center py-4 text-muted">All client invoices are settled!</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    @foreach ($unpaid as $inv)
        <form id="pause-form-{{ $inv->id }}" method="POST" action="{{ route('admin.credit-control.toggle-pause', $inv->id) }}" class="d-none">@csrf</form>
    @endforeach
</div>
@endsection
