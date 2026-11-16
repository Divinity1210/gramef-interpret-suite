@extends('layouts.app')

@section('content')
<div class="container-fluid px-2">
    <div class="row justify-content-center">
        <div class="col-12" style="max-width: 1600px;">
            <div class="card">
                <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                    <h4 class="mb-2 mb-md-0">Agent Invoice Preview #{{ $invoice->invoice_number }}</h4>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a href="{{ route('invoices.admin.index', ['type' => 'agent']) }}" class="btn btn-secondary az-btn az-btn-secondary" title="Back to Invoices">Back to Invoices</a>
                        <a href="{{ route('invoices.admin.agent.pdf', $invoice->id) }}" class="btn btn-outline-primary az-btn az-btn-outline" target="_blank" title="Print PDF">Print PDF</a>
                    </div>
                </div>
                <div class="card-body p-2 p-md-3">
                    @if(session('success'))
                        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
                    @endif
                    <div>
                        <iframe id="invoice-preview-frame" src="{{ route('invoices.admin.agent.pdf', $invoice->id) }}" title="Invoice PDF Preview" style="width:100%; height:80vh; border:1px solid #ddd; border-radius: 6px;" allow="fullscreen"></iframe>
                    </div>

                    <div class="invoice-preview-actions mt-2" style="position:sticky; bottom:0; z-index:10; display:flex; justify-content:center; gap:10px; padding:10px 12px; background:#f8f9fa; border-top:1px solid #e9ecef; border-radius:0 0 6px 6px;">
                        <a href="{{ route('invoices.admin.index', ['type' => 'agent']) }}" class="btn btn-outline-secondary az-btn az-btn-secondary">Back</a>
                        <a href="{{ route('invoices.admin.agent.pdf', $invoice->id) }}" class="btn btn-outline-dark az-btn az-btn-outline" target="_blank">Print Invoice</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/invoice-admin.js') }}"></script>
@endpush