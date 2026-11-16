@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Remittances Management</h4>
                    <div class="btn-group">
                        <a href="{{ route('remittances.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Remittance
                        </a>
                        <a href="{{ route('exports.index') }}" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Export
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Filter Section -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <form method="GET" action="{{ route('admin.remittances.index') }}" class="row g-3" id="adminRemittancesFilter">
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="">All Statuses</option>
                                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                        <option value="processed" {{ request('status') == 'processed' ? 'selected' : '' }}>Processed</option>
                                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                    </select>
                                </div>
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
                                <div class="col-md-4">
                                    @include('partials.filters.date-range', [
                                        'mode' => 'single',
                                        'formId' => 'adminRemittancesFilter',
                                        'inputId' => 'adminRemittancesDateFilter',
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
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                                    <a href="{{ route('admin.remittances.index') }}" class="btn btn-secondary">Clear</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Pending</h6>
                                            <h3 class="mb-0">{{ $stats['pending'] ?? 0 }}</h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-clock fa-2x"></i>
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
                                            <h6 class="card-title">Approved</h6>
                                            <h3 class="mb-0">{{ $stats['approved'] ?? 0 }}</h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-check fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Processed</h6>
                                            <h3 class="mb-0">{{ $stats['processed'] ?? 0 }}</h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-money-bill fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Rejected</h6>
                                            <h3 class="mb-0">{{ $stats['rejected'] ?? 0 }}</h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-times fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Remittances Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Remittance #</th>
                                    <th>Client</th>
                                    <th>Invoice</th>
                                    <th>Evidence</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($remittances as $remittance)
                                    <tr>
                                        <td>
                                            <strong>{{ $remittance->remittance_number }}</strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-2">
                                                <div class="avatar-title bg-info rounded-circle">
                                                        {{ substr(optional(optional($remittance->clientInvoice)->client)->user->first_name ?? 'U', 0, 1) }}
                                                    </div>
                                                </div>
                                                <div>
                                                    @php
                                                        $user = optional(optional($remittance->clientInvoice)->client)->user;
                                                    @endphp
                                                    <div class="fw-bold">{{ optional($user)->first_name }} {{ optional($user)->last_name }}</div>
                                                    <small class="text-muted">{{ optional($user)->email ?? '' }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <a href="{{ route('invoices.admin.client.show', optional($remittance->clientInvoice)->id) }}" class="text-decoration-none">
                                                    <strong>{{ optional($remittance->clientInvoice)->invoice_number }}</strong>
                                                </a>
                                            </div>
                                            @if(optional($remittance->clientInvoice)->date_range_start && optional($remittance->clientInvoice)->date_range_end)
                                                <small class="text-muted">
                                                    {{ \Carbon\Carbon::parse($remittance->clientInvoice->date_range_start)->format('M d') }} - 
                                                    {{ \Carbon\Carbon::parse($remittance->clientInvoice->date_range_end)->format('M d, Y') }}
                                                </small>
                                            @endif
                                            @php $jobCount = optional(optional($remittance->clientInvoice)->items)->count(); @endphp
                                            @if($jobCount)
                                                <br><small class="text-muted">Includes {{ $jobCount }} job{{ $jobCount === 1 ? '' : 's' }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $invoice = $remittance->clientInvoice;
                                                $proofs = optional($invoice->paymentProofs)->count();
                                                $receipts = optional($invoice->receipts)->count();
                                            @endphp
                                            <div class="d-flex flex-column">
                                                <span class="badge bg-info mb-1">Payment Proofs: {{ $proofs }}</span>
                                                <span class="badge bg-primary">Receipts: {{ $receipts }}</span>
                                                <div class="mt-2">
                                                    <a href="{{ route('invoices.admin.client.show', optional($remittance->clientInvoice)->id) }}" class="btn btn-sm btn-outline-secondary">
                                                        View Invoice
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success">£{{ number_format($remittance->amount, 2) }}</span>
                                            @if($remittance->amount != $remittance->clientInvoice->total_amount)
                                                <br><small class="text-muted">of £{{ number_format($remittance->clientInvoice->total_amount, 2) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $statusColors = [
                                                    'pending' => 'warning',
                                                    'approved' => 'success',
                                                    'processed' => 'primary',
                                                    'rejected' => 'danger'
                                                ];
                                                $color = $statusColors[$remittance->status] ?? 'secondary';
                                            @endphp
                                            <span class="badge bg-{{ $color }}">{{ ucfirst($remittance->status) }}</span>
                                        </td>
                                        <td>
                                            @if($remittance->due_date)
                                                <div>{{ \Carbon\Carbon::parse($remittance->due_date)->format('M d, Y') }}</div>
                                                @php
                                                    $dueDate = \Carbon\Carbon::parse($remittance->due_date);
                                                    $now = \Carbon\Carbon::now();
                                                    $isOverdue = $dueDate->isPast() && $remittance->status !== 'processed';
                                                @endphp
                                                @if($isOverdue)
                                                    <small class="text-danger">
                                                        <i class="fas fa-exclamation-triangle"></i> Overdue
                                                    </small>
                                                @else
                                                    <small class="text-muted">{{ $dueDate->diffForHumans() }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted">Not set</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div>{{ $remittance->created_at->format('M d, Y') }}</div>
                                            <small class="text-muted">{{ $remittance->created_at->format('H:i') }}</small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('remittances.show', $remittance) }}" class="btn btn-sm btn-primary" title="View">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                
                                                @if($remittance->status === 'pending')
                                                    <button type="button" class="btn btn-sm btn-success js-update-remittance-status"
                                                            data-remittance-id="{{ $remittance->id }}" data-status="approved" title="Approve">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger js-update-remittance-status"
                                                            data-remittance-id="{{ $remittance->id }}" data-status="rejected" title="Reject">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                @endif
                                                
                                                @if($remittance->status === 'approved')
                                                    <button type="button" class="btn btn-sm btn-primary js-update-remittance-status"
                                                            data-remittance-id="{{ $remittance->id }}" data-status="processed" title="Mark as Processed">
                                                        <i class="fas fa-money-bill"></i> Process
                                                    </button>
                                                @endif
                                                
                                                <a href="{{ route('remittances.download', $remittance) }}" class="btn btn-sm btn-secondary" 
                                                   target="_blank" title="Download PDF">
                                                    <i class="fas fa-download"></i> PDF
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                                <p>No remittances found matching your criteria.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if($remittances->hasPages())
                        <div class="d-flex justify-content-center mt-4">
                            {{ $remittances->appends(request()->query())->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Remittance Status</h5>
                <button type="button" class="btn- btn-close close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    @csrf
                    <input type="hidden" id="remittanceId" name="remittance_id">
                    <input type="hidden" id="newStatus" name="status">
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Add any notes about this status change..."></textarea>
                    </div>
                    
                    <div id="processedFields" style="display: none;">
                        <div class="mb-3">
                            <label for="processed_date" class="form-label">Processed Date</label>
                            <input type="date" class="form-control" id="processed_date" name="processed_date" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="mb-3">
                            <label for="reference_number" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                   placeholder="Transaction or reference number">
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span id="statusMessage"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmStatusUpdate()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Inline styles removed to avoid Vue template compilation warnings. Rely on Bootstrap utility classes. -->

<script>
function updateStatus(remittanceId, status) {
    document.getElementById('remittanceId').value = remittanceId;
    document.getElementById('newStatus').value = status;

    const messages = {
        approved: 'Are you sure you want to approve this remittance? This will allow it to be processed for payment.',
        rejected: 'Are you sure you want to reject this remittance? This action will require the remittance to be recreated.',
        processed: 'Are you sure you want to mark this remittance as processed? This indicates payment has been sent to the client.'
    };

    document.getElementById('statusMessage').textContent = messages[status];

    // Show additional fields for processed status
    const processedFields = document.getElementById('processedFields');
    processedFields.style.display = (status === 'processed') ? 'block' : 'none';

    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}

function confirmStatusUpdate() {
    const form = document.getElementById('statusForm');
    const formData = new FormData(form);
    const remittanceId = formData.get('remittance_id');
    const status = formData.get('status');
    const notes = formData.get('notes');
    const processedDate = formData.get('processed_date');
    const referenceNumber = formData.get('reference_number');

    const payload = { status, notes };
    if (status === 'processed') {
        payload.processed_date = processedDate;
        payload.reference_number = referenceNumber;
    }

    fetch(`/admin/remittances/${remittanceId}/status`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(payload)
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error updating status: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the status.');
        });

    const modal = bootstrap.Modal.getInstance(document.getElementById('statusModal'));
    modal.hide();
}

// Delegate click events to avoid inline onclick with Blade interpolation
document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.js-update-remittance-status');
    if (!btn) return;
    var id = btn.getAttribute('data-remittance-id');
    var status = btn.getAttribute('data-status');
    updateStatus(id, status);
});
</script>
@endsection