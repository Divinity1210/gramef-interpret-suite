@extends('layouts.app')

@section('content')
    <div class="container-fluid px-2">
        <div class="row justify-content-center">
            <div class="col-12 client-invoice-show" style="max-width: 1600px;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Invoice {{ $invoice->invoice_number }}</h1>
                    <div class="btn-group" role="group">
                        <a href="{{ route('invoices.client.index') }}" class="btn btn-outline-secondary">← Back to
                            Invoices</a>
                        <a href="{{ route('invoices.client.pdf', $invoice->id) }}" class="btn btn-outline-primary"
                            target="_blank">Download PDF</a>
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#paymentProofModal">
                            Submit Payment Proof
                        </button>
                        @if(auth()->user() && auth()->user()->hasRole('admin') && strtolower((string) ($invoice->status ?? 'draft')) === 'sent')
                            <form action="{{ route('invoices.admin.client.mark-paid', $invoice->id) }}" method="POST"
                                class="d-inline" onsubmit="return confirm('Mark this invoice as paid?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-success">Mark as Paid</button>
                            </form>
                        @endif
                    </div>
                </div>

                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        <strong>There was a problem submitting your payment proof:</strong>
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row">
                    <div class="col-md-8">
                        <!-- Invoice Details -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Invoice Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="font-weight-bold">Invoice Number</label>
                                        <p class="mb-0">{{ $invoice->invoice_number }}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="font-weight-bold">Status</label>
                                        <p class="mb-0">
                                            @php
                                                $status = $invoice->status ?? 'draft';
                                                $statusMap = [
                                                    'draft' => 'secondary',
                                                    'sent' => 'info',
                                                    'paid' => 'success',
                                                    'cancelled' => 'danger',
                                                ];
                                                $badge = $statusMap[$status] ?? 'secondary';
                                            @endphp
                                            <span
                                                class="badge badge-{{ $badge }}">{{ $status === 'sent' ? 'Received' : ucfirst($status) }}</span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="font-weight-bold">Company</label>
                                        <p class="mb-0">{{ optional($invoice->company)->name ?? '—' }}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="font-weight-bold">Invoice Period</label>
                                        <p class="mb-0">
                                            {{ optional($invoice->date_range_start)->format('Y-m-d') }} —
                                            {{ optional($invoice->date_range_end)->format('Y-m-d') }}
                                        </p>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="font-weight-bold">Subtotal</label>
                                        <p class="mb-0">£{{ number_format($invoice->subtotal, 2) }}</p>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="font-weight-bold">VAT ({{ $invoice->vat_rate }}%)</label>
                                        <p class="mb-0">£{{ number_format($invoice->vat_amount, 2) }}</p>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="font-weight-bold">Total</label>
                                        <p class="mb-0 text-primary">
                                            <strong>£{{ number_format($invoice->total, 2) }}</strong>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Invoice Items -->
                        @if($invoice->items && $invoice->items->count() > 0)
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Invoice Items</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Description</th>
                                                    <th>Job Type</th>
                                                    <th>Quantity</th>
                                                    <th>Rate</th>
                                                    <th>Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($invoice->items as $item)
                                                    <tr>
                                                        <td>{{ $item->description ?? ($item->job->title ?? '—') }}</td>
                                                        <td>
                                                            @php
                                                                $skillName = null;
                                                                if ($item->job_id) {
                                                                    // Client invoice items use belongsTo(Job::class) which targets the abstract base.
                                                                    // We need to resolve the actual job from InterpreterJob or TranslatorJob.
                                                                    $jobType = $item->job_type ?? ($item->metadata['job_type'] ?? null);
                                                                    $resolvedJob = null;
                                                                    if ($jobType && str_contains($jobType, 'TranslatorJob')) {
                                                                        $resolvedJob = \App\TranslatorJob::find($item->job_id);
                                                                    } elseif ($jobType && str_contains($jobType, 'InterpreterJob')) {
                                                                        $resolvedJob = \App\InterpreterJob::find($item->job_id);
                                                                    } else {
                                                                        // job_type is NULL — try InterpreterJob first (more common), then TranslatorJob
                                                                        $resolvedJob = \App\InterpreterJob::find($item->job_id)
                                                                            ?? \App\TranslatorJob::find($item->job_id);
                                                                    }
                                                                    $skillName = $resolvedJob ? optional($resolvedJob->skill)->skill : null;
                                                                }
                                                            @endphp
                                                            @if($skillName)
                                                                <span class="badge badge-info">{{ $skillName }}</span>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                        <td>{{ $item->quantity ?? 1 }}</td>
                                                        <td>£{{ number_format($item->rate ?? 0, 2) }}</td>
                                                        <td>£{{ number_format($item->amount ?? 0, 2) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Payment Proofs -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Payment Proofs ({{ $invoice->paymentProofs->count() }})</h5>
                            </div>
                            <div class="card-body">
                                @if($invoice->paymentProofs->count() > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Submitted At</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Files</th>
                                                    <th>Notes</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($invoice->paymentProofs as $proof)
                                                    <tr>
                                                        <td>{{ optional($proof->created_at)->format('Y-m-d H:i') }}</td>
                                                        <td><strong>£{{ number_format($proof->amount, 2) }}</strong></td>
                                                        <td>
                                                            @php
                                                                $pstatus = $proof->status ?? 'pending';
                                                                $pmap = [
                                                                    'pending' => 'warning',
                                                                    'verified' => 'success',
                                                                    'rejected' => 'danger',
                                                                ];
                                                                $pbadge = $pmap[$pstatus] ?? 'secondary';
                                                            @endphp
                                                            <span class="badge badge-{{ $pbadge }}">{{ ucfirst($pstatus) }}</span>
                                                            @if($proof->rejection_reason)
                                                                <small class="text-muted d-block">Reason:
                                                                    {{ $proof->rejection_reason }}</small>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($proof->documents && $proof->documents->count() > 0)
                                                                @foreach($proof->documents as $doc)
                                                                    <a href="{{ $doc->fullUrl }}" target="_blank" class="d-block">
                                                                        <i class="fas fa-paperclip"></i> {{ $doc->name }}
                                                                    </a>
                                                                @endforeach
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                        <td>{{ $proof->notes ? Str::limit($proof->notes, 80) : '—' }}</td>
                                                        <td>
                                                            @can('delete', $proof)
                                                                @if(($proof->status ?? 'pending') === 'pending')
                                                                    <form action="{{ route('payment-proofs.destroy', $proof->id) }}"
                                                                        method="POST"
                                                                        onsubmit="return confirm('Are you sure you want to delete this pending payment proof?');">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit"
                                                                            class="btn btn-sm btn-outline-danger">Delete</button>
                                                                    </form>
                                                                @endif
                                                            @endcan
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-paperclip fa-3x mb-3 text-muted"></i>
                                        <p class="mb-0">No payment proofs submitted yet.</p>
                                        <p class="small">Use the "Submit Payment Proof" button to upload your remittance or bank
                                            confirmation.</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Receipts Section -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Receipts ({{ $invoice->receipts->count() }})</h5>
                                @if($invoice->receipts->count() > 0)
                                    <a href="{{ route('receipts.client.index') }}" class="btn btn-sm btn-outline-primary">View
                                        All Receipts</a>
                                @endif
                            </div>
                            <div class="card-body">
                                @if($invoice->receipts->count() > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Receipt No.</th>
                                                    <th>Amount Paid</th>
                                                    <th>Issued Date</th>
                                                    <th>Payment Proofs</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($invoice->receipts as $receipt)
                                                    <tr>
                                                        <td><strong>{{ $receipt->receipt_number }}</strong></td>
                                                        <td><strong>£{{ number_format($receipt->total_paid, 2) }}</strong></td>
                                                        <td>{{ $receipt->issued_at->format('Y-m-d H:i') }}</td>
                                                        <td>
                                                            <span class="badge badge-info">{{ $receipt->paymentProofs->count() }}
                                                                proof(s)</span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <a class="btn btn-outline-primary"
                                                                    href="{{ route('receipts.client.show', $receipt) }}">View</a>
                                                                <a class="btn btn-primary"
                                                                    href="{{ route('receipts.client.download', $receipt) }}">Download</a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    @php
                                        $totalPaid = $invoice->receipts->sum('total_paid');
                                        $remainingBalance = $invoice->total - $totalPaid;
                                    @endphp

                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between">
                                                <span>Total Paid:</span>
                                                <strong class="text-success">£{{ number_format($totalPaid, 2) }}</strong>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between">
                                                <span>Remaining Balance:</span>
                                                <strong class="{{ $remainingBalance > 0 ? 'text-warning' : 'text-success' }}">
                                                    £{{ number_format($remainingBalance, 2) }}
                                                </strong>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-receipt fa-3x mb-3 text-muted"></i>
                                        <p class="mb-0">No receipts found for this invoice.</p>
                                        <p class="small">Receipts will appear here once your payment proofs are verified.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Payment Summary -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Payment Summary</h5>
                            </div>
                            <div class="card-body">
                                @php
                                    $totalPaid = $invoice->receipts->sum('total_paid');
                                    $remainingBalance = $invoice->total - $totalPaid;
                                    $paymentPercentage = $invoice->total > 0 ? ($totalPaid / $invoice->total) * 100 : 0;
                                @endphp

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Payment Progress</span>
                                        <span>{{ number_format($paymentPercentage, 1) }}%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar {{ $paymentPercentage >= 100 ? 'bg-success' : 'bg-primary' }} js-progress-bar"
                                            role="progressbar" aria-valuemin="0" aria-valuemax="100"
                                            aria-valuenow="{{ (int) min($paymentPercentage, 100) }}"
                                            data-progress="{{ (int) min($paymentPercentage, 100) }}">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <div class="d-flex justify-content-between">
                                        <span>Invoice Total:</span>
                                        <strong>£{{ number_format($invoice->total, 2) }}</strong>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between">
                                        <span>Total Paid:</span>
                                        <strong class="text-success">£{{ number_format($totalPaid, 2) }}</strong>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Remaining:</span>
                                        <strong class="{{ $remainingBalance > 0 ? 'text-warning' : 'text-success' }}">
                                            £{{ number_format($remainingBalance, 2) }}
                                        </strong>
                                    </div>
                                </div>

                                @if($remainingBalance > 0)
                                    <button type="button" class="btn btn-primary btn-block" data-toggle="modal"
                                        data-target="#paymentProofModal">
                                        Submit Payment Proof
                                    </button>
                                @else
                                    <div class="alert alert-success text-center mb-0">
                                        <i class="fas fa-check-circle"></i> Fully Paid
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <a href="{{ route('invoices.client.pdf', $invoice->id) }}"
                                    class="btn btn-outline-primary mb-2" target="_blank">
                                    <i class="fas fa-file-pdf"></i> Download Invoice PDF
                                </a>
                                @if($invoice->receipts->count() > 0)
                                    <a href="{{ route('receipts.client.index') }}" class="btn btn-outline-secondary">
                                        <i class="fas fa-receipt"></i> View All My Receipts
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Proof Modal -->
                <div class="modal fade" id="paymentProofModal" tabindex="-1" aria-labelledby="paymentProofModalLabel"
                    aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="paymentProofModalLabel">Submit Payment Proof for Invoice
                                    #{{ $invoice->invoice_number }}</h5>
                                <button type="button" class=" btn-close close" data-dismiss="modal" data-bs-dismiss="modal"
                                    aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <form action="{{ route('invoices.client.payment-proofs.store', $invoice->id) }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label>Amount Paid (GBP)</label>
                                            <input type="number" name="amount" step="0.01" min="0.01" class="form-control"
                                                required>
                                        </div>
                                        <div class="col-md-4">
                                            <label>Paid At</label>
                                            <input type="date" name="paid_at" class="form-control"
                                                value="{{ old('paid_at', now()->toDateString()) }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label>Method</label>
                                            <input type="text" name="method" class="form-control"
                                                placeholder="Bank Transfer, Card, etc.">
                                        </div>
                                        <div class="col-md-6">
                                            <label>Reference</label>
                                            <input type="text" name="reference" class="form-control"
                                                placeholder="Transaction ID or note">
                                        </div>
                                        <div class="col-md-6">
                                            <label>Upload Files</label>
                                            <input type="file" name="files[]" class="form-control" multiple required
                                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                            <small class="text-muted">You can attach multiple files. Max size per file:
                                                10MB.</small>
                                        </div>
                                        <div class="col-12">
                                            <label>Notes</label>
                                            <textarea name="notes" class="form-control" rows="3"
                                                placeholder="Any details to help us verify the payment"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Submit</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inline data for JS (avoids Blade directives inside <script>) -->
    <div id="invoice-data" data-has-errors="{{ $errors->any() ? '1' : '0' }}" data-old-invoice-id="{{ old('invoice_id') }}"
        data-this-invoice-id="{{ $invoice->id }}" style="display:none"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var bars = document.querySelectorAll('.js-progress-bar');
            for (var i = 0; i < bars.length; i++) {
                var el = bars[i];
                var val = parseFloat(el.getAttribute('data-progress')) || 0;
                if (val < 0) val = 0;
                if (val > 100) val = 100;
                el.style.width = val + '%';
            }

            // Retrieve error context from data-* attributes to keep JavaScript clean of Blade directives
            var dataEl = document.getElementById('invoice-data');
            var hasErrors = !!(dataEl && dataEl.dataset.hasErrors === '1');
            var oldInvoiceId = dataEl && dataEl.dataset.oldInvoiceId ? String(dataEl.dataset.oldInvoiceId) : '';
            var thisInvoiceId = dataEl && dataEl.dataset.thisInvoiceId ? String(dataEl.dataset.thisInvoiceId) : '';

            // If validation errors occurred for the payment proof form, re-open the modal so the user can see and correct them.
            if (hasErrors && oldInvoiceId && oldInvoiceId === thisInvoiceId) {
                if (window.$ && $('#paymentProofModal').modal) {
                    $('#paymentProofModal').modal('show');
                }
            }
        });
    </script>
@endsection

@push('styles')
    <style>
        /* Widen modal on large screens, keep responsive */
        @media (min-width: 992px) {
            #paymentProofModal .modal-dialog {
                max-width: min(1200px, 96vw);
            }
        }

        /* Prevent header button overflow */
        .client-invoice-show .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        /* Width is controlled by container-fluid wrapper with max-width on column */
    </style>
@endpush