@extends('layouts.app')

@section('content')
<div class="container-fluid px-2">
    <div class="row justify-content-center">
        <div class="col-12" style="max-width: 1600px;">
            <div class="card">
                <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                    <h4 class="mb-2 mb-md-0">Client Invoice Preview #{{ $invoice->invoice_number }}</h4>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <!-- Template toggle: Auto (default), Single (v2), Multiple (v3) -->
                        <form method="POST" action="{{ route('invoices.admin.client.update', $invoice->id) }}" class="d-inline-flex align-items-center gap-2 align-items-center">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="redirect" value="preview">
                            <label for="template-select" class="mb-0 small text-muted">Template</label>
                            <select id="template-select" name="template" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:160px;">
                                @php $templateMode = session('invoice_template_mode_' . $invoice->id) ?? 'auto'; @endphp
                                <option value="auto" @if($templateMode==='auto') selected @endif>Auto</option>
                                <option value="v2" @if($templateMode==='v2') selected @endif>Single (v2)</option>
                                <option value="v3" @if($templateMode==='v3') selected @endif>Multiple (v3)</option>
                            </select>
                            @isset($distinctJobs)
                                @php 
                                    // Multi is now based solely on multiple DISTINCT jobs
                                    $multi = ($distinctJobs ?? 0) > 1; 
                                    $templateMode = session('invoice_template_mode_' . $invoice->id) ?? 'auto';
                                @endphp
                                @if($multi)
                                    <span class="badge bg-primary ms-2" title="This invoice contains multiple jobs ({{ $distinctJobs }}). The Bulk editor is enabled for a faster workflow.">Multi‑job</span>
                                    @if(($effectiveTemplate ?? 'v2') === 'v3' && $templateMode === 'v2')
                                        <small class="text-muted ms-2" title="For clarity and speed, the preview and PDF use the Multiple (v3) layout when more than one job exists.">(Forced to Multiple for preview/PDF)</small>
                                    @endif
                                @endif
                            @endisset
                        </form>
                        <a href="{{ route('invoices.admin.index', ['type' => 'client']) }}" class="btn btn-secondary az-btn az-btn-secondary" title="Back to Invoices">Back to Invoices</a>
                        <a href="{{ route('invoices.admin.client.pdf', $invoice->id) }}" class="btn btn-outline-primary az-btn az-btn-outline" target="_blank" title="Print PDF">Print PDF</a>
                        @php $disableSend = false; @endphp
                        @php $effectiveTemplate = isset($effectiveTemplate) ? $effectiveTemplate : (($invoice->template ?? 'v2')); @endphp
                        @if($effectiveTemplate === 'v3')
                            <button type="button" class="btn btn-info az-btn az-btn-secondary" id="btn-bulk-detail" title="Bulk invoice job details">Bulk Detail</button>
                        @else
                            <button type="button" class="btn btn-info az-btn az-btn-secondary" id="btn-invoice-detail" title="Add invoice details">Invoice Detail</button>
                        @endif
                        @php $defaultSubject = 'Invoice '.($invoice->invoice_number ?? '').' from '.(config('bank.account_name') ?? config('app.name')); @endphp
                        <button type="button" class="btn btn-success az-btn az-btn-primary" id="btn-send-email"
                            data-invoice-id="{{ $invoice->id }}"
                            data-status="{{ $invoice->status }}"
                            data-sent-at="{{ optional($invoice->sent_at)->format('Y-m-d H:i') }}"
                            data-default-email="{{ $defaultInvoiceEmail }}"
                            data-default-subject="{{ $defaultSubject }}"
                        >Send Email</button>
                    </div>
                </div>
                <div class="card-body p-2 p-md-3">
                    @if(session('success'))
                        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
                    @endif
                    @php $verifiedTotal = optional($invoice->paymentProofs)->where('status','verified')->sum('amount'); @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Payment Proofs</h5>
                            <div class="small text-muted">
                                Verified total: £{{ number_format((float)$verifiedTotal, 2) }} / Invoice total £{{ number_format((float)$invoice->total, 2) }}
                            </div>
                        </div>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Amount</th>
                                        <th>Paid At</th>
                                        <th>Status</th>
                                        <th>Submitted By</th>
                                        <th>Files</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse(($invoice->paymentProofs ?? []) as $proof)
                                    <tr>
                                        <td>#{{ $proof->id }}</td>
                                        <td>£{{ number_format((float)$proof->amount, 2) }} {{ $proof->currency }}</td>
                                        <td>{{ optional($proof->paid_at)->format('Y-m-d') ?: '—' }}</td>
                                        <td>
                                            @if($proof->status === 'verified')
                                                <span class="badge bg-success">Verified</span>
                                            @elseif($proof->status === 'rejected')
                                                <span class="badge bg-danger" title="{{ $proof->rejection_reason }}">Rejected</span>
                                            @else
                                                <span class="badge bg-secondary">Pending</span>
                                            @endif
                                        </td>
                                        <td>{{ data_get($proof, 'submitter.full_name', '—') }}</td>
                                        <td>
                                            @foreach(($proof->documents ?? []) as $doc)
                                                <a href="{{ $doc->fullUrl }}" target="_blank" class="d-inline-block me-2">{{ $doc->name }}</a>
                                            @endforeach
                                        </td>
                                        <td>
                                            @if($proof->status === 'pending')
                                                <form action="{{ route('payment-proofs.verify', $proof->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-success">Verify</button>
                                                </form>
                                                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-toggle="collapse" data-bs-target="#rejectForm-{{ $proof->id }}" data-target="#rejectForm-{{ $proof->id }}">Reject</button>
                                                <div class="collapse mt-2" id="rejectForm-{{ $proof->id }}">
                                                    <form action="{{ route('payment-proofs.reject', $proof->id) }}" method="POST" class="d-flex gap-2">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason for rejection" required>
                                                        <button type="submit" class="btn btn-sm btn-danger">Confirm</button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-muted text-center">No payment proofs submitted yet.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Receipts Panel -->
                    @php 
                        $receipts = $invoice->receipts ?? collect();
                        $receiptsTotal = $receipts->sum('total_paid');
                    @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Receipts</h5>
                            <div class="small text-muted">
                                {{ $receipts->count() }} receipt{{ $receipts->count() === 1 ? '' : 's' }} · Total paid: £{{ number_format((float)$receiptsTotal, 2) }}
                            </div>
                        </div>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Issued At</th>
                                        <th>Total Paid</th>
                                        <th>Generated By</th>
                                        <th>Proof IDs</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($receipts as $receipt)
                                    <tr>
                                        <td>{{ $receipt->receipt_number ?? ('#'.$receipt->id) }}</td>
                                        <td>{{ optional($receipt->issued_at)->format('Y-m-d H:i') ?: '—' }}</td>
                                        <td>£{{ number_format((float)$receipt->total_paid, 2) }}</td>
                                        <td>{{ optional($receipt->generator)->full_name ?? optional($receipt->generator)->email ?? '—' }}</td>
                                        <td>
                                            @php $ids = (array) ($receipt->payment_proof_ids ?? []); @endphp
                                            @if(count($ids))
                                                @foreach($ids as $rid)
                                                    <span class="badge bg-light text-dark border me-1">#{{ $rid }}</span>
                                                @endforeach
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($receipt->pdf_path)
                                                <a href="{{ route('receipts.view', $receipt->id) }}" target="_blank" class="btn btn-sm btn-outline-primary me-1">View/Print</a>
                                                <a href="{{ route('receipts.download', $receipt->id) }}" class="btn btn-sm btn-outline-secondary">Download</a>
                                            @else
                                                <span class="text-muted small">PDF missing</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-muted text-center">No receipts generated yet.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <iframe id="invoice-preview-frame" src="{{ route('invoices.admin.client.pdf', $invoice->id) }}" title="Invoice PDF Preview" style="width:100%; height:80vh; border:1px solid #ddd; border-radius: 6px;" allow="fullscreen"></iframe>
                    </div>

                    <!-- Footer action buttons similar to the provided design -->
                    <div class="invoice-preview-actions mt-2" style="position:sticky; bottom:0; z-index:10; display:flex; justify-content:center; gap:10px; padding:10px 12px; background:#f8f9fa; border-top:1px solid #e9ecef; border-radius:0 0 6px 6px;">
                        <a href="{{ route('invoices.admin.index', ['type' => 'client']) }}" class="btn btn-outline-secondary az-btn az-btn-secondary">Back</a>
                        <button type="button" class="btn btn-dark az-btn az-btn-primary" id="btn-send-mail-footer"
                            data-invoice-id="{{ $invoice->id }}"
                            data-status="{{ $invoice->status }}"
                            data-sent-at="{{ optional($invoice->sent_at)->format('Y-m-d H:i') }}"
                            data-default-email="{{ $defaultInvoiceEmail }}"
                            data-default-subject="{{ $defaultSubject }}"
                        >Send Mail</button>
                        <a href="{{ route('invoices.admin.client.pdf', $invoice->id) }}" class="btn btn-outline-dark az-btn az-btn-outline" target="_blank">Print Invoice</a>
                    </div>
                </div>

                <!-- Single Invoice Detail Modal (original) -->
                @if($effectiveTemplate !== 'v3')
                <div id="invoice-detail-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1050;">
                    <div style="background:#fff; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.2); width:95%; max-width:820px; margin:5% auto; padding:20px; max-height:85vh; overflow-y:auto;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Invoice Detail</h5>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-modal-close">Close</button>
                        </div>
                        <form id="invoice-detail-form" method="POST" action="{{ route('invoices.admin.client.update', $invoice->id) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="redirect" value="preview">
                            @php
                                $md0 = (isset($invoice->items) && $invoice->items->first() && is_array($invoice->items->first()->metadata ?? null))
                                    ? $invoice->items->first()->metadata
                                    : [];
                                $billMd = isset($md0['bill_to_override']) && is_array($md0['bill_to_override']) ? $md0['bill_to_override'] : [];
                            @endphp
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label mb-2">Bill To Override</label>
                                    <div class="row g-2">
                                        <div class="col-12"><input type="text" name="bill_to_name" class="form-control" value="{{ $billMd['name'] ?? optional(optional($invoice->client)->user)->getFullName() }}" placeholder="Name"></div>
                                        <div class="col-12"><input type="email" name="bill_to_email" class="form-control" value="{{ $billMd['email'] ?? optional(optional($invoice->client)->user)->email }}" placeholder="Email"></div>
                                        <div class="col-12"><input type="text" name="bill_to_phone" class="form-control" value="{{ $billMd['phone'] ?? optional($invoice->client)->contact_number }}" placeholder="Phone"></div>
                                        <div class="col-12"><input type="text" name="bill_to_address_line_1" class="form-control" value="{{ $billMd['address_line_1'] ?? optional($invoice->client)->client_address_line_1 }}" placeholder="Address line 1"></div>
                                        <div class="col-12"><input type="text" name="bill_to_address_line_2" class="form-control" value="{{ $billMd['address_line_2'] ?? optional($invoice->client)->client_address_line_2 }}" placeholder="Address line 2"></div>
                                        <div class="col-6"><input type="text" name="bill_to_county" class="form-control" value="{{ $billMd['county'] ?? optional($invoice->client)->client_county }}" placeholder="County"></div>
                                        <div class="col-6"><input type="text" name="bill_to_postcode" class="form-control" value="{{ $billMd['postcode'] ?? optional($invoice->client)->client_postcode }}" placeholder="Postcode"></div>
                                    </div>
                                    <div class="form-text">Fill only when billing details differ from the account.</div>
                                </div>
                            </div>
                            <div class="row g-3 mt-2">
                                <div class="col-md-8">
                                    <label class="form-label">Invoice Number</label>
                                    @php
                                        // Default to existing invoice number; if single job, suggest Job ID
                                        $baseInv = $invoice->invoice_number;
                                        $firstItem = optional($invoice->items)->first();
                                        $suggestJobId = $firstItem && $firstItem->job_id ? (string)$firstItem->job_id : null;
                                    @endphp
                                    <div class="d-flex" style="gap:8px; align-items:center;">
                                        <input type="text" name="invoice_number" class="form-control" value="{{ $baseInv }}" placeholder="You can use the Job ID, and add an optional prefix/suffix">
                                        @if($suggestJobId)
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-use-job-id" title="Use Job ID" data-suggest-job-id="{{ $suggestJobId }}">Use Job ID</button>
                                            <input type="hidden" id="suggest_job_id" value="{{ $suggestJobId }}">
                                        @endif
                                    </div>
                                    <div class="form-text">Admins can edit this to set a Job ID-based invoice number and optionally add a prefix/suffix.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Invoice Date</label>
                                    @php
                                        $invoiceDateValue = ($invoice->invoice_date instanceof \Illuminate\Support\Carbon)
                                            ? $invoice->invoice_date->format('Y-m-d')
                                            : ($invoice->invoice_date ?: now()->toDateString());
                                    @endphp
                                    <input type="date" name="invoice_date" class="form-control" value="{{ $invoiceDateValue }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Payment Terms</label>
                                    <input type="text" name="payment_terms_text" class="form-control" value="{{ $invoice->payment_terms_text }}" placeholder="e.g. 28 days from invoice date">
                                </div>
                                @php
                                    $firstItem = optional($invoice->items)->first();
                                    $jobForInvoice = $firstItem ? $firstItem->job : null;
                                @endphp
                                <div class="col-md-6">
                                    <label class="form-label">File Reference</label>
                                    <input type="text" name="file_reference" class="form-control" value="{{ $invoice->file_reference }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Client Reference / PO / Cost Code</label>
                                    <input type="text" name="client_reference" class="form-control" value="{{ $invoice->client_reference }}" placeholder="Client Reference / PO / Cost Code">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Patient Number</label>
                                    <input type="text" name="patient_number" class="form-control" value="{{ $invoice->patient_number }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">PID</label>
                                    <input type="text" name="pid" class="form-control" value="{{ $invoice->pid }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Interpreting Time (minutes)</label>
                                    <input type="number" min="0" step="1" name="interpreting_time_minutes" class="form-control" value="{{ $invoice->interpreting_time_minutes }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Travel Time (minutes)</label>
                                    <input type="number" min="0" step="1" name="travel_time_minutes" class="form-control" value="{{ $invoice->travel_time_minutes }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Parking (£)</label>
                                    <input type="number" min="0" step="0.01" name="parking_amount" class="form-control" value="{{ number_format((float)$invoice->parking_amount, 2, '.', '') }}">
                                </div>
                                <div class="col-md-6">
                                    @php
                                        $firstMd = (isset($firstItem) && $firstItem && is_array($firstItem->metadata ?? null)) ? $firstItem->metadata : [];
                                        $tlbls = isset($firstMd['charge_labels']) && is_array($firstMd['charge_labels']) ? $firstMd['charge_labels'] : [];
                                        $tvals = isset($firstMd['charge_values']) && is_array($firstMd['charge_values']) ? $firstMd['charge_values'] : [];
                                        $translationPrefill = null;
                                        foreach ($tlbls as $i => $lbl) {
                                            $norm = strtolower(trim((string)$lbl));
                                            if ($norm === 'translation') { $translationPrefill = isset($tvals[$i]) ? (float)$tvals[$i] : null; break; }
                                        }
                                    @endphp
                                    <label class="form-label">Translation Cost (£)</label>
                                    <input type="number" min="0" step="0.01" name="translation_cost" class="form-control" value="{{ $translationPrefill !== null ? number_format((float)$translationPrefill, 2, '.', '') : '' }}">
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="include_vat" id="includeVatCheck" value="1" @if($invoice->include_vat) checked @endif>
                                        <label class="form-check-label" for="includeVatCheck">Include VAT</label>
                                    </div>
                                    <div class="mt-2">
                                        <label class="form-label">VAT Rate (%)</label>
                                        <input type="number" min="0" max="50" step="0.1" name="vat_rate" class="form-control" value="{{ $invoice->vat_rate ?? 20 }}">
                                    </div>
                                </div>

                                <!-- Additional Items -->
                                <div class="col-12">
                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label mb-0">Add Items</label>
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-item">Add Item</button>
                                    </div>
                                    <div id="additional-items">
                                        <!-- item rows will be appended here -->
                                    </div>
                                    <div class="form-text">Each item will appear as a separate line on the PDF and be included in totals. Leave blank rows to ignore.</div>
                                </div>

                                <!-- Header (Top-right) Summary Items -->
                                <div class="col-12">
                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label mb-0">Top-right Summary Items</label>
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-header-item">Add Summary Item</button>
                                    </div>
                                    @php
                                        $existingHeaderItems = [];
                                        $md0 = (isset($invoice->items) && $invoice->items->first() && is_array($invoice->items->first()->metadata ?? null))
                                            ? $invoice->items->first()->metadata
                                            : [];
                                        if(isset($md0['header_items']) && is_array($md0['header_items'])){
                                            $existingHeaderItems = $md0['header_items'];
                                        }
                                    @endphp
                                    <div id="header-items-container">
                                        @foreach($existingHeaderItems as $hi)
                                            @php $lbl = (string)($hi['label'] ?? ''); $val = (string)($hi['value'] ?? ''); @endphp
                                            <div class="row g-2 align-items-center header-item-row mb-2">
                                                <div class="col-md-6"><input type="text" name="header_item_labels[]" class="form-control" value="{{ $lbl }}" placeholder="Label e.g. Cost Centre"></div>
                                                <div class="col-md-5"><input type="text" name="header_item_values[]" class="form-control" value="{{ $val }}" placeholder="Value e.g. CC-123"></div>
                                                <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-header-item">&times;</button></div>
                                            </div>
                                            @endforeach
                                        </div>
                                        <div class="form-text">These label/value pairs appear in the top-right of the PDF (e.g., Parking, Travel Time notes, custom references).</div>
                                    </div>

                            </div>
                            <div class="mt-3 d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-outline-secondary" id="btn-modal-cancel">Cancel</button>
                                <button type="submit" class="btn btn-primary">Submit Details</button>
                            </div>
                        </form>
                    </div>
                </div>
                @endif

                <!-- Bulk Invoice Detail Modal -->
                @if($effectiveTemplate === 'v3')
                <div id="bulk-invoice-detail-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1050;">
                    <div style="background:#fff; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.2); width:95%; max-width:1100px; margin:3% auto; padding:0; max-height:90vh; overflow-y:auto;">
                        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                            <h5 class="mb-0">Bulk Invoice Details</h5>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-bulk-modal-close">Close</button>
                        </div>
                        <form id="bulk-invoice-detail-form" method="POST" action="{{ route('invoices.admin.client.update', $invoice->id) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="redirect" value="preview">
                            <div class="row g-0">
                                <!-- Left: Job list -->
                                <div class="col-md-4 border-end" style="max-height:70vh; overflow-y:auto;">
                                    <div class="p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="fw-bold">Jobs in this invoice</div>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-prev-job">Prev</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-next-job">Next</button>
                                            </div>
                                        </div>
                                        <div id="bulk-job-list" class="list-group"></div>
                                    </div>
                                </div>
                                <!-- Right: General + Job-specific form -->
                                <div class="col-md-8">
                                    <div class="p-3">
                                        <div class="mb-3">
                                            <div class="fw-bold mb-2">General Invoice Information</div>
                                                <div class="row g-2">
                                                <div class="col-md-6">
                                                    <label class="form-label">Invoice Number</label>
                                                    <input type="text" name="invoice_number" class="form-control" value="{{ $invoice->invoice_number }}" placeholder="You can use one of the Job IDs, and add a prefix/suffix">
                                                </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Invoice Date</label>
                                                        @php
                                                            $invoiceDateValue = ($invoice->invoice_date instanceof \Illuminate\Support\Carbon)
                                                                ? $invoice->invoice_date->format('Y-m-d')
                                                                : ($invoice->invoice_date ?: now()->toDateString());
                                                    @endphp
                                                    <input type="date" name="invoice_date" class="form-control" value="{{ $invoiceDateValue }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Payment Terms</label>
                                                    <input type="text" name="payment_terms_text" class="form-control" value="{{ $invoice->payment_terms_text }}" placeholder="e.g. 28 days from invoice date">
                                                </div>
                                                <!-- Removed general Parking for v3; parking is per-job only -->
                                                <div class="col-md-6">
                                                    <div class="form-check mt-4">
                                                        <input class="form-check-input" type="checkbox" name="include_vat" id="bulkIncludeVatCheck" value="1" @if($invoice->include_vat) checked @endif>
                                                        <label class="form-check-label" for="bulkIncludeVatCheck">Include VAT</label>
                                                    </div>
                                                    <div class="mt-2">
                                                        <label class="form-label">VAT Rate (%)</label>
                                                        <input type="number" min="0" max="50" step="0.1" name="vat_rate" class="form-control" value="{{ $invoice->vat_rate ?? 20 }}">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <hr>

                                        <!-- Add Items (row-level) -->
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="form-label mb-0">Add Items</label>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-bulk-add-item">Add Item</button>
                                            </div>
                                            <div id="bulk-additional-items"></div>
                                            <div class="form-text">Each item becomes a separate job row. Select the job and fill its details below after adding.</div>
                                        </div>

                                        <!-- Header (Top-right) Summary Items for v3 as well -->
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <label class="form-label mb-0">Top-right Summary Items</label>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-bulk-add-header-item">Add Summary Item</button>
                                                </div>
                                            @php
                                                // In bulk v3 mode, the single-invoice block above (which defines
                                                // $existingHeaderItems) is not rendered. Compute header items here.
                                                $bulkHeaderItems = [];
                                                $md0 = (isset($invoice->items) && $invoice->items->first() && is_array($invoice->items->first()->metadata ?? null))
                                                    ? $invoice->items->first()->metadata
                                                    : [];
                                                if(isset($md0['header_items']) && is_array($md0['header_items'])){
                                                    $bulkHeaderItems = $md0['header_items'];
                                                }
                                            @endphp
                                            <div id="bulk-header-items-container">
                                                @foreach($bulkHeaderItems as $hi)
                                                    @php $lbl = (string)($hi['label'] ?? ''); $val = (string)($hi['value'] ?? ''); @endphp
                                                    <div class="row g-2 align-items-center header-item-row mb-2">
                                                        <div class="col-md-6"><input type="text" name="header_item_labels[]" class="form-control" value="{{ $lbl }}" placeholder="Label e.g. Cost Centre"></div>
                                                        <div class="col-md-5"><input type="text" name="header_item_values[]" class="form-control" value="{{ $val }}" placeholder="Value e.g. CC-123"></div>
                                                        <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-header-item">&times;</button></div>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="form-text">Shown in the PDF header across all jobs in this invoice.</div>
                                        </div>

                                        <hr>

                                        <div class="mb-2 d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="fw-bold">Editing Job</div>
                                                <div id="current-job-label" class="text-muted small">Select a job from the left list</div>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-primary" id="job-index-indicator">0 / 0</span>
                                            </div>
                                        </div>

                                        <div class="row g-3" id="bulk-job-fields">
                                            <div class="col-md-6">
                                                <label class="form-label">Client Reference / PO / Cost Code</label>
                                                <input type="text" class="form-control" id="field-client-reference" placeholder="Client Reference / PO / Cost Code">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">File Reference</label>
                                                <input type="text" class="form-control" id="field-file-reference" placeholder="File reference">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Patient Number</label>
                                                <input type="text" class="form-control" id="field-patient-number" placeholder="Patient number">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">PID</label>
                                                <input type="text" class="form-control" id="field-pid" placeholder="PID">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Interpreting Time (minutes)</label>
                                                <input type="number" min="0" step="1" class="form-control" id="field-interpreting-minutes" placeholder="0">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Travel Time (minutes)</label>
                                                <input type="number" min="0" step="1" class="form-control" id="field-travel-minutes" placeholder="0">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Parking (£)</label>
                                                <input type="number" min="0" step="0.01" class="form-control" id="field-parking-amount" placeholder="0.00">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Translation Cost (£)</label>
                                                <input type="number" min="0" step="0.01" class="form-control" id="field-translation-cost" placeholder="0.00">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Service Date</label>
                                                <input type="date" class="form-control" id="field-date">
                                            </div>
                                        </div>

                                        <div class="mt-3 d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-outline-secondary" id="btn-bulk-modal-cancel">Cancel</button>
                                            <button type="submit" class="btn btn-primary" id="btn-bulk-submit">Submit Details</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/invoice-admin.js') }}"></script>
