@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Edit Remittance - {{ $remittance->remittance_number }}</h4>
                    <div>
                        <a href="{{ route('remittances.show', $remittance) }}" class="btn btn-info">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        <a href="{{ route('remittances.index') }}" class="btn btn-secondary ml-2">
                            <i class="fas fa-arrow-left"></i> Back to Remittances
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if($remittance->status !== 'pending')
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            This remittance has been {{ $remittance->status }} and cannot be edited.
                        </div>
                    @else
                        <form action="{{ route('remittances.update', $remittance) }}" method="POST" enctype="multipart/form-data" id="remittanceForm">
                            @csrf
                            @method('PUT')
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="client_invoice_id">Client Invoice <span class="text-danger">*</span></label>
                                        <select name="client_invoice_id" id="client_invoice_id" class="form-control @error('client_invoice_id') is-invalid @enderror" required>
                                            <option value="">Select Client Invoice</option>
                                            @foreach($clientInvoices as $invoice)
                                                <option value="{{ $invoice->id }}" 
                                                        data-amount="{{ $invoice->total }}"
                                                        {{ (old('client_invoice_id', $remittance->client_invoice_id) == $invoice->id) ? 'selected' : '' }}>
                                                    {{ $invoice->invoice_number }} - {{ $invoice->client->name ?? 'N/A' }} (£{{ number_format($invoice->total, 2) }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('client_invoice_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount">Amount <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">£</span>
                                            </div>
                                            <input type="number" 
                                                   name="amount" 
                                                   id="amount" 
                                                   class="form-control @error('amount') is-invalid @enderror" 
                                                   step="0.01" 
                                                   min="0" 
                                                   value="{{ old('amount', $remittance->amount) }}" 
                                                   required>
                                        </div>
                                        @error('amount')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="remittance_file">Replace Remittance File</label>
                                        <div class="custom-file">
                                            <input type="file" 
                                                   name="remittance_file" 
                                                   id="remittance_file" 
                                                   class="custom-file-input @error('remittance_file') is-invalid @enderror" 
                                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                            <label class="custom-file-label" for="remittance_file">Choose new file...</label>
                                        </div>
                                        <small class="form-text text-muted">
                                            Leave empty to keep current file. Accepted formats: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG (Max: 10MB)
                                        </small>
                                        @error('remittance_file')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        
                                        @if($remittance->file_path)
                                            @php $remDisk = env('REMITTANCES_DISK') ?: (app()->environment('production') ? 's3' : 'public'); $remExists = \Illuminate\Support\Facades\Storage::disk($remDisk)->exists($remittance->file_path); @endphp
                                            <div class="mt-2">
                                                <strong>Current File:</strong> 
                                                <span class="text-info">{{ $remittance->original_filename ?? basename($remittance->file_path) }}</span>
                                                @if($remExists)
                                                    <a href="{{ route('remittances.download', $remittance) }}" class="btn btn-sm btn-outline-info ml-2">
                                                        <i class="fas fa-download"></i> Download Current
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="notes">Notes</label>
                                        <textarea name="notes" 
                                                  id="notes" 
                                                  class="form-control @error('notes') is-invalid @enderror" 
                                                  rows="4" 
                                                  placeholder="Add any additional notes about this remittance...">{{ old('notes', $remittance->notes) }}</textarea>
                                        @error('notes')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Update Summary</h6>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <strong>Remittance Number:</strong> {{ $remittance->remittance_number }}
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>Invoice:</strong> <span id="selectedInvoice">{{ $remittance->clientInvoice->invoice_number ?? 'Not selected' }}</span>
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>Amount:</strong> <span id="displayAmount">£{{ number_format($remittance->amount, 2) }}</span>
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>File:</strong> <span id="selectedFile">{{ $remittance->original_filename ?? 'Current file' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Remittance
                                </button>
                                <a href="{{ route('remittances.show', $remittance) }}" class="btn btn-secondary ml-2">Cancel</a>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const clientInvoiceSelect = document.getElementById('client_invoice_id');
    const amountInput = document.getElementById('amount');
    const fileInput = document.getElementById('remittance_file');
    const selectedInvoice = document.getElementById('selectedInvoice');
    const displayAmount = document.getElementById('displayAmount');
    const selectedFile = document.getElementById('selectedFile');

    // Update invoice selection
    if (clientInvoiceSelect) {
        clientInvoiceSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                selectedInvoice.textContent = selectedOption.text.split(' - ')[0]; // Get just the invoice number
                const invoiceAmount = selectedOption.getAttribute('data-amount');
                if (invoiceAmount && !amountInput.value) {
                    amountInput.value = parseFloat(invoiceAmount).toFixed(2);
                    updateAmountDisplay();
                }
            } else {
                selectedInvoice.textContent = 'Not selected';
            }
        });
    }

    // Update amount display
    if (amountInput) {
        amountInput.addEventListener('input', updateAmountDisplay);
    }

    function updateAmountDisplay() {
        const amount = parseFloat(amountInput.value) || 0;
        displayAmount.textContent = '£' + amount.toFixed(2);
    }

    // Update file selection
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const fileName = this.files[0] ? this.files[0].name : '{{ $remittance->original_filename ?? "Current file" }}';
            selectedFile.textContent = fileName;
            
            // Update the custom file input label
            const label = document.querySelector('.custom-file-label');
            if (label) {
                label.textContent = this.files[0] ? fileName : 'Choose new file...';
            }
        });
    }

    // Form validation
    const form = document.getElementById('remittanceForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!clientInvoiceSelect.value) {
                e.preventDefault();
                alert('Please select a client invoice.');
                return false;
            }
            
            if (!amountInput.value || parseFloat(amountInput.value) <= 0) {
                e.preventDefault();
                alert('Please enter a valid amount.');
                return false;
            }
            
            // Check file size if new file is selected (10MB limit)
            if (fileInput.files[0] && fileInput.files[0].size > 10 * 1024 * 1024) {
                e.preventDefault();
                alert('File size must be less than 10MB.');
                return false;
            }
        });
    }

    // Initialize display
    updateAmountDisplay();
});
</script>
@endsection
