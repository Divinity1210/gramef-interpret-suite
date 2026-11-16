@extends('layouts.app')

@section('content')
<div class="container-fluid px-2">
    <div class="row justify-content-center">
        <div class="col-12" style="max-width: 1600px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Invoice Board</h1>
        <a href="{{ route('invoices.agent.create') }}" class="btn btn-success">Create Invoice</a>
    </div>

    <!-- Invoice Type Tabs -->
    <div class="row mb-4">
        <div class="col-12">
            <ul class="nav nav-tabs" id="invoiceTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="interpreter-tab" data-bs-toggle="tab" data-bs-target="#interpreter-invoices" type="button" role="tab" aria-controls="interpreter-invoices" aria-selected="true">
                        Interpreter Invoices 
                        <span class="badge bg-success ms-2">{{ $interpreterCount ?? 0 }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="translation-tab" data-bs-toggle="tab" data-bs-target="#translation-invoices" type="button" role="tab" aria-controls="translation-invoices" aria-selected="false">
                        Translation Invoices 
                        <span class="badge bg-info ms-2">{{ $translationCount ?? 0 }}</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content" id="invoiceTabsContent">
        <!-- Interpreter Invoices Tab -->
        <div class="tab-pane fade show active" id="interpreter-invoices" role="tabpanel" aria-labelledby="interpreter-tab">
            <form method="GET" class="filters row g-2 mb-3" id="agentInterpreterInvoicesFilter">
                @csrf
                <input type="hidden" name="tab" value="interpreter">
                <div class="col-md-6 col-lg-6">
                    @include('partials.filters.date-range', [
                        'mode' => 'single',
                        'formId' => 'agentInterpreterInvoicesFilter',
                        'inputId' => 'interpreterDateFilter',
                        'paramName' => 'date',
                        'startName' => 'start_date',
                        'endName' => 'end_date',
                        'startId' => 'start_date',
                        'endId' => 'end_date',
                        'placeholder' => 'Filter By Date...',
                        'submitWhen' => 'change'
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
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Invoice No.</th>
                            <th>Period</th>
                            <th>Subtotal</th>
                            <th>Expenses</th>
                            <th>VAT</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($interpreterInvoices as $invoice)
                            <tr>
                                <td>{{ $invoice->id }}</td>
                                <td>
                                    <span class="badge bg-success me-2">INT</span>
                                    {{ $invoice->invoice_number }}
                                </td>
                                <td>{{ optional($invoice->date_from)->format('Y-m-d') }} — {{ optional($invoice->date_to)->format('Y-m-d') }}</td>
                                <td>£{{ number_format($invoice->subtotal, 2) }}</td>
                                @php
                                    $expensesTotal = optional($invoice->items)->sum(function($i){ return ($i->expenses_amount + $i->surcharge + $i->other_charges); });
                                @endphp
                                <td>£{{ number_format($expensesTotal, 2) }}</td>
                                <td>0% ( £{{ number_format($invoice->vat_amount ?? 0, 2) }} )</td>
                                <td><strong>£{{ number_format($invoice->total_amount ?? 0, 2) }}</strong></td>
                                <td><span class="badge bg-secondary">{{ ucfirst($invoice->status_label ?? 'draft') }}</span></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a class="btn btn-primary" href="{{ route('invoices.agent.show', $invoice->id) }}">View</a>
                                        @php $statusLabel = strtolower((string)($invoice->status_label ?? 'draft')); @endphp
                                        @if($statusLabel === 'draft')
                                            <form action="{{ route('invoices.agent.delete', $invoice->id) }}" method="POST" onsubmit="return confirm('Delete this draft invoice? This will unlock included jobs for re-invoicing.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">No interpreter invoices found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $interpreterInvoices->appends(request()->query())->links() }}
            </div>
        </div>

        <!-- Translation Invoices Tab -->
        <div class="tab-pane fade" id="translation-invoices" role="tabpanel" aria-labelledby="translation-tab">
            <form method="GET" class="filters row g-2 mb-3" id="agentTranslationInvoicesFilter">
                @csrf
                <input type="hidden" name="tab" value="translation">
                <div class="col-md-6 col-lg-6">
                    @include('partials.filters.date-range', [
                        'mode' => 'single',
                        'formId' => 'agentTranslationInvoicesFilter',
                        'inputId' => 'translationDateFilter',
                        'paramName' => 'date',
                        'startName' => 'start_date',
                        'endName' => 'end_date',
                        'startId' => 'start_date',
                        'endId' => 'end_date',
                        'placeholder' => 'Filter By Date...',
                        'submitWhen' => 'change'
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
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Invoice No.</th>
                            <th>Period</th>
                            <th>Subtotal</th>
                            <th>Expenses</th>
                            <th>VAT</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($translationInvoices as $invoice)
                            <tr>
                                <td>{{ $invoice->id }}</td>
                                <td>
                                    <span class="badge bg-info me-2">TRA</span>
                                    {{ $invoice->invoice_number }}
                                </td>
                                <td>{{ optional($invoice->date_from)->format('Y-m-d') }} — {{ optional($invoice->date_to)->format('Y-m-d') }}</td>
                                <td>£{{ number_format($invoice->subtotal, 2) }}</td>
                                @php
                                    $expensesTotal = optional($invoice->items)->sum(function($i){ return ($i->expenses_amount + $i->surcharge + $i->other_charges); });
                                @endphp
                                <td>£{{ number_format($expensesTotal, 2) }}</td>
                                <td>0% ( £{{ number_format($invoice->vat_amount ?? 0, 2) }} )</td>
                                <td><strong>£{{ number_format($invoice->total_amount ?? 0, 2) }}</strong></td>
                                <td><span class="badge bg-secondary">{{ ucfirst($invoice->status_label ?? 'draft') }}</span></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a class="btn btn-primary" href="{{ route('invoices.agent.show', $invoice->id) }}">View</a>
                                        @php $statusLabel = strtolower((string)($invoice->status_label ?? 'draft')); @endphp
                                        @if($statusLabel === 'draft')
                                            <form action="{{ route('invoices.agent.delete', $invoice->id) }}" method="POST" onsubmit="return confirm('Delete this draft invoice? This will unlock included jobs for re-invoicing.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">No translation invoices found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $translationInvoices->appends(request()->query())->links() }}
            </div>
        </div>
    </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle tab switching based on URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    
    if (activeTab === 'translation') {
        const translationTab = document.getElementById('translation-tab');
        const interpreterTab = document.getElementById('interpreter-tab');
        const translationPane = document.getElementById('translation-invoices');
        const interpreterPane = document.getElementById('interpreter-invoices');
        
        // Switch to translation tab
        interpreterTab.classList.remove('active');
        interpreterTab.setAttribute('aria-selected', 'false');
        translationTab.classList.add('active');
        translationTab.setAttribute('aria-selected', 'true');
        
        interpreterPane.classList.remove('show', 'active');
        translationPane.classList.add('show', 'active');
    }
    
    // Add click event listeners to tabs
    const interpreterTab = document.getElementById('interpreter-tab');
    const translationTab = document.getElementById('translation-tab');
    
    if (interpreterTab) {
        interpreterTab.addEventListener('click', function(e) {
            e.preventDefault();
            const url = new URL(window.location);
            url.searchParams.set('tab', 'interpreter');
            window.location.href = url.toString();
        });
    }
    
    if (translationTab) {
        translationTab.addEventListener('click', function(e) {
            e.preventDefault();
            const url = new URL(window.location);
            url.searchParams.set('tab', 'translation');
            window.location.href = url.toString();
        });
    }
});
</script>
@endsection
