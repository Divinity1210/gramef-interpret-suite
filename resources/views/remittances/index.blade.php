@extends('layouts.app')

@section('content')
<div class="container-fluid" style="max-width: 1400px;">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="mb-0">Receipts</h1>
                <a href="{{ route('remittances.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Upload New Remittance
                </a>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form id="remittances-filters" method="GET" action="{{ route('remittances.index') }}" class="row g-3 filters">
                        <div class="col-md-3">
                            <label for="status" class="form-label visually-hidden">Status</label>
                            <select name="status" id="status" class="form-select section__input input input--select">
                                <option value="">Filter By Status...</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                            </select>
                        </div>
                        @if(isset($clientOptions) && $clientOptions->count() > 0)
                        <div class="col-md-3">
                            <label for="client_id" class="form-label visually-hidden">Client</label>
                            <select name="client_id" id="client_id" class="form-select section__input input input--select">
                                <option value="">Filter By Client...</option>
                                @foreach($clientOptions as $id => $name)
                                    <option value="{{ $id }}" {{ request('client_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="col-md-6">
                            @include('partials.filters.date-range', [
                                'mode' => 'single',
                                'formId' => 'remittances-filters',
                                'inputId' => 'remittances-date',
                                'paramName' => 'date',
                                'startName' => 'date_from',
                                'endName' => 'date_to',
                                'startId' => 'date_from',
                                'endId' => 'date_to',
                                'mirrorHidden' => true,
                                'placeholder' => 'Filter By Date...'
                            ])
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                Filter
                            </button>
                            <a href="{{ route('remittances.index') }}" class="btn btn-secondary">
                                Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Remittances Table -->
            <div class="card">
                <div class="card-body">
                    @if($remittances->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Remittance #</th>
                                        <th>Client</th>
                                        <th>Client Invoice</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Uploaded By</th>
                                        <th>Upload Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($remittances as $remittance)
                                        <tr>
                                            <td>
                                                <a href="{{ route('remittances.show', $remittance) }}" class="text-decoration-none">
                                                    {{ $remittance->remittance_number }}
                                                </a>
                                            </td>
                                            <td>
                                                @php $client = optional($remittance->clientInvoice)->client; @endphp
                                                @if($client)
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-2">
                                                            <i class="fas fa-user-circle text-secondary"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold">{{ optional($client->user)->first_name }} {{ optional($client->user)->last_name }}</div>
                                                            <small class="text-muted">{{ optional($client->user)->email }}</small>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="text-muted">Unknown</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($remittance->clientInvoice)
                                                    <a href="{{ route('invoices.client.show', $remittance->clientInvoice->id) }}" class="text-decoration-none">
                                                        {{ $remittance->clientInvoice->invoice_number }}
                                                    </a>
                                                    @php $itemsCount = optional($remittance->clientInvoice->items)->count(); @endphp
                                                    @if($itemsCount)
                                                        <br><small class="text-muted">Includes {{ $itemsCount }} job{{ $itemsCount === 1 ? '' : 's' }}</small>
                                                    @endif
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td>£{{ number_format($remittance->amount, 2) }}</td>
                                            <td>
                                                <span class="badge bg-{{ $remittance->status === 'approved' ? 'success' : ($remittance->status === 'pending' ? 'warning' : 'danger') }}">
                                                    {{ ucfirst($remittance->status) }}
                                                </span>
                                            </td>
                                            <td>{{ $remittance->uploadedBy->name ?? 'N/A' }}</td>
                                            <td>{{ $remittance->created_at->format('d/m/Y') }}</td>
                                            <td>
                                                <div class="btn-group" role="group">
                                    <a href="{{ route('remittances.show', $remittance) }}" 
                                       class="btn btn-sm btn-primary" title="View">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    @if($remittance->status === 'pending')
                                        <a href="{{ route('remittances.edit', $remittance) }}" 
                                           class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        @if(auth()->user()->hasRole('admin'))
                                            <form method="POST" action="{{ route('remittances.approve', $remittance) }}" 
                                                  class="d-inline" onsubmit="return confirm('Approve this remittance?')">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-success" title="Approve">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('remittances.reject', $remittance) }}" 
                                                  class="d-inline" onsubmit="return confirm('Reject this remittance?')">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-danger" title="Reject">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                    @if($remittance->file_path)
                                        <a href="{{ route('remittances.download', $remittance) }}" 
                                           class="btn btn-sm btn-info" title="Download">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    @endif
                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-center mt-4">
                            {{ $remittances->appends(request()->query())->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fas fa-file-upload fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No remittances found</h5>
                            <p class="text-muted">Upload your first remittance to get started.</p>
                            <a href="{{ route('remittances.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Upload Remittance
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Moved inline styles to global CSS to avoid Vue template warnings -->
@endsection