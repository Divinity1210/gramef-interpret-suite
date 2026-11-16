@php
    $invoice = $remittance->clientInvoice;
@endphp
<p>Hello {{ optional(optional($invoice)->client)->name ?? 'Customer' }},</p>
<p>Your remittance has been approved.</p>
<ul>
  <li>Remittance #: {{ $remittance->remittance_number ?? $remittance->id }}</li>
  <li>Invoice #: {{ optional($invoice)->invoice_number ?? 'N/A' }}</li>
  <li>Amount: £{{ number_format($remittance->amount, 2) }}</li>
</ul>
<p>You can download the remittance file here: <a href="{{ $downloadUrl }}">Download</a></p>
<p>Or view details here: <a href="{{ $showUrl }}">View Remittance</a></p>
<p>Thank you,<br>{{ optional($invoice->company)->name ?? config('app.name') }}</p>