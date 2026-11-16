@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header">
                <h1 class="page-title">Export System</h1>
                <p class="page-subtitle">Export invoices, payslips, and remittances in various formats</p>
            </div>
        </div>
    </div>

    <!-- Export Options -->
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-file-invoice text-primary"></i>
                        Export Invoices
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('exports.invoices') }}">
                        @csrf
                        <div class="form-group">
                            <label for="invoice_type">Invoice Type</label>
                            <select name="type" id="invoice_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="agent">Agent Invoices</option>
                                <option value="client">Client Invoices</option>
                                <option value="both">Both Types</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="invoice_format">Export Format</label>
                            <select name="format" id="invoice_format" class="form-control" required>
                                <option value="excel">Excel (.xlsx)</option>
                                <option value="csv">CSV</option>
                                <option value="pdf">PDF Report</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            @include('partials.filters.date-range', [
                                'mode' => 'single',
                                'inputId' => 'invoice_date_range',
                                'paramName' => 'date',
                                'startName' => 'start_date',
                                'endName' => 'end_date',
                                'startId' => 'invoice_start_date',
                                'endId' => 'invoice_end_date',
                                'mirrorHidden' => true,
                                'placeholder' => 'Filter By Date...'
                            ])
                        </div>
                        
                        <div class="form-group">
                            <label for="invoice_status">Status Filter</label>
                            <select name="status" id="invoice_status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="paid">Paid</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-download"></i> Export Invoices
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-receipt text-success"></i>
                        Export Payslips
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('exports.payslips') }}">
                        @csrf
                        <div class="form-group">
                            <label for="payslip_format">Export Format</label>
                            <select name="format" id="payslip_format" class="form-control" required>
                                <option value="excel">Excel (.xlsx)</option>
                                <option value="csv">CSV</option>
                                <option value="pdf">PDF Report</option>
                                <option value="zip">ZIP (Individual PDFs)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="payslip_agent">Agent Filter</label>
                            <select name="agent_id" id="payslip_agent" class="form-control">
                                <option value="">All Agents</option>
                                @if(isset($agents))
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        
                        <div class="form-group">
                            @include('partials.filters.date-range', [
                                'mode' => 'single',
                                'inputId' => 'payslip_date_range',
                                'paramName' => 'date',
                                'startName' => 'start_date',
                                'endName' => 'end_date',
                                'startId' => 'payslip_start_date',
                                'endId' => 'payslip_end_date',
                                'mirrorHidden' => true,
                                'placeholder' => 'Filter By Date...'
                            ])
                        </div>
                        
                        <div class="form-group">
                            <label for="payslip_status">Status Filter</label>
                            <select name="status" id="payslip_status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-download"></i> Export Payslips
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-upload text-info"></i>
                        Export Remittances
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('exports.remittances') }}">
                        @csrf
                        <div class="form-group">
                            <label for="remittance_format">Export Format</label>
                            <select name="format" id="remittance_format" class="form-control" required>
                                <option value="excel">Excel (.xlsx)</option>
                                <option value="csv">CSV</option>
                                <option value="pdf">PDF Report</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="remittance_client">Client Filter</label>
                            <select name="client_id" id="remittance_client" class="form-control">
                                <option value="">All Clients</option>
                                @if(isset($clients))
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        
                        <div class="form-group">
                            @include('partials.filters.date-range', [
                                'mode' => 'single',
                                'inputId' => 'remittance_date_range',
                                'paramName' => 'date',
                                'startName' => 'start_date',
                                'endName' => 'end_date',
                                'startId' => 'remittance_start_date',
                                'endId' => 'remittance_end_date',
                                'mirrorHidden' => true,
                                'placeholder' => 'Filter By Date...'
                            ])
                        </div>
                        
                        <div class="form-group">
                            <label for="remittance_status">Status Filter</label>
                            <select name="status" id="remittance_status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-info btn-block">
                            <i class="fas fa-download"></i> Export Remittances
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Export Options -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-archive text-warning"></i>
                        Bulk Export Options
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('exports.bulk') }}">
                                @csrf
                                <div class="form-group">
                                    <label for="bulk_types">Export Types</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="types[]" value="invoices" id="bulk_invoices">
                                        <label class="form-check-label" for="bulk_invoices">
                                            Invoices
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="types[]" value="payslips" id="bulk_payslips">
                                        <label class="form-check-label" for="bulk_payslips">
                                            Payslips
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="types[]" value="remittances" id="bulk_remittances">
                                        <label class="form-check-label" for="bulk_remittances">
                                            Remittances
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="bulk_format">Export Format</label>
                                    <select name="format" id="bulk_format" class="form-control" required>
                                        <option value="zip">ZIP Archive</option>
                                        <option value="excel">Combined Excel</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    @include('partials.filters.date-range', [
                                        'mode' => 'single',
                                        'inputId' => 'bulk_date_range',
                                        'paramName' => 'date',
                                        'startName' => 'start_date',
                                        'endName' => 'end_date',
                                        'startId' => 'bulk_start_date',
                                        'endId' => 'bulk_end_date',
                                        'mirrorHidden' => true,
                                        'placeholder' => 'Filter By Date...'
                                    ])
                                </div>
                                
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-archive"></i> Create Bulk Export
                                </button>
                            </form>
                        </div>
                        
                        <div class="col-md-6">
                            <h6>Export Statistics</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <tr>
                                            <td>Total Invoices:</td>
                                            <td><strong>{{ $stats['total_invoices'] ?? 0 }}</strong></td>
                                        </tr>
                                        <tr>
                                            <td>Total Payslips:</td>
                                            <td><strong>{{ $stats['total_payslips'] ?? 0 }}</strong></td>
                                        </tr>
                                        <tr>
                                            <td>Total Remittances:</td>
                                            <td><strong>{{ $stats['total_remittances'] ?? 0 }}</strong></td>
                                        </tr>
                                        <tr>
                                            <td>Last Export:</td>
                                            <td><strong>{{ $stats['last_export'] ?? 'Never' }}</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i>
                                <strong>Note:</strong> Large exports may take several minutes to process. You will receive an email notification when your export is ready for download.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Exports -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Exports</h5>
                </div>
                <div class="card-body">
                    @if(isset($recentExports) && $recentExports->count() > 0)
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Export Type</th>
                                        <th>Format</th>
                                        <th>Date Range</th>
                                        <th>Created</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentExports as $export)
                                    <tr>
                                        <td>{{ ucfirst($export->type) }}</td>
                                        <td>{{ strtoupper($export->format) }}</td>
                                        <td>
                                            @if($export->start_date && $export->end_date)
                                                {{ $export->start_date }} to {{ $export->end_date }}
                                            @else
                                                All Time
                                            @endif
                                        </td>
                                        <td>{{ $export->created_at->format('M d, Y H:i') }}</td>
                                        <td>
                                            @if($export->status === 'completed')
                                                <span class="badge badge-success">Completed</span>
                                            @elseif($export->status === 'processing')
                                                <span class="badge badge-warning">Processing</span>
                                            @else
                                                <span class="badge badge-danger">Failed</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($export->status === 'completed')
                                                <a href="{{ route('exports.download', $export) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted">No recent exports found.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.page-header {
    margin-bottom: 2rem;
}

