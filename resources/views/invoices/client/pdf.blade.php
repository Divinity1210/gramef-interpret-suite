<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Client Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #333; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header .title { font-size: 20px; font-weight: bold; }
        .meta { margin-bottom: 15px; }
        .meta div { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        th { background: #f5f5f5; text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals { margin-top: 15px; }
        .totals table td { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Client Invoice</div>
        <div>Invoice #: {{ $invoice->invoice_number }}</div>
    </div>

    <div class="meta">
        <div><strong>Client:</strong> {{ optional($invoice->client)->user->fullName ?? '—' }}</div>
        <div><strong>Company:</strong> {{ optional($invoice->company)->organisation_company ?? '—' }}</div>
        <div><strong>Period:</strong> {{ optional($invoice->date_range_start)->format('Y-m-d') }} — {{ optional($invoice->date_range_end)->format('Y-m-d') }}</div>
        <div><strong>Status:</strong> {{ ucfirst($invoice->status ?? 'draft') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Job ID</th>
                <th>Description</th>
                <th class="text-right">Amount (£)</th>
                <th class="text-right">Surcharge (£)</th>
                <th class="text-right">Other Charges (£)</th>
                <th class="text-center">VAT Applicable</th>
                <th class="text-right">Line Total (£)</th>
                <th class="text-right">VAT (£)</th>
                <th class="text-right">Total with VAT (£)</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($invoice->items ?? []) as $item)
                @php
                    $lineTotal = ($item->amount ?? 0) + ($item->surcharge ?? 0) + ($item->other_charges ?? 0);
                    $lineVat = ($item->vat_applicable ?? false) ? ($lineTotal * ($invoice->vat_rate ?? 0) / 100) : 0;
                    $lineTotalWithVat = $lineTotal + $lineVat;
                @endphp
                <tr>
                    <td class="text-center">{{ optional($item->job)->id }}</td>
                    <td>{{ $item->description ?? '—' }}</td>
                    <td class="text-right">{{ number_format($item->amount ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($item->surcharge ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($item->other_charges ?? 0, 2) }}</td>
                    <td class="text-center">{{ ($item->vat_applicable ?? false) ? 'Yes' : 'No' }}</td>
                    <td class="text-right">{{ number_format($lineTotal, 2) }}</td>
                    <td class="text-right">{{ number_format($lineVat, 2) }}</td>
                    <td class="text-right">{{ number_format($lineTotalWithVat, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td>Subtotal</td>
                <td class="text-right">£{{ number_format($invoice->subtotal ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>VAT ({{ $invoice->vat_rate ?? 0 }}%)</td>
                <td class="text-right">£{{ number_format($invoice->vat_amount ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Total</td>
                <td class="text-right"><strong>£{{ number_format($invoice->total ?? 0, 2) }}</strong></td>
            </tr>
        </table>
    </div>
</body>
</html>