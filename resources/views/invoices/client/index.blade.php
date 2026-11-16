@extends('layouts.app')

@section('content')
<div class="container-fluid px-2">
    <div class="row justify-content-center">
        <div class="col-12" style="max-width: 1600px;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="mb-0">My Invoices</h1>
                <a href="{{ route('invoices.client.index') }}" class="btn btn-outline-secondary">Refresh</a>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filter Invoices</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-2" id="clientInvoicesFilter">
                        <div class="col-md-8">
                            @include('partials.filters.date-range', [
                                'mode' => 'single',
                                'formId' => 'clientInvoicesFilter',
                                'inputId' => 'clientInvoicesDateFilter',
                                'paramName' => 'date',
                                'startName' => 'date_range_start',
                                'endName' => 'date_range_end',
                                'startId' => 'date_range_start',
                                'endId' => 'date_range_end',
                                'mirrorHidden' => true,
                                'placeholder' => 'Filter By Date...'
                            ])
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">Any</option>
                                @foreach(['draft','submitted','sent','paid','cancelled'] as $status)
                                    <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button class="btn btn-primary w-100" type="submit">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-table"></i> Invoices <span class="badge bg-secondary ms-2">{{ $invoices->total() }}</span></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Invoice No.</th>
                                    <th>Company</th>
                                    <th>Period</th>
                                    <th>Subtotal</th>
                                    <th>VAT</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($invoices as $invoice)
                                    <tr>
                                        <td>{{ $invoice->id }}</td>
                                        <td>{{ $invoice->invoice_number }}</td>
                                        <td>{{ optional($invoice->company)->name ?? '—' }}</td>
                                        <td>{{ optional($invoice->date_range_start)->format('Y-m-d') }} — {{ optional($invoice->date_range_end)->format('Y-m-d') }}</td>
                                        <td>£{{ number_format($invoice->subtotal, 2) }}</td>
                                        <td>{{ number_format($invoice->vat_rate, 0) }}% ( £{{ number_format($invoice->vat_amount, 2) }} )</td>
                                        <td><strong>£{{ number_format($invoice->total, 2) }}</strong></td>
                                        <td><span class="badge bg-secondary">{{ ucfirst($invoice->status ?? 'draft') }}</span></td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a class="btn btn-outline-primary" href="{{ route('invoices.client.show', $invoice->id) }}">View</a>
                                                <a class="btn btn-primary" href="{{ route('invoices.client.pdf', $invoice->id) }}" target="_blank">Download PDF</a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No invoices found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    {{ $invoices->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection