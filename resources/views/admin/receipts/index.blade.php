@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Receipts</h4>
                    <div class="btn-group">
                        <a href="{{ route('exports.index') }}" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Export
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Filter Section -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <form method="GET" action="{{ route('admin.receipts.index') }}" class="row g-3" id="adminReceiptsFilter">
                                <div class="col-md-3">
                                    <label for="client_id" class="form-label">Client</label>
                                    <select name="client_id" id="client_id" class="form-select">
                                        <option value="">All Clients</option>
                                        @foreach($clients as $client)
                                            @php
                                                $first = optional($client->user)->first_name;
                                                $last = optional($client->user)->last_name;
                                                $label = trim(($first ?? '') . ' ' . ($last ?? ''));
                                            @endphp
                                            <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>
                                                {{ $label !== '' ? $label : 'Unknown' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    @include('partials.filters.date-range', [
                                        'mode' => 'single',
                                        'formId' => 'adminReceiptsFilter',
                                        'inputId' => 'adminReceiptsDateFilter',
                                        'paramName' => 'date',
                                        'startName' => 'start_date',
                                        'endName' => 'end_date',
                                        'startId' => 'start_date',
                                        'endId' => 'end_date',
                                        'mirrorHidden' => true,
                                        'placeholder' => 'Filter By Date...',
                                        'submitWhen' => 'change'
                                    ])
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                                    <a href="{{ route('admin.receipts.index') }}" class="btn btn-secondary">Clear</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Receipts Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Receipt #</th>
                                    <th>Client</th>
                                    <th>Invoice</th>
                                    <th>Company</th>
                                    <th>Amount Paid</th>
                                    <th>Issued</th>
                                    <th>Proofs</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($receipts as $receipt)
                                    <tr>
                                        <td><strong>{{ $receipt->receipt_number }}</strong></td>
                                        <td>
                                            @php $user = optional(optional($receipt->clientInvoice)->client)->user; @endphp
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-2">
                                                    <div class="avatar-title bg-info rounded-circle">
                                                        {{ substr(optional($user)->first_name ?? 'U', 0, 1) }}
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="fw-bold">{{ optional($user)->first_name }} {{ optional($user)->last_name }}</div>
                                                    <small class="text-muted">{{ optional($user)->email ?? '' }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @php $invoice = $receipt->clientInvoice; @endphp
                                            @if($invoice)
                                                <a href="{{ route('invoices.admin.client.show', $invoice->id) }}" class="text-decoration-none">
                                                    <strong>{{ $invoice->invoice_number }}</strong>
                                                </a>
                                            @else
                                                <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td>{{ optional(optional($receipt->clientInvoice)->company)->name ?? '—' }}</td>
                                        <td><strong>£{{ number_format($receipt->total_paid, 2) }}</strong></td>
                                        <td>{{ optional($receipt->issued_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td>
                                            <span class="badge bg-info">{{ $receipt->paymentProofs->count() }} proof(s)</span>
                                        </td>
                                        <td class="d-flex gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('receipts.view', $receipt) }}" target="_blank">
                                                View
                                            </a>
                                            <a class="btn btn-sm btn-primary" href="{{ route('receipts.download', $receipt) }}">
                                                Download PDF
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">No receipts found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if($receipts->hasPages())
                        <div class="d-flex justify-content-center mt-4">
                            {{ $receipts->appends(request()->query())->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection