<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Client Invoice {{ $invoice->invoice_number }} (v3)</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #333; margin: 12px 16px; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; vertical-align: top; }
        th { background: #f5f5f5; text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .meta-box { border: 1px solid #9c9c9c; padding: 8px; width: 100%; font-size: 10px; background: #fff; }
        .meta-grid { width: 100%; border-collapse: collapse; }
        .meta-grid td { border: none; vertical-align: top; padding: 2px 8px; }
        .meta-grid .left { width: 50%; }
        .meta-grid .right { width: 50%; }
        .section-title { text-align: center; font-weight: bold; margin: 10px 0 6px; }
        .summary { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .summary td { border: none; padding: 4px 6px; }
        .summary tr td:first-child { width: 85%; }
        .summary tr td:last-child { width: 15%; text-align: right; }
        .header-grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .header-grid .logo-cell { padding: 0 0 6px 0; border: none; }
        .header-grid .single-box-cell { padding: 0; border: none; }
        .small { font-size: 10px; }
    </style>
</head>
<body>
@php
    $formatHrMin = function ($minutes) {
        if (!$minutes || $minutes < 0) return 'N/A';
        $h = intdiv((int)$minutes, 60);
        $m = ((int)$minutes) % 60;
        $parts = [];
        if ($h > 0) { $parts[] = $h . 'hr'; }
        if ($m > 0) { $parts[] = $m . 'min'; }
        return empty($parts) ? '0min' : implode('', $parts);
    };

    // First related job (if any) to carry job creation details
    $job = null;
    if (isset($invoice->items) && count($invoice->items)) {
        foreach ($invoice->items as $it) {
            if (isset($it->job) && $it->job) { $job = $it->job; break; }
        }
        // Fallback: if belongsTo(Job::class) returned null, resolve from concrete models
        if (!$job) {
            foreach ($invoice->items as $it) {
                if ($it->job_id) {
                    $jt = $it->job_type ?? (is_array($it->metadata) ? ($it->metadata['job_type'] ?? null) : null);
                    if ($jt && str_contains($jt, 'TranslatorJob')) {
                        $job = \App\TranslatorJob::find($it->job_id);
                    } else {
                        $job = \App\InterpreterJob::find($it->job_id) ?? \App\TranslatorJob::find($it->job_id);
                    }
                    if ($job) break;
                }
            }
        }
    }

    // Brand logo selection
    $logoCandidates = ['img/logo-full-atoz.jpg', 'img/a-to-z-logo.png', 'img/logo.jpg', 'img/logo.svg'];
    $logoFile = public_path('img/logo.svg');
    foreach ($logoCandidates as $rel) {
        $abs = public_path($rel);
        if (file_exists($abs)) { $logoFile = $abs; break; }
    }
@endphp

<table class="header-grid">
    <tr>
        <td class="logo-cell" colspan="2" style="text-align:left; border:none;">
            <img src="file://{{ $logoFile }}" alt="A to Z" style="height: 70px; margin-bottom: 6px; max-width: 100%;">
        </td>
    </tr>
    <tr>
        <td class="single-box-cell" colspan="2">
            <div class="meta-box">
                <table class="meta-grid">
                    <tr>
                        <td class="left">
                            @php
                                $md0 = (isset($invoice->items) && $invoice->items->first() && is_array($invoice->items->first()->metadata ?? null))
                                    ? $invoice->items->first()->metadata
                                    : [];
                                $billTo = is_array($md0['bill_to_override'] ?? null) ? $md0['bill_to_override'] : [];
                                $billName = ($billTo['name'] ?? '') !== '' ? $billTo['name'] : (optional(optional($invoice->client)->user)->getFullName() ?? '—');
                                $billEmail = ($billTo['email'] ?? '') !== '' ? $billTo['email'] : (optional(optional($invoice->client)->user)->email ?? '—');
                                $billPhone = (array_key_exists('phone', $billTo)) ? ((trim((string)$billTo['phone']) !== '') ? $billTo['phone'] : null) : (optional($invoice->client)->contact_number ?? null);
                                $addrParts = [];
                                $a1 = ($billTo['address_line_1'] ?? '') !== '' ? $billTo['address_line_1'] : (optional($invoice->client)->client_address_line_1 ?? null);
                                $a2 = ($billTo['address_line_2'] ?? '') !== '' ? $billTo['address_line_2'] : (optional($invoice->client)->client_address_line_2 ?? null);
                                $ac = ($billTo['county'] ?? '') !== '' ? $billTo['county'] : (optional($invoice->client)->client_county ?? null);
                                $ap = ($billTo['postcode'] ?? '') !== '' ? $billTo['postcode'] : (optional($invoice->client)->client_postcode ?? null);
                                if ($a1) $addrParts[] = $a1;
                                if ($a2) $addrParts[] = $a2;
                                if ($ac) $addrParts[] = $ac;
                                if ($ap) $addrParts[] = $ap;
                            @endphp
                            <div><strong>Bill To</strong></div>
                            <div>{{ $billName }}</div>
                            <div>{{ $billEmail }}</div>
                            @if(!empty($addrParts))
                                <div>{{ implode(', ', $addrParts) }}</div>
                            @endif
                            @if($billPhone)
                                <div>{{ $billPhone }}</div>
                            @endif
                        </td>
            <td class="right">
                <div><strong>Invoice Number:</strong> {{ $invoice->invoice_number }}</div>
                <div><strong>Invoice Date:</strong> {{ optional($invoice->invoice_date ?? $invoice->created_at)->format('d/m/Y') }}</div>
                <div><strong>Terms of Payment:</strong> {{ $invoice->payment_terms_text ?? '28 days from invoice date' }}</div>
                <div><strong>Company VAT No.:</strong> {{ config('bank.vat_number') }}</div>
                @if(optional(optional($invoice->client)->organisation)->vat_number)
                    <div><strong>Customer VAT No.:</strong> {{ optional($invoice->client->organisation)->vat_number }}</div>
                @endif
                @php
                    $displayFileRef = $invoice->file_reference;
                    $displayClientRef = $invoice->client_reference;
                @endphp
                @if(!empty(trim((string)$displayFileRef)))
                    <div><strong>File Reference:</strong> {{ $displayFileRef }}</div>
                @endif
                @if(!empty(trim((string)$displayClientRef)))
                    <div><strong>Client Reference / PO / Cost Code:</strong> {{ $displayClientRef }}</div>
                @endif
                @if(!empty(trim((string)$invoice->patient_number)))
                    <div><strong>Patient number:</strong> {{ $invoice->patient_number }}</div>
                @endif
                @if(!empty(trim((string)$invoice->pid)))
                    <div><strong>PID:</strong> {{ $invoice->pid }}</div>
                @endif
                @php
                    // Parking in v3 is the sum of per-item parking amounts; show grand total if present
                    $parkingTotal = is_numeric($invoice->parking_amount) ? (float)$invoice->parking_amount : 0.0;
                    // Travel time: prefer invoice-level aggregate
                    $travelMinutes = $invoice->travel_time_minutes ?? null;
                    // Header items stored on first item metadata
                    $headerItems = [];
                    if(isset($invoice->items) && count($invoice->items)){
                        $md0 = is_array($invoice->items->first()->metadata ?? null) ? $invoice->items->first()->metadata : [];
                        if(isset($md0['header_items']) && is_array($md0['header_items'])){
                            $headerItems = $md0['header_items'];
                        }
                    }
                @endphp
                @if(!empty($headerItems))
                    @foreach($headerItems as $hi)
                        @php $lbl = trim((string)($hi['label'] ?? '')); $val = trim((string)($hi['value'] ?? '')); @endphp
                        @if($lbl !== '' && $val !== '')
                            <div><strong>{{ $lbl }}:</strong> {{ $val }}</div>
                        @endif
                    @endforeach
                @endif
                @if($job)
                    @php
                        $jobRef = method_exists($job, 'getReferenceAttribute') ? $job->reference : ($job->client_reference ?? $job->file_reference ?? null);
                        $isInterpreter = $job instanceof \App\InterpreterJob;
                        $isTranslator = $job instanceof \App\TranslatorJob;
                        $lang = $isInterpreter ? optional($job->toLanguage)->name : trim(sprintf('%s → %s', optional($job->fromLanguage)->name, optional($job->toLanguage)->name), ' → ');
                        $apptDate = $isInterpreter ? optional($job->appointment_date)->format('d/m/Y') : optional($job->target_date)->format('d/m/Y');
                    @endphp
                    @if($lang)
                        <div><strong>Language:</strong> {{ $lang }}</div>
                    @endif
                @endif
            </td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="section-title">INVOICE FOR AN ATTACHED DOCUMENT FOR INTERPRETING/TRANSLATION</div>
<div class="section-title">SERVICES PROVIDED BY<br> A to Z Interpreting and Translation Services Ltd</div>

<table>
    <thead>
        <tr>
            <th style="width:26%;">Service (Interpreter)</th>
            <th style="width:8%;" class="text-center">Job</th>
            <th style="width:10%;" class="text-center">Date of Job</th>
            <th style="width:36%;">Invoice Details</th>
            <th style="width:20%;">Charge</th>
        </tr>
    </thead>
    <tbody>
        @php $items = $invoice->items ?? collect(); @endphp
        @forelse($items as $item)
            @php
                $baseAmount = (float) ($item->amount ?? 0);
                $charges = (float) ($item->surcharge ?? 0) + (float) ($item->other_charges ?? 0);
                $md = is_array($item->metadata ?? null) ? $item->metadata : [];
                $mdDate = $md['date'] ?? null;
                $jobDate = null;
                $resolvedJob = $item->job ?: $job;
                if (!$resolvedJob && $item->job_id) {
                    // belongsTo(Job::class) targets the abstract base; resolve from concrete models
                    $jt = $md['job_type'] ?? ($item->job_type ?? null);
                    if ($jt && str_contains($jt, 'TranslatorJob')) {
                        $resolvedJob = \App\TranslatorJob::find($item->job_id);
                    } else {
                        $resolvedJob = \App\InterpreterJob::find($item->job_id)
                            ?? \App\TranslatorJob::find($item->job_id);
                    }
                }
                if ($resolvedJob) {
                    try {
                        if ($resolvedJob instanceof \App\InterpreterJob) {
                            $jobDate = $resolvedJob->appointment_date ? \Carbon\Carbon::parse($resolvedJob->appointment_date)->format('d/m/Y') : null;
                        } elseif ($resolvedJob instanceof \App\TranslatorJob) {
                            $jobDate = $resolvedJob->target_date ? \Carbon\Carbon::parse($resolvedJob->target_date)->format('d/m/Y') : null;
                        }
                    } catch (\Throwable $e) { $jobDate = null; }
                }
                $source = $md['source'] ?? null;
                $isStandalone = ($source === 'admin_invoice_detail_modal') && empty($item->job_id);
                try { 
                    if ($isStandalone) {
                        $displayDate = $mdDate ? \Carbon\Carbon::parse($mdDate)->format('d/m/Y') : '';
                    } else {
                        $displayDate = $jobDate ?: ($mdDate ? \Carbon\Carbon::parse($mdDate)->format('d/m/Y') : '');
                    }
                }
                catch (\Throwable $e) { 
                    $displayDate = $jobDate ?: ($mdDate ?: ''); 
                }
                $job = $item->job ?? null;
                $jobRef = $item->job_id ?: '—';
                // Prefer per-item metadata values, fallback to invoice-level
                $itemInterpMinutes = $md['interpreting_time_minutes'] ?? $invoice->interpreting_time_minutes;
                $itemTravelMinutes = $md['travel_time_minutes'] ?? $invoice->travel_time_minutes;
                $interpretingText = 'Total Interpreting Time: ' . $formatHrMin($itemInterpMinutes);
                $travelText = 'Travel Time: ' . $formatHrMin($itemTravelMinutes);
                $intRateHour = isset($md['interpreting_rate_hour']) ? (float)$md['interpreting_rate_hour'] : null;
                $intOohRateHour = isset($md['interpreting_ooh_rate_hour']) ? (float)$md['interpreting_ooh_rate_hour'] : null;
                $intNormalMinutes = isset($md['interpreting_normal_minutes']) && is_numeric($md['interpreting_normal_minutes']) ? (float)$md['interpreting_normal_minutes'] : null;
                $intOohMinutes = isset($md['interpreting_ooh_minutes']) && is_numeric($md['interpreting_ooh_minutes']) ? (float)$md['interpreting_ooh_minutes'] : null;
                $travelRateHour = isset($md['travel_rate_hour']) ? (float)$md['travel_rate_hour'] : null;
                $travelFrom = $md['travel_from_postcode'] ?? null;
                $travelTo = $md['travel_to_postcode'] ?? null;
                $itemFileRef = $md['file_reference'] ?? ($invoice->file_reference ?? null);
                $itemClientRef = $md['client_reference'] ?? ($invoice->client_reference ?? null);
                // PID: prefer per-item metadata, then invoice-level PID, then job.personal_identity_number
                $itemPid = $md['pid'] ?? $invoice->pid ?? null;
                $jobPid = ($job && $job instanceof \App\InterpreterJob) ? ($job->personal_identity_number ?? null) : null;
                $pidToShow = $itemPid ?: $jobPid;
                // Patient number: prefer per-item metadata, then invoice-level, then job.patient_number
                $itemPatientNo = $md['patient_number'] ?? null;
                $invoicePatientNo = $invoice->patient_number ?? null;
                $jobPatientNo = ($job && $job instanceof \App\InterpreterJob) ? ($job->patient_number ?? null) : null;
                $patientNoToShow = $itemPatientNo ?: $invoicePatientNo ?: $jobPatientNo;
                // Parking: prefer explicit per-item metadata, fallback to charge labels in metadata
                $itemParking = null;
                if (isset($md['parking_amount']) && is_numeric($md['parking_amount'])) {
                    $itemParking = (float) $md['parking_amount'];
                } else {
                    $labels = isset($md['charge_labels']) && is_array($md['charge_labels']) ? $md['charge_labels'] : [];
                    $values = isset($md['charge_values']) && is_array($md['charge_values']) ? $md['charge_values'] : [];
                    foreach ($labels as $idx => $lbl) {
                        $norm = strtolower(trim((string)$lbl));
                        if ($norm === 'parking') {
                            $v = isset($values[$idx]) ? (float) $values[$idx] : null;
                            if ($v !== null && $v >= 0) { $itemParking = $v; }
                            break;
                        }
                    }
                }
            @endphp
            <tr>
                <td>
                    {{ $item->description ?? 'Interpreting' }}
                    @php 
                        $jobRefDisplay = $item->job ? (method_exists($item->job, 'getReferenceAttribute') ? $item->job->reference : ($item->job->client_reference ?? $item->job->file_reference ?? $item->job_id)) : ($item->job_id ?? null);
                        $itemSkill = $resolvedJob ? optional($resolvedJob->skill)->skill : null;
                    @endphp
                    @if($itemSkill)
                        <div class="small"><strong>{{ $itemSkill }}</strong></div>
                    @endif
                    @if($jobRefDisplay)
                        <div class="small">Job: {{ $jobRefDisplay }}</div>
                    @endif
                    @if($itemInterpMinutes)
                        <div class="small">{{ $interpretingText }}</div>
                    @endif
                </td>
                <td class="text-center">{{ $jobRef }}</td>
                <td class="text-center">{{ $displayDate }}</td>
                <td>
                    @if($item->job_id)
                        @if(!empty(trim((string)$itemFileRef)))
                            <div>File Reference: {{ $itemFileRef }}</div>
                        @endif
                        @if(!empty(trim((string)$itemClientRef)))
                            <div>Client Reference / PO / Cost Code: {{ $itemClientRef }}</div>
                        @endif
                        @if($intRateHour)
                            <div>Charge: £{{ number_format($intRateHour, 2) }} per hour</div>
                        @endif
                        @if($intNormalMinutes && $intRateHour)
                            <div>Normal time: {{ $formatHrMin($intNormalMinutes) }} @ £{{ number_format($intRateHour, 2) }}/hour</div>
                        @endif
                        @if($intOohMinutes && $intOohRateHour)
                            <div>Out of hours: {{ $formatHrMin($intOohMinutes) }} @ £{{ number_format($intOohRateHour, 2) }}/hour</div>
                        @endif
                        @if($itemInterpMinutes)
                            <div>{{ $interpretingText }}</div>
                        @endif
                        @if($itemTravelMinutes)
                            <div>{{ $travelText }}@if($travelRateHour) @ £{{ number_format($travelRateHour, 2) }} per hour @endif</div>
                        @endif
                        @if($travelFrom || $travelTo)
                            <div>• {{ trim(($travelFrom ?? '') . ' - ' . ($travelTo ?? ''), ' - ') }}</div>
                        @endif
                        @if($patientNoToShow)
                            <div>Patient number: {{ $patientNoToShow }}</div>
                        @endif
                        @if($pidToShow)
                            <div>PID : {{ $pidToShow }}</div>
                        @endif
                    @else
                        <!-- Standalone additional item: keep details minimal -->
                        @if($intRateHour)
                            <div>Charge: £{{ number_format($intRateHour, 2) }} per hour</div>
                        @endif
                    @endif
                </td>
                <td>
                    @php $lineTotal = $baseAmount + $charges + ((isset($itemParking) && $itemParking !== null) ? (float)$itemParking : 0.0); @endphp
                    <div>{{ $item->job_id ? 'Interpreting' : 'Item' }}: {{ number_format($baseAmount, 2) }}</div>
                    
                    @if($item->surcharge && (float)$item->surcharge > 0)
                        <div>Surcharge: {{ number_format((float)$item->surcharge, 2) }}</div>
                    @endif
                    @php
                        $labels = isset($md['charge_labels']) && is_array($md['charge_labels']) ? $md['charge_labels'] : [];
                        $values = isset($md['charge_values']) && is_array($md['charge_values']) ? $md['charge_values'] : [];
                        $seenLabels = [];
                    @endphp
                    @if(empty($labels) && $item->other_charges && (float)$item->other_charges > 0)
                        <div>Other: {{ number_format((float)$item->other_charges, 2) }}</div>
                    @endif
                    @if(!empty($labels))
                        @foreach($labels as $idx => $lbl)
                            @php
                                $val = isset($values[$idx]) ? (float)$values[$idx] : null;
                                $norm = strtolower(trim((string)$lbl));
                                $isMileage = ($norm === 'mileage');
                                $miles = $isMileage ? (isset($md['mileage_miles']) ? (float)$md['mileage_miles'] : null) : null;
                                $rate = $isMileage ? (isset($md['mileage_rate']) ? (float)$md['mileage_rate'] : null) : null;
                            @endphp
                            @if($val !== null && $val >= 0 && $norm !== 'parking' && !isset($seenLabels[$norm]))
                                @if($isMileage && $miles !== null && $rate !== null)
                                    <div>Mileage: {{ rtrim(rtrim(number_format($miles, 2), '0'), '.') }} miles @ £{{ number_format($rate, 2) }}/mile = {{ number_format($val, 2) }}</div>
                                @else
                                    <div>{{ ucfirst($lbl) }}: {{ number_format($val, 2) }}</div>
                                @endif
                                @php $seenLabels[$norm] = true; @endphp
                            @endif
                        @endforeach
                    @endif
                    @if(isset($itemParking) && $itemParking !== null)
                        <div>Parking: {{ number_format((float)$itemParking, 2) }}</div>
                    @endif
                    <hr style="border:none; border-top:1px solid #ddd; margin:4px 0;">
                    <div><strong>Total:</strong> {{ number_format($lineTotal, 2) }}</div>
                </td>
            </tr>
        @empty
            @php
                $serviceDate = optional($invoice->date_range_start ?? $invoice->invoice_date ?? $invoice->created_at)->format('d/m/Y');
                $serviceDesc = $invoice->service_description ?? 'Interpreting';
                $serviceAmount = (float) ($invoice->subtotal ?? 0);
            @endphp
            <tr>
                <td>{{ $serviceDesc }}</td>
                <td class="text-center">—</td>
                <td class="text-center">{{ $serviceDate }}</td>
                <td>
                    @if(!empty(trim((string)$invoice->file_reference)))
                        <div>File Reference: {{ $invoice->file_reference }}</div>
                    @endif
                    @if(!empty(trim((string)$invoice->client_reference)))
                        <div>Client Reference / PO / Cost Code: {{ $invoice->client_reference }}</div>
                    @endif
                    <div>Total Interpreting Time: {{ $formatHrMin($invoice->interpreting_time_minutes) }}</div>
                    <div>Travel Time: {{ $formatHrMin($invoice->travel_time_minutes) }}</div>
                </td>
                <td><div>Interpreting: {{ number_format($serviceAmount, 2) }}</div></td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="summary" style="width:100%; border-collapse:collapse; margin-top:8px;">
    <colgroup>
        <col style="width:85%;">
        <col style="width:15%;">
    </colgroup>
    <!-- Parking summary removed for v3; totals below include per-item parking within line totals -->
    <tr>
        <td><strong>Subtotal:</strong></td>
        <td style="text-align:right;">£{{ number_format($invoice->subtotal ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td><strong>VAT {{ $invoice->vat_rate ?? 0 }}%:</strong></td>
        <td style="text-align:right;">£{{ number_format($invoice->vat_amount ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td><strong>INVOICE TOTAL:</strong></td>
        <td style="text-align:right;"><strong>£{{ number_format($invoice->total ?? 0, 2) }}</strong></td>
    </tr>
</table>

<div class="small" style="margin-top:10px;">
    <p>{{ config('bank.notes') }}</p>
    <p>
        Account Name: {{ config('bank.account_name') }}<br>
        Sort Code: {{ config('bank.sort_code') }}<br>
        Account Number: {{ config('bank.account_number') }}
    </p>
    <div style="margin-top:8px;">
        <img src="file://{{ public_path('img/footer.jpg') }}" alt="Footer logos" style="width:100%; height:auto;">
    </div>
</div>
</body>
</html>
