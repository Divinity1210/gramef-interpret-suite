@extends('layouts.app')

@section('content')
<div class="container-fluid px-2">
    <div class="row justify-content-center">
        <div class="col-12" style="max-width: 1600px;">
            <h1 class="mb-3">My Receipts</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form method="GET" class="row g-2 mb-3" id="clientReceiptsFilter">
        <div class="col-md-8">
            @include('partials.filters.date-range', [
                'mode' => 'single',
                'formId' => 'clientReceiptsFilter',
                'inputId' => 'clientReceiptsDateFilter',
                'paramName' => 'date',
                'startName' => 'date_range_start',
                'endName' => 'date_range_end',
                'startId' => 'date_range_start',
                'endId' => 'date_range_end',
                'mirrorHidden' => true,
                'placeholder' => 'Filter By Date...'
            ])
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button class="btn btn-primary w-100" type="submit">Filter</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Receipt No.</th>
                    <th>Invoice No.</th>
                    <th>Company</th>
                    <th>Amount Paid</th>
                    <th>Issued Date</th>
                    <th>Payment Proofs</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receipts as $receipt)
                    <tr>
                        <td><strong>{{ $receipt->receipt_number }}</strong></td>
                        <td>{{ $receipt->clientInvoice->invoice_number }}</td>
                        <td>{{ optional($receipt->clientInvoice->company)->name ?? '—' }}</td>
                        <td><strong>£{{ number_format($receipt->total_paid, 2) }}</strong></td>
                        <td>{{ $receipt->issued_at->format('Y-m-d H:i') }}</td>
                        <td>
                            <span class="badge bg-info">{{ $receipt->paymentProofs->count() }} proof(s)</span>
                        </td>
                        <td class="d-flex gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('receipts.client.show', $receipt) }}">View</a>
                            <a class="btn btn-sm btn-primary" href="{{ route('receipts.client.download', $receipt) }}">Download PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">No receipts found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $receipts->links() }}
    </div>
        </div>
    </div>
</div>
@endsection