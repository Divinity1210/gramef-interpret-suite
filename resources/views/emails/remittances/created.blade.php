@php
    $invoice = $remittance->clientInvoice;
@endphp
<p>Hello Admin,</p>
<p>A new remittance has been uploaded and is pending approval.</p>
<ul>
  <li>Remittance #: {{ $remittance->remittance_number ?? $remittance->id }}</li>
  <li>Invoice #: {{ optional($invoice)->invoice_number ?? 'N/A' }}</li>
  <li>Client: {{ optional(optional($invoice)->client)->name ?? 'N/A' }}</li>
  <li>Amount: £{{ number_format($remittance->amount, 2) }}</li>
</ul>
<p>You can review it here: <a href="{{ $showUrl }}">View Remittance</a></p>
<p>Thanks,<br>{{ config('app.name') }}</p>