@php
    $existingStandaloneItems = ($invoice->items ?? collect())->filter(function($i){
        $md = is_array($i->metadata ?? null) ? $i->metadata : (is_string($i->metadata ?? null) ? (json_decode($i->metadata, true) ?: []) : []);
        return isset($md['source']) && $md['source'] === 'admin_invoice_detail_modal';
    })->map(function($i){
        $md = is_array($i->metadata ?? null) ? $i->metadata : (is_string($i->metadata ?? null) ? (json_decode($i->metadata, true) ?: []) : []);
        return [
            'id' => $i->id,
            'description' => (string) ($i->description ?? ''),
            'date' => (string) ($md['date'] ?? ''),
            'amount' => (float) ($i->amount ?? 0),
        ];
    })->values();
@endphp
<script type="application/json" id="existing-additional-items-json">{!! json_encode($existingStandaloneItems) !!}</script>
<!-- Embedded data for JS without Blade directives inside JS -->
<script type="application/json" id="invoice-config-json">{!! json_encode(['template' => ($effectiveTemplate ?? ($invoice->template ?? 'v2'))]) !!}</script>
<!-- Removed job-options/existing-job-ids payloads as job selection inside modals is no longer needed -->
@if(($effectiveTemplate ?? ($invoice->template ?? 'v2')) === 'v3')
<script type="application/json" id="job-items-json">{!! json_encode($invoice->items->map(function($i){
    $job = $i->job; 
    $label = '#'.$i->job_id;
    $date = null;
    $language = null;
    $meta = (array) $i->metadata;

    if($job){
        if(method_exists($job, 'getAttribute') && $job instanceof \App\InterpreterJob){
            $date = $job->appointment_date ? \Carbon\Carbon::parse($job->appointment_date)->format('Y-m-d') : null;
            $language = optional($job->toLanguage)->name;
            $label = '#'.$job->id.' • '.($date ?? '—').' • '.($language ?? '—');

            // Prefill from interpreter job and timesheet if not already set
            if(!array_key_exists('client_reference', $meta) || $meta['client_reference'] === null || $meta['client_reference'] === ''){
                $meta['client_reference'] = $job->client_reference ?? null;
            }
            if(!array_key_exists('file_reference', $meta) || $meta['file_reference'] === null || $meta['file_reference'] === ''){
                $meta['file_reference'] = $job->file_reference ?? null;
            }
            if(!array_key_exists('pid', $meta) || $meta['pid'] === null || $meta['pid'] === ''){
                $meta['pid'] = $job->personal_identity_number ?? null;
            }
            if(!array_key_exists('patient_number', $meta) || $meta['patient_number'] === null || $meta['patient_number'] === ''){
                $meta['patient_number'] = $job->patient_number ?? null;
            }

            // Timesheet-based interpreting minutes
            $ts = method_exists($job, 'timesheet') ? $job->timesheet : null;
            if((!array_key_exists('interpreting_time_minutes', $meta) || $meta['interpreting_time_minutes'] === null || $meta['interpreting_time_minutes'] === '')){
                $mins = null;
                if($ts){
                    $clientMins = (int)($ts->client_duration_hours ?? 0) * 60 + (int)($ts->client_duration_minutes ?? 0);
                    $agentMins = (int)($ts->agent_duration_hours ?? 0) * 60 + (int)($ts->agent_duration_minutes ?? 0);
                    $mins = $clientMins > 0 ? $clientMins : ($agentMins > 0 ? $agentMins : null);
                }
                // Fallback to job duration if timesheet missing
                if($mins === null){
                    if(!empty($job->duration_minutes)){
                        $mins = (int) $job->duration_minutes;
                    } elseif(!empty($job->duration_hours)) {
                        $mins = (int) $job->duration_hours * 60;
                    }
                }
                if($mins !== null){ $meta['interpreting_time_minutes'] = $mins; }
            }

            // Travel minutes from TravelExpences (agent-entered travel times)
            if((!array_key_exists('travel_time_minutes', $meta) || $meta['travel_time_minutes'] === null || $meta['travel_time_minutes'] === '')){
                try {
                    $travel = \App\TravelExpences::where('job_id', $job->id)->first();
                    if($travel){
                        $start = $travel->travel_start_time ?? null;
                        $end = $travel->travel_end_time ?? null;
                        $mins = null;
                        if($start && $end){
                            // Parse as H:i:s then fallback to H:i
                            $sObj = null; $eObj = null;
                            try {
                                $sObj = \Carbon\Carbon::createFromFormat('H:i:s', $start);
                                $eObj = \Carbon\Carbon::createFromFormat('H:i:s', $end);
                            } catch(\Exception $e) {
                                try {
                                    $sObj = \Carbon\Carbon::createFromFormat('H:i', $start);
                                    $eObj = \Carbon\Carbon::createFromFormat('H:i', $end);
                                } catch(\Exception $e2) {
                                    $sObj = null; $eObj = null;
                                }
                            }
                            if($sObj && $eObj){
                                // Calculate minutes allowing midnight crossover
                                $sMin = $sObj->hour * 60 + $sObj->minute;
                                $eMin = $eObj->hour * 60 + $eObj->minute;
                                $mins = $eMin >= $sMin ? ($eMin - $sMin) : ($eMin + 1440 - $sMin);
                            }
                        }
                        if($mins !== null && $mins >= 0){
                            $meta['travel_time_minutes'] = $mins;
                        }
                        // Fallback: if service date not set above, use travel_date
                        if((!array_key_exists('date', $meta) || empty($meta['date']) || $meta['date'] === '') && !empty($travel->travel_date)){
                            $meta['date'] = \Carbon\Carbon::parse($travel->travel_date)->format('Y-m-d');
                        }
                    }
                } catch(\Exception $e){ /* ignore travel prefill errors */ }
            }

            // Parking amount from existing metadata or charge labels (admin entered during timesheet invoice creation)
            if(!array_key_exists('parking_amount', $meta) || $meta['parking_amount'] === null || $meta['parking_amount'] === ''){
                $parkingVal = null;
                $labels = isset($meta['charge_labels']) && is_array($meta['charge_labels']) ? $meta['charge_labels'] : [];
                $values = isset($meta['charge_values']) && is_array($meta['charge_values']) ? $meta['charge_values'] : [];
                foreach($labels as $idx => $lbl){
                    $norm = strtolower(trim((string)$lbl));
                    if($norm === 'parking'){
                        $v = isset($values[$idx]) ? (float)$values[$idx] : null;
                        if($v !== null && $v >= 0){ $parkingVal = $v; }
                        break;
                    }
                }
                if($parkingVal !== null){ $meta['parking_amount'] = $parkingVal; }
            }
        } else {
            // Translator or other job type
            $date = $job->target_date ? \Carbon\Carbon::parse($job->target_date)->format('Y-m-d') : null;
            $language = trim((optional($job->fromLanguage)->name).' → '.(optional($job->toLanguage)->name), ' → ');
            $label = '#'.$job->id.' • '.($date ?? '—').' • '.($language ?? '—');
        }
    }

    // Default the service date when not provided in metadata
    if(!array_key_exists('date', $meta) || !$meta['date'] || $meta['date'] === ''){
        $meta['date'] = $date;
    }

    return [
        'item_id' => $i->id,
        'job_id' => $i->job_id,
        'label' => $label,
        'amount' => (float)$i->amount,
        'meta' => $meta,
    ];
})->filter(function($x){ return $x['job_id'] !== null; })->values()) !!}</script>
@endif
<script>
    (function(){
        // Config
        const configEl = document.getElementById('invoice-config-json');
        const CONFIG = configEl ? JSON.parse(configEl.textContent || '{}') : {};
        const IS_V3 = CONFIG.template === 'v3';

        // Job selection inside add-item rows has been removed; jobs are managed at the top of the modal.

        // Single modal wiring (Template v2)
        if(!IS_V3){
            const btnOpen = document.getElementById('btn-invoice-detail');
            const modal = document.getElementById('invoice-detail-modal');
            const closeBtn = document.getElementById('btn-modal-close');
            const cancelBtn = document.getElementById('btn-modal-cancel');
            const addItemBtn = document.getElementById('btn-add-item');
            const itemsContainer = document.getElementById('additional-items');
            const invForm = document.getElementById('invoice-detail-form');
            const invNumInput = invForm ? invForm.querySelector('input[name="invoice_number"]') : null;
            const useJobIdBtn = document.getElementById('btn-use-job-id');
            const headerAddBtn = document.getElementById('btn-add-header-item');
            const headerContainer = document.getElementById('header-items-container');

            function openModal(){ if(modal) modal.style.display = 'block'; }
            function closeModal(){ if(modal) modal.style.display = 'none'; }
            if(btnOpen){ btnOpen.addEventListener('click', openModal); }
            if(closeBtn){ closeBtn.addEventListener('click', closeModal); }
            if(cancelBtn){ cancelBtn.addEventListener('click', closeModal); }

            function createItemRow(initial) {
                const row = document.createElement('div');
                row.className = 'row g-2 align-items-end mb-2';
                row.innerHTML = `
                    <div class="col-md-6">
                        <label class="form-label">Item Description</label>
                        <input type="text" name="new_item_descriptions[]" class="form-control" placeholder="e.g. Additional interpreting">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="new_item_dates[]" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Amount (£)</label>
                        <input type="number" name="new_item_amounts[]" min="0" step="0.01" class="form-control" placeholder="0.00">
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-outline-danger" title="Remove" aria-label="Remove">&times;</button>
                    </div>
                `;
                const removeBtn = row.querySelector('button');
                removeBtn.addEventListener('click', () => row.remove());
                if(initial && typeof initial === 'object'){
                    row.querySelector('input[name="new_item_descriptions[]"]').value = initial.description || '';
                    row.querySelector('input[name="new_item_dates[]"]').value = initial.date || '';
                    row.querySelector('input[name="new_item_amounts[]"]').value = (initial.amount !== undefined && initial.amount !== null) ? String(initial.amount) : '';
                    // Add hidden inputs to update existing item instead of creating new
                    const hid = document.createElement('input');
                    hid.type = 'hidden';
                    hid.name = `existing_item_ids[]`;
                    hid.value = String(initial.id);
                    row.appendChild(hid);
                    // Mirror fields with existing_ names for server-side update
                    row.querySelector('input[name="new_item_descriptions[]"]').setAttribute('name', `existing_item_descriptions[${initial.id}]`);
                    row.querySelector('input[name="new_item_dates[]"]').setAttribute('name', `existing_item_dates[${initial.id}]`);
                    row.querySelector('input[name="new_item_amounts[]"]').setAttribute('name', `existing_item_amounts[${initial.id}]`);
                    // Remove button should mark for deletion instead of removing silently
                    removeBtn.addEventListener('click', () => {
                        const r = document.createElement('input');
                        r.type = 'hidden';
                        r.name = `existing_item_remove[${initial.id}]`;
                        r.value = '1';
                        invForm.appendChild(r);
                        row.remove();
                    }, { once: true });
                }
                return row;
            }

            function addItemRow(){
                if(!itemsContainer) return;
                itemsContainer.appendChild(createItemRow());
            }

            if(addItemBtn){
                addItemBtn.addEventListener('click', addItemRow);
                // Pre-populate existing standalone items for editing/removal
                try {
                    const exEl = document.getElementById('existing-additional-items-json');
                    const EXISTING = exEl ? JSON.parse(exEl.textContent || '[]') : [];
                    EXISTING.forEach((it) => itemsContainer.appendChild(createItemRow(it)));
                } catch(e) {}
                // Add one empty row for convenience
                addItemRow();
            }

            // Use Job ID to populate invoice number quickly
            if(useJobIdBtn && invNumInput){
                const suggested = useJobIdBtn.getAttribute('data-suggest-job-id');
                useJobIdBtn.addEventListener('click', function(){
                    if(suggested){
                        invNumInput.value = suggested;
                        try { invNumInput.focus(); invNumInput.setSelectionRange(invNumInput.value.length, invNumInput.value.length); } catch(e) {}
                    }
                });
            }

            // Header summary items (top-right of PDF)
            function createHeaderItemRow(label = '', value = ''){
                const row = document.createElement('div');
                row.className = 'row g-2 align-items-center header-item-row mb-2';
                row.innerHTML = `
                    <div class="col-md-6"><input type="text" name="header_item_labels[]" class="form-control" value="${label}" placeholder="Label e.g. Cost Centre"></div>
                    <div class="col-md-5"><input type="text" name="header_item_values[]" class="form-control" value="${value}" placeholder="Value e.g. CC-123"></div>
                    <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-header-item" title="Remove">&times;</button></div>
                `;
                const removeBtn = row.querySelector('.btn-remove-header-item');
                removeBtn && removeBtn.addEventListener('click', () => row.remove());
                return row;
            }
            if(headerContainer){
                // Delegate removal for pre-rendered rows (safety)
                headerContainer.addEventListener('click', function(e){
                    const btn = e.target.closest('.btn-remove-header-item');
                    if(btn){ const row = btn.closest('.header-item-row'); row && row.remove(); }
                });
            }
            if(headerAddBtn && headerContainer){
                headerAddBtn.addEventListener('click', function(){
                    headerContainer.appendChild(createHeaderItemRow());
                });
            }
        }

        // Bulk modal wiring (Template v3)
        if(IS_V3){
            const bulkBtnOpen = document.getElementById('btn-bulk-detail');
            const bulkModal = document.getElementById('bulk-invoice-detail-modal');
            const bulkCloseBtn = document.getElementById('btn-bulk-modal-close');
            const bulkCancelBtn = document.getElementById('btn-bulk-modal-cancel');
            const bulkJobList = document.getElementById('bulk-job-list');
            const bulkForm = document.getElementById('bulk-invoice-detail-form');
            const jobLabel = document.getElementById('current-job-label');
            const jobIndexIndicator = document.getElementById('job-index-indicator');
            const btnPrevJob = document.getElementById('btn-prev-job');
            const btnNextJob = document.getElementById('btn-next-job');

            // Header items in bulk modal (invoice-wide)
            const bulkHeaderAddBtn = document.getElementById('btn-bulk-add-header-item');
            const bulkHeaderContainer = document.getElementById('bulk-header-items-container');
            function createBulkHeaderItemRow(label = '', value = ''){
                const row = document.createElement('div');
                row.className = 'row g-2 align-items-center header-item-row mb-2';
                row.innerHTML = `
                    <div class="col-md-6"><input type="text" name="header_item_labels[]" class="form-control" value="${label}" placeholder="Label e.g. Cost Centre"></div>
                    <div class="col-md-5"><input type="text" name="header_item_values[]" class="form-control" value="${value}" placeholder="Value e.g. CC-123"></div>
                    <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-header-item" title="Remove">&times;</button></div>
                `;
                const removeBtn = row.querySelector('.btn-remove-header-item');
                removeBtn && removeBtn.addEventListener('click', () => row.remove());
                return row;
            }
            if(bulkHeaderContainer){
                bulkHeaderContainer.addEventListener('click', function(e){
                    const btn = e.target.closest('.btn-remove-header-item');
                    if(btn){ const row = btn.closest('.header-item-row'); row && row.remove(); }
                });
            }
            if(bulkHeaderAddBtn && bulkHeaderContainer){
                bulkHeaderAddBtn.addEventListener('click', function(){
                    bulkHeaderContainer.appendChild(createBulkHeaderItemRow());
                });
            }

            // Add Items section inside bulk modal
            const bulkAddItemBtn = document.getElementById('btn-bulk-add-item');
            const bulkItemsContainer = document.getElementById('bulk-additional-items');

            function createBulkItemRow(initial){
                const row = document.createElement('div');
                row.className = 'row g-2 align-items-end mb-2';
                row.innerHTML = `
                    <div class="col-md-6">
                        <label class="form-label">Item Description</label>
                        <input type="text" name="new_item_descriptions[]" class="form-control" placeholder="e.g. Interpreting">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="new_item_dates[]" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Amount (£)</label>
                        <input type="number" name="new_item_amounts[]" min="0" step="0.01" class="form-control" placeholder="0.00">
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-outline-danger" title="Remove" aria-label="Remove">&times;</button>
                    </div>
                `;
                const removeBtn = row.querySelector('button');
                removeBtn.addEventListener('click', () => row.remove());
                if(initial && typeof initial === 'object'){
                    row.querySelector('input[name=\"new_item_descriptions[]\"]').value = initial.description || '';
                    row.querySelector('input[name=\"new_item_dates[]\"]').value = initial.date || '';
                    row.querySelector('input[name=\"new_item_amounts[]\"]').value = (initial.amount !== undefined && initial.amount !== null) ? String(initial.amount) : '';
                    const hid = document.createElement('input');
                    hid.type = 'hidden';
                    hid.name = `existing_item_ids[]`;
                    hid.value = String(initial.id);
                    row.appendChild(hid);
                    row.querySelector('input[name=\"new_item_descriptions[]\"]').setAttribute('name', `existing_item_descriptions[${initial.id}]`);
                    row.querySelector('input[name=\"new_item_dates[]\"]').setAttribute('name', `existing_item_dates[${initial.id}]`);
                    row.querySelector('input[name=\"new_item_amounts[]\"]').setAttribute('name', `existing_item_amounts[${initial.id}]`);
                    removeBtn.addEventListener('click', () => {
                        const r = document.createElement('input');
                        r.type = 'hidden';
                        r.name = `existing_item_remove[${initial.id}]`;
                        r.value = '1';
                        bulkForm.appendChild(r);
                    }, { once: true });
                }
                return row;
            }
            function addBulkItemRow(){ if(bulkItemsContainer) bulkItemsContainer.appendChild(createBulkItemRow()); }
            if(bulkAddItemBtn){ bulkAddItemBtn.addEventListener('click', addBulkItemRow); }
            // Pre-populate existing standalone items
            try {
                const exEl = document.getElementById('existing-additional-items-json');
                const EXISTING = exEl ? JSON.parse(exEl.textContent || '[]') : [];
                EXISTING.forEach((it) => bulkItemsContainer.appendChild(createBulkItemRow(it)));
            } catch(e) {}
        
            // Fields
            const fClientRef = document.getElementById('field-client-reference');
            const fFileRef = document.getElementById('field-file-reference');
            const fPatientNo = document.getElementById('field-patient-number');
            const fPid = document.getElementById('field-pid');
            const fInterpMin = document.getElementById('field-interpreting-minutes');
            const fTravelMin = document.getElementById('field-travel-minutes');
            const fParkingAmount = document.getElementById('field-parking-amount');
            const fTranslationCost = document.getElementById('field-translation-cost');
            const fDate = document.getElementById('field-date');

            function openBulk(){ if(bulkModal) bulkModal.style.display = 'block'; }
            function closeBulk(){ if(bulkModal) bulkModal.style.display = 'none'; }
            if(bulkBtnOpen){ bulkBtnOpen.addEventListener('click', openBulk); }
            if(bulkCloseBtn){ bulkCloseBtn.addEventListener('click', closeBulk); }
            if(bulkCancelBtn){ bulkCancelBtn.addEventListener('click', closeBulk); }

            // Build jobs list from invoice items (only those with job_id)
            const jobItemsEl = document.getElementById('job-items-json');
            const JOB_ITEMS = jobItemsEl ? JSON.parse(jobItemsEl.textContent || '[]') : [];

            let bulkState = {};
            // Start with no selection to avoid saving an empty state for the first job
            let currentIndex = -1;
            let dirty = {}; // map item_id => boolean

            function renderJobList(){
                bulkJobList.innerHTML = '';
                JOB_ITEMS.forEach((j, idx) => {
                    const a = document.createElement('button');
                    a.type = 'button';
                    a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
                    a.dataset.idx = String(idx);
                    a.innerHTML = `<span>${j.label}</span><span class="badge bg-secondary">£${Number(j.amount).toFixed(2)}</span>`;
                    a.addEventListener('click', () => selectJob(idx));
                    bulkJobList.appendChild(a);
                });
                updateActiveHighlight();
                updateIndexBadge();
            }

            function updateActiveHighlight(){
                [...bulkJobList.children].forEach((el, i) => {
                    if(i === currentIndex){ el.classList.add('active'); }
                    else { el.classList.remove('active'); }
                });
            }

            function updateIndexBadge(){
                jobIndexIndicator.textContent = `${JOB_ITEMS.length ? (currentIndex+1) : 0} / ${JOB_ITEMS.length}`;
            }

            function loadFieldsFor(idx){
                const j = JOB_ITEMS[idx];
                if(!j){ return; }
                const existing = bulkState[j.item_id];
                const isPopulated = (s) => {
                    if(!s || typeof s !== 'object') return false;
                    const vals = [
                        s.client_reference,
                        s.file_reference,
                        s.patient_number,
                        s.pid,
                        s.interpreting_time_minutes,
                        s.travel_time_minutes,
                        s.parking_amount,
                        s.date
                    ];
                    return vals.some(v => v !== undefined && v !== null && v !== '');
                };
                const s = isPopulated(existing) ? existing : (j.meta || {});
                jobLabel.textContent = j.label;
                fClientRef.value = s.client_reference || '';
                fFileRef.value = s.file_reference || '';
                fPatientNo.value = s.patient_number || '';
                fPid.value = s.pid || '';
                fInterpMin.value = (s.interpreting_time_minutes !== undefined && s.interpreting_time_minutes !== null) ? String(s.interpreting_time_minutes) : '';
                fTravelMin.value = (s.travel_time_minutes !== undefined && s.travel_time_minutes !== null) ? String(s.travel_time_minutes) : '';
                fParkingAmount.value = (s.parking_amount !== undefined && s.parking_amount !== null) ? String(s.parking_amount) : '';
                if(s.translation_cost === undefined){
                    let tVal = null;
                    try {
                        const labels = Array.isArray(s.charge_labels) ? s.charge_labels : [];
                        const values = Array.isArray(s.charge_values) ? s.charge_values : [];
                        for(let i=0;i<labels.length;i++){
                            const norm = String(labels[i] || '').trim().toLowerCase();
                            if(norm === 'translation'){ const v = values[i]; if(v !== undefined && v !== null){ tVal = parseFloat(v); } break; }
                        }
                    } catch(e){}
                    s.translation_cost = tVal;
                }
                fTranslationCost.value = (s.translation_cost !== undefined && s.translation_cost !== null) ? String(s.translation_cost) : '';
                fDate.value = s.date || '';
            }

            function saveFieldsFor(idx){
                const j = JOB_ITEMS[idx];
                if(!j) return;
                const before = JSON.stringify(bulkState[j.item_id] || {});
                bulkState[j.item_id] = {
                    client_reference: fClientRef.value.trim(),
                    file_reference: fFileRef.value.trim(),
                    patient_number: fPatientNo.value.trim(),
                    pid: fPid.value.trim(),
                    interpreting_time_minutes: fInterpMin.value !== '' ? parseInt(fInterpMin.value, 10) : null,
                    travel_time_minutes: fTravelMin.value !== '' ? parseInt(fTravelMin.value, 10) : null,
                    parking_amount: fParkingAmount.value !== '' ? parseFloat(fParkingAmount.value) : null,
                    translation_cost: fTranslationCost.value !== '' ? parseFloat(fTranslationCost.value) : null,
                    date: fDate.value || null,
                };
                const after = JSON.stringify(bulkState[j.item_id]);
                dirty[j.item_id] = before !== after;
                updateJobDirtyBadge(j.item_id);
            }

            function selectJob(idx){
                // Save current only if a job has been selected before
                if(currentIndex >= 0){ saveFieldsFor(currentIndex); }
                // Switch
                currentIndex = idx;
                updateActiveHighlight();
                updateIndexBadge();
                loadFieldsFor(currentIndex);
            }

            function updateJobDirtyBadge(itemId){
                // Add a small dot before label to indicate unsaved edits
                [...bulkJobList.children].forEach((el) => {
                    const idx = parseInt(el.dataset.idx, 10);
                    const obj = JOB_ITEMS[idx];
                    if(!obj) return;
                    if(obj.item_id === itemId && dirty[itemId]){
                        el.classList.add('list-group-item-warning');
                    } else if(obj.item_id === itemId && !dirty[itemId]){
                        el.classList.remove('list-group-item-warning');
                    }
                });
            }

            // Mark dirty as user types
            [fClientRef, fFileRef, fPatientNo, fPid, fInterpMin, fTravelMin, fParkingAmount, fTranslationCost, fDate].forEach((el)=>{
                if(!el) return;
                el.addEventListener('input', () => {
                    const j = JOB_ITEMS[currentIndex];
                    if(!j) return;
                    dirty[j.item_id] = true;
                    updateJobDirtyBadge(j.item_id);
                });
            });

            btnPrevJob && btnPrevJob.addEventListener('click', function(){
                if(!JOB_ITEMS.length) return;
                if(currentIndex >= 0){ saveFieldsFor(currentIndex); }
                currentIndex = (currentIndex - 1 + JOB_ITEMS.length) % JOB_ITEMS.length;
                updateActiveHighlight();
                updateIndexBadge();
                loadFieldsFor(currentIndex);
            });
            btnNextJob && btnNextJob.addEventListener('click', function(){
                if(!JOB_ITEMS.length) return;
                if(currentIndex >= 0){ saveFieldsFor(currentIndex); }
                currentIndex = (currentIndex + 1) % JOB_ITEMS.length;
                updateActiveHighlight();
                updateIndexBadge();
                loadFieldsFor(currentIndex);
            });

            // Keyboard navigation (Left/Right arrows)
            document.addEventListener('keydown', function(e){
                if(bulkModal && bulkModal.style.display === 'block'){
                    if(e.key === 'ArrowLeft' && btnPrevJob){ btnPrevJob.click(); }
                    if(e.key === 'ArrowRight' && btnNextJob){ btnNextJob.click(); }
                }
            });

            // Initialize
            renderJobList();
            if(JOB_ITEMS.length){ selectJob(0); }

            // Auto-open the bulk modal for multi-job invoices to streamline UX
            // Do NOT auto-open immediately after a successful save (flash success present or temporary sessionStorage flag),
            // unless explicitly requested via ?open=bulk
            try {
                const url = new URL(window.location.href);
                const forceOpen = url.searchParams.get('open') === 'bulk';
                const hasSuccessFlash = !!document.querySelector('.alert.alert-success');
                const skipOnce = sessionStorage.getItem('skipBulkAutoOpenOnce') === '1';
                if(skipOnce){ sessionStorage.removeItem('skipBulkAutoOpenOnce'); }
                const autoOpen = JOB_ITEMS.length > 1 && !hasSuccessFlash && !skipOnce;
                const shouldOpen = forceOpen || autoOpen;
                if(shouldOpen && bulkModal && bulkModal.style.display !== 'block'){
                    setTimeout(openBulk, 50);
                }
            } catch (e) { /* no-op */ }

            // On submit, materialize hidden inputs for per-item arrays
            bulkForm && bulkForm.addEventListener('submit', function(){
                // Close immediately for a smoother UX; the page will refresh after save
                try { closeBulk(); } catch(e) {}
                saveFieldsFor(currentIndex);
                // Clear previous hidden inputs
                const oldHidden = this.querySelectorAll('.bulk-hidden');
                oldHidden.forEach(el => el.remove());

                Object.entries(bulkState).forEach(([itemId, s]) => {
                    const add = (name, value) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `${name}[${itemId}]`;
                        input.value = value ?? '';
                        input.className = 'bulk-hidden';
                        bulkForm.appendChild(input);
                    };
                    if(s.client_reference !== undefined) add('item_client_reference', s.client_reference);
                    if(s.file_reference !== undefined) add('item_file_reference', s.file_reference);
                    if(s.patient_number !== undefined) add('item_patient_number', s.patient_number);
                    if(s.pid !== undefined) add('item_pid', s.pid);
                    if(s.interpreting_time_minutes !== undefined && s.interpreting_time_minutes !== null) add('item_interpreting_minutes', s.interpreting_time_minutes);
                    if(s.travel_time_minutes !== undefined && s.travel_time_minutes !== null) add('item_travel_minutes', s.travel_time_minutes);
                    if(s.parking_amount !== undefined && s.parking_amount !== null) add('item_parking_amount', s.parking_amount);
                    if(s.translation_cost !== undefined && s.translation_cost !== null) add('item_translation_cost', s.translation_cost);
                    if(s.date !== undefined && s.date !== null) add('item_dates', s.date);
                });

                // Set a short-lived flag so that after the redirect, the bulk modal does not auto-open again
                try { sessionStorage.setItem('skipBulkAutoOpenOnce', '1'); } catch(e) {}
            });
        }
    })();
