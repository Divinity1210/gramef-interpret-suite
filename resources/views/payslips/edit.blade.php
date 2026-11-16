@extends('layouts.app')

@section('content')
<div class="container-fluid" style="max-width: 1400px;">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="mb-0">Edit Payslip #{{ $payslip->payslip_number }}</h1>
                <div>
                    <a href="{{ route('payslips.show', $payslip) }}" class="btn btn-secondary me-2">
                        <i class="fas fa-eye"></i> View Payslip
                    </a>
                    <a href="{{ route('payslips.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Payslips
                    </a>
                </div>
            </div>

            @if($payslip->status !== 'pending')
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    This payslip has been {{ $payslip->status }} and cannot be edited.
                </div>
            @else
                <form method="POST" action="{{ route('payslips.update', $payslip) }}" id="payslipForm">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Agent Information -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Agent Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="agent_id" class="form-label">Agent <span class="text-danger">*</span></label>
                                            <select name="agent_id" id="agent_id" class="form-select @error('agent_id') is-invalid @enderror" required>
                                                <option value="">Select Agent</option>
                                                @foreach($agents as $id => $name)
                                                    <option value="{{ $id }}" 
                                                        {{ (old('agent_id', $payslip->agent_id) == $id) ? 'selected' : '' }}>
                                                        {{ $name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('agent_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label for="agent_invoice_id" class="form-label">Related Invoice</label>
                                            <input type="hidden" name="agent_invoice_id" value="{{ old('agent_invoice_id', $payslip->agent_invoice_id) }}">
                                            <input type="text" class="form-control" readonly 
                                                   value="{{ $payslip->invoice ? $payslip->invoice->invoice_number : 'None' }}">
                                            <small class="form-text text-muted">
                                                @if($payslip->invoice)
                                                    Invoice period: {{ $payslip->invoice->date_from }} to {{ $payslip->invoice->date_to }}
                                                @else
                                                    No related invoice
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Payslip Period</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="period_start" class="form-label">Start Date</label>
                                            <input type="date" name="period_start" id="period_start" class="form-control @error('period_start') is-invalid @enderror" value="{{ old('period_start', optional($payslip->period_start)->format('Y-m-d')) }}">
                                            @error('period_start')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label for="period_end" class="form-label">End Date</label>
                                            <input type="date" name="period_end" id="period_end" class="form-control @error('period_end') is-invalid @enderror" value="{{ old('period_end', optional($payslip->period_end)->format('Y-m-d')) }}">
                                            @error('period_end')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payslip Items -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Payslip Items</h5>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="addItem()">
                                        <i class="fas fa-plus"></i> Add Item
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div id="items-container" data-initial-count="{{ count($payslip->items ?? []) }}">
                                        @foreach($payslip->items as $index => $item)
                                            @php
                                                $descVal = old('items.'.$index.'.description', $item['description'] ?? ($item['job_description'] ?? ''));
                                                $amountVal = old('items.'.$index.'.amount', $item['amount'] ?? ($item['total_amount'] ?? (($item['base_amount'] ?? 0) + ($item['expenses_amount'] ?? 0) + ($item['surcharge'] ?? 0) + ($item['other_charges'] ?? 0))));
                                                $adjustVal = old('items.'.$index.'.adjustment', $item['adjustment'] ?? 0);
                                            @endphp
                                            <div class="item-row mb-3" data-index="{{ $index }}">
                                                <div class="row">
                                                    <div class="col-md-5">
                                                        <label class="form-label">Description</label>
                                                        <input type="text" name="items[{{ $index }}][description]" 
                                                               class="form-control" 
                                                               value="{{ $descVal }}" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Amount</label>
                                                        <input type="number" name="items[{{ $index }}][amount]" 
                                                               class="form-control item-amount" 
                                                               value="{{ $amountVal }}" 
                                                               step="0.01" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Adjustment</label>
                                                        <input type="number" name="items[{{ $index }}][adjustment]" 
                                                               class="form-control item-adjustment" 
                                                               value="{{ $adjustVal }}" 
                                                               step="0.01">
                                                    </div>
                                                    <div class="col-md-1 d-flex align-items-end">
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="removeItem(this)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <!-- Bank Details -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Bank Details (Optional)</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="bank_name" class="form-label">Bank Name</label>
                                            <input type="text" name="bank_details[bank_name]" id="bank_name" 
                                                   class="form-control" 
                                                   value="{{ old('bank_details.bank_name', $payslip->bank_snapshot['bank_name'] ?? '') }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="account_name" class="form-label">Account Name</label>
                                            <input type="text" name="bank_details[account_name]" id="account_name" 
                                                   class="form-control" 
                                                   value="{{ old('bank_details.account_name', $payslip->bank_snapshot['account_name'] ?? '') }}">
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <label for="account_number" class="form-label">Account Number</label>
                                            <input type="text" name="bank_details[account_number]" id="account_number" 
                                                   class="form-control" 
                                                   value="{{ old('bank_details.account_number', $payslip->bank_snapshot['account_number'] ?? '') }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="sort_code" class="form-label">Sort Code</label>
                                            <input type="text" name="bank_details[sort_code]" id="sort_code" 
                                                   class="form-control" 
                                                   value="{{ old('bank_details.sort_code', $payslip->bank_snapshot['sort_code'] ?? '') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <!-- Totals Summary -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-2">
                                        <div class="col-6">Subtotal:</div>
                                        <div class="col-6 text-end">
                                            <strong>£<span id="subtotal-display">{{ number_format($payslip->subtotal, 2) }}</span></strong>
                                            <input type="hidden" name="subtotal" id="subtotal" value="{{ $payslip->subtotal }}">
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-6">Adjustments:</div>
                                        <div class="col-6 text-end">
                                            <span id="adjustments-display" class="{{ $payslip->adjustments_total >= 0 ? 'text-success' : 'text-danger' }}">
                                                £{{ number_format($payslip->adjustments_total, 2) }}
                                            </span>
                                            <input type="hidden" name="adjustments_total" id="adjustments_total" value="{{ $payslip->adjustments_total }}">
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-6"><strong>Total:</strong></div>
                                        <div class="col-6 text-end">
                                            <strong class="text-primary">£<span id="total-display">{{ number_format($payslip->total, 2) }}</span></strong>
                                            <input type="hidden" name="total" id="total" value="{{ $payslip->total }}">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="card">
                                <div class="card-body">
                                    <button type="submit" class="btn btn-primary w-100 mb-2">
                                        <i class="fas fa-save"></i> Update Payslip
                                    </button>
                                    <a href="{{ route('payslips.show', $payslip) }}" class="btn btn-secondary w-100">
                                        Cancel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>

<script>
let itemIndex = Number(document.getElementById('items-container').dataset.initialCount || '0');

function addItem() {
    const container = document.getElementById('items-container');
    const itemHtml = `
        <div class="item-row mb-3" data-index="${itemIndex}">
            <div class="row">
                <div class="col-md-5">
                    <label class="form-label">Description</label>
                    <input type="text" name="items[${itemIndex}][description]" 
                           class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Amount</label>
                    <input type="number" name="items[${itemIndex}][amount]" 
                           class="form-control item-amount" 
                           step="0.01" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Adjustment</label>
                    <input type="number" name="items[${itemIndex}][adjustment]" 
                           class="form-control item-adjustment" 
                           value="0" step="0.01">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-sm btn-danger" 
                            onclick="removeItem(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', itemHtml);
    itemIndex++;
    calculateTotals();
}

function removeItem(button) {
    const itemRow = button.closest('.item-row');
    if (document.querySelectorAll('.item-row').length > 1) {
        itemRow.remove();
        calculateTotals();
    } else {
        alert('At least one item is required.');
    }
}

function calculateTotals() {
    let subtotal = 0;
    let adjustmentsTotal = 0;

    document.querySelectorAll('.item-amount').forEach(input => {
        subtotal += parseFloat(input.value) || 0;
    });

    document.querySelectorAll('.item-adjustment').forEach(input => {
        adjustmentsTotal += parseFloat(input.value) || 0;
    });

    const total = subtotal + adjustmentsTotal;

    document.getElementById('subtotal-display').textContent = subtotal.toFixed(2);
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    
    document.getElementById('adjustments-display').textContent = '£' + adjustmentsTotal.toFixed(2);
    document.getElementById('adjustments-display').className = adjustmentsTotal >= 0 ? 'text-success' : 'text-danger';
    document.getElementById('adjustments_total').value = adjustmentsTotal.toFixed(2);
    
    document.getElementById('total-display').textContent = total.toFixed(2);
    document.getElementById('total').value = total.toFixed(2);
}

// Event listeners for real-time calculation
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('item-amount') || e.target.classList.contains('item-adjustment')) {
        calculateTotals();
    }
});

// Initial calculation
document.addEventListener('DOMContentLoaded', function() {
    calculateTotals();
    // Add quick loading state on form submission
    var form = document.getElementById('payslipForm');
    if (form) {
        form.addEventListener('submit', function(){
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Updating...';
            }
        });
    }
});
</script>
@endsection
