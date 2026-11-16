<p>Dear Admin,</p>

<p>A new payment proof has been submitted by {{ optional($invoice->client->user)->full_name ?? 'a client' }} for invoice <strong>#{{ $invoice->invoice_number ?? $invoice->id }}</strong>.</p>

<ul>
    <li>Amount: {{ number_format((float) $proof->amount, 2) }} {{ $proof->currency }}</li>
    <li>Paid date: {{ optional($proof->paid_at)->format('Y-m-d') ?? 'N/A' }}</li>
    <li>Method: {{ $proof->method ?: 'N/A' }}</li>
    <li>Reference: {{ $proof->reference ?: 'N/A' }}</li>
    <li>Submitted by: {{ optional($proof->submitter)->full_name ?? 'N/A' }}</li>
    <li>Notes: {{ $proof->notes ?: '—' }}</li>
    <li>Manage: <a href="{{ $adminPreviewUrl }}">Review payment proofs for this invoice</a></li>
    </ul>

<p>Regards,<br>{{ config('app.name') }}</p>