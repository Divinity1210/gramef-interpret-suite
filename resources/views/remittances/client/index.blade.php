@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Remittances</h1>
        <a href="{{ route('invoices.client.index') }}" class="btn btn-outline-secondary">View All Invoices</a>
    </div>

    <div class="alert alert-info">
        <strong>Bank Details</strong><br>
        Please remit payments using the bank details below and submit a payment proof to speed up verification.
        <div class="mt-2">
            Account Name: {{ config('bank.account_name') }}<br>
            Sort Code: {{ config('bank.sort_code') }}<br>
            Account Number: {{ config('bank.account_number') }}
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Outstanding and Recent Invoices</h5>
        </div>
        <div class="card-body">
            @if(isset($invoices) && $invoices->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Invoice No.</th>
                                <th>Company</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Remaining</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoices as $invoice)
                                @php
                                    $totalPaid = $invoice->receipts->sum('total_paid');
                                    $remaining = max(0, ($invoice->total ?? 0) - $totalPaid);
                                @endphp
                                <tr>
                                    <td>{{ $invoice->id }}</td>
                                    <td>{{ $invoice->invoice_number }}</td>
                                    <td>{{ optional($invoice->company)->name ?? '—' }}</td>
                                    <td><strong>£{{ number_format($invoice->total, 2) }}</strong></td>
                                    <td class="text-success">£{{ number_format($totalPaid, 2) }}</td>
                                    <td class="{{ $remaining > 0 ? 'text-warning' : 'text-success' }}">£{{ number_format($remaining, 2) }}</td>
                                    <td><span class="badge badge-secondary">{{ ucfirst($invoice->status ?? 'draft') }}</span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a class="btn btn-outline-primary" href="{{ route('invoices.client.show', $invoice->id) }}">View</a>
                                            <a class="btn btn-outline-secondary" href="{{ route('invoices.client.pdf', $invoice->id) }}" target="_blank">Download PDF</a>
                                            @if($remaining > 0)
                                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#paymentProofModal-{{ $invoice->id }}">
                                                    Submit Payment Proof
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                <!-- Modal per invoice -->
                                <div class="modal fade" id="paymentProofModal-{{ $invoice->id }}" tabindex="-1" aria-labelledby="paymentProofModalLabel-{{ $invoice->id }}" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="paymentProofModalLabel-{{ $invoice->id }}">Submit Payment Proof for Invoice #{{ $invoice->invoice_number }}</h5>
                                                <button type="button" class=" btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                            </div>
                                            <form action="{{ route('invoices.client.payment-proofs.store', $invoice->id) }}" method="POST" enctype="multipart/form-data">
                                                @csrf
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <label class="form-label">Amount Paid (GBP)</label>
                                                            <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Paid At</label>
                                                            <input type="date" name="paid_at" class="form-control" value="{{ old('paid_at', now()->toDateString()) }}">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Method</label>
                                                            <input type="text" name="method" class="form-control" placeholder="Bank Transfer, Card, etc.">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Reference</label>
                                                            <input type="text" name="reference" class="form-control" placeholder="Transaction ID or note">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Upload Files</label>
                                                            <input type="file" name="files[]" class="form-control" multiple required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                                            <small class="text-muted">Attach your remittance slip or bank confirmation. Max size per file: 10MB.</small>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Notes</label>
                                                            <textarea name="notes" class="form-control" rows="3" placeholder="Any details to help us verify the payment"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Submit</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>
                    {{ $invoices->links() }}
                </div>
            @else
                <div class="text-center text-muted py-4">
                    <i class="fas fa-receipt fa-3x mb-3 text-muted"></i>
                    <p class="mb-0">No invoices found.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection