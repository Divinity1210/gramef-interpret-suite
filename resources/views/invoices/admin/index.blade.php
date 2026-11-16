@extends('layouts.app')

@section('content')
<link href="/css/invoice-admin.css" rel="stylesheet">

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Invoice Management Dashboard</h1>
        <div class="d-flex gap-2">
            <button class="btn btn-info az-btn az-btn-secondary" onclick="exportInvoices()">
                <i class="fas fa-download"></i> Export to CSV
            </button>
            <div class="dropdown">
                <button class="btn btn-success az-btn az-btn-secondary dropdown-toggle" type="button" id="bulkActionsDropdown" data-custom-dropdown aria-expanded="false" title="Bulk actions">
                    <i class="fas fa-tasks"></i> Bulk Actions
                </button>
                <ul class="dropdown-menu" id="bulkActionsMenu" aria-labelledby="bulkActionsDropdown">
                    @if(($filters['type'] ?? 'agent') === 'client')
                        <li>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="performBulkSend()" title="Send selected client invoices via email">
                                <i class="fas fa-paper-plane text-info"></i> Send Selected
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                    @endif
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="performBulkAction('approve')">
                        <i class="fas fa-check text-success"></i> Approve Selected
                    </a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="performBulkAction('mark_paid')">
                        <i class="fas fa-money-bill text-success"></i> Mark as Paid
                    </a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="performBulkAction('cancel')">
                        <i class="fas fa-ban text-warning"></i> Cancel Selected
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="performBulkAction('delete')">
                        <i class="fas fa-trash"></i> Delete Selected
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Summary Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Invoices</h6>
                            <h3 class="mb-0">{{ $invoices->total() }}</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-file-invoice fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Pending Review</h6>
                            @php
                                $pendingCount = $invoices->getCollection()->filter(function($inv) use ($filters) {
                                    $label = (($filters['type'] ?? 'agent') === 'client')
                                        ? ($inv->status ?? 'draft')
                                        : ($inv->status_label ?? ($inv->status ?? 'draft'));
                                    return $label === 'submitted';
                                })->count();
                            @endphp
                            <h3 class="mb-0">{{ $pendingCount }}</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Value</h6>
                            <h3 class="mb-0">£{{ number_format($invoices->sum(($filters['type'] ?? 'agent') === 'client' ? 'total' : 'total_amount'), 2) }}</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-pound-sign fa-2x opacity-75"></i>
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
                            <h6 class="card-title">This Month</h6>
                            <h3 class="mb-0">{{ $invoices->where('created_at', '>=', now()->startOfMonth())->count() }}</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-alt fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <span><i class="fas fa-filter"></i> Advanced Filters</span>
            </h5>
        </div>
        <div>
            <div class="card-body">
                <form method="GET" class="filters row g-3" id="adminInvoicesFilter">
                    <div class="col-md-2">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            @foreach(['agent' => 'Agent Invoices', 'client' => 'Client Invoices'] as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['type'] ?? 'agent') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Search Name</label>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Agent/Client name...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Ref #</label>
                        <input type="text" name="ref" value="{{ $filters['ref'] ?? '' }}" class="form-control" placeholder="Invoice # or Job ID">
                    </div>
                    <div class="col-md-4 col-lg-4">
                        @include('partials.filters.date-range', [
                            'mode' => 'single',
                            'formId' => 'adminInvoicesFilter',
                            'inputId' => 'adminInvoicesDateFilter',
                            'paramName' => 'date',
                            'startName' => 'start_date',
                            'endName' => 'end_date',
                            'startId' => 'start_date',
                            'endId' => 'end_date',
                            'placeholder' => 'Filter By Date...',
                            'submitWhen' => 'change'
                        ])
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            @foreach(['draft','submitted','sent','paid','cancelled'] as $status)
                                <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="btn-group w-100">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="{{ route('invoices.admin.index') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Enhanced Invoice Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-table"></i> Invoice List
                <span class="badge bg-secondary ms-2">{{ $invoices->total() }} total</span>
            </h5>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" onclick="selectAll()">
                    <i class="fas fa-check-square"></i> Select All
                </button>
                <button class="btn btn-outline-secondary" onclick="clearSelection()">
                    <i class="fas fa-square"></i> Clear
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="table-dark" style="position: sticky; top: 0; z-index: 10;">
                    <tr>
                        <th width="40">
                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()">
                        </th>
                        <th>Invoice #</th>
                        <th>{{ ($filters['type'] ?? 'agent') === 'client' ? 'Company' : 'Agent' }}</th>
                        <th>Contact</th>
                        <th>Period</th>
                        <th>Created</th>
                        <th>Subtotal</th>
                        @if(($filters['type'] ?? 'agent') === 'agent')
                            <th>Expenses</th>
                        @endif
                        <th>VAT</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th width="200">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($invoices as $invoice)
                        <tr class="invoice-row" data-invoice-id="{{ $invoice->id }}">
                            <td>
                                <input type="checkbox" class="invoice-checkbox" value="{{ $invoice->id }}">
                            </td>
                            <td>
                                <strong>{{ $invoice->invoice_number }}</strong>
                                <br><small class="text-muted">#{{ $invoice->id }}</small>
                            </td>

                            @if(($filters['type'] ?? 'agent') === 'client')
                                <td>
                                    <div>
                                        <strong>{{ optional($invoice->company)->name ?? '—' }}</strong>
                                        @if($invoice->client && $invoice->client->user)
                                            <br><small class="text-muted">{{ $invoice->client->user->getFullName() }}</small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if($invoice->client && $invoice->client->user)
                                        <i class="fas fa-envelope text-muted"></i> {{ $invoice->client->user->email }}
                                        @if($invoice->client->contact_number)
                                            <br><i class="fas fa-phone text-muted"></i> {{ $invoice->client->contact_number }}
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            @else
                                <td>
                                    <div>
                                        <strong>{{ optional(optional($invoice->agent)->user)->getFullName() ?? '—' }}</strong>
                                        @if($invoice->agent && $invoice->agent->user)
                                            <br><small class="text-muted">Agent ID: {{ $invoice->agent->id }}</small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if($invoice->agent && $invoice->agent->user)
                                        <i class="fas fa-envelope text-muted"></i> {{ $invoice->agent->user->email }}
                                        @if($invoice->agent->contact_number)
                                            <br><i class="fas fa-phone text-muted"></i> {{ $invoice->agent->contact_number }}
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            @endif

                            <td>
                                @if(($filters['type'] ?? 'agent') === 'client')
                                    <small>{{ optional($invoice->date_range_start)->format('M d') }} — {{ optional($invoice->date_range_end)->format('M d, Y') }}</small>
                                @else
                                    <small>{{ optional($invoice->date_from)->format('M d') }} — {{ optional($invoice->date_to)->format('M d, Y') }}</small>
                                @endif
                            </td>
                            <td>
                                <small>{{ $invoice->created_at->format('M d, Y') }}</small>
                                <br><small class="text-muted">{{ $invoice->created_at->diffForHumans() }}</small>
                            </td>
                            <td>£{{ number_format($invoice->subtotal, 2) }}</td>
                            @if(($filters['type'] ?? 'agent') === 'agent')
                                @php
                                    $expensesTotal = optional($invoice->items)->sum(function($i) {
                                        return ($i->expenses_amount + $i->surcharge + $i->other_charges);
                                    });
                                @endphp
                                <td>£{{ number_format($expensesTotal, 2) }}</td>
                            @endif
                            <td>£{{ number_format($invoice->vat_amount, 2) }}</td>
                            <td>
                                <strong class="text-success">£{{ number_format(($filters['type'] ?? 'agent') === 'client' ? $invoice->total : $invoice->total_amount, 2) }}</strong>
                            </td>
                            <td>
                                @php
                                    $statusColors = [
                                        'draft' => 'secondary',
                                        'submitted' => 'warning',
                                        'sent' => 'info',
                                        'paid' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    $statusLabel = (($filters['type'] ?? 'agent') === 'client')
                                        ? ($invoice->status ?? 'draft')
                                        : ($invoice->status_label ?? ($invoice->status ?? 'draft'));
                                    $statusColor = $statusColors[$statusLabel] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $statusColor }}">{{ ucfirst($statusLabel) }}</span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    {{-- Streamlined actions: keep only essential actions per type --}}
                                    @php
                                        $effectiveStatus = (($filters['type'] ?? 'agent') === 'client') ? ($invoice->status ?? 'draft') : ($invoice->status_label ?? ($invoice->status ?? 'draft'));
                                        $disableSend = ($effectiveStatus === 'sent' || $effectiveStatus === 'paid' || !($invoice->client && $invoice->client->user && $invoice->client->user->email));
                                        $sendTooltip = $disableSend ? ($effectiveStatus === 'sent' ? 'Already sent' : ($effectiveStatus === 'paid' ? 'Already paid' : 'Missing client email')) : 'Send Email';
                                    @endphp
                                    @if(($filters['type'] ?? 'agent') === 'client')
                                        <a class="btn btn-outline-info az-btn az-btn-secondary btn-action" href="{{ route('invoices.admin.client.preview', $invoice->id) }}" title="Preview invoice" data-bs-toggle="tooltip">
                                            <i class="fas fa-search"></i> Preview
                                        </a>
                                        <button type="button" class="btn btn-outline-primary az-btn az-btn-primary btn-action"
                                                data-action="send-invoice" data-invoice-id="{{ $invoice->id }}"
                                                title="{{ $sendTooltip }}" data-bs-toggle="tooltip" @if($disableSend) disabled @endif>
                                            <i class="fas fa-paper-plane"></i> Send
                                        </button>
                                    @else
                                        <a class="btn btn-outline-info az-btn az-btn-secondary btn-action" href="{{ route('invoices.admin.agent.preview', $invoice->id) }}" title="Preview invoice" data-bs-toggle="tooltip">
                                            <i class="fas fa-search"></i> Preview
                                        </a>
                                    @endif
                                    @if((($filters['type'] ?? 'agent') === 'client' ? ($invoice->status ?? 'draft') : ($invoice->status_label ?? ($invoice->status ?? 'draft'))) === 'submitted')
                                        <button type="button" class="btn btn-success btn-action"
                                                data-action="approve-invoice" data-invoice-id="{{ $invoice->id }}" data-invoice-type="{{ $filters['type'] ?? 'agent' }}"
                                                title="Approve" data-bs-toggle="tooltip">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    @endif
                                    @if(($filters['type'] ?? 'agent') === 'agent')
                                        <button type="button"
                                                class="btn btn-primary btn-action payslip-btn"
                                                data-action="generate-payslip" data-invoice-id="{{ $invoice->id }}"
                                                title="Generate Payslip" data-bs-toggle="tooltip">
                                            <i class="fas fa-file-invoice-dollar me-1"></i>Generate Payslip
                                        </button>
                                        @if($effectiveStatus !== 'paid')
                                            <button type="button" class="btn btn-outline-success btn-action"
                                                    data-action="mark-paid" data-invoice-id="{{ $invoice->id }}"
                                                    title="Mark as Paid" data-bs-toggle="tooltip">
                                                <i class="fas fa-money-bill"></i> Mark Paid
                                            </button>
                                        @endif
                                    @elseif(($filters['type'] ?? 'agent') === 'client')
                                        @if(($invoice->status ?? 'draft') === 'sent')
                                            <button type="button" class="btn btn-outline-success btn-action"
                                                    data-action="mark-paid" data-invoice-id="{{ $invoice->id }}"
                                                    title="Mark as Paid" data-bs-toggle="tooltip">
                                                <i class="fas fa-money-bill"></i> Mark Paid
                                            </button>
                                        @endif
                                    @endif
                                    <button type="button" class="btn btn-outline-danger az-btn az-btn-danger btn-action"
                                            data-action="delete-invoice" data-invoice-id="{{ $invoice->id }}"
                                            title="Delete invoice" data-bs-toggle="tooltip">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <h5>No invoices found</h5>
                                    <p>Try adjusting your filters or create a new invoice.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($invoices->hasPages())
            <div class="card-footer">
                {{ $invoices->appends(request()->query())->links('custom.pagination') }}
            </div>
        @endif
    </div>
</div>

<!-- Payslip Generation Modal -->
<div class="modal fade payslip-modal" id="payslipModal" tabindex="-1" aria-labelledby="payslipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="payslipModalLabel">
                    <i class="fas fa-receipt me-2"></i>Generate Payslip
                </h5>
                <button type="button" class="btn- btn-close close  btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="payslipModalBody">
                <!-- Dynamic content will be loaded here -->
                <div class="text-center py-5">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Loading invoice details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-success" id="generatePayslipBtn">
                    <i class="fas fa-save me-1"></i>Generate Payslip
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Job Modal -->
<div class="modal fade" id="addJobModal" tabindex="-1" aria-labelledby="addJobModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addJobModalLabel">
                    <i class="fas fa-plus me-2"></i>Add Job
                </h5>
                <button type="button" class="btn- btn-close close  btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="jobSearchInput" class="form-label">Enter Job ID or Reference</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="jobSearchInput" placeholder="Enter Job ID or Job Reference">
                        <button class="btn btn-primary" type="button" onclick="searchJob()">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
                <div id="jobSearchResult"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-success" id="addJobBtn" onclick="addJobToPayslip()" style="display: none;">
                    <i class="fas fa-plus me-1"></i>Add Job
                </button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
/* Make Add Job modal as wide as job board modals while staying responsive */
#addJobModal .modal-dialog { max-width: min(1200px, 96vw); }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/invoice-admin.js') }}"></script>
@endpush
@endsection
