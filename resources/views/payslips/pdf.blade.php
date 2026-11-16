<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip #{{ $payslip->payslip_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        
        .document-title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 15px;
        }
        /* Company logo shown in header instead of text */
        .company-logo {
            height: 60px;
        }
        
        .payslip-number {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .info-section {
            margin-bottom: 25px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .info-column {
            width: 48%;
        }
        
        .info-item {
            margin-bottom: 8px;
        }
        
        .label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        
        .items-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .items-table .amount {
            text-align: right;
        }
        
        .totals-section {
            float: right;
            width: 300px;
            margin-top: 20px;
        }
        
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .totals-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .totals-table .label-col {
            text-align: left;
            font-weight: bold;
        }
        
        .totals-table .amount-col {
            text-align: right;
        }
        
        .total-row {
            border-top: 2px solid #007bff;
            font-weight: bold;
            font-size: 14px;
        }
        
        .total-row td {
            padding: 12px 8px;
            border-bottom: none;
        }
        
        .bank-details {
            margin-top: 40px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .bank-details h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #007bff;
        }
        
        .bank-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .bank-column {
            width: 48%;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

.status-paid {
    background-color: #cfe2ff;
    color: #084298;
}
        
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <img src="{{ public_path('img/a-to-z-logo.png') }}" alt="AtoZ Interpreting" class="company-logo">
        </div>
        <div class="document-title">PAYSLIP</div>
        <div class="payslip-number">#{{ $payslip->payslip_number }}</div>
        <span class="status-badge status-{{ $payslip->status }}">{{ ucfirst($payslip->status) }}</span>
    </div>

    <div class="info-section">
        <div class="info-row">
            <div class="info-column">
                <div class="info-item">
                    <span class="label">Agent:</span> {{ data_get($payslip, 'agent.user.full_name', 'Unknown') }}
                </div>
                <div class="info-item">
                    <span class="label">Email:</span> {{ data_get($payslip, 'agent.user.email', 'N/A') }}
                </div>
                @if($payslip->invoice)
                    <div class="info-item">
                        <span class="label">Invoice:</span> {{ $payslip->invoice->invoice_number }}
                    </div>
                @endif
                <div class="info-item">
                    <span class="label">Period:</span>
                    @if($payslip->period_start || $payslip->period_end)
                        {{ $payslip->period_start ? \Carbon\Carbon::parse($payslip->period_start)->format('d/m/Y') : '' }}
                        to
                        {{ $payslip->period_end ? \Carbon\Carbon::parse($payslip->period_end)->format('d/m/Y') : '' }}
                    @elseif($payslip->invoice)
                        {{ $payslip->invoice->date_from ? \Carbon\Carbon::parse($payslip->invoice->date_from)->format('d/m/Y') : '' }}
                        to
                        {{ $payslip->invoice->date_to ? \Carbon\Carbon::parse($payslip->invoice->date_to)->format('d/m/Y') : '' }}
                    @endif
                </div>
            </div>
            <div class="info-column">
                <div class="info-item">
                    <span class="label">Created:</span> {{ $payslip->created_at->format('d/m/Y') }}
                </div>
                @if($payslip->approved_at)
                    <div class="info-item">
                        <span class="label">Approved:</span> {{ $payslip->approved_at->format('d/m/Y') }}
                    </div>
                    <div class="info-item">
                        <span class="label">Approved By:</span> {{ optional($payslip->approvedBy)->full_name ?? 'System' }}
                    </div>
                @endif
                @if($payslip->paid_at)
                    <div class="info-item">
                        <span class="label">Paid:</span> {{ $payslip->paid_at->format('d/m/Y') }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Language</th>
                <th>Duration</th>
                <th>Description</th>
                <th class="amount">Parking/Travel</th>
                <th class="amount">Amount</th>
                <th class="amount">Adjustment</th>
                <th class="amount">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($itemsDisplay ?? []) as $row)
                @php
                    $desc = $row['description'] ?? 'Item';
                    $date = $row['date'] ?? null;
                    $lang = $row['language'] ?? null;
                    $dur = $row['duration'] ?? null;
                    $parking = isset($row['parking']) ? (float)$row['parking'] : null;
                    $amount = (float)($row['amount'] ?? 0);
                    $adjustment = (float)($row['adjustment'] ?? 0);
                @endphp
                <tr>
                    <td>{{ $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '-' }}</td>
                    <td>{{ $lang ?? '-' }}</td>
                    <td>{{ $dur ?? '-' }}</td>
                    <td>{{ $desc }}</td>
                    <td class="amount">@if($parking && $parking != 0) £{{ number_format($parking, 2) }} @else - @endif</td>
                    <td class="amount">£{{ number_format($amount, 2) }}</td>
                    <td class="amount">@if($adjustment != 0) £{{ number_format($adjustment, 2) }} @else - @endif</td>
                    <td class="amount">£{{ number_format($amount + $adjustment, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="clearfix">
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="label-col">Subtotal:</td>
                    <td class="amount-col">£{{ number_format($payslip->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td class="label-col">Adjustments:</td>
                    <td class="amount-col">£{{ number_format($payslip->adjustments_total, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td class="label-col">TOTAL:</td>
                    <td class="amount-col">£{{ number_format($payslip->total, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    @if(!empty($bankDetails))
        <div class="bank-details">
            <h4>Bank Details</h4>
            <div class="bank-row">
                <div class="bank-column">
                    @if(!empty($bankDetails['bank_name']))
                        <div class="info-item">
                            <span class="label">Bank Name:</span> {{ $bankDetails['bank_name'] }}
                        </div>
                    @endif
                    @if(!empty($bankDetails['account_holder']))
                        <div class="info-item">
                            <span class="label">Account Holder:</span> {{ $bankDetails['account_holder'] }}
                        </div>
                    @endif
                </div>
                <div class="bank-column">
                    @if(!empty($bankDetails['account_number']))
                        <div class="info-item">
                            <span class="label">Account Number:</span> {{ $bankDetails['account_number'] }}
                        </div>
                    @endif
                    @if(!empty($bankDetails['sort_code']))
                        <div class="info-item">
                            <span class="label">Sort Code:</span> {{ $bankDetails['sort_code'] }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="footer">
        <p>This payslip was generated on {{ now()->format('d/m/Y H:i') }}</p>
        <p>atozinterpreting.com – A to Z Interpreting and Translation Services Ltd</p>
    </div>
</body>
</html>