</script>

<!-- Send Invoice Email Modal -->
<div id="send-invoice-email-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1060;">
    <div style="background:#fff; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.2); width:95%; max-width:680px; margin:6% auto; padding:20px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0" id="send-email-modal-title">Send Invoice Email</h5>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-send-email-close">Close</button>
        </div>
        <div id="already-sent-summary" class="alert alert-info py-2 px-3 mb-3" style="display:none;"><small>
            <span class="me-2">This invoice was already sent on</span>
            <strong id="already-sent-at"></strong>.
            <span class="ms-2">You can resend it with updated recipients and message.</span>
        </small></div>
        <div class="row g-3">
            <div class="col-12">
                <label for="send-email-to" class="form-label">To</label>
                <input type="email" id="send-email-to" class="form-control" placeholder="name@example.com" value="{{ $defaultInvoiceEmail }}">
            </div>
            <div class="col-md-6">
                <label for="send-email-cc" class="form-label">CC</label>
                <input type="text" id="send-email-cc" class="form-control" placeholder="e.g. accounts@example.com; other@domain.com">
                <div class="form-text">Separate multiple emails with commas or semicolons.</div>
            </div>
            <div class="col-md-6">
                <label for="send-email-bcc" class="form-label">BCC</label>
                <input type="text" id="send-email-bcc" class="form-control" placeholder="optional">
            </div>
            <div class="col-12">
                <label for="send-email-subject" class="form-label">Subject</label>
                <input type="text" id="send-email-subject" class="form-control" value="{{ 'Invoice '.($invoice->invoice_number ?? '').' from '.(config('bank.account_name') ?? config('app.name')) }}">
            </div>
            <div class="col-12">
                <label for="send-email-message" class="form-label">Message (optional)</label>
                <textarea id="send-email-message" rows="4" class="form-control" placeholder="Add a short note to include in the email body"></textarea>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="send-email-remember">
                    <label class="form-check-label" for="send-email-remember">Remember this email for this client</label>
                </div>
                <div class="form-text">For company clients, this updates the organisation invoice email.</div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="button" class="btn btn-outline-secondary" id="btn-send-email-cancel">Cancel</button>
            <button type="button" class="btn btn-primary" id="btn-send-email-confirm">Send</button>
        </div>
    </div>
    <script>
        // Lightweight wiring for closing the modal from within the Blade file
        (function(){
            const modal = document.getElementById('send-invoice-email-modal');
            const btnClose = document.getElementById('btn-send-email-close');
            const btnCancel = document.getElementById('btn-send-email-cancel');
            function close(){ if(modal) modal.style.display = 'none'; }
            if(btnClose) btnClose.addEventListener('click', close);
            if(btnCancel) btnCancel.addEventListener('click', close);
        })();
    </script>
</div>
@endpush
