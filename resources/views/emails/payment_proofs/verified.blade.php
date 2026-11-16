<p>Dear {{ optional($invoice->client->user)->full_name ?? 'Customer' }},</p>

<p>We have verified your payment for invoice <strong>#{{ $invoice->invoice_number ?? $invoice->id }}</strong>.</p>

<ul>
    <li>Amount verified: {{ number_format((float) $proof->amount, 2) }} {{ $proof->currency }}</li>
    <li>Paid date: {{ optional($proof->paid_at)->format('Y-m-d') ?? 'N/A' }}</li>
    @if($receipt)
        <li>Receipt number: {{ $receipt->receipt_number }}</li>
    @endif
    <li>View invoice: <a href="{{ $invoicePreviewUrl }}">Invoice details</a></li>
</ul>

<p>Thank you for your payment.</p>

<p>Regards,<br>{{ config('app.name') }}</p>