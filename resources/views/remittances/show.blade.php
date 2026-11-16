@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Receipt Evidence - {{ $remittance->remittance_number }}</h4>
                    <div>
                        @php $remDisk = env('REMITTANCES_DISK') ?: (app()->environment('production') ? 's3' : 'public'); $remExists = $remittance->file_path ? \Illuminate\Support\Facades\Storage::disk($remDisk)->exists($remittance->file_path) : false; @endphp
                        @if($remittance->file_path && $remExists)
                            <a href="{{ route('remittances.download', $remittance) }}" class="btn btn-info">
                                <i class="fas fa-download"></i> Download File
                            </a>
                        @endif
                        @if($remittance->status === 'pending' && auth()->user()->hasRole('admin'))
                            <button type="button" class="btn btn-success ml-2" data-toggle="modal" data-target="#approveModal">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button type="button" class="btn btn-danger ml-2" data-toggle="modal" data-target="#rejectModal">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        @endif
                        @if($remittance->status === 'pending')
                            <a href="{{ route('remittances.edit', $remittance) }}" class="btn btn-warning ml-2">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        @endif
                        <a href="{{ route('remittances.index') }}" class="btn btn-secondary ml-2">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Basic Information -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">Basic Information</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Remittance Number:</strong></td>
                                            <td>{{ $remittance->remittance_number }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Status:</strong></td>
                                            <td>
                                                @if($remittance->status === 'pending')
                                                    <span class="badge badge-warning">Pending</span>
                                                @elseif($remittance->status === 'approved')
                                                    <span class="badge badge-success">Approved</span>
                                                @elseif($remittance->status === 'rejected')
                                                    <span class="badge badge-danger">Rejected</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Amount:</strong></td>
                                            <td>£{{ number_format($remittance->amount, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Uploaded By:</strong></td>
                                            <td>{{ $remittance->uploader->name ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Upload Date:</strong></td>
                                            <td>{{ $remittance->created_at->format('d/m/Y H:i') }}</td>
                                        </tr>
                                        @if($remittance->approved_by)
                                        <tr>
                                            <td><strong>Approved By:</strong></td>
                                            <td>{{ $remittance->approver->name ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Approval Date:</strong></td>
                                            <td>{{ $remittance->approved_at ? $remittance->approved_at->format('d/m/Y H:i') : 'N/A' }}</td>
                                        </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Client Invoice Information -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">Client Invoice Information</h6>
                                </div>
                                <div class="card-body">
                                    @if($remittance->clientInvoice)
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Invoice Number:</strong></td>
                                            <td>
                                                <a href="{{ route('client-invoices.show', $remittance->clientInvoice) }}">
                                                    {{ $remittance->clientInvoice->invoice_number }}
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Client:</strong></td>
                                            <td>{{ $remittance->clientInvoice->client->name ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Invoice Total:</strong></td>
                                            <td>£{{ number_format($remittance->clientInvoice->total, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Invoice Date:</strong></td>
                                            <td>{{ $remittance->clientInvoice->created_at->format('d/m/Y') }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Invoice Status:</strong></td>
                                            <td>
                                                @if($remittance->clientInvoice->status === 'draft')
                                                    <span class="badge badge-secondary">Draft</span>
                                                @elseif($remittance->clientInvoice->status === 'sent')
                                                    <span class="badge badge-info">Sent</span>
                                                @elseif($remittance->clientInvoice->status === 'paid')
                                                    <span class="badge badge-success">Paid</span>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                    @else
                                    <p class="text-muted">No client invoice associated.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- File Information -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">File Information</h6>
                                </div>
                                <div class="card-body">
                                    @if($remittance->file_path)
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-borderless">
                                                <tr>
                                                    <td><strong>Original Filename:</strong></td>
                                                    <td>{{ $remittance->original_filename ?? 'N/A' }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>File Size:</strong></td>
                                                    <td>
                                                        @php $size = $remExists ? \Illuminate\Support\Facades\Storage::disk($remDisk)->size($remittance->file_path) : null; @endphp
                                                        @if($size !== null)
                                                            {{ number_format($size / 1024, 2) }} KB
                                                        @else
                                                            File not found
                                                        @endif
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><strong>File Type:</strong></td>
                                                    <td>{{ strtoupper(pathinfo($remittance->file_path, PATHINFO_EXTENSION)) }}</td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            @if($remExists)
                                                <div class="text-center">
                                                    <i class="fas fa-file-alt fa-3x text-primary mb-2"></i>
                                                    <br>
                                                    <a href="{{ route('remittances.download', $remittance) }}" class="btn btn-primary">
                                                        <i class="fas fa-download"></i> Download File
                                                    </a>
                                                </div>
                                            @else
                                                <div class="text-center text-danger">
                                                    <i class="fas fa-exclamation-triangle fa-3x mb-2"></i>
                                                    <br>
                                                    <span>File not found on server</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    @else
                                    <p class="text-muted">No file uploaded.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    @if($remittance->notes)
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">Notes</h6>
                                </div>
                                <div class="card-body">
                                    <p>{{ $remittance->notes }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Status History -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Status History</h6>
                                </div>
                                <div class="card-body">
                                    <div class="timeline">
                                        <div class="timeline-item">
                                            <div class="timeline-marker bg-primary"></div>
                                            <div class="timeline-content">
                                                <h6 class="timeline-title">Remittance Uploaded</h6>
                                                <p class="timeline-text">
                                                    Uploaded by {{ $remittance->uploader->name ?? 'N/A' }}
                                                    <br>
                                                    <small class="text-muted">{{ $remittance->created_at->format('d/m/Y H:i:s') }}</small>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        @if($remittance->status === 'approved' && $remittance->approved_at)
                                        <div class="timeline-item">
                                            <div class="timeline-marker bg-success"></div>
                                            <div class="timeline-content">
                                                <h6 class="timeline-title">Remittance Approved</h6>
                                                <p class="timeline-text">
                                                    Approved by {{ $remittance->approver->name ?? 'N/A' }}
                                                    <br>
                                                    <small class="text-muted">{{ $remittance->approved_at->format('d/m/Y H:i:s') }}</small>
                                                </p>
                                            </div>
                                        </div>
                                        @elseif($remittance->status === 'rejected' && $remittance->approved_at)
                                        <div class="timeline-item">
                                            <div class="timeline-marker bg-danger"></div>
                                            <div class="timeline-content">
                                                <h6 class="timeline-title">Remittance Rejected</h6>
                                                <p class="timeline-text">
                                                    Rejected by {{ $remittance->approver->name ?? 'N/A' }}
                                                    <br>
                                                    <small class="text-muted">{{ $remittance->approved_at->format('d/m/Y H:i:s') }}</small>
                                                </p>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
@if($remittance->status === 'pending' && auth()->user()->hasRole('admin'))
<div class="modal fade" id="approveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Remittance</h5>
                <button type="button" class=" btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>
            <form action="{{ route('remittances.approve', $remittance) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <p>Are you sure you want to approve this remittance?</p>
                    <p><strong>Remittance Number:</strong> {{ $remittance->remittance_number }}</p>
                    <p><strong>Amount:</strong> £{{ number_format($remittance->amount, 2) }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Remittance</h5>
                <button type="button" class=" btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>
            <form action="{{ route('remittances.reject', $remittance) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <p>Are you sure you want to reject this remittance?</p>
                    <p><strong>Remittance Number:</strong> {{ $remittance->remittance_number }}</p>
                    <p><strong>Amount:</strong> £{{ number_format($remittance->amount, 2) }}</p>
                    
                    <div class="form-group mt-3">
                        <label for="rejection_reason">Reason for Rejection (Optional)</label>
                        <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="3" placeholder="Provide a reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -35px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: -30px;
    top: 17px;
    width: 2px;
    height: calc(100% + 20px);
    background-color: #dee2e6;
}

.timeline-title {
    margin-bottom: 5px;
    font-weight: 600;
}

.timeline-text {
    margin-bottom: 0;
    color: #6c757d;
}
</style>
@endsection
