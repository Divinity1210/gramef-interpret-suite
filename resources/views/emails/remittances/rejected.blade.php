@php
    $invoice = $remittance->clientInvoice;
@endphp
<p>Hello {{ optional($remittance->uploadedBy)->getFullName() ?? 'User' }},</p>
<p>Unfortunately, the remittance you uploaded has been rejected.</p>
<ul>
  <li>Remittance #: {{ $remittance->remittance_number ?? $remittance->id }}</li>
  <li>Invoice #: {{ optional($invoice)->invoice_number ?? 'N/A' }}</li>
  <li>Amount: £{{ number_format($remittance->amount, 2) }}</li>
</ul>
<p>You can review it here: <a href="{{ $showUrl }}">View Remittance</a></p>
<p>If you have any questions, please reply to this email.</p>
<p>Thanks,<br>{{ config('app.name') }}</p>