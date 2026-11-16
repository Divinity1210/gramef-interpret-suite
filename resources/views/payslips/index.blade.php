@extends('layouts.app')

@section('content')
<div class="container-fluid" style="max-width: 1400px;">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="mb-0">Payslips</h1>
                @if(auth()->check() && auth()->user()->hasRole('admin'))
                    <a href="{{ route('payslips.create') }}" class="btn btn-primary az-btn az-btn-primary">
                        <i class="fas fa-plus"></i> Create Payslip
                    </a>
                @endif
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form id="payslips-filters" method="GET" action="{{ route('payslips.index') }}">
                        <div class="row g-3 filters">
                            <div class="col-md-3">
                                <label for="status" class="form-label visually-hidden">Status</label>
                                <select name="status" id="status" class="form-select section__input input input--select">
                                    <option value="">Filter By Status...</option>
                                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                    <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="agent_id" class="form-label visually-hidden">Agent</label>
                                <select name="agent_id" id="agent_id" class="form-select section__input input input--select select2">
                                    <option value="">Filter By Agent...</option>
                                    @foreach($agents as $id => $name)
                                        <option value="{{ $id }}" {{ request('agent_id') == $id ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                @include('partials.filters.date-range', [
                                    'mode' => 'single',
                                    'formId' => 'payslips-filters',
                                    'inputId' => 'payslips-date',
                                    'paramName' => 'date',
                                    'startName' => 'date_from',
                                    'endName' => 'date_to',
                                    'startId' => 'date_from',
                                    'endId' => 'date_to',
                                    'mirrorHidden' => true,
                                    'placeholder' => 'Filter By Date...'
                                ])
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary az-btn az-btn-primary me-2">Filter</button>
                                <a href="{{ route('payslips.index') }}" class="btn btn-secondary az-btn az-btn-secondary">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payslips Table -->
            @if($payslips->count() > 0)
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Payslip #</th>
                                        <th>Agent</th>
                                        <th>Invoice #</th>
                                        <th>Subtotal</th>
                                        <th>Adjustments</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payslips as $payslip)
                                        <tr id="payslip-row-{{ $payslip->id }}" data-payslip-id="{{ $payslip->id }}">
                                            <td>
                                                <a href="{{ route('payslips.show', $payslip) }}" class="text-decoration-none">
                                                    {{ $payslip->payslip_number }}
                                                </a>
                                            </td>
                                            <td>{{ data_get($payslip, 'agent.user.full_name', 'Unknown') }}</td>
                                            <td>
                                                @if($payslip->invoice)
                                                    {{ $payslip->invoice->invoice_number }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>£{{ number_format($payslip->subtotal, 2) }}</td>
                                            <td>
                                                @if($payslip->adjustments_total != 0)
                                                    <span class="{{ $payslip->adjustments_total > 0 ? 'text-success' : 'text-danger' }}">
                                                        £{{ number_format($payslip->adjustments_total, 2) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">£0.00</span>
                                                @endif
                                            </td>
                                            <td><strong>£{{ number_format($payslip->total, 2) }}</strong></td>
                                            <td>
                                                @php
                                                    $invLabel = strtolower(optional($payslip->invoice)->status_label ?? optional($payslip->invoice)->status ?? '');
                                                    $displayStatus = strtolower($payslip->status);
                                                    if ($invLabel === 'paid') { $displayStatus = 'paid'; }
                                                @endphp
                                                @switch($displayStatus)
                                                    @case('pending')
                                                        <span class="badge bg-warning">Pending</span>
                                                        @break
                                                    @case('approved')
                                                        <span class="badge bg-success">Approved</span>
                                                        @break
                                                    @case('paid')
                                                        <span class="badge bg-primary">Paid</span>
                                                        @break
                                                    @default
                                                        <span class="badge bg-secondary">{{ ucfirst($displayStatus) }}</span>
                                                @endswitch
                                            </td>
                                            <td>{{ $payslip->created_at->format('d/m/Y') }}</td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2 justify-content-start">
                                                    {{-- View Button (Always visible) --}}
                                                    <a href="{{ route('payslips.show', $payslip) }}" 
                                                       class="btn btn-light az-btn az-btn-secondary btn-sm flex-fill text-nowrap" title="View">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>

                                                    @if(auth()->check() && auth()->user()->hasRole('admin'))
                                                        {{-- Primary Action Button --}}
                                                        @if($payslip->status === 'pending')
                                                            <form method="POST" action="{{ route('payslips.approve', $payslip) }}" 
                                                                   class="approve-form d-inline flex-fill" 
                                                                   onsubmit="return confirm('Are you sure you want to approve this payslip?')">
                                                                @csrf
                                                                <button type="submit" class="btn btn-success az-btn btn-sm w-100 text-nowrap text-white" title="Approve">
                                                                    <i class="fas fa-check"></i> Approve
                                                                </button>
                                                            </form>
                                                        @elseif($payslip->status === 'approved' && strtolower(optional($payslip->invoice)->status_label ?? optional($payslip->invoice)->status ?? '') !== 'paid' && empty($payslip->paid_at))
                                                            <form method="POST" action="{{ route('payslips.mark-paid', $payslip) }}" class="d-inline flex-fill" onsubmit="return confirm('Mark this payslip as paid?')">
                                                                @csrf
                                                                <button type="submit" class="btn btn-primary az-btn az-btn-primary btn-sm w-100 text-nowrap text-white" title="Mark Paid">
                                                                    <i class="fas fa-check-circle"></i> Mark Paid
                                                                </button>
                                                            </form>
                                                        @endif

                                                        {{-- Edit for Pending --}}
                                                        @if($payslip->status === 'pending')
                                                            <a href="{{ route('payslips.edit', $payslip) }}" 
                                                               class="btn btn-light az-btn az-btn-secondary btn-sm flex-fill text-nowrap" title="Edit">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                        @endif

                                                        {{-- Delete for Pending --}}
                                                        @if($payslip->status === 'pending')
                                                            <form method="POST" action="{{ route('payslips.destroy', $payslip) }}" class="d-inline flex-fill" onsubmit="return confirm('Delete this payslip? This will detach items and cannot be undone.')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-light az-btn az-btn-secondary btn-sm w-100 text-nowrap" title="Delete">
                                                                    <i class="fas fa-trash text-danger"></i> Delete
                                                                </button>
                                                            </form>
                                                        @endif

                                                        {{-- Recall for Approved --}}
                                                        @if($payslip->status === 'approved' && strtolower(optional($payslip->invoice)->status_label ?? optional($payslip->invoice)->status ?? '') !== 'paid' && empty($payslip->paid_at))
                                                            <form method="POST" action="{{ route('admin.payslips.recall', $payslip) }}" class="d-inline flex-fill" onsubmit="return confirm('Recall this payslip to pending so it can be edited?')">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" class="btn btn-light az-btn az-btn-secondary btn-sm w-100 text-nowrap" title="Recall">
                                                                    <i class="fas fa-undo"></i> Recall
                                                                </button>
                                                            </form>
                                                        @endif

                                                        {{-- PDF (Always available if created) --}}
                                                        <a href="{{ route('payslips.pdf', $payslip) }}" 
                                                           class="btn btn-light az-btn az-btn-secondary btn-sm flex-fill text-nowrap" title="Download PDF">
                                                            <i class="fas fa-file-pdf"></i> PDF
                                                        </a>
                                                    @else
                                                        {{-- Non-admin PDF button fallback --}}
                                                        <a href="{{ route('payslips.pdf', $payslip) }}" 
                                                           class="btn btn-light az-btn az-btn-secondary btn-sm flex-fill text-nowrap" title="Download PDF">
                                                            <i class="fas fa-file-pdf"></i> PDF
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        @if($payslips->hasPages())
                            <div class="d-flex justify-content-center">
                                {{ $payslips->appends(request()->query())->links('custom.pagination') }}
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body text-center">
                        <div class="py-4">
                            <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No payslips found</h5>
                            <p class="text-muted">Create your first payslip to get started.</p>
                            @if(auth()->check() && auth()->user()->hasRole('admin'))
                                <a href="{{ route('payslips.create') }}" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Create Payslip
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
<script>
// Highlight the newly created payslip row after redirect
document.addEventListener('DOMContentLoaded', function () {
    try {
        const id = sessionStorage.getItem('lastPayslipId');
        const total = sessionStorage.getItem('lastPayslipTotal');
        if (id) {
            const row = document.querySelector('#payslip-row-' + id);
            if (row) {
                // Apply a temporary highlight and scroll into view
                row.classList.add('table-success');
                row.style.transition = 'background-color 0.6s ease';
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(function () {
                    row.classList.remove('table-success');
                }, 4500);
            }
            // Clear stored context
            sessionStorage.removeItem('lastPayslipId');
            sessionStorage.removeItem('lastPayslipTotal');
        }
    } catch (e) {}
});

// Enhance UX: show quick loading state on Approve button submissions
(function(){
    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('form.approve-form').forEach(function(form){
            form.addEventListener('submit', function(){
                var btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Approving...';
                }
            });
        });
    });
})();
</script>
@push('scripts')
<script>
    (function(){
        var init = function(){
            var el = $('#agent_id');
            if (!el.length) return;
            if (typeof $.fn.select2 !== 'function') return;
            try {
                el.select2({ width: '100%', placeholder: 'Filter By Agent...', allowClear: true });
            } catch(e) {}
        };
        if (typeof $.fn.select2 !== 'function') {
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js';
            s.async = true;
            s.onload = init;
            document.head.appendChild(s);
        } else {
            init();
        }
    })();
</script>
@endpush
@endsection
