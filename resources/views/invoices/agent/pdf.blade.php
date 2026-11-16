<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Agent Invoice PDF</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
        }

        .header {
            margin-bottom: 10px;
        }

        .header h2 {
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: left;
        }

        .totals {
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>Agent Invoice #{{ $invoice->invoice_number }}</h2>
        <div>Agent: {{ optional($invoice->agent->user)->getFullName() }}</div>
        <div>Period: {{ optional($invoice->date_from)->format('Y-m-d') }} —
            {{ optional($invoice->date_to)->format('Y-m-d') }}</div>
        <div>Status: {{ ucfirst($invoice->status ?? 'draft') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Job ID</th>
                <th>Language</th>
                <th>Service Type</th>
                <th>Description</th>
                <th>Duration</th>
                <th>Base</th>
                <th>Expenses</th>
                <th>Parking/Travel</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                @php
                    $computedRow = isset($computed) ? ($computed[$item->id] ?? null) : null;
                    $job = $item->job ?? null;
                    $jtMeta = $item->metadata['job_type'] ?? '';
                    $jt = $item->job_type ?? $jtMeta;
                    $dateStr = $computedRow['date'] ?? null;
                    if (!$dateStr) {
                        $date = $job ? ($job->appointment_date ?? $job->target_date) : null;
                        $dateStr = $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '-';
                    }
                    $language = $computedRow['language'] ?? null;
                    if (!$language) {
                        $language = '-';
                        if ($job) {
                            if (strpos((string) $jt, 'TranslatorJob') !== false) {
                                $language = trim(((string) optional(optional($job)->fromLanguage)->name) . ' → ' . ((string) optional(optional($job)->toLanguage)->name));
                            } else {
                                $language = optional(optional($job)->toLanguage)->name ?? '-';
                            }
                        }
                    }
                    $duration = $computedRow['duration'] ?? null;
                    if (!$duration) {
                        $duration = '-';
                        if ($job && strpos((string) $jt, 'InterpreterJob') !== false) {
                            $duration = ((int) ($job->duration_hours ?? 0)) . 'h ' . ((int) ($job->duration_minutes ?? 0)) . 'm';
                        }
                    }
                @endphp
                <tr>
                    <td>{{ $dateStr }}</td>
                    <td>{{ $item->job_id }}</td>
                    <td>{{ $language }}</td>
                    <td>{{ $job ? optional($job->skill)->skill ?? '-' : '-' }}</td>
                    <td>{{ $item->description }}</td>
                    <td>{{ $duration }}</td>
                    <td>£{{ number_format($item->base_amount, 2) }}</td>
                    <td>£{{ number_format($item->expenses_amount, 2) }}</td>
                    <td>£{{ number_format($item->surcharge, 2) }}</td>
                    <td><strong>£{{ number_format($item->total_amount, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div>Subtotal: £{{ number_format($invoice->subtotal ?? 0, 2) }}</div>
        @php $expensesTotal = optional($invoice->items)->sum(function ($i) {
        return ($i->expenses_amount + $i->surcharge + $i->other_charges); }); @endphp
        <div>Expenses: £{{ number_format($expensesTotal, 2) }}</div>
        <div><strong>Total: £{{ number_format($invoice->total_amount ?? 0, 2) }}</strong></div>
    </div>
</body>

</html>