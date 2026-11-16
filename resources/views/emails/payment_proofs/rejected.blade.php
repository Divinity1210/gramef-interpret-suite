<p>Dear {{ optional($invoice->client->user)->full_name ?? 'Customer' }},</p>

<p>Unfortunately, your payment proof for invoice <strong>#{{ $invoice->invoice_number ?? $invoice->id }}</strong> was rejected.</p>

<p><strong>Reason:</strong> {{ $proof->rejection_reason ?? 'N/A' }}</p>

<p>Please review and resubmit the correct payment details or contact support if you need assistance.</p>

<p>You can view your invoice here: <a href="{{ $invoicePreviewUrl }}">Invoice details</a></p>

<p>Regards,<br>{{ config('app.name') }}</p>