.page-title {
    font-size: 2rem;
    font-weight: 600;
    color: #333;
}

.page-subtitle {
    color: #666;
    margin-bottom: 0;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.form-group {
    margin-bottom: 1rem;
}

.btn-block {
    width: 100%;
}

.table-sm th, .table-sm td {
    padding: 0.5rem;
}

.badge {
    font-size: 0.75em;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set default date ranges
    const today = new Date();
    const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDayOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    const startDateInputs = document.querySelectorAll('input[name="start_date"]');
    const endDateInputs = document.querySelectorAll('input[name="end_date"]');
    
    startDateInputs.forEach(input => {
        input.value = firstDayOfMonth.toISOString().split('T')[0];
    });
    
    endDateInputs.forEach(input => {
        input.value = lastDayOfMonth.toISOString().split('T')[0];
    });
    
    // If using the shared date-range partial, also reflect defaults in the visible range field
    document.querySelectorAll('.input--date-filter').forEach(function(el){
        var startId = el.getAttribute('data-start-id');
        var endId = el.getAttribute('data-end-id');
        var sEl = startId ? document.getElementById(startId) : null;
        var eEl = endId ? document.getElementById(endId) : null;
        var s = sEl ? sEl.value : '';
        var e = eEl ? eEl.value : '';
        if (s && e) {
            el.value = s + ' to ' + e;
        }
    });
    
    // Form validation
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        });
    });
});
</script>
@endpush