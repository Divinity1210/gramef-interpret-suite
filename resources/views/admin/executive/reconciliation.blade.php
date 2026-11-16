@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap:12px;">
        <div>
            <div style="font-family:var(--font-mono);font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#2563EB;">
                Deliverable 03 · Bookkeeping &amp; Bank Payout Automation
            </div>
            <h1 class="page-title mb-1">Bulk Reconciliation, Smart Remittance Matcher &amp; Bank Sheet</h1>
            <p class="page-subtitle">Match NHS/Council multi-invoice remittance slips automatically, bulk mark invoices paid, and export BACS/Faster Payments CSV payout sheets.</p>
        </div>

        <a href="{{ route('admin.reconciliation.bank-payout-csv') }}" class="btn btn-success px-4 py-2 font-weight-bold" style="border-radius:999px;">
            <i class="fas fa-file-csv mr-2"></i>Download Bank Payment File ({{ $approvedPayslips->count() }} Approved)
        </a>
    </div>

    <div class="row mb-4">
        <div class="col-lg-6 mb-4">
            <div class="ms-card p-4 h-100">
                <h5 class="font-weight-bold mb-2"><i class="fas fa-magic text-primary mr-2"></i>3B. Smart Remittance Reader (Multi-Invoice Matching)</h5>
                <p class="small text-muted mb-3">Paste any council, court, or NHS trust remittance slip (or upload CSV/TXT) containing multiple <code>CI-XXXXXX</code> invoice numbers &amp; amounts.</p>
                <form method="POST" action="{{ route('admin.reconciliation.match-remittance') }}" enctype="multipart/form-data">
                    @csrf
                    <textarea id="remittanceInput" name="remittance_text" rows="5" class="form-control mb-2 tabular-nums" style="font-size:12.5px;"
                        placeholder="Example:&#10;CI-104201  NHS Trust Batch  £1420.00&#10;CI-104203  NHS Trust Batch  £645.00">{{ $remittanceText }}</textarea>
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3" style="gap:8px;">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                            onclick="document.getElementById('remittanceInput').value='BACS Remittance Advice - UHB NHS Trust\nCI-104201 | Interpreting Batch | £1420.00\nCI-104203 | Outpatients Batch | £645.00\nCI-104205 | Maternity Batch | £384.00';">
                            Load Sample NHS Remittance
                        </button>
                        <button type="submit" class="btn btn-sm btn-primary px-3" style="border-radius:999px;">Scan &amp; Match Invoices</button>
                    </div>
                </form>

                @if (!empty($matchedResults))
                    <form method="POST" action="{{ route('admin.reconciliation.apply-remittance') }}">
                        @csrf
                        <div class="table-responsive border rounded">
                            <table class="table table-sm mb-0" style="font-size:12.5px;">
                                <thead class="bg-light">
                                    <tr>
                                        <th></th>
                                        <th>Invoice #</th>
                                        <th>Client</th>
                                        <th>Remittance</th>
                                        <th>Match</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($matchedResults as $m)
                                        <tr>
                                            <td>
                                                @if($m['found'] && $m['status'] !== 'paid')
                                                    <input type="checkbox" name="matched_invoice_ids[]" value="{{ $m['invoice_id'] }}" checked>
                                                @endif
                                            </td>
                                            <td class="tabular-nums font-weight-bold">{{ $m['invoice_number'] }}</td>
                                            <td>{{ $m['client_name'] }}</td>
                                            <td class="tabular-nums">£{{ number_format($m['remittance_amount'], 2) }}</td>
                                            <td>
                                                @if(!$m['found'])
                                                    <span class="badge badge-secondary">Not Found</span>
                                                @elseif($m['status'] === 'paid')
                                                    <span class="badge badge-info">Already Paid</span>
                                                @elseif($m['exact_match'])
                                                    <span class="badge badge-success">Exact Match</span>
                                                @else
                                                    <span class="badge badge-warning">Partial / Check</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="btn btn-success btn-sm mt-3 btn-block font-weight-bold" style="border-radius:999px;">
                            1-Click Reconcile Matched &amp; Generate PDF Receipts
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="ms-card p-4 h-100">
                <h5 class="font-weight-bold mb-2"><i class="fas fa-university text-success mr-2"></i>3C. Approved Interpreter Payslips (Batch Bank Payout)</h5>
                <p class="small text-muted mb-3">Download the BACS/Faster Payments CSV above, pay your interpreters in one batch, then click <strong>Bulk Mark Selected as Paid</strong>.</p>
                <form method="POST" action="{{ route('admin.reconciliation.bulk-mark-paid') }}">
                    @csrf
                    <input type="hidden" name="target_type" value="payslips">
                    <div class="table-responsive border rounded">
                        <table class="table table-sm mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th><input type="checkbox" checked onclick="document.querySelectorAll('.ps-chk').forEach(c => c.checked = this.checked)"></th>
                                    <th>Payslip #</th>
                                    <th>Interpreter</th>
                                    <th>Bank Sort / Acct</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($approvedPayslips as $ps)
                                    @php $bank = is_array($ps->bank_snapshot) ? $ps->bank_snapshot : []; @endphp
                                    <tr>
                                        <td><input type="checkbox" name="ids[]" value="{{ $ps->id }}" class="ps-chk" checked></td>
                                        <td class="tabular-nums font-weight-bold">{{ $ps->payslip_number }}</td>
                                        <td>{{ optional(optional($ps->agent)->user)->getFullName() ?? 'Interpreter' }}</td>
                                        <td class="tabular-nums text-muted">{{ $bank['sort_code'] ?? '20-00-00' }} / {{ $bank['account_number'] ?? '••••5678' }}</td>
                                        <td class="tabular-nums font-weight-bold">£{{ number_format((float)$ps->total, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No approved payslips awaiting payout ({{ $paidPayslipsCount }} already paid).</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($approvedPayslips->isNotEmpty())
                        <button type="submit" class="btn btn-dark btn-sm mt-3 btn-block font-weight-bold" style="border-radius:999px;">
                            Bulk Mark Selected Payslips as Paid
                        </button>
                    @endif
                </form>
            </div>
        </div>
    </div>

    <div class="ms-card overflow-hidden">
        <form method="POST" action="{{ route('admin.reconciliation.bulk-mark-paid') }}">
            @csrf
            <input type="hidden" name="target_type" value="client_invoices">
            <div class="p-3 d-flex flex-wrap justify-content-between align-items-center" style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;gap:10px;">
                <div class="font-weight-bold"><i class="fas fa-check-square text-success mr-2"></i>3A. Fast Bulk “Mark as Paid” — Unpaid Client Invoices ({{ $sentInvoices->count() }})</div>
                <button type="submit" class="btn btn-success btn-sm px-4" style="border-radius:999px;font-weight:600;">
                    Bulk Mark Ticked Invoices as Paid
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:13px;">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" onclick="document.querySelectorAll('.ci-chk').forEach(c => c.checked = this.checked)"></th>
                            <th>Invoice #</th>
                            <th>Client / Organisation</th>
                            <th>Invoice Date</th>
                            <th>Status</th>
                            <th class="text-right">Total (£)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sentInvoices as $ci)
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="{{ $ci->id }}" class="ci-chk"></td>
                                <td class="tabular-nums font-weight-bold">{{ $ci->invoice_number }}</td>
                                <td>{{ optional(optional($ci->client)->organisation)->organisation_company ?? optional(optional($ci->client)->user)->getFullName() }}</td>
                                <td class="tabular-nums">{{ optional($ci->invoice_date)->format('d M Y') }}</td>
                                <td><span class="badge badge-warning">{{ ucfirst($ci->status) }}</span></td>
                                <td class="text-right tabular-nums font-weight-bold">£{{ number_format((float)$ci->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-4 text-muted">All client invoices are marked Paid.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>
@endsection
