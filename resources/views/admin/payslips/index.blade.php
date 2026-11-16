@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Payslips Management</h4>
                    <div class="btn-group">
                        <a href="{{ route('payslips.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Payslip
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
                            <form method="GET" action="{{ route('admin.payslips.index') }}" class="row g-3" id="adminPayslipsFilter">
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="">All Statuses</option>
                                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="agent_id" class="form-label">Agent</label>
                                    <select name="agent_id" id="agent_id" class="form-select">
                                        <option value="">All Agents</option>
                                        @foreach($agents as $agent)
                                            <option value="{{ $agent->id }}" {{ request('agent_id') == $agent->id ? 'selected' : '' }}>
                                                {{ data_get($agent, 'user.full_name', 'Unknown') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    @include('partials.filters.date-range', [
                                        'mode' => 'single',
                                        'formId' => 'adminPayslipsFilter',
                                        'inputId' => 'adminPayslipsDateFilter',
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
                                    <a href="{{ route('admin.payslips.index') }}" class="btn btn-secondary">Clear</a>
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
                                            <h6 class="card-title">Paid</h6>
                                            <h3 class="mb-0">{{ $stats['paid'] ?? 0 }}</h3>
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

                    <!-- Payslips Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Payslip #</th>
                                    <th>Agent</th>
                                    <th>Period</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($payslips as $payslip)
                                    <tr>
                                        <td>
                                            <strong>{{ $payslip->payslip_number }}</strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-2">
                                                    <div class="avatar-title bg-primary rounded-circle">
                                                        {{ substr(data_get($payslip, 'agent.user.full_name', 'U'), 0, 1) }}
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="fw-bold">{{ data_get($payslip, 'agent.user.full_name', 'Unknown') }}</div>
                                                    <small class="text-muted">{{ data_get($payslip, 'agent.user.email', '') }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                @if($payslip->invoice)
                                                    <strong>{{ $payslip->invoice->date_from ? \Carbon\Carbon::parse($payslip->invoice->date_from)->format('M d') : '—' }} - 
                                                    {{ $payslip->invoice->date_to ? \Carbon\Carbon::parse($payslip->invoice->date_to)->format('M d, Y') : '' }}</strong>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success">£{{ number_format($payslip->total, 2) }}</span>
                                        </td>
                                        <td>
                                            @php
                                                $invLabel = strtolower(optional($payslip->invoice)->status_label ?? optional($payslip->invoice)->status ?? '');
                                                $displayStatus = strtolower($payslip->status);
                                                if ($invLabel === 'paid') { $displayStatus = 'paid'; }
                                                $statusColors = [
                                                    'pending' => 'warning',
                                                    'approved' => 'success',
                                                    'paid' => 'primary',
                                                    'rejected' => 'danger'
                                                ];
                                                $color = $statusColors[$displayStatus] ?? 'secondary';
                                            @endphp
                                            <span class="badge bg-{{ $color }}">{{ ucfirst($displayStatus) }}</span>
                                        </td>
                                        <td>
                                            <div>{{ $payslip->created_at->format('M d, Y') }}</div>
                                            <small class="text-muted">{{ $payslip->created_at->format('H:i') }}</small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('admin.payslips.show', $payslip) }}" class="btn btn-sm btn-primary" title="View">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                
                                                @if($payslip->status === 'pending')
                                                    <button type="button" class="btn btn-sm btn-success js-update-status"
                                                            data-agent-id="{{ $payslip->agent_id }}"
                                                            data-payslip-id="{{ $payslip->id }}"
                                                            data-status="approved" title="Approve">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger js-update-status"
                                                            data-payslip-id="{{ $payslip->id }}"
                                                            data-status="rejected" title="Reject">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                @endif
                                                
                                                @php
                                                    $invLabel = strtolower(optional($payslip->invoice)->status_label ?? optional($payslip->invoice)->status ?? '');
                                                    $canRecall = ($payslip->status === 'approved') && $invLabel !== 'paid' && empty($payslip->paid_at);
                                                    $canPay = ($payslip->status === 'approved') && $invLabel !== 'paid' && empty($payslip->paid_at);
                                                @endphp
                                                @if($canPay)
                                                    <button type="button" class="btn btn-sm btn-primary js-update-status"
                                                            data-agent-id="{{ $payslip->agent_id }}"
                                                            data-payslip-id="{{ $payslip->id }}"
                                                            data-status="paid" title="Mark as Paid">
                                                        <i class="fas fa-money-bill"></i> Pay
                                                    </button>
                                                @endif
                                                
                                                <a href="{{ route('payslips.pdf', $payslip) }}" class="btn btn-sm btn-secondary" 
                                                   target="_blank" title="Download PDF">
                                                    <i class="fas fa-download"></i> PDF
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                                <p>No payslips found matching your criteria.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if($payslips->hasPages())
                        <div class="d-flex justify-content-center mt-4">
                            {{ $payslips->appends(request()->query())->links() }}
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
                <h5 class="modal-title">Update Payslip Status</h5>
                <button type="button" class="btn- btn-close close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    @csrf
                    <input type="hidden" id="payslipId" name="payslip_id">
                    <input type="hidden" id="newStatus" name="status">
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Add any notes about this status change..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span id="statusMessage"></span>
                    </div>

                    <div id="bankDetailsContainer" class="mt-3" style="display:none;">
                        <h6>Agent Banking Details</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2"><strong>Bank Name:</strong> <span id="bankName"></span></div>
                                <div class="mb-2"><strong>Account Holder:</strong> <span id="accountHolder"></span></div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2"><strong>Account Number:</strong> <span id="accountNumber"></span></div>
                                <div class="mb-2"><strong>Sort Code:</strong> <span id="sortCode"></span></div>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-2">
                            <i class="fas fa-shield-alt"></i>
                            Banking details are shown for payment processing only and will be captured as a masked snapshot in the payslip.
                        </div>
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

<style>
.avatar-sm {
    width: 40px;
    height: 40px;
}

.avatar-title {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: white;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.table th {
    border-top: none;
    font-weight: 600;
}

.btn-group .btn {
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}
</style>

<script>
function updateStatus(e, payslipId, status) {
    document.getElementById('payslipId').value = payslipId;
    document.getElementById('newStatus').value = status;

    // Reset bank details section
    document.getElementById('bankDetailsContainer').style.display = 'none';
    document.getElementById('bankName').textContent = '';
    document.getElementById('accountHolder').textContent = '';
    document.getElementById('accountNumber').textContent = '';
    document.getElementById('sortCode').textContent = '';
    
    const messages = {
        'approved': 'Are you sure you want to approve this payslip? This will allow it to be processed for payment.',
        'rejected': 'Are you sure you want to reject this payslip? This action will require the payslip to be recreated.',
        'paid': 'Are you sure you want to mark this payslip as paid? This indicates payment has been processed.'
    };
    
    document.getElementById('statusMessage').textContent = messages[status];

    // For approved/paid, fetch and show agent bank details for admin
    if (status === 'approved' || status === 'paid') {
        const agentId = e && e.currentTarget ? e.currentTarget.dataset.agentId : null;
        if (agentId) {
            fetch(`/api/admin/agents/${agentId}/bank-account`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.exists) {
                    document.getElementById('bankName').textContent = data.bank_name || '—';
                    document.getElementById('accountHolder').textContent = data.account_holder || '—';
                    // Show masked if provided, else full
                    const accountNumber = data.account_number_masked || data.account_number || '—';
                    const sortCode = data.sort_code_masked || data.sort_code || '—';
                    document.getElementById('accountNumber').textContent = accountNumber;
                    document.getElementById('sortCode').textContent = sortCode;
                    document.getElementById('bankDetailsContainer').style.display = 'block';
                } else {
                    document.getElementById('statusMessage').textContent += ' (No banking details found for this agent.)';
                }
            })
            .catch(() => {
                document.getElementById('statusMessage').textContent += ' (Failed to load banking details.)';
            });
        }
    }
    
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}

function confirmStatusUpdate() {
    const form = document.getElementById('statusForm');
    const formData = new FormData(form);
    const payslipId = formData.get('payslip_id');
    const status = formData.get('status');
    const notes = formData.get('notes');
    
    fetch(`/admin/payslips/${payslipId}/status`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            status: status,
            notes: notes
        })
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
    var btn = ev.target.closest('.js-update-status');
    if (!btn) return;
    var payslipId = btn.getAttribute('data-payslip-id');
    var status = btn.getAttribute('data-status');
    // Pass a synthetic event with currentTarget for dataset access
    updateStatus({ currentTarget: btn }, payslipId, status);
});
</script>
@endsection
