@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Create Client Invoice</h1>
        <a href="{{ route('invoices.admin.index', ['type' => 'client']) }}" class="btn btn-outline-secondary">Back to Invoices</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('invoices.admin.client.store') }}" class="card">
        @csrf
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Client</label>
                    <select name="client_id" class="form-select" required>
                        <option value="">-- Select Client --</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ optional($client->user)->getFullName() }} (ID: {{ $client->id }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Company (optional)</label>
                    <select name="company_id" class="form-select">
                        <option value="">-- Select Company --</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Invoice Date</label>
                    <input type="date" name="invoice_date" value="{{ $defaults['invoice_date'] }}" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Service Date</label>
                    <input type="date" name="service_date" value="{{ $defaults['invoice_date'] }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Service Description</label>
                    <input type="text" name="service_description" class="form-control" placeholder="Interpreting/Translation" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Service Amount (£)</label>
                    <input type="number" step="0.01" name="service_amount" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Parking (£)</label>
                    <input type="number" step="0.01" name="parking_amount" value="0" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Include VAT?</label>
                    <select name="include_vat" class="form-select">
                        <option value="1" {{ $defaults['include_vat'] ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ !$defaults['include_vat'] ? 'selected' : '' }}>No</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">VAT Rate (%)</label>
                    <input type="number" step="0.01" name="vat_rate" value="{{ $defaults['vat_rate'] }}" class="form-control">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Payment Terms</label>
                    <input type="text" name="payment_terms_text" value="{{ $defaults['payment_terms_text'] }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">File Reference</label>
                    <input type="text" name="file_reference" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Client Reference / PO / Cost Code</label>
                    <input type="text" name="client_reference" class="form-control" placeholder="Client Reference / PO / Cost Code">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Patient Number</label>
                    <input type="text" name="patient_number" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">PID</label>
                    <input type="text" name="pid" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Interpreting Time (minutes)</label>
                    <input type="number" name="interpreting_time_minutes" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Travel Time (minutes)</label>
                    <input type="number" name="travel_time_minutes" class="form-control">
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <div class="text-muted">Template: v2</div>
            <button class="btn btn-primary" type="submit">
                <i class="fas fa-save"></i> Create Invoice
            </button>
        </div>
    </form>
</div>
@endsection
