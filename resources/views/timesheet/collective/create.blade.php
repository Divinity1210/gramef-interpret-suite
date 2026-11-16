@extends('layouts.app')

@section('content')
<div class="container py-4">
    <!-- Lightweight page loading overlay -->
    <div id="page-loader" aria-hidden="true" style="display:none; position:fixed; inset:0; background:rgba(255,255,255,0.7); z-index:9999; align-items:center; justify-content:center; flex-direction:column;">
        <div class="spinner-border text-primary" role="status" aria-label="Loading"></div>
        <div class="mt-2 text-primary fw-bold">Please wait…</div>
    </div>
    
    <div class="mb-3">
        <h2 class="h4">Create Consolidated Client Invoice</h2>
        @if($client)
            <div class="text-muted">Client: {{ $client->name ?? optional($client->user)->getFullName() ?? 'N/A' }} | Date Range: {{ $filters['start_date'] ?? 'N/A' }} to {{ $filters['end_date'] ?? 'N/A' }}</div>
        @endif
    </div>

    @if(!empty($builderErrors))
        <div class="alert alert-danger" role="alert">
            <strong>Cannot create invoice yet.</strong>
            <ul class="mb-0">
                @foreach($builderErrors as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <?php
        $initialDisplayed = is_countable($rows) ? count($rows) : 0;
        $initialCap = config('app.collective_builder_cap', 200);
        $initialTotal = $initialDisplayed; // Fallback until AJAX loads meta
        $initialCapped = false;
        $initialFilterSummary = '';
        if (!empty($filters)) {
            $parts = [];
            if (!empty($filters['client_name'])) $parts[] = 'Client: '.$filters['client_name'];
            if (!empty($filters['agent_name'])) $parts[] = 'Agent: '.$filters['agent_name'];
            if (!empty($filters['ref'])) $parts[] = 'Ref: '.$filters['ref'];
            if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
                $parts[] = 'Date: '.($filters['start_date'] ?? '—').' → '.($filters['end_date'] ?? '—');
            }
            $initialFilterSummary = implode(' • ', $parts);
        }
        $meta = $resultMeta ?? null;
    ?>

    <!-- Cap banner + quick filters -->
    <div id="cap-banner" class="alert alert-warning d-flex justify-content-between align-items-center" role="alert"
         data-cap="{{ $meta['cap'] ?? $initialCap }}"
         data-capped="{{ isset($meta['capped']) && $meta['capped'] ? 1 : 0 }}"
         data-total="{{ $meta['total'] ?? $initialTotal }}"
         data-displayed="{{ $meta['displayed'] ?? $initialDisplayed }}"
         data-filter-summary="{{ $meta['filter_summary'] ?? $initialFilterSummary }}">
        <div>
            <strong>Showing <span class="js-displayed">{{ $meta['displayed'] ?? $initialDisplayed }}</span> of <span class="js-total">{{ $meta['total'] ?? $initialTotal }}</span> results</strong>
            <span class="js-cap-msg">@if(isset($meta['capped']) && $meta['capped']) (capped at {{ $meta['cap'] }}) @endif</span>
            <div class="small text-muted js-filter-summary">Filters: {{ ($meta['filter_summary'] ?? $initialFilterSummary) ?: 'none' }}</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="applyFilters">Apply Filters</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="resetFilters">Reset</button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end" id="filterBar">
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1" for="filterClient">Client</label>
                    <input type="text" class="form-control form-control-sm" id="filterClient" value="{{ $filters['client_name'] ?? '' }}" placeholder="e.g. FM Ade">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1" for="filterAgent">Agent</label>
                    <input type="text" class="form-control form-control-sm" id="filterAgent" value="{{ $filters['agent_name'] ?? '' }}" placeholder="e.g. John Smith">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label mb-1" for="filterRef">Job Ref</label>
                    <input type="text" class="form-control form-control-sm" id="filterRef" value="{{ $filters['ref'] ?? '' }}" placeholder="e.g. 14788">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1" for="collectiveDateFilter">Date Range</label>
                    @include('partials.filters.date-range', [
                        'mode' => 'single',
                        'inputId' => 'collectiveDateFilter',
                        'paramName' => 'date',
                        'startName' => 'start_date',
                        'endName' => 'end_date',
                        'startId' => 'filterStart',
                        'endId' => 'filterEnd',
                        'mirrorHidden' => true,
                        'placeholder' => 'Filter By Date...'
                    ])
                </div>
                <div class="col-12 col-md-4" style="position:relative;">
                    <input type="hidden" id="filterCompanyId" value="{{ $filters['company_id'] ?? '' }}">
                    <label class="form-label mb-1" for="filterCompany">Company</label>
                    <input type="text" class="form-control form-control-sm" id="filterCompany" value="" placeholder="e.g. Acme Hospital" autocomplete="off">
                    <div id="collectiveCompanySuggestions" class="list-group" style="display:none; position:absolute; top:calc(100% + 4px); left:0; width:100%; max-height:280px; overflow-y:auto; box-shadow:0 10px 20px rgba(0,0,0,0.15); border:1px solid #e5e7eb; border-radius:6px; background:#fff; z-index:1035;"></div>
                    <div id="collectiveSelectedCompany" class="selected-client-chip" style="display:none; position:absolute; right:8px; top:-10px;"><span id="collectiveSelectedCompanyLabel"></span><button type="button" id="collectiveSelectedCompanyClear" class="chip- btn-close close" aria-label="Clear" aria-label="Close">&times;</button></div>
                </div>
            </div>
        </div>
    </div>

    <form action="{{ route('timesheet.collective.store') }}" method="POST" id="collective-form">
        @csrf
        <input type="hidden" name="client_id" value="{{ optional($client)->id }}" />
        <input type="hidden" name="include_vat" id="include_vat" value="1" />
        <input type="hidden" name="vat_rate" id="vat_rate" value="20" />

        <div class="table-responsive" style="position:relative;">
            <table class="table table-sm table-bordered align-middle" id="rows-table">
                <thead class="thead-light">
                    <tr>
                        <th class="sel-col sticky-col">Sel</th>
                        <th>Job</th>
                        <th>Date</th>
                        <th style="width:120px;">Time</th>
                        <th>Language</th>
                        <th>Agent</th>
                        <th style="width:120px;">Normal (min)</th>
                        <th style="width:120px;">OOH (min)</th>
                        <th style="width:140px;">Normal (£/h)</th>
                        <th style="width:140px;">OOH (£/h)</th>
                        <th style="width:140px;">Payslip (£)</th>
                        <th style="width:160px;">Agent Entry (£)</th>
                        <th style="width:160px;">Amount (£)</th>
                        <th>Description</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $r)
                        <tr data-job-id="{{ $r['job_id'] }}"
                            data-ts-id="{{ $r['timesheet_id'] }}"
                            data-client-id="{{ $r['client_id'] ?? '' }}"
                            data-start-time="{{ $r['start_time'] ?? '' }}"
                            data-end-time="{{ $r['end_time'] ?? '' }}"
                            class="js-row {{ $r['already_invoiced'] ? 'table-warning' : '' }}">
                            <td class="sel-col sticky-col">
                                <input type="checkbox" class="js-select" aria-label="Select job {{ $r['job_id'] }}" {{ $r['has_amount'] && !$r['already_invoiced'] ? 'checked' : '' }} {{ $r['already_invoiced'] ? 'disabled' : '' }} />
                            </td>
                            <td>{{ $r['job_id'] }}</td>
                            <td>{{ $r['date'] }}</td>
                            <td class="js-time">{{ trim(($r['start_time'] ?? '') . ' - ' . ($r['end_time'] ?? ''), ' -') }}</td>
                            <td>{{ $r['language'] }}</td>
                            <td>{{ $r['agent_name'] ?? '—' }}</td>
                            <td class="js-normal-min text-end">0</td>
                            <td class="js-ooh-min text-end">0</td>
                            <td>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm js-int-rate" value="" {{ $r['already_invoiced'] ? 'disabled' : '' }} />
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm js-int-ooh-rate" value="" {{ $r['already_invoiced'] ? 'disabled' : '' }} />
                            </td>
                            <td>
                                @if(isset($r['payslip_amount']) && $r['payslip_amount'] !== null)
                                    £{{ number_format($r['payslip_amount'], 2) }}
                                @else
                                    <span class="text-muted">--</span>
                                @endif
                            </td>
                            <td>
                                @if(isset($r['agent_entry_total']) && $r['agent_entry_total'] !== null)
                                    <div>£{{ number_format($r['agent_entry_total'], 2) }}</div>
                                    @if(!empty($r['agent_entry_breakdown']))
                                        @php $b = $r['agent_entry_breakdown']; @endphp
                                        <div class="muted-breakdown">base £{{ number_format($b['base'] ?? 0, 2) }} • exp £{{ number_format($b['expenses'] ?? 0, 2) }} • sur £{{ number_format($b['surcharge'] ?? 0, 2) }} • other £{{ number_format($b['other'] ?? 0, 2) }}</div>
                                    @endif
                                @else
                                    <span class="text-muted">--</span>
                                @endif
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm js-amount" value="{{ number_format($r['amount'], 2, '.', '') }}" {{ $r['already_invoiced'] ? 'disabled' : '' }} />
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm js-desc" value="{{ $r['description'] ?? '' }}" {{ $r['already_invoiced'] ? 'disabled' : '' }} />
                            </td>
                            <td>
                                @if($r['already_invoiced'])
                                    <span class="badge bg-danger">Already Invoiced</span>
                                @elseif(!$r['has_amount'])
                                    <span class="badge bg-warning text-dark">Missing Amount</span>
                                @else
                                    <span class="badge bg-success">Ready</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination bar -->
        <div class="d-flex justify-content-between align-items-center mt-2">
            <div class="text-muted small">Page <span id="pg-current">1</span> of <span id="pg-total">1</span></div>
            <nav class="collective-pagination" id="paginationBar" aria-label="Pagination"></nav>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="toggleVat" checked>
                    <label class="form-check-label" for="toggleVat">Include VAT</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-control form-control-sm" type="number" step="0.1" id="vatRateInput" value="20" style="width:90px;" aria-label="VAT rate">
                    <span class="ms-1">%</span>
                    <span id="vatError" class="small text-danger ms-2" style="display:none;"></span>
                </div>
            </div>
            <div class="text-end">
                <div>Subtotal: £<span id="subtotal">0.00</span></div>
                <div>VAT: £<span id="vat">0.00</span></div>
                <div class="fw-bold">Total: £<span id="total">0.00</span></div>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary" id="createBtn" disabled>Create Invoice</button>
            <a href="{{ route('timesheet.index', $filters) }}" class="btn btn-outline-secondary">Back to Timesheets</a>
        </div>

        <!-- Hidden items payload container -->
        <div id="items-container" class="d-none"></div>
    </form>
