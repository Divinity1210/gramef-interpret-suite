{{-- # --}}
@extends('layouts.app')

@section('content')
<main class="section">
    @include('partials.job-header', ['job' => $interpreterJob, 'type' => 'interpreter-jobs'])
    @php
        $timesheet = $interpreterJob->timesheet;
        $isPaid = \App\ClientInvoiceItem::where('job_id', $interpreterJob->id)->whereHas('invoice', function($q){ $q->where('status','paid'); })->exists();
    @endphp

    <div class="row">
        <div class="col-xl-6 mb-4">
            @auth
            @hasanyrole('admin|super-admin|client')
                @php
                    $hasDigitalTimesheet = !empty(optional($interpreterJob->timesheet)->agent_signature) && !empty(optional($interpreterJob->timesheet)->client_signature);
                    $uploadedSigned = $interpreterJob->documents->firstWhere('type', config('enums.document_types')['signed_timesheet']);
                @endphp

                <div class="d-flex flex-wrap">
                    @if($hasDigitalTimesheet)
                        <a href="{{ route('timesheet-pdf', ['id' => $interpreterJob->timesheet->id]) }}" class="btn btn--primary mr-2 mb-2">
                            Print timesheet
                        </a>
                    @endif
                    @if($uploadedSigned)
                        <a href="{{ $uploadedSigned->fullUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn--secondary mb-2">
                            View uploaded timesheet
                        </a>
                        <button type="button" class="btn btn--primary ml-2 mb-2" onclick="(function(u){var w=window.open(u,'_blank');if(!w){location.href=u;return;}w.addEventListener('load',function(){try{w.print();}catch(e){}});})( '{{ $uploadedSigned->fullUrl }}' )">
                            Print uploaded timesheet
                        </button>
                        @hasanyrole('admin|super-admin')
                            @php $isVerified = ($interpreterJob->signature_method === 'paper') && ($interpreterJob->timesheet_status === 'verified'); @endphp
                            @if(!$isVerified)
                                <form method="POST" action="{{ route('interpreter-jobs.timesheets.verify', $interpreterJob) }}" class="ml-2 mb-2 d-inline-block">
                                    @csrf
                                    <button type="submit" class="btn btn--primary">Verify paper timesheet</button>
                                </form>
                            @else
                                <span class="badge badge-success ml-2 mb-2">Verified</span>
                            @endif
                        @endhasanyrole
                    @endif

                    @hasanyrole('admin|super-admin')
                        @php
                            $eligibleForInvoice = ($timesheet && ($interpreterJob->statusName === 'completed'))
                                || ($timesheet && ($interpreterJob->statusName === 'cancelled') && method_exists($interpreterJob, 'isLateCancellation') && $interpreterJob->isLateCancellation());
                        @endphp
                        @if($eligibleForInvoice)
                            @php
                                $jobId = $interpreterJob->id;
                                $clientItem = \App\ClientInvoiceItem::where('job_id', $jobId)->orderByDesc('id')->first();
                                $clientInvoiceId = optional($clientItem)->client_invoice_id;
                                $agentInvoiceItem = \App\AgentInvoiceItem::where('job_id', $jobId)->orderByDesc('id')->first();
                                $payslipAmount = optional($agentInvoiceItem)->total_amount;
                                $agentEntry = \App\AgentJobEntry::where('job_id', $jobId)
                                    ->where('agent_id', $interpreterJob->agent_id ?? optional($timesheet)->agent_id)
                                    ->first();
                                $agentAmount = optional($agentEntry)->total_amount;
                                $existingLabels = [];
                                $existingValues = [];
                                $existingIntRate = '';
                                $existingIntMinutes = '';
                                $existingUseIntAsBase = '';
                                $existingIntOohRate = '';
                                $existingTravelRate = '';
                                $existingTravelMinutes = '';
                                $existingTravelFrom = '';
                                $existingTravelTo = '';
                                $existingMileageMiles = '';
                                $existingMileageRate = '';
                                if ($clientItem) {
                                    $meta = is_array($clientItem->metadata) ? $clientItem->metadata : (is_string($clientItem->metadata) ? (json_decode($clientItem->metadata, true) ?: []) : []);
                                    $existingLabels = is_array($meta) && isset($meta['charge_labels']) && is_array($meta['charge_labels']) ? $meta['charge_labels'] : [];
                                    $existingValues = is_array($meta) && isset($meta['charge_values']) && is_array($meta['charge_values']) ? $meta['charge_values'] : [];
                                    $existingIntRate = is_array($meta) && isset($meta['interpreting_rate_hour']) ? (string)$meta['interpreting_rate_hour'] : '';
                                    $existingIntMinutes = is_array($meta) && isset($meta['interpreting_time_minutes']) ? (string)$meta['interpreting_time_minutes'] : '';
                                    $existingUseIntAsBase = is_array($meta) && isset($meta['use_interpreting_as_base']) ? ((bool)$meta['use_interpreting_as_base'] ? '1' : '0') : '';
                                    $existingIntOohRate = is_array($meta) && isset($meta['interpreting_ooh_rate_hour']) ? (string)$meta['interpreting_ooh_rate_hour'] : '';
                                    $existingTravelRate = is_array($meta) && isset($meta['travel_rate_hour']) ? (string)$meta['travel_rate_hour'] : '';
                                    $existingTravelMinutes = is_array($meta) && isset($meta['travel_time_minutes']) ? (string)$meta['travel_time_minutes'] : '';
                                    $existingTravelFrom = is_array($meta) && isset($meta['travel_from_postcode']) ? (string)$meta['travel_from_postcode'] : '';
                                    $existingTravelTo = is_array($meta) && isset($meta['travel_to_postcode']) ? (string)$meta['travel_to_postcode'] : '';
                                    $existingMileageMiles = is_array($meta) && isset($meta['mileage_miles']) ? (string)$meta['mileage_miles'] : '';
                                    $existingMileageRate = is_array($meta) && isset($meta['mileage_rate']) ? (string)$meta['mileage_rate'] : '';
                                }
                                $adminBtnLabel = $clientItem ? 'Edit Amount' : 'Add Amount';
                                $baseForAdminModal = $clientItem ? (float)$clientItem->amount : ($agentAmount ?? $payslipAmount ?? 0.0);
                            @endphp
                            <form method="POST" action="{{ route('timesheet.send-invoice', $timesheet->id) }}" class="d-inline-block mr-2 mb-2">
                                @csrf
                                <button type="submit" class="btn btn--secondary js-confirm" data-confirm-message="Generate a client invoice for this timesheet?">Send Invoice</button>
                            </form>
                            <button type="button"
                                    class="btn btn--success mb-2 js-admin-add-amount"
                                    style="background-color:#28a745; color:#fff;"
                                    data-timesheet-id="{{ $timesheet->id }}"
                                    data-base-amount="{{ number_format($baseForAdminModal, 2, '.', '') }}"
                                    data-charge-labels='{{ json_encode($existingLabels) }}'
                                    data-charge-values='{{ json_encode($existingValues) }}'
                                    data-int-rate='{{ $existingIntRate }}'
                                    data-int-ooh-rate='{{ $existingIntOohRate }}'
                                    data-int-minutes='{{ $existingIntMinutes }}'
                                    data-int-use-base='{{ $existingUseIntAsBase }}'
                                    data-travel-rate='{{ $existingTravelRate }}'
                                    data-travel-minutes='{{ $existingTravelMinutes }}'
                                    data-travel-from='{{ $existingTravelFrom }}'
                                    data-travel-to='{{ $existingTravelTo }}'
                                    data-mileage-miles='{{ $existingMileageMiles }}'
                                    data-mileage-rate='{{ $existingMileageRate }}'
                                    data-appt-date='{{ optional($interpreterJob->appointment_date)->toDateString() }}'
                                    data-start-time='{{ (optional($timesheet)->agent_start_time ?: optional($timesheet)->start_time ?: $interpreterJob->start_time) }}'
                                    data-end-time='{{ (optional($timesheet)->agent_end_time ?: optional($timesheet)->end_time ?: $interpreterJob->end_time) }}'
                                    data-duration-minutes='{{ (int)((optional($timesheet)->agent_duration_hours ?? 0) * 60 + (optional($timesheet)->agent_duration_minutes ?? 0)) }}'>{{ $adminBtnLabel }}</button>
                            @if($clientInvoiceId)
                                @hasanyrole('admin|super-admin')
                                    <a href="{{ route('invoices.admin.client.preview', $clientInvoiceId) }}" class="btn btn--secondary ml-2 mb-2">Invoice Detail</a>
                                @endhasanyrole
                                @hasanyrole('client')
                                    <a href="{{ route('invoices.client.show', $clientInvoiceId) }}" class="btn btn--secondary ml-2 mb-2">Invoice Detail</a>
                                @endhasanyrole
                            @endif
                        @endif
                    @endhasanyrole
                </div>
            @endhasanyrole
            @endauth
        </div>

        <div class="col-xl-6">
            @hasanyrole('agent|client|new-agent')
                @if(!$isPaid)
                    <a href="{{ route('timesheet.edit', $interpreterJob) }}" class="btn btn--secondary mb-3">Sign Timesheet</a>
                @endif
            @endhasanyrole
            @hasanyrole('agent|new-agent')
                @include('interpreter-jobs.partials.file-upload', [
                    'interpreterJob' => $interpreterJob,
                    'type' => config('enums.document_types')['signed_timesheet'],
                    'label' => 'Timesheet (signed/scan)',
                ])
            @else
                <div class="font-weight-bold mb-2">Timesheet (signed/scan)</div>
                <ul class="list-unstyled mb-0">
                    @forelse ($interpreterJob->documents->where('type', config('enums.document_types')['signed_timesheet']) as $document)
                        <li>
                            <a href="{{ $document->fullUrl }}">
                                {{ $document->name }}
                            </a>
                        </li>
                    @empty
                        <li>This file has not been uploaded</li>
                    @endforelse
                </ul>
            @endrole
            <div class="mt-4">
                <div class="font-weight-bold mb-2">Timesheet details</div>
                @if($timesheet && ($timesheet->agent_start_time || $timesheet->agent_end_time || $timesheet->client_name || $timesheet->client_designation || $timesheet->client_phone))
                    <ul class="list-unstyled mb-0">
                        @if($timesheet->agent_start_time)<li>Agent start time: {{ $timesheet->agent_start_time }}</li>@endif
                        @if($timesheet->agent_end_time)<li>Agent end time: {{ $timesheet->agent_end_time }}</li>@endif
                        @if($timesheet->client_name)<li>Client name: {{ $timesheet->client_name }}</li>@endif
                        @if($timesheet->client_designation)<li>Client designation: {{ $timesheet->client_designation }}</li>@endif
                        @if($timesheet->client_phone)<li>Client phone: {{ $timesheet->client_phone }}</li>@endif
                    </ul>
                @else
                    <div class="text-gray">No timesheet details captured</div>
                @endif
            </div>
        </div>
    </div>
</main>

@hasanyrole('admin|super-admin')
<div id="admin-amount-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; width:min(1200px, 96vw); border-radius:8px; padding:16px; position:relative; max-height:85vh; overflow-y:auto;">
        <div class="d-flex justify-content-between align-items-center" style="margin-bottom:8px;">
            <h5 style="margin:0;">Add Charges</h5>
            <button type="button" id="admin-amount-close" class="btn btn--secondary">Close</button>
        </div>
        <div style="border-top:1px solid #eee; padding-top:12px;">
            <div class="form-group">
                <label for="admin-base-amount">Job Amount (£) <span class="badge badge-info">Inherited from Agent</span></label>
                <input id="admin-base-amount" type="number" step="0.01" min="0" class="form-control" value="0.00" />
            </div>
            <div style="display:flex; gap:12px; margin-top:8px;">
                <div style="flex:1; border:1px solid #eee; border-radius:6px; padding:10px;">
                    <div class="d-flex justify-content-between align-items-center" style="margin-bottom:6px;">
                        <strong>Interpreting</strong>
                        <label style="margin:0; font-weight:normal;"><input type="checkbox" id="admin-int-use-base" checked /> Use as Job Amount</label>
                    </div>
                    <div class="d-flex" style="gap:8px; align-items:flex-end;">
                        <div style="flex:1;">
                            <label for="admin-int-rate">Rate (£/hour)</label>
                            <input id="admin-int-rate" type="number" step="0.01" min="0" class="form-control" placeholder="e.g. 28.00" />
                        </div>
                        <div style="flex:1;">
                            <label for="admin-int-minutes">Interpreting Time (minutes)</label>
                            <input id="admin-int-minutes" type="number" step="1" min="0" class="form-control" placeholder="e.g. 120" />
                        </div>
                <div style="flex:1;">
                    <label for="admin-int-ooh-rate">Out of Hours (£/hour)</label>
                    <input id="admin-int-ooh-rate" type="number" step="0.01" min="0" class="form-control" placeholder="e.g. 35.00" />
                </div>
                        <div style="flex:1;">
                            <label>Calculated</label>
                            <div id="admin-int-cost" class="form-control" style="background:#f8f9fa;">0.00</div>
                        </div>
                    </div>
            <div class="text-muted" style="margin-top:6px;">
                Normal minutes: <span id="admin-int-normal-min">0</span> • Out of hours minutes: <span id="admin-int-ooh-min">0</span>
            </div>
                </div>
                <div style="flex:1; border:1px solid #eee; border-radius:6px; padding:10px;">
                    <div class="d-flex justify-content-between align-items-center" style="margin-bottom:6px;">
                        <strong>Travel Time</strong>
                        <label style="margin:0; font-weight:normal;"><input type="checkbox" id="admin-travel-include" /> Include as Charge</label>
                    </div>
                    <div class="d-flex" style="gap:8px; align-items:flex-end;">
                        <div style="flex:1;">
                            <label for="admin-travel-rate">Rate (£/hour)</label>
                            <input id="admin-travel-rate" type="number" step="0.01" min="0" class="form-control" placeholder="e.g. 18.00" />
                        </div>
                        <div style="flex:1;">
                            <label for="admin-travel-minutes">Travel Time (minutes)</label>
                            <input id="admin-travel-minutes" type="number" step="1" min="0" class="form-control" placeholder="e.g. 30" />
                        </div>
                        <div style="flex:1;">
                            <label>Calculated</label>
                            <div id="admin-travel-cost" class="form-control" style="background:#f8f9fa;">0.00</div>
                        </div>
                    </div>
                    <div class="d-flex" style="gap:8px; margin-top:8px;">
                        <div style="flex:1;">
                            <label for="admin-travel-from">From Postcode</label>
                            <input id="admin-travel-from" type="text" class="form-control" placeholder="e.g. B8 1SU" />
                        </div>
                        <div style="flex:1;">
                            <label for="admin-travel-to">To Postcode</label>
                            <input id="admin-travel-to" type="text" class="form-control" placeholder="e.g. B18 6HN" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex" style="justify-content:space-between; align-items:center; margin-top:8px;">
                <strong>Amount Total (£): <span id="admin-amount-total">0.00</span></strong>
            </div>
            <div class="text-muted" style="margin-top:6px;">
                Client total incl VAT: £<span id="admin-amount-total-incl-vat">0.00</span>
            </div>
            <p class="text-muted" style="margin-top:6px;">
                Note: Admin-added charges affect the client invoice only and do not modify the agent’s payslip amounts.
            </p>
            <hr />
            <div>
                <div class="d-flex justify-content-between align-items-center">
                    <label>Charges</label>
                    <div style="display:flex; gap:8px;">
                        <button type="button" id="admin-add-mileage" class="btn btn--secondary">+ Add Mileage</button>
                        <button type="button" id="admin-add-charge" class="btn btn--primary">+ Add Charge</button>
                    </div>
                </div>
                <div id="admin-charges-container" style="margin-top:8px;"></div>
            </div>
            <div style="margin-top:12px;">
                <label><input type="checkbox" id="admin-include-vat" checked /> Include VAT (20%)</label>
            </div>
            <div class="d-flex" style="justify-content:flex-end; gap:8px; margin-top:12px;">
                <button type="button" id="admin-submit-amount" class="btn btn--success" style="background-color:#28a745; color:#fff;">Submit Amount</button>
            </div>
            <div id="admin-amount-error" class="alert alert-danger" style="display:none; margin-top:10px;"></div>
        </div>
    </div>
</div>

<form id="admin-amount-submit-form" method="POST" style="display:none;">
    @csrf
    <input type="hidden" name="admin_override" value="1" />
    <input type="hidden" name="base_amount" id="admin-form-base-amount" />
    <input type="hidden" name="include_vat" id="admin-form-include-vat" value="1" />
    <input type="hidden" name="vat_rate" id="admin-form-vat-rate" value="20" />
    <input type="hidden" name="parking_amount" id="admin-form-parking" value="0" />
    <input type="hidden" name="charges[]" id="admin-form-charges" />
    <input type="hidden" name="charge_labels[]" id="admin-form-charge-labels" />
    <input type="hidden" name="mileage_miles" id="admin-form-mileage-miles" value="" />
    <input type="hidden" name="mileage_rate" id="admin-form-mileage-rate" value="" />
    <input type="hidden" name="interpreting_minutes" id="admin-form-int-minutes" value="" />
    <input type="hidden" name="interpreting_rate_hour" id="admin-form-int-rate" value="" />
    <input type="hidden" name="interpreting_ooh_rate_hour" id="admin-form-int-ooh-rate" value="" />
    <input type="hidden" name="use_interpreting_as_base" id="admin-form-int-use-base" value="1" />
    <input type="hidden" name="travel_minutes" id="admin-form-travel-minutes" value="" />
    <input type="hidden" name="travel_rate_hour" id="admin-form-travel-rate" value="" />
    <input type="hidden" name="travel_from_postcode" id="admin-form-travel-from" value="" />
    <input type="hidden" name="travel_to_postcode" id="admin-form-travel-to" value="" />
</form>
@endhasanyrole
@endsection

@push('scripts')
<script>
    (function(){
        const modal = document.getElementById('admin-amount-modal');
        const closeBtn = document.getElementById('admin-amount-close');
        const baseInput = document.getElementById('admin-base-amount');
        const totalEl = document.getElementById('admin-amount-total');
        const totalInclVatEl = document.getElementById('admin-amount-total-incl-vat');
        const addChargeBtn = document.getElementById('admin-add-charge');
        const addMileageBtn = document.getElementById('admin-add-mileage');
        const chargesContainer = document.getElementById('admin-charges-container');
        const submitBtn = document.getElementById('admin-submit-amount');
        const errorEl = document.getElementById('admin-amount-error');
        const form = document.getElementById('admin-amount-submit-form');
        const includeVatCheckbox = document.getElementById('admin-include-vat');
        const intUseBaseChk = document.getElementById('admin-int-use-base');
        const intRateInput = document.getElementById('admin-int-rate');
        const intMinutesInput = document.getElementById('admin-int-minutes');
        const intOohRateInput = document.getElementById('admin-int-ooh-rate');
        const intCostEl = document.getElementById('admin-int-cost');
        const intNormalMinEl = document.getElementById('admin-int-normal-min');
        const intOohMinEl = document.getElementById('admin-int-ooh-min');
        const travelIncludeChk = document.getElementById('admin-travel-include');
        const travelRateInput = document.getElementById('admin-travel-rate');
        const travelMinutesInput = document.getElementById('admin-travel-minutes');
        const travelCostEl = document.getElementById('admin-travel-cost');
        const travelFromInput = document.getElementById('admin-travel-from');
        const travelToInput = document.getElementById('admin-travel-to');
        let activeTimesheetId = null;
        let activeSplit = { total: 0, normal: 0, ooh: 0 };

        if (!modal) {
            return;
        }

        function format(n){
            return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2);
        }

        function parseTimeToMinutes(t){
            const s = (t || '').toString().trim();
            if (!s) return null;
            const m = s.match(/^(\d{1,2}):(\d{2})/);
            if (!m) return null;
            const hh = parseInt(m[1], 10);
            const mm = parseInt(m[2], 10);
            if (!isFinite(hh) || !isFinite(mm)) return null;
            if (hh < 0 || hh > 23 || mm < 0 || mm > 59) return null;
            return (hh * 60) + mm;
        }

        function computeOutOfHoursSplit(startTime, endTime){
            const startMin = parseTimeToMinutes(startTime);
            const endMinRaw = parseTimeToMinutes(endTime);
            if (startMin === null || endMinRaw === null) return { total: 0, normal: 0, ooh: 0 };
            let endMin = endMinRaw;
            if (endMin <= startMin) endMin += 1440;
            const total = Math.max(0, endMin - startMin);
            if (!total) return { total: 0, normal: 0, ooh: 0 };
            let normal = 0;
            const days = Math.ceil(endMin / 1440);
            for (let d = 0; d < days; d++) {
                const dayStart = d * 1440;
                const dayEnd = (d + 1) * 1440;
                const segStart = Math.max(startMin, dayStart);
                const segEnd = Math.min(endMin, dayEnd);
                if (segEnd <= segStart) continue;
                const normalStart = dayStart + 480;
                const normalEnd = dayStart + 1080;
                const overlapStart = Math.max(segStart, normalStart);
                const overlapEnd = Math.min(segEnd, normalEnd);
                if (overlapEnd > overlapStart) normal += (overlapEnd - overlapStart);
            }
            if (normal < 0) normal = 0;
            if (normal > total) normal = total;
            return { total, normal, ooh: (total - normal) };
        }

        function recalc(){
            const base = parseFloat(baseInput.value || '0');
            const intRate = parseFloat(intRateInput?.value || '0');
            const intMin = parseFloat(intMinutesInput?.value || '0');
            const oohRate = parseFloat(intOohRateInput?.value || '0');
            const splitTotal = Number(activeSplit.total || 0);
            const minutesForCalc = (!isNaN(intMin) && intMin > 0) ? intMin : splitTotal;
            let normalMin = minutesForCalc;
            let oohMin = 0;
            if (splitTotal > 0 && minutesForCalc > 0) {
                const factor = minutesForCalc / splitTotal;
                normalMin = Math.round((Number(activeSplit.normal || 0) * factor));
                if (normalMin < 0) normalMin = 0;
                if (normalMin > minutesForCalc) normalMin = minutesForCalc;
                oohMin = Math.max(0, minutesForCalc - normalMin);
            }
            if (intNormalMinEl) intNormalMinEl.textContent = String(Math.round(normalMin));
            if (intOohMinEl) intOohMinEl.textContent = String(Math.round(oohMin));
            let intCost = 0;
            if (!isNaN(intRate) && minutesForCalc > 0) {
                if (!isNaN(oohRate) && oohRate > 0 && oohMin > 0) {
                    intCost = (intRate * (normalMin/60)) + (oohRate * (oohMin/60));
                } else {
                    intCost = (intRate * (minutesForCalc/60));
                }
            }
            if (intCostEl) intCostEl.textContent = format(intCost);
            if (intUseBaseChk?.checked) {
                baseInput.value = format(intCost);
            }
            const baseEff = parseFloat(baseInput.value || '0');
            const trRate = parseFloat(travelRateInput?.value || '0');
            const trMin = parseFloat(travelMinutesInput?.value || '0');
            const trCost = (!isNaN(trRate) && !isNaN(trMin)) ? (trRate * (trMin/60)) : 0;
            if (travelCostEl) travelCostEl.textContent = format(trCost);
            let sumCharges = 0;
            if (chargesContainer) {
                chargesContainer.querySelectorAll('.charge-row').forEach(row => {
                    const val = parseFloat(row.querySelector('.charge-value').value || '0');
                    const include = row.querySelector('.charge-include').checked;
                    if (!isNaN(val) && include) sumCharges += val;
                });
            }
            if (travelIncludeChk?.checked && trCost > 0) {
                sumCharges += trCost;
            }
            const subtotal = baseEff + sumCharges;
            totalEl.textContent = format(subtotal);
            const includeVat = !!(includeVatCheckbox?.checked);
            const vatRatePct = parseFloat(document.getElementById('admin-form-vat-rate').value || '20');
            const vatRate = includeVat ? (vatRatePct / 100) : 0;
            const totalInclVat = subtotal + (subtotal * vatRate);
            totalInclVatEl.textContent = format(totalInclVat);
        }
        function addChargeRow(label='', value=''){
            const row = document.createElement('div');
            row.className = 'charge-row d-flex';
            row.style.cssText = 'gap:8px; align-items:center; margin-bottom:6px;';
            row.innerHTML = `
                <input type="text" class="form-control charge-label" placeholder="Charge Type" value="${label}" />
                <input type="number" step="0.01" min="0" class="form-control charge-value" placeholder="Value (£)" value="${value}" />
                <label style="margin:0 8px; white-space:nowrap;"><input type="checkbox" class="charge-include" checked /> Include <span class="badge badge-warning">Admin-added</span></label>
                <button type="button" class="btn btn-sm btn-danger charge-remove" title="Remove this row" aria-label="Remove" style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;padding:0;font-weight:bold;line-height:1;">×</button>
            `;
            if (!chargesContainer) return;
            chargesContainer.appendChild(row);
            row.querySelector('.charge-value').addEventListener('input', recalc);
            row.querySelector('.charge-include').addEventListener('change', recalc);
            row.querySelector('.charge-remove').addEventListener('click', () => { row.remove(); recalc(); });
        }

        function addMileageRow(miles='', rate=''){
            const row = document.createElement('div');
            row.className = 'charge-row d-flex mileage-row';
            row.style.cssText = 'gap:8px; align-items:center; margin-bottom:6px;';
            row.innerHTML = `
                <input type="text" class="form-control charge-label" value="Mileage" disabled style="max-width:110px;" />
                <input type="number" step="0.01" min="0" class="form-control mileage-miles" placeholder="Miles" value="${miles}" style="max-width:120px;" />
                <input type="number" step="0.01" min="0" class="form-control mileage-rate" placeholder="Rate (£/mile)" value="${rate}" style="max-width:160px;" />
                <input type="number" step="0.01" min="0" class="form-control charge-value" placeholder="Value (£)" value="0.00" readonly style="max-width:140px;" />
                <label style="margin:0 8px; white-space:nowrap;"><input type="checkbox" class="charge-include" checked /> Include <span class="badge badge-warning">Admin-added</span></label>
                <button type="button" class="btn btn-sm btn-danger charge-remove" title="Remove this row" aria-label="Remove" style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;padding:0;font-weight:bold;line-height:1;">×</button>
            `;
            if (!chargesContainer) return;
            chargesContainer.appendChild(row);
            const milesInput = row.querySelector('.mileage-miles');
            const rateInput = row.querySelector('.mileage-rate');
            const valueInput = row.querySelector('.charge-value');
            function updateMileage(){
                const m = parseFloat(milesInput.value || '0');
                const r = parseFloat(rateInput.value || '0');
                const v = (!isNaN(m) && !isNaN(r)) ? (m * r) : 0;
                valueInput.value = format(v);
                recalc();
            }
            milesInput.addEventListener('input', updateMileage);
            rateInput.addEventListener('input', updateMileage);
            row.querySelector('.charge-include').addEventListener('change', recalc);
            row.querySelector('.charge-remove').addEventListener('click', () => { row.remove(); recalc(); });
            updateMileage();
        }

        document.querySelectorAll('.js-admin-add-amount').forEach(btn => {
            btn.addEventListener('click', () => {
                chargesContainer.innerHTML = '';
                errorEl.style.display = 'none';
                errorEl.textContent = '';
                baseInput.value = btn.getAttribute('data-base-amount') || '0.00';
                activeTimesheetId = btn.getAttribute('data-timesheet-id');
                try {
                    const labels = JSON.parse(btn.getAttribute('data-charge-labels') || '[]') || [];
                    const values = JSON.parse(btn.getAttribute('data-charge-values') || '[]') || [];
                    labels.forEach((label, idx) => {
                        const lv = values[idx];
                        const ln = (label || '').toString().trim().toLowerCase();
                        if (ln === 'mileage') { return; }
                        addChargeRow(label, (lv !== undefined && lv !== null) ? lv : '');
                    });
                } catch (e) {}

                const mileageMiles = btn.getAttribute('data-mileage-miles') || '';
                const mileageRate = btn.getAttribute('data-mileage-rate') || '';
                if ((mileageMiles && mileageMiles !== '') || (mileageRate && mileageRate !== '')) {
                    addMileageRow(mileageMiles, mileageRate);
                }

                if (intRateInput) intRateInput.value = btn.getAttribute('data-int-rate') || '';
                if (intOohRateInput) intOohRateInput.value = btn.getAttribute('data-int-ooh-rate') || '';
                if (intMinutesInput) intMinutesInput.value = btn.getAttribute('data-int-minutes') || '';
                const useBase = btn.getAttribute('data-int-use-base');
                if (intUseBaseChk) intUseBaseChk.checked = (useBase === '' || useBase === null) ? true : (useBase === '1');
                if (travelRateInput) travelRateInput.value = btn.getAttribute('data-travel-rate') || '';
                if (travelMinutesInput) travelMinutesInput.value = btn.getAttribute('data-travel-minutes') || '';
                if (travelFromInput) travelFromInput.value = btn.getAttribute('data-travel-from') || '';
                if (travelToInput) travelToInput.value = btn.getAttribute('data-travel-to') || '';
                const st = btn.getAttribute('data-start-time') || '';
                const et = btn.getAttribute('data-end-time') || '';
                activeSplit = computeOutOfHoursSplit(st, et);
                if (intMinutesInput && (!intMinutesInput.value || String(intMinutesInput.value).trim() === '') && activeSplit.total > 0) {
                    intMinutesInput.value = String(activeSplit.total);
                }
                recalc();
                modal.style.display = 'flex';
            });
        });
        if (closeBtn) closeBtn.addEventListener('click', () => { modal.style.display = 'none'; });
        if (addChargeBtn) addChargeBtn.addEventListener('click', () => { addChargeRow(); });
        if (addMileageBtn) addMileageBtn.addEventListener('click', () => { addMileageRow(); });
        if (baseInput) baseInput.addEventListener('input', recalc);
        if (intRateInput) intRateInput.addEventListener('input', recalc);
        if (intMinutesInput) intMinutesInput.addEventListener('input', recalc);
        if (intOohRateInput) intOohRateInput.addEventListener('input', recalc);
        if (intUseBaseChk) intUseBaseChk.addEventListener('change', recalc);
        if (travelRateInput) travelRateInput.addEventListener('input', recalc);
        if (travelMinutesInput) travelMinutesInput.addEventListener('input', recalc);
        if (travelIncludeChk) travelIncludeChk.addEventListener('change', recalc);

        if (submitBtn) submitBtn.addEventListener('click', () => {
            const baseVal = parseFloat(baseInput.value || '0');
            if (isNaN(baseVal) || baseVal < 0) {
                errorEl.textContent = 'Please enter a valid base amount.';
                errorEl.style.display = 'block';
                return;
            }
            const chargeLabels = [];
            const chargeValues = [];
            let mileageMiles = '';
            let mileageRate = '';
            let travelMinutes = '';
            let travelRate = '';
            let travelFrom = '';
            let travelTo = '';
            let hasInvalid = false;
            chargesContainer.querySelectorAll('.charge-row').forEach(row => {
                const labelInput = row.querySelector('.charge-label');
                const label = (labelInput ? labelInput.value.trim() : '').replace(/^\s+|\s+$/g, '');
                const valStr = (row.querySelector('.charge-value') ? row.querySelector('.charge-value').value : '');
                const include = row.querySelector('.charge-include').checked;
                const val = parseFloat(valStr || '0');
                if (!include) return;
                if (label.length === 0) { hasInvalid = true; }
                if (isNaN(val) || val < 0) { hasInvalid = true; }
                chargeLabels.push(label);
                chargeValues.push(val.toFixed(2));
                if (row.classList.contains('mileage-row')) {
                    const mIn = row.querySelector('.mileage-miles');
                    const rIn = row.querySelector('.mileage-rate');
                    const mVal = mIn ? parseFloat(mIn.value || '0') : 0;
                    const rVal = rIn ? parseFloat(rIn.value || '0') : 0;
                    if (!isNaN(mVal) && mVal >= 0) { mileageMiles = mVal.toFixed(2); }
                    if (!isNaN(rVal) && rVal >= 0) { mileageRate = rVal.toFixed(2); }
                }
            });
            const trRateVal = parseFloat(travelRateInput?.value || '0');
            const trMinVal = parseFloat(travelMinutesInput?.value || '0');
            const trCostVal = (!isNaN(trRateVal) && !isNaN(trMinVal)) ? (trRateVal * (trMinVal/60)) : 0;
            if ((travelIncludeChk?.checked) && trCostVal > 0) {
                chargeLabels.push('Travel Time');
                chargeValues.push(trCostVal.toFixed(2));
            }
            if (!isNaN(trMinVal) && trMinVal >= 0) travelMinutes = trMinVal.toFixed(0);
            if (!isNaN(trRateVal) && trRateVal >= 0) travelRate = trRateVal.toFixed(2);
            travelFrom = travelFromInput ? (travelFromInput.value || '').trim() : '';
            travelTo = travelToInput ? (travelToInput.value || '').trim() : '';
            if (hasInvalid) {
                errorEl.textContent = 'Please ensure each charge has a label and a valid non-negative amount.';
                errorEl.style.display = 'block';
                return;
            }
            errorEl.style.display = 'none';
            const proceed = confirm('This will create a client invoice using the agent amount plus admin-added charges. Agent payslip amounts remain unchanged. Proceed?');
            if (!proceed) { return; }
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            document.getElementById('admin-form-base-amount').value = (parseFloat(baseInput.value || '0')).toFixed(2);
            document.getElementById('admin-form-include-vat').value = includeVatCheckbox?.checked ? '1' : '0';
            document.getElementById('admin-form-vat-rate').value = includeVatCheckbox?.checked ? '20' : '0';
            document.getElementById('admin-form-charges').value = JSON.stringify(chargeValues);
            document.getElementById('admin-form-charge-labels').value = JSON.stringify(chargeLabels);
            let parkingVal = 0;
            chargeLabels.forEach((label, idx) => {
                if ((label || '').trim().toLowerCase() === 'parking') {
                    const v = parseFloat(chargeValues[idx] || '0');
                    if (!isNaN(v) && v >= 0) parkingVal = v;
                }
            });
            document.getElementById('admin-form-parking').value = parkingVal.toFixed(2);
            document.getElementById('admin-form-mileage-miles').value = mileageMiles;
            document.getElementById('admin-form-mileage-rate').value = mileageRate;
            const intRateVal = parseFloat(intRateInput?.value || '0');
            const intMinVal = parseFloat(intMinutesInput?.value || '0');
            const intOohRateVal = parseFloat(intOohRateInput?.value || '0');
            document.getElementById('admin-form-int-rate').value = (!isNaN(intRateVal) && intRateVal >= 0) ? intRateVal.toFixed(2) : '';
            document.getElementById('admin-form-int-minutes').value = (!isNaN(intMinVal) && intMinVal >= 0) ? intMinVal.toFixed(0) : '';
            document.getElementById('admin-form-int-ooh-rate').value = (!isNaN(intOohRateVal) && intOohRateVal >= 0) ? intOohRateVal.toFixed(2) : '';
            document.getElementById('admin-form-int-use-base').value = intUseBaseChk?.checked ? '1' : '0';
            document.getElementById('admin-form-travel-rate').value = travelRate;
            document.getElementById('admin-form-travel-minutes').value = travelMinutes;
            document.getElementById('admin-form-travel-from').value = travelFrom;
            document.getElementById('admin-form-travel-to').value = travelTo;
            form.setAttribute('action', `${window.location.origin}/timesheet/${activeTimesheetId}/send-invoice`);
            form.submit();
        });
        if (includeVatCheckbox) includeVatCheckbox.addEventListener('change', recalc);
    })();
</script>
@endpush
