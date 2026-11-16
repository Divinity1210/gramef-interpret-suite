@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Upload New Remittance</h4>
                    <a href="{{ route('remittances.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Remittances
                    </a>
                </div>
                <div class="card-body">
                    <form action="{{ route('remittances.store') }}" method="POST" enctype="multipart/form-data" id="remittanceForm">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="client_invoice_id">Client Invoice <span class="text-danger">*</span></label>
                                    <select name="client_invoice_id" id="client_invoice_id" class="form-control @error('client_invoice_id') is-invalid @enderror" required>
                                        <option value="">Select Client Invoice</option>
                                        @foreach($clientInvoices as $invoice)
                                            <option value="{{ $invoice->id }}" 
                                                    data-amount="{{ $invoice->total }}"
                                                    {{ old('client_invoice_id') == $invoice->id ? 'selected' : '' }}>
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
                                               value="{{ old('amount') }}" 
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
                                    <label for="remittance_file">Remittance File <span class="text-danger">*</span></label>
                                    <div class="custom-file">
                                        <input type="file" 
                                               name="remittance_file" 
                                               id="remittance_file" 
                                               class="custom-file-input @error('remittance_file') is-invalid @enderror" 
                                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" 
                                               required>
                                        <label class="custom-file-label" for="remittance_file">Choose file...</label>
                                    </div>
                                    <small class="form-text text-muted">
                                        Accepted formats: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG (Max: 10MB)
                                    </small>
                                    @error('remittance_file')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
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
                                              placeholder="Add any additional notes about this remittance...">{{ old('notes') }}</textarea>
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
                                        <h6 class="card-title">Upload Summary</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <strong>Invoice:</strong> <span id="selectedInvoice">Not selected</span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Amount:</strong> <span id="displayAmount">£0.00</span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>File:</strong> <span id="selectedFile">No file selected</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload Remittance
                            </button>
                            <a href="{{ route('remittances.index') }}" class="btn btn-secondary ml-2">Cancel</a>
                        </div>
                    </form>
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
    clientInvoiceSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            selectedInvoice.textContent = selectedOption.text;
            const invoiceAmount = selectedOption.getAttribute('data-amount');
            if (invoiceAmount && !amountInput.value) {
                amountInput.value = parseFloat(invoiceAmount).toFixed(2);
                updateAmountDisplay();
            }
        } else {
            selectedInvoice.textContent = 'Not selected';
        }
    });

    // Update amount display
    amountInput.addEventListener('input', updateAmountDisplay);

    function updateAmountDisplay() {
        const amount = parseFloat(amountInput.value) || 0;
        displayAmount.textContent = '£' + amount.toFixed(2);
    }

    // Update file selection
    fileInput.addEventListener('change', function() {
        const fileName = this.files[0] ? this.files[0].name : 'No file selected';
        selectedFile.textContent = fileName;
        
        // Update the custom file input label
        const label = document.querySelector('.custom-file-label');
        label.textContent = fileName;
    });

    // Form validation
    document.getElementById('remittanceForm').addEventListener('submit', function(e) {
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
        
        if (!fileInput.files[0]) {
            e.preventDefault();
            alert('Please select a file to upload.');
            return false;
        }
        
        // Check file size (10MB limit)
        if (fileInput.files[0].size > 10 * 1024 * 1024) {
            e.preventDefault();
            alert('File size must be less than 10MB.');
            return false;
        }
    });

    // Initialize display
    updateAmountDisplay();
});
</script>
@endsection