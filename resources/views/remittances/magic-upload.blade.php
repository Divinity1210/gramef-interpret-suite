<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>1-Click Remittance Confirmation — {{ $tenantBrand->brand_name ?? 'GRAMEF InterpretFlow' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="/css/gramef-motionsites.css?v=20261116" rel="stylesheet">
    <style>
        html, body {
            background: #07090E;
            color: #0F172A;
            margin: 0;
            padding: 16px;
            min-height: 100vh;
            font-family: var(--font-sans);
        }
        .rm-card {
            max-width: 620px;
            margin: 20px auto;
            background: #FFFFFF;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.45);
        }
        .rm-head {
            background: #0B0F19;
            color: #FFFFFF;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .rm-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            font-size: 14px;
            font-family: var(--font-sans);
        }
    </style>
</head>
<body>
    <div class="rm-card">
        <div class="rm-head">
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="ms-brand-mark">{{ $tenantBrand->logo_text ?? 'GRAMEF' }}</div>
                <div>
                    <div style="font-weight:700;font-size:15px;">1-Click Accounts Payable Remittance</div>
                    <div style="font-family:var(--font-mono);font-size:10.5px;color:#34D399;letter-spacing:0.1em;text-transform:uppercase;">No Login Required • Stops All Reminders Immediately</div>
                </div>
            </div>
        </div>

        <div style="padding:24px;">
            @if (session('success'))
                <div style="background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46;padding:14px 16px;border-radius:12px;margin-bottom:18px;font-size:13.5px;font-weight:600;">
                    {{ session('success') }}
                </div>
            @endif

            <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:14px;padding:16px;margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:11px;font-family:var(--font-mono);color:#64748B;">INVOICE NUMBER</div>
                        <div class="tabular-nums" style="font-size:20px;font-weight:700;color:#0F172A;">{{ $invoice->invoice_number }}</div>
                        <div style="font-size:13px;color:#475569;margin-top:2px;">
                            {{ optional(optional($invoice->client)->organisation)->organisation_company ?? optional(optional($invoice->client)->user)->getFullName() }}
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:11px;font-family:var(--font-mono);color:#64748B;">AMOUNT DUE</div>
                        <div class="tabular-nums" style="font-size:24px;font-weight:700;color:#2563EB;">£{{ number_format((float)$invoice->total, 2) }}</div>
                    </div>
                </div>
            </div>

            @if ($allClientUnpaid->count() > 1)
                <div style="margin-bottom:20px;">
                    <div style="font-size:12px;font-weight:700;color:#334155;margin-bottom:8px;">Summary Statement of Account ({{ $allClientUnpaid->count() }} Open Invoices)</div>
                    <div style="border:1px solid #E2E8F0;border-radius:12px;overflow:hidden;">
                        @foreach ($allClientUnpaid as $openInv)
                            <div style="display:flex;justify-content:space-between;padding:8px 12px;border-bottom:1px solid #F1F5F9;font-size:12.5px;">
                                <span class="tabular-nums font-weight-bold">{{ $openInv->invoice_number }}</span>
                                <span style="color:#64748B;">{{ optional($openInv->invoice_date)->format('d M Y') }}</span>
                                <span class="tabular-nums font-weight-bold">£{{ number_format((float)$openInv->total, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('magic.remittance.store', $token) }}">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">BACS / Payment Reference *</label>
                        <input type="text" name="reference" class="rm-input" required placeholder="e.g. NHS-BACS-88412" value="{{ old('reference', 'BACS-' . strtoupper(substr($invoice->invoice_number, -6))) }}">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Amount Paid (£) *</label>
                        <input type="number" step="0.01" name="amount" class="rm-input tabular-nums" required value="{{ old('amount', $invoice->total) }}">
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Remittance Note (Optional)</label>
                    <textarea name="notes" rows="2" class="rm-input" placeholder="e.g. Included in Tuesday NHS Trust BACS payment run."></textarea>
                </div>
                <button type="submit" style="width:100%;padding:14px 20px;border-radius:999px;border:0;background:#0F172A;color:#FFFFFF;font-size:15px;font-weight:700;cursor:pointer;">
                    Confirm Payment &amp; Stop Reminders
                </button>
            </form>
        </div>
    </div>
</body>
</html>
