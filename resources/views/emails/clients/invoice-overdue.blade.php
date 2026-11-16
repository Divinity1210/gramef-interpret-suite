<p>Dear {{ optional($invoice->client->user)->full_name ?? 'Client' }},</p>

<p>This is a friendly reminder that your invoice <strong>#{{ $invoice->invoice_number ?? $invoice->id }}</strong> dated <strong>{{ optional($invoice->invoice_date)->format('Y-m-d') ?? 'N/A' }}</strong> is now overdue.</p>

<ul>
    <li>Total amount: {{ number_format((float) $invoice->total, 2) }}</li>
    <li>Status: {{ ucfirst($invoice->status) }}</li>
    <li>View invoice: <a href="{{ route('invoices.client.show', $invoice->id) }}">Click here</a></li>
    <li>Submit payment proof: Use the "Submit Payment Proof" button on the invoice page</li>
    <li>Download PDF: <a href="{{ route('invoices.client.pdf', $invoice->id) }}">Invoice PDF</a></li>
    </ul>

<p>If you have already made the payment, please submit a payment proof to help us verify and issue a receipt promptly.</p>

<p>Regards,<br>{{ config('app.name') }}</p>