@extends('layouts.app')

@section('content')
<div class="container-fluid" style="max-width: 1400px;">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="mb-0">Payslip #{{ $payslip->payslip_number }}</h1>
                <div>
                    <a href="{{ route('payslips.index') }}" class="btn btn-outline-secondary az-btn az-btn-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Back to Payslips
                    </a>
                    @if($payslip->status === 'pending' && auth()->check() && auth()->user()->hasRole('admin'))
                        <form method="POST" action="{{ route('payslips.approve', $payslip) }}" class="approve-form d-inline">
                            @csrf
                            <!-- switched to POST to match route definition -->
                            <button type="submit" class="btn btn-success az-btn me-2" 
                                    onclick="return confirm('Are you sure you want to approve this payslip?')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </form>
                        <form method="POST" action="{{ route('payslips.destroy', $payslip) }}" class="d-inline" onsubmit="return confirm('Delete this payslip? This will detach items and cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger az-btn az-btn-outline me-2">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    @endif
                    @php
                        $invLabel = strtolower(optional($payslip->invoice)->status_label ?? optional($payslip->invoice)->status ?? '');
                        $displayStatus = strtolower($payslip->status);
                        if ($invLabel === 'paid') { $displayStatus = 'paid'; }
                    @endphp
                    @if($displayStatus === 'approved')
                        <a href="{{ route('payslips.pdf', $payslip) }}" class="btn btn-outline-primary az-btn az-btn-outline me-2" target="_blank">
                            <i class="fas fa-file-pdf"></i> View PDF
                        </a>
                        @if(auth()->check() && auth()->user()->hasRole('admin') && empty($payslip->paid_at))
                            <form method="POST" action="{{ route('payslips.mark-paid', $payslip) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-primary az-btn az-btn-primary me-2" onclick="return confirm('Mark this payslip as paid?')">
                                    <i class="fas fa-check-circle"></i> Mark Paid
                                </button>
                            </form>
                        @endif
                        @if(auth()->check() && auth()->user()->hasRole('admin') && empty($payslip->paid_at) && $invLabel !== 'paid')
                            <form method="POST" action="{{ route('admin.payslips.recall', $payslip) }}" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-outline-warning az-btn az-btn-outline me-2" onclick="return confirm('Recall this payslip to pending so it can be edited?')">
                                    <i class="fas fa-undo"></i> Recall to Edit
                                </button>
                            </form>
                        @endif
                    @endif
                    @if($payslip->status === 'pending' && auth()->check() && auth()->user()->hasRole('admin'))
                        <a href="{{ route('payslips.edit', $payslip) }}" class="btn btn-outline-secondary az-btn az-btn-secondary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    @endif
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Payslip Details -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Payslip Information</h5>
                            @php
                                $invLabel = strtolower(optional($payslip->invoice)->status_label ?? optional($payslip->invoice)->status ?? '');
                                $displayStatus = strtolower($payslip->status);
                                if ($invLabel === 'paid') { $displayStatus = 'paid'; }
                            @endphp
                            <span class="badge badge-{{ $displayStatus === 'approved' ? 'success' : ($displayStatus === 'pending' ? 'warning' : ($displayStatus === 'paid' ? 'primary' : 'secondary')) }}">
                                {{ ucfirst($displayStatus) }}
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Agent:</strong> {{ data_get($payslip, 'agent.user.full_name', 'Unknown') }}</p>
                                    <p><strong>Email:</strong> {{ data_get($payslip, 'agent.user.email', 'N/A') }}</p>
                                    @if($payslip->invoice)
                                        <p><strong>Related Invoice:</strong> 
                                            <a href="{{ route('invoices.admin.agent.show', $payslip->invoice->id) }}">
                                                {{ $payslip->invoice->invoice_number }}
                                            </a>
                                        </p>
                                        <p><strong>Invoice Period:</strong> 
                                            {{ $payslip->invoice->date_from ? \Carbon\Carbon::parse($payslip->invoice->date_from)->format('d/m/Y') : '' }}
                                            to
                                            {{ $payslip->invoice->date_to ? \Carbon\Carbon::parse($payslip->invoice->date_to)->format('d/m/Y') : '' }}
                                        </p>
                                    @endif
                                    @if($payslip->period_start || $payslip->period_end)
                                        <p><strong>Payslip Period:</strong>
                                            {{ $payslip->period_start ? \Carbon\Carbon::parse($payslip->period_start)->format('d/m/Y') : '' }}
                                            to
                                            {{ $payslip->period_end ? \Carbon\Carbon::parse($payslip->period_end)->format('d/m/Y') : '' }}
                                        </p>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Created:</strong> {{ $payslip->created_at->format('d/m/Y H:i') }}</p>
                                    @if($payslip->approved_at)
                                        <p><strong>Approved:</strong> {{ $payslip->approved_at->format('d/m/Y H:i') }}</p>
                                        <p><strong>Approved By:</strong> {{ optional($payslip->approvedBy)->full_name ?? 'N/A' }}</p>
                                    @endif
                                    @if($payslip->pdf_path)
                                        <p><strong>PDF Generated:</strong> 
                                            <i class="fas fa-check text-success"></i> Available
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payslip Items -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Payslip Items</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Language</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Documents</th>
                                            <th class="text-end">Amount</th>
                                            <th class="text-end">Adjustment</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(($itemsDisplay ?? []) as $row)
                                            @php
                                                $desc = $row['description'] ?? 'Item';
                                                $date = $row['date'] ?? null;
                                                $lang = $row['language'] ?? null;
                                                $type = $row['type'] ?? 'N/A';
                                                $amount = (float)($row['amount'] ?? 0);
                                                $adjustment = (float)($row['adjustment'] ?? 0);
                                            @endphp
                                            <tr>
                                                <td>{{ $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : 'N/A' }}</td>
                                                <td>{{ $lang ?? 'N/A' }}</td>
                                                <td>{{ $type }}</td>
                                                <td>{{ $desc }}</td>
                                                <td><span class="text-muted">—</span></td>
                                                <td class="text-end">£{{ number_format($amount, 2) }}</td>
                                                <td class="text-end">
                                                    @if($adjustment != 0)
                                                        <span class="{{ $adjustment > 0 ? 'text-success' : 'text-danger' }}">
                                                            £{{ number_format($adjustment, 2) }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">£0.00</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    <strong>£{{ number_format(($amount + $adjustment), 2) }}</strong>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Details (snapshot with fallback) -->
                    @if(!empty($bankDetails))
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Bank Details Snapshot</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    @foreach($bankDetails as $key => $value)
                                        @if(!empty($value))
                                            <div class="col-md-6 mb-2">
                                                <strong>{{ ucwords(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="col-lg-4">
                    <!-- Summary -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-6">Subtotal:</div>
                                <div class="col-6 text-end">
                                    <strong>£{{ number_format($payslip->subtotal, 2) }}</strong>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6">Adjustments:</div>
                                <div class="col-6 text-end">
                                    <span class="{{ $payslip->adjustments_total >= 0 ? 'text-success' : 'text-danger' }}">
                                        £{{ number_format($payslip->adjustments_total, 2) }}
                                    </span>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-6"><strong>Total:</strong></div>
                                <div class="col-6 text-end">
                                    <strong class="text-primary fs-5">£{{ number_format($payslip->total, 2) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status History -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Status History</h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-primary"></div>
                                    <div class="timeline-content">
                                        <h6 class="timeline-title">Created</h6>
                                        <p class="timeline-text">{{ $payslip->created_at->format('d/m/Y H:i') }}</p>
                                    </div>
                                </div>
                                @if($payslip->approved_at)
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-success"></div>
                                        <div class="timeline-content">
                                            <h6 class="timeline-title">Approved</h6>
                                            <p class="timeline-text">
                                                {{ $payslip->approved_at->format('d/m/Y H:i') }}
                                                <br><small class="text-muted">by {{ optional($payslip->approvedBy)->full_name ?? 'System' }}</small>
                                            </p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                            @if($payslip->paid_at)
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-primary"></div>
                                    <div class="timeline-content">
                                        <h6 class="timeline-title">Paid</h6>
                                        <p class="timeline-text">{{ $payslip->paid_at->format('d/m/Y H:i') }}</p>
                                    </div>
                                </div>
                            @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.timeline { position: relative; padding-left: 30px; }
.timeline::before { content: ''; position: absolute; left: 15px; top: 0; bottom: 0; width: 2px; background: #dee2e6; }
.timeline-item { position: relative; margin-bottom: 20px; }
.timeline-marker { position: absolute; left: -22px; top: 0; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; }
.timeline-content { padding-left: 10px; }
.timeline-title { margin-bottom: 5px; font-size: 14px; font-weight: 600; }
.timeline-text { margin-bottom: 0; font-size: 13px; color: #6c757d; }
.badge-warning { background-color: #ffc107; color: #212529; }
.badge-success { background-color: #198754; color: #fff; }
.badge-secondary { background-color: #6c757d; color: #fff; }
</style>
@endpush

@push('scripts')
<script>
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
@endpush
@endsection
