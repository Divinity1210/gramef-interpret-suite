@extends('layouts.email')

@section('content')
<p>Dear {{ optional(optional($invoice->client)->user)->getFullName() ?? 'Customer' }},</p>

<p>Please find your invoice <strong>{{ $invoice->invoice_number }}</strong> below. You can view and print the PDF using the link:</p>

<p><a href="{{ $pdfUrl }}" target="_blank">View Invoice PDF</a></p>

@if(!empty($customMessage))
    <p style="white-space: pre-line">{!! nl2br(e($customMessage)) !!}</p>
@endif

<p>Summary:</p>
<ul>
    <li>Invoice Date: {{ optional($invoice->invoice_date)->format('d/m/Y') ?? optional($invoice->created_at)->format('d/m/Y') }}</li>
    <li>Subtotal: £{{ number_format($invoice->subtotal ?? 0, 2) }}</li>
    <li>VAT ({{ $invoice->vat_rate ?? 0 }}%): £{{ number_format($invoice->vat_amount ?? 0, 2) }}</li>
    <li><strong>Total: £{{ number_format($invoice->total ?? 0, 2) }}</strong></li>
</ul>

<p>If you have any questions, please reply to this email.</p>

<p>Regards,<br>{{ optional($invoice->company)->name ?? config('app.name') }}</p>
@endsection