</div>
@endsection

@push('styles')
<style>
    /* Sticky header + sticky first column for better UX on wide tables */
    #rows-table thead th {
        position: sticky; top: 0; z-index: 5; background: #f8f9fa;
    }
    #rows-table th.sticky-col, #rows-table td.sticky-col {
        position: sticky; left: 0; z-index: 6; background: #fff; box-shadow: 2px 0 0 rgba(0,0,0,0.05);
    }
    /* Keep checkbox centered and compact */
    #rows-table .sel-col { width: 48px; text-align: center; }
    /* Ensure native checkbox renders visibly inside sticky column regardless of Bootstrap rules */
    #rows-table .sel-col input[type="checkbox"] {
        position: static !important;        /* keep inside cell flow */
        margin: 0 !important;               /* neutralize negative margins */
        float: none !important;             /* avoid odd alignment */
        display: inline-block !important;   /* allow centering */
        vertical-align: middle;
        width: 16px; height: 16px;          /* explicit size to avoid collapse */
        opacity: 1 !important; visibility: visible !important; pointer-events: auto !important;
        -webkit-appearance: checkbox; -moz-appearance: checkbox; appearance: auto; /* force native look */
        accent-color: #0d6efd;              /* clearer on white (Chrome/Edge) */
    }
    /* Small helper text styling for breakdowns */
    .muted-breakdown { font-size: 12px; color: #6c757d; line-height: 1.2; }
    /* Pagination bar */
    .collective-pagination { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
    .collective-pagination .page-link { cursor:pointer; padding:4px 8px; border:1px solid #dee2e6; border-radius:4px; background:#fff; }
    .collective-pagination .page-link[aria-disabled="true"] { opacity: .5; cursor:not-allowed; }
    .collective-pagination .page-link.active { background:#0d6efd; color:#fff; border-color:#0d6efd; }
</style>
@endpush

@push('scripts')
<script>
(function(){
    const form = document.getElementById('collective-form');
    const table = document.getElementById('rows-table');
    const createBtn = document.getElementById('createBtn');
    const subtotalEl = document.getElementById('subtotal');
    const vatEl = document.getElementById('vat');
    const totalEl = document.getElementById('total');
    const itemsContainer = document.getElementById('items-container');
    const toggleVat = document.getElementById('toggleVat');
    const vatRateInput = document.getElementById('vatRateInput');
    const includeVatInput = document.getElementById('include_vat');
    const vatRateHidden = document.getElementById('vat_rate');
    const vatError = document.getElementById('vatError');
    const loader = document.getElementById('page-loader');

    // AJAX filtering UI elements
    const DATA_ENDPOINT = "{{ route('timesheet.collective.data') }}";
    const capBanner = document.getElementById('cap-banner');
    const tableBody = table.querySelector('tbody');
    const filterClientInput = document.getElementById('filterClient');
    const filterAgentInput = document.getElementById('filterAgent');
    const filterRefInput = document.getElementById('filterRef');
    const filterStartInput = document.getElementById('filterStart');
    const filterEndInput = document.getElementById('filterEnd');
    const filterCompanyInput = document.getElementById('filterCompany');
    const filterCompanyIdHidden = document.getElementById('filterCompanyId');
    const collectiveCompanySuggestions = document.getElementById('collectiveCompanySuggestions');
    const collectiveSelectedCompany = document.getElementById('collectiveSelectedCompany');
    const collectiveSelectedCompanyLabel = document.getElementById('collectiveSelectedCompanyLabel');
    const collectiveSelectedCompanyClear = document.getElementById('collectiveSelectedCompanyClear');
    const companyByIdUrlTemplate = "{{ route('api.companies.show.get', ['company' => '*']) }}";
    const applyFiltersBtn = document.getElementById('applyFilters');
    const resetFiltersBtn = document.getElementById('resetFilters');
    const DATA_CACHE = {};
    const CACHE_TTL_MS = 60 * 1000; // soft TTL for in-memory cache
    const paginationBar = document.getElementById('paginationBar');
    const pgCurrent = document.getElementById('pg-current');
    const pgTotal = document.getElementById('pg-total');
    let CURRENT_PAGE = 1; let PER_PAGE = 25; // default, can be overridden by server meta

    function showLoader(){ if(loader) loader.style.display = 'flex'; }
    function hideLoader(){ if(loader) loader.style.display = 'none'; }

    function toNum(v){ const n = parseFloat(v); return isNaN(n) ? 0 : n; }
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

    function sanitizeVatRate(){
        let v = toNum(vatRateInput.value);
        if (!isFinite(v)) v = 0;
        if (v < 0) v = 0;
        if (v > 50) v = 50; // guard against accidental extreme values
        // keep one decimal place to align with backend expectations
        vatRateInput.value = v.toFixed(1);
        return v;
    }

    function updateVatControlsUI(){
        const includeVat = !!toggleVat.checked;
        includeVatInput.value = includeVat ? 1 : 0;
        // Enable/disable VAT rate input for clearer UX
        vatRateInput.disabled = !includeVat;
        if (!includeVat) {
            vatError.style.display = 'none';
            vatRateHidden.value = '0.0';
        } else {
            // If turning VAT on and field is empty or zero, default sensibly to 20.0
            const current = toNum(vatRateInput.value);
            if (!current) {
                vatRateInput.value = '20.0';
            }
        }
    }

    function collectSelected(){
        const rows = [...table.querySelectorAll('tbody tr.js-row')];
        const selected = [];
        let errors = [];
        rows.forEach((tr) => {
            const checkbox = tr.querySelector('.js-select');
            const amountInput = tr.querySelector('.js-amount');
            const descInput = tr.querySelector('.js-desc');
            const jobId = tr.getAttribute('data-job-id');
            const tsId = tr.getAttribute('data-ts-id');
            const clientId = tr.getAttribute('data-client-id');
            const alreadyInvoiced = tr.classList.contains('table-warning');
            const selectedState = checkbox && checkbox.checked && !checkbox.disabled;
            const amount = toNum(amountInput ? amountInput.value : 0);
            const intRateEl = tr.querySelector('.js-int-rate');
            const intOohRateEl = tr.querySelector('.js-int-ooh-rate');
            const intRate = intRateEl ? intRateEl.value : '';
            const intOohRate = intOohRateEl ? intOohRateEl.value : '';
            const intTotalMin = tr.dataset.intTotalMinutes || '';
            const intNormalMin = tr.dataset.intNormalMinutes || '';
            const intOohMin = tr.dataset.intOohMinutes || '';

            // validation per row
            if (selectedState && (alreadyInvoiced || amount <= 0)) {
                if (alreadyInvoiced) errors.push(`Job ${jobId} already invoiced`);
                if (amount <= 0) errors.push(`Missing amount for Job ${jobId}`);
            }

            if (selectedState) {
                selected.push({
                    timesheet_id: tsId,
                    job_id: jobId,
                    client_id: clientId,
                    amount: amount,
                    description: descInput ? descInput.value : '',
                    interpreting_rate_hour: intRate,
                    interpreting_ooh_rate_hour: intOohRate,
                    interpreting_time_minutes: intTotalMin,
                    interpreting_normal_minutes: intNormalMin,
                    interpreting_ooh_minutes: intOohMin,
                });
            }
        });
        return {selected, errors};
    }

    function renderTotals(){
        const {selected} = collectSelected();
        const subtotal = selected.reduce((acc, it) => acc + toNum(it.amount), 0);
        const includeVat = toggleVat.checked;
        const vatRate = includeVat ? sanitizeVatRate() : 0;
        const vatAmount = includeVat ? (subtotal * (vatRate/100)) : 0;
        const total = subtotal + vatAmount;
        subtotalEl.textContent = subtotal.toFixed(2);
        vatEl.textContent = vatAmount.toFixed(2);
        totalEl.textContent = total.toFixed(2);
        includeVatInput.value = includeVat ? 1 : 0;
        vatRateHidden.value = vatRate.toFixed(1);
    }

    function syncItemsPayload(){
        const {selected} = collectSelected();
        itemsContainer.innerHTML = '';
        // Sync client_id hidden input from selected rows (enforce single client)
        const clientInput = form.querySelector('input[name="client_id"]');
        const uniqueClientIds = [...new Set(selected.map(it => String(it.client_id || '').trim()).filter(Boolean))];
        if (clientInput) {
            if (uniqueClientIds.length === 1) { clientInput.value = uniqueClientIds[0]; }
            else { clientInput.value = ''; }
        }
        selected.forEach((it, idx) => {
            const base = `items[${idx}]`;
            itemsContainer.insertAdjacentHTML('beforeend',
                `<input type="hidden" name="${base}[timesheet_id]" value="${it.timesheet_id}">`+
                `<input type="hidden" name="${base}[job_id]" value="${it.job_id}">`+
                `<input type="hidden" name="${base}[amount]" value="${toNum(it.amount).toFixed(2)}">`+
                `<input type="hidden" name="${base}[description]" value="${(it.description||'').replace(/\"/g,'&quot;')}">`+
                `<input type="hidden" name="${base}[interpreting_time_minutes]" value="${it.interpreting_time_minutes || ''}">`+
                `<input type="hidden" name="${base}[interpreting_rate_hour]" value="${it.interpreting_rate_hour || ''}">`+
                `<input type="hidden" name="${base}[interpreting_ooh_rate_hour]" value="${it.interpreting_ooh_rate_hour || ''}">`+
                `<input type="hidden" name="${base}[interpreting_normal_minutes]" value="${it.interpreting_normal_minutes || ''}">`+
                `<input type="hidden" name="${base}[interpreting_ooh_minutes]" value="${it.interpreting_ooh_minutes || ''}">`
            );
        });
    }

    function validateForm(){
        const {selected, errors} = collectSelected();
        const hasRows = selected.length > 0;
        const validAmounts = selected.every(it => toNum(it.amount) > 0);
        // VAT validation
        let vatOk = true;
        let vatMsg = '';
        if (toggleVat.checked) {
            const rate = toNum(vatRateInput.value);
            if (rate < 0 || rate > 50) {
                vatOk = false; vatMsg = 'VAT rate must be between 0 and 50%';
            }
        }
        if (vatError) {
            if (!vatOk) { vatError.textContent = vatMsg; vatError.style.display = 'inline'; }
            else { vatError.textContent = ''; vatError.style.display = 'none'; }
        }
        const ok = hasRows && validAmounts && errors.length === 0 && vatOk;
        createBtn.disabled = !ok;
        return ok;
    }

    function updateCapUI(meta){
        if (!capBanner) return;
        const displayedEl = capBanner.querySelector('.js-displayed');
        const totalElBanner = capBanner.querySelector('.js-total');
        const capMsgEl = capBanner.querySelector('.js-cap-msg');
        const filterSummaryEl = capBanner.querySelector('.js-filter-summary');
        if (displayedEl) displayedEl.textContent = meta.displayed ?? 0;
        if (totalElBanner) totalElBanner.textContent = meta.total ?? meta.displayed ?? 0;
        if (capMsgEl) capMsgEl.textContent = meta.capped ? `(capped at ${meta.cap})` : '';
        if (filterSummaryEl) filterSummaryEl.textContent = 'Filters: ' + (meta.filter_summary || 'none');
        capBanner.dataset.cap = meta.cap;
        capBanner.dataset.capped = meta.capped ? 1 : 0;
        capBanner.dataset.total = meta.total ?? meta.displayed ?? 0;
        capBanner.dataset.displayed = meta.displayed ?? 0;
        capBanner.dataset.filterSummary = meta.filter_summary || '';
    }

    function initOutOfHoursColumns(){
        const rows = [...table.querySelectorAll('tbody tr.js-row')];
        rows.forEach(tr => {
            const startTime = tr.getAttribute('data-start-time') || '';
            const endTime = tr.getAttribute('data-end-time') || '';
            const split = computeOutOfHoursSplit(startTime, endTime);
            tr.dataset.intTotalMinutes = String(split.total || 0);
            tr.dataset.intNormalMinutes = String(split.normal || 0);
            tr.dataset.intOohMinutes = String(split.ooh || 0);
            const timeCell = tr.querySelector('.js-time');
            if (timeCell && (!timeCell.textContent || !timeCell.textContent.trim())) {
                timeCell.textContent = [startTime, endTime].filter(Boolean).join(' - ');
            }
            const normalCell = tr.querySelector('.js-normal-min');
            const oohCell = tr.querySelector('.js-ooh-min');
            if (normalCell) normalCell.textContent = String(split.normal || 0);
            if (oohCell) oohCell.textContent = String(split.ooh || 0);
        });
    }

    function renderTableRows(rows){
        tableBody.innerHTML = '';
        (rows || []).forEach(r => {
            const disabled = !!r.already_invoiced;
            const checked = !!r.has_amount && !disabled;
            const amt = Number(r.amount || 0).toFixed(2);
            const agentName = r.agent_name || '—';
            const desc = r.description || '';
            const startTime = (r.start_time || '').toString();
            const endTime = (r.end_time || '').toString();
            const split = computeOutOfHoursSplit(startTime, endTime);
            const payslip = (r.payslip_amount !== null && r.payslip_amount !== undefined) ? `£${Number(r.payslip_amount||0).toFixed(2)}` : '<span class="text-muted">--</span>';
            const aeTotal = (r.agent_entry_total !== null && r.agent_entry_total !== undefined) ? Number(r.agent_entry_total||0).toFixed(2) : null;
            const bd = r.agent_entry_breakdown || {};
            const breakdownHtml = (aeTotal !== null)
                ? `<div>£${aeTotal}</div><div class="muted-breakdown">base £${Number(bd.base||0).toFixed(2)} • exp £${Number(bd.expenses||0).toFixed(2)} • sur £${Number(bd.surcharge||0).toFixed(2)} • other £${Number(bd.other||0).toFixed(2)}</div>`
                : '<span class="text-muted">--</span>';
            tableBody.insertAdjacentHTML('beforeend',
                `<tr data-job-id="${r.job_id}" data-ts-id="${r.timesheet_id}" data-client-id="${r.client_id ?? ''}" data-start-time="${startTime}" data-end-time="${endTime}" class="js-row ${disabled ? 'table-warning' : ''}">`+
                `<td class="sel-col sticky-col"><input type="checkbox" class="js-select" aria-label="Select job ${r.job_id ?? ''}" ${checked ? 'checked' : ''} ${disabled ? 'disabled' : ''}></td>`+
                `<td>${r.job_id ?? ''}</td>`+
                `<td>${r.date ?? ''}</td>`+
                `<td class="js-time">${[startTime, endTime].filter(Boolean).join(' - ')}</td>`+
                `<td>${r.language ?? ''}</td>`+
                `<td>${agentName}</td>`+
                `<td class="js-normal-min text-end">${split.normal || 0}</td>`+
                `<td class="js-ooh-min text-end">${split.ooh || 0}</td>`+
                `<td><input type="number" step="0.01" min="0" class="form-control form-control-sm js-int-rate" value="" ${disabled ? 'disabled' : ''}></td>`+
                `<td><input type="number" step="0.01" min="0" class="form-control form-control-sm js-int-ooh-rate" value="" ${disabled ? 'disabled' : ''}></td>`+
                `<td>${payslip}</td>`+
                `<td>${breakdownHtml}</td>`+
                `<td><input type="number" step="0.01" min="0" class="form-control form-control-sm js-amount" value="${amt}" ${disabled ? 'disabled' : ''}></td>`+
                `<td><input type="text" class="form-control form-control-sm js-desc" value="${desc}" ${disabled ? 'disabled' : ''}></td>`+
                `<td>${disabled ? '<span class="badge bg-danger">Already Invoiced</span>' : (!r.has_amount ? '<span class="badge bg-warning text-dark">Missing Amount</span>' : '<span class="badge bg-success">Ready</span>')}</td>`+
                `</tr>`
            );
        });
        initOutOfHoursColumns();
        renderTotals();
        syncItemsPayload();
        validateForm();
    }

    function getFilterParams(){
        return {
            client_name: (filterClientInput.value || '').trim() || undefined,
            agent_name: (filterAgentInput.value || '').trim() || undefined,
            ref: (filterRefInput.value || '').trim() || undefined,
            start_date: filterStartInput.value || undefined,
            end_date: filterEndInput.value || undefined,
            company_id: (filterCompanyIdHidden && filterCompanyIdHidden.value || '').trim() || undefined,
        };
    }

    async function fetchAndRender(params, opts){
        const options = Object.assign({ force: false }, (opts||{}));
        const key = JSON.stringify(params);
        createBtn.disabled = true;
        showLoader();
        try {
            const entry = DATA_CACHE[key];
            const fresh = entry && ((Date.now() - entry.ts) < CACHE_TTL_MS);
            if (!options.force && fresh) {
                const cached = entry.data;
                renderTableRows(cached.rows || []);
                updateCapUI(cached.meta || {});
                renderPagination(cached.meta || {});
                return;
            }
            const url = new URL(DATA_ENDPOINT, window.location.origin);
            Object.keys(params || {}).forEach(k => {
                const v = params[k];
                if (v !== undefined && v !== '') url.searchParams.set(k, v);
            });
            const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('Network error: '+res.status);
            const json = await res.json();
            DATA_CACHE[key] = { data: json, ts: Date.now() };
            renderTableRows(json.rows || []);
            updateCapUI({
                cap: json.meta?.cap,
                capped: json.meta?.capped,
                total: json.meta?.total,
                displayed: json.meta?.displayed,
                filter_summary: json.meta?.filter_summary,
            });
            renderPagination(json.meta || {});
        } catch (err) {
            console.error(err);
            alert('Unable to fetch filtered data. Please try again or adjust filters.');
        } finally {
            hideLoader();
            renderTotals();
            syncItemsPayload();
            validateForm();
        }
    }

    function initCapUiFromDataset(){
        if (!capBanner) return;
        const cap = parseInt(capBanner.dataset.cap || '0', 10);
        const capped = capBanner.dataset.capped === '1';
        const total = parseInt(capBanner.dataset.total || capBanner.dataset.displayed || '0', 10);
        const displayed = parseInt(capBanner.dataset.displayed || '0', 10);
        const filter_summary = capBanner.dataset.filterSummary || '';
        updateCapUI({ cap, capped, total, displayed, filter_summary });
    }

    // Live listeners for table interactions
    table.addEventListener('input', function(e){
        if (e.target.classList.contains('js-amount') || e.target.classList.contains('js-desc') || e.target.classList.contains('js-int-rate') || e.target.classList.contains('js-int-ooh-rate')) {
            const tr = e.target.closest('tr');
            if (tr && e.target.classList.contains('js-amount')) {
                tr.dataset.manualAmount = '1';
            }
            if (tr && (e.target.classList.contains('js-int-rate') || e.target.classList.contains('js-int-ooh-rate'))) {
                tr.dataset.manualAmount = '';
                const split = computeOutOfHoursSplit(tr.getAttribute('data-start-time') || '', tr.getAttribute('data-end-time') || '');
                tr.dataset.intTotalMinutes = String(split.total || 0);
                tr.dataset.intNormalMinutes = String(split.normal || 0);
                tr.dataset.intOohMinutes = String(split.ooh || 0);
                const normalCell = tr.querySelector('.js-normal-min');
                const oohCell = tr.querySelector('.js-ooh-min');
                if (normalCell) normalCell.textContent = String(split.normal || 0);
                if (oohCell) oohCell.textContent = String(split.ooh || 0);

                const intRateEl = tr.querySelector('.js-int-rate');
                const intOohRateEl = tr.querySelector('.js-int-ooh-rate');
                const amountEl = tr.querySelector('.js-amount');
                const intRate = parseFloat(intRateEl?.value || '');
                const oohRate = parseFloat(intOohRateEl?.value || '');
                if (amountEl && isFinite(intRate) && intRate >= 0 && split.total > 0 && tr.dataset.manualAmount !== '1') {
                    const normalMin = Number(split.normal || 0);
                    const oohMin = Number(split.ooh || 0);
                    let next = 0;
                    if (isFinite(oohRate) && oohRate >= 0 && oohMin > 0) {
                        next = (intRate * (normalMin/60)) + (oohRate * (oohMin/60));
                    } else {
                        next = (intRate * (split.total/60));
                    }
                    amountEl.value = Number(next).toFixed(2);
                }
            }
            renderTotals();
            syncItemsPayload();
            validateForm();
        }
    });
    table.addEventListener('change', function(e){
        if (e.target.classList.contains('js-select')) {
            renderTotals();
            syncItemsPayload();
            validateForm();
        }
    });
    toggleVat.addEventListener('change', function(){ updateVatControlsUI(); renderTotals(); validateForm(); });
    vatRateInput.addEventListener('input', function(){ sanitizeVatRate(); renderTotals(); validateForm(); });

    // Filter bar actions
    if (applyFiltersBtn) applyFiltersBtn.addEventListener('click', function(){ CURRENT_PAGE = 1; const p = Object.assign({}, getFilterParams(), { page: CURRENT_PAGE, per_page: PER_PAGE }); fetchAndRender(p, { force: true }); });
    if (resetFiltersBtn) resetFiltersBtn.addEventListener('click', function(){
        filterClientInput.value = '';
        filterAgentInput.value = '';
        filterRefInput.value = '';
        filterStartInput.value = '';
        filterEndInput.value = '';
        if (filterCompanyInput) filterCompanyInput.value = '';
        if (filterCompanyIdHidden) filterCompanyIdHidden.value = '';
        if (collectiveSelectedCompany) collectiveSelectedCompany.style.display = 'none';
        CURRENT_PAGE = 1; fetchAndRender({ page: CURRENT_PAGE, per_page: PER_PAGE }, { force: true });
    });

    function showCompanyChip(id, label){ if (!collectiveSelectedCompany) return; filterCompanyIdHidden.value = id; collectiveSelectedCompanyLabel.textContent = label || ('Company #' + id); collectiveSelectedCompany.style.display = 'inline-flex'; }
    function hideCompanyChip(){ if (!collectiveSelectedCompany) return; filterCompanyIdHidden.value = ''; collectiveSelectedCompany.style.display = 'none'; collectiveSelectedCompanyLabel.textContent = ''; }
    async function fetchCompanies(q){
        try {
            const url = `${"{{ route('admin.job-payments.companies') }}"}?q=${encodeURIComponent(q)}&limit=20`;
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!resp.ok) return [];
            const data = await resp.json();
            return (data.results || []);
        } catch (e) { return []; }
    }
    function renderCompanySuggestions(items){
        if (!collectiveCompanySuggestions) return;
        collectiveCompanySuggestions.innerHTML = '';
        if (!items.length) { collectiveCompanySuggestions.style.display = 'none'; return; }
        items.forEach(it => {
            const a = document.createElement('a');
            a.href = '#';
            a.className = 'list-group-item list-group-item-action';
            a.textContent = it.label || it.name || ('#'+it.id);
            a.addEventListener('mousedown', function(ev){ ev.preventDefault(); showCompanyChip(it.id, it.label || it.name); if (filterCompanyInput) filterCompanyInput.value = ''; collectiveCompanySuggestions.style.display = 'none'; CURRENT_PAGE = 1; const p = Object.assign({}, getFilterParams(), { page: CURRENT_PAGE, per_page: PER_PAGE }); fetchAndRender(p, { force: true }); });
            collectiveCompanySuggestions.appendChild(a);
        });
        collectiveCompanySuggestions.style.display = 'block';
    }
    async function fetchCompanyNameById(id){
        try {
            const url = companyByIdUrlTemplate.replace('*', encodeURIComponent(id));
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!resp.ok) return null;
            const data = await resp.json();
            return (data && data.name) ? data.name : null;
        } catch (e) { return null; }
    }
    let companiesTimer = null;
    if (filterCompanyInput) {
        filterCompanyInput.addEventListener('input', function(){ const q = filterCompanyInput.value.trim(); clearTimeout(companiesTimer); companiesTimer = setTimeout(async function(){ const items = await fetchCompanies(q); renderCompanySuggestions(items); }, 200); });
        filterCompanyInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); CURRENT_PAGE = 1; const p = Object.assign({}, getFilterParams(), { page: CURRENT_PAGE, per_page: PER_PAGE }); fetchAndRender(p, { force: true }); } });
        filterCompanyInput.addEventListener('blur', function(){ setTimeout(function(){ if (collectiveCompanySuggestions) collectiveCompanySuggestions.style.display = 'none'; }, 150); });
    }
    if (collectiveSelectedCompanyClear) collectiveSelectedCompanyClear.addEventListener('click', function(){ hideCompanyChip(); if (filterCompanyInput) filterCompanyInput.value = ''; if (collectiveCompanySuggestions) collectiveCompanySuggestions.style.display = 'none'; CURRENT_PAGE = 1; const p = Object.assign({}, getFilterParams(), { page: CURRENT_PAGE, per_page: PER_PAGE }); fetchAndRender(p, { force: true }); });
    if (filterCompanyIdHidden && filterCompanyIdHidden.value) { showCompanyChip(filterCompanyIdHidden.value); fetchCompanyNameById(filterCompanyIdHidden.value).then(name => { if (name) { showCompanyChip(filterCompanyIdHidden.value, name); } }); }

    function renderPagination(meta){
        if (!paginationBar) return;
        const total = parseInt(meta.total || '0', 10);
        const perPage = parseInt(meta.per_page || PER_PAGE, 10);
        const page = parseInt(meta.current_page || CURRENT_PAGE, 10);
        const totalPages = Math.max(1, Math.ceil(total / Math.max(1, perPage)));
        CURRENT_PAGE = page; PER_PAGE = perPage;
        pgCurrent.textContent = String(page); pgTotal.textContent = String(totalPages);
        const parts = [];
        const mkBtn = (label, targetPage, disabled=false, active=false) => {
            return `<button class="page-link ${active?'active':''}" ${disabled?'aria-disabled="true"':''} data-page="${targetPage}">${label}</button>`;
        };
        parts.push(mkBtn('« Prev', Math.max(1, page-1), page<=1));
        // show up to 5 pages around current
        const start = Math.max(1, page-2);
        const end = Math.min(totalPages, page+2);
        for (let p=start; p<=end; p++) { parts.push(mkBtn(String(p), p, false, p===page)); }
        parts.push(mkBtn('Next »', Math.min(totalPages, page+1), page>=totalPages));
        paginationBar.innerHTML = parts.join('');
        paginationBar.querySelectorAll('.page-link').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const disabled = btn.getAttribute('aria-disabled') === 'true';
                if (disabled) return;
                const target = parseInt(btn.dataset.page || '1', 10);
                const params = Object.assign({}, getFilterParams(), { page: target, per_page: PER_PAGE });
                fetchAndRender(params);
            });
        });
    }

    // Initial
    initCapUiFromDataset();
    updateVatControlsUI();
    initOutOfHoursColumns();
    renderTotals();
    syncItemsPayload();
    validateForm();
    // Initialize pagination from server-side meta if present on first load
    renderPagination({
        total: parseInt(document.getElementById('cap-banner')?.dataset.total || '0', 10),
        displayed: parseInt(document.getElementById('cap-banner')?.dataset.displayed || '0', 10),
        current_page: 1,
        per_page: PER_PAGE
    });

    form.addEventListener('submit', function(e){
        if (!validateForm()) {
            e.preventDefault();
            alert('Please fix validation errors before creating the invoice. Ensure all selected rows have a valid amount and are not already invoiced.');
            return false;
        }
        // Show loader while submitting to improve UX on slower responses
        showLoader();
    });
})();
</script>
@endpush
