@extends('layouts.app')

@section('content')
@php
    $dev = isset($devMode) && $devMode ? true : false;
    $baseData = $baseDataUrl ?? url('/admin/job-payments/data');
    $baseExport = $baseExportUrl ?? url('/admin/job-payments/export');
    $basePreview = isset($basePreviewUrl) ? $basePreviewUrl : url('/admin/job-payments/preview');
    $baseClients = isset($baseClientsUrl) ? $baseClientsUrl : url('/admin/job-payments/clients');
    $baseCompanies = isset($baseCompaniesUrl) ? $baseCompaniesUrl : url('/admin/job-payments/companies');
    $baseExportStart = isset($baseExportStartUrl) ? $baseExportStartUrl : url('/admin/job-payments/export/start');
    $baseExportStatus = isset($baseExportStatusUrl) ? $baseExportStatusUrl : url('/admin/job-payments/export/status/0');
    $baseExportDownload = isset($baseExportDownloadUrl) ? $baseExportDownloadUrl : url('/admin/job-payments/export/download/0');
    $defaultMode = $defaultMode ?? 'client';
    $availableColumns = $columns ?? [
        'job_id' => 'Job ID',
        'job_type' => 'Job Type',
        'client_name' => 'Client',
        'agent_name' => 'Agent',
        'language' => 'Language',
        'appointment_date' => 'Appointment Date',
        'invoice_number' => 'Invoice #',
        'invoice_total' => 'Invoice Total',
        'paid_amount' => 'Paid Amount',
        'balance_amount' => 'Balance',
        'last_payment_date' => 'Last Payment',
        'payment_status' => 'Payment Status',
        'mode' => 'Perspective',
    ];
@endphp

<div class="container-fluid mt-3 mb-5" style="max-width: 1400px;">
<h3>Accounting Reports @if($dev) <small class="text-muted">(Dev Preview)</small> @endif</h3>

    <div class="filters">
        <form id="filters-form" class="row g-3">
            <input type="hidden" id="base-data-url" value="{{ $baseData }}" />
            <input type="hidden" id="base-export-url" value="{{ $baseExport }}" />
            <input type="hidden" id="base-preview-url" value="{{ $basePreview }}" />
            <input type="hidden" id="base-clients-url" value="{{ $baseClients }}" />
            <input type="hidden" id="base-companies-url" value="{{ $baseCompanies }}" />
            <input type="hidden" id="base-export-start-url" value="{{ $baseExportStart }}" />
            <input type="hidden" id="base-export-status-url" value="{{ $baseExportStatus }}" />
            <input type="hidden" id="base-export-download-url" value="{{ $baseExportDownload }}" />

            <!-- Top row: core filters -->
            <div class="col-lg-3 col-md-6">
                <label class="form-label visually-hidden">Perspective</label>
                <select class="form-select section__input input input--select" name="mode" id="mode">
                    <option value="client" {{ $defaultMode === 'client' ? 'selected' : '' }}>Against Client</option>
                    <option value="agent" {{ $defaultMode === 'agent' ? 'selected' : '' }}>Against Agent</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label visually-hidden">Status</label>
                <select class="form-select section__input input input--select" name="status" id="status">
                    <option value="unpaid" selected>Unpaid</option>
                    <option value="paid">Paid</option>
                    <option value="not_invoiced">Not Invoiced</option>
                    <option value="all">All</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label visually-hidden">Job Type</label>
                <select class="form-select section__input input input--select" name="job_type" id="job_type">
                    <option value="all" selected>All Job Types</option>
                    <option value="interpreter">Interpreter</option>
                    <option value="translator">Translator</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label visually-hidden">Search</label>
                <input type="text" class="form-control section__input input input--text" name="search" id="search" placeholder="Job ID or Name" />
            </div>
            <!-- Client filter: unified search + selection -->
            <div class="col-lg-3 col-md-6">
                <label class="form-label visually-hidden">Client Search</label>
                <div class="client-search-wrapper">
                    <input type="text" class="form-control section__input input input--text" id="client-search" placeholder="Filter By Client..." autocomplete="off" />
                    <!-- Suggestions list rendered dynamically (overlay) -->
                    <div id="client-suggestions" class="list-group" style="display:none;"></div>
                </div>
                <!-- Selected client pill -->
                <div id="selected-client-chip" class="selected-client-chip" style="display:none;">
                    <span id="selected-client-label"></span>
                    <button type="button" id="selected-client-clear" class="chip- btn-close close" aria-label="Clear" aria-label="Close">&times;</button>
                </div>
                <small class="text-muted">Type to search, then click a suggestion to select. Clear to remove.</small>
            </div>
            <!-- Company filter: unified search + selection -->
            <div class="col-lg-3 col-md-6">
                <label class="form-label visually-hidden">Company Search</label>
                <div class="client-search-wrapper">
                    <input type="text" class="form-control section__input input input--text" id="company-search" placeholder="Filter By Company..." autocomplete="off" />
                    <div id="company-suggestions" class="list-group" style="display:none;"></div>
                </div>
                <div id="selected-company-chip" class="selected-client-chip" style="display:none;">
                    <span id="selected-company-label"></span>
                    <button type="button" id="selected-company-clear" class="chip- btn-close close" aria-label="Clear" aria-label="Close">&times;</button>
                </div>
                <small class="text-muted">Type to search, then click a suggestion to select. Clear to remove.</small>
            </div>

            <!-- Second row: date range + actions -->
            <div class="col-lg-6 col-md-12">
                @include('partials.filters.date-range', [
                    'mode' => 'single',
                    'formId' => 'filters-form',
                    'inputId' => 'job-payments-date',
                    'paramName' => 'date',
                    'startName' => 'date_from',
                    'endName' => 'date_to',
                    'startId' => 'date_from',
                    'endId' => 'date_to',
                    'mirrorHidden' => true,
                    'placeholder' => 'Filter By Date...',
                    'submitWhen' => 'change'
                ])
            </div>
            <div class="col-lg-6 col-md-12">
                <div class="filters-actions">
                    <button type="button" id="client-clear" class="btn btn-outline-secondary">Clear Client</button>
                    <button type="button" id="company-clear" class="btn btn-outline-secondary">Clear Company</button>
                    <button type="button" id="apply-filters" class="btn btn-primary">Filter</button>
                </div>
            </div>

            <div class="col-12 mt-2">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="form-label m-0">Columns</label>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Column visibility controls">
                        <button type="button" id="columns-select-all" class="btn btn-outline-secondary">Select All</button>
                        <button type="button" id="columns-clear-all" class="btn btn-outline-secondary">Clear All</button>
                    </div>
                </div>
                <div class="row mt-1">
                    @foreach($availableColumns as $key => $label)
                        <div class="col-md-2">
                            <div class="form-check">
                                <input class="form-check-input column-check" type="checkbox" value="{{ $key }}" id="col-{{ $key }}" checked>
                                <label class="form-check-label" for="col-{{ $key }}">{{ $label }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </form>
    </div>

    <div class="mb-3 d-flex gap-2">
        <button id="export-unpaid" class="btn btn-outline-danger">Export Unpaid Jobs</button>
        <button id="export-paid" class="btn btn-outline-success">Export Paid Jobs</button>
        <button id="export-selected" class="btn btn-outline-secondary">Export Selected</button>
        <button id="preview-export" class="btn btn-outline-primary">Preview (Top 25)</button>
        <button id="create-client-invoice" class="btn btn-success">Create Client Invoice from Selected</button>
    </div>

    <div id="export-status-bar" class="alert alert-info" role="alert">
        <span id="export-status-text">Preparing export…</span>
        <span class="spinner" id="export-status-spinner" aria-hidden="true"></span>
        <a href="#" id="export-download-link" class="btn btn-sm btn-primary ms-2" style="display:none;">Download</a>
    </div>

    <div class="table-wrap">
        <div class="loading" id="loading">Loading...</div>
        <table class="table table-striped" id="results-table">
            <thead>
            <tr>
                <th><input type="checkbox" id="select-all" /></th>
                <th class="col-job_id">Job ID</th>
                <th class="col-job_type">Job Type</th>
                <th class="col-client_name">Client</th>
                <th class="col-agent_name">Agent</th>
                <th class="col-language">Language</th>
                <th class="col-appointment_date">Appointment Date</th>
                <th class="col-invoice_number">Invoice #</th>
                <th class="col-invoice_total">Invoice Total</th>
                <th class="col-paid_amount">Paid Amount</th>
                <th class="col-balance_amount">Balance</th>
                <th class="col-payment_status">Status</th>
                <th class="col-last_payment_date">Last Payment</th>
                <th class="col-mode">Perspective</th>
            </tr>
            </thead>
            <tbody id="results-body">
            </tbody>
        </table>
        <div class="d-flex justify-content-between align-items-center" id="pagination-bar">
            <div id="pagination-info" class="small text-muted"></div>
            <div class="pagination-controls">
                <button class="btn btn-sm btn-outline-primary" id="prev-page">Prev</button>
                <button class="btn btn-sm btn-outline-primary" id="next-page">Next</button>
            </div>
        </div>
    </div>

    <!-- Hidden export form for CSV downloads -->
        <form id="export-form" method="POST" action="{{ $baseExport }}" target="_blank" style="display:none;">
            <input type="hidden" name="mode" id="export-mode" />
            <input type="hidden" name="status" id="export-status" />
            <input type="hidden" name="search" id="export-search" />
            <input type="hidden" name="date_from" id="export-date-from" />
            <input type="hidden" name="date_to" id="export-date-to" />
            <input type="hidden" name="client_id" id="export-client-id" />
            <input type="hidden" name="agent_id" id="export-agent-id" />
            <input type="hidden" name="company_id" id="export-company-id" />
            <input type="hidden" name="job_type" id="export-job-type" />
            <input type="hidden" name="columns" id="export-columns" />
            <input type="hidden" name="selected_job_ids" id="export-selected-ids" />
            @csrf
        </form>
    </div>

    <!-- Preview modal -->
    <div class="modal-backdrop" id="preview-backdrop"></div>
    <div class="modal-panel" id="preview-panel">
    <div class="modal-header">
        <h5 class="m-0">Export Preview</h5>
        <button class="btn btn-sm btn-outline-secondary" id="preview-close">Close</button>
    </div>
    <div class="modal-body">
        <div id="preview-loading" style="display:none;" class="mb-2"><span class="spinner"></span> Loading preview…</div>
        <div class="table-responsive">
            <table class="table table-sm table-striped" id="preview-table">
                <thead><tr id="preview-head"></tr></thead>
                <tbody id="preview-body"></tbody>
            </table>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary" id="preview-close-2">Close</button>
    </div>
    </div>

    <!-- JSON payload for column labels (consumed by the JS below) -->
    <script type="application/json" id="column-labels-json">@json($availableColumns)</script>

@endsection

@push('styles')
<style>
    .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; }
    .badge-paid { background: #28a745; color: #fff; }
    .badge-unpaid { background: #dc3545; color: #fff; }
    .filters { background: #f8f9fa; padding: 1rem; border-radius: 0.25rem; margin-bottom: 1rem; }
    .table-wrap { position: relative; }
    .loading { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: none; }
    /* Simple modal styling */
    .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: none; }
    /* Ensure correct stacking order so the panel is above the backdrop */
    .modal-backdrop { z-index: 1040; }
    .modal-panel { position: fixed; top: 6%; left: 50%; transform: translateX(-50%); width: min(1600px, 98vw); background: #fff; border-radius: 12px; box-shadow: 0 12px 36px rgba(0,0,0,0.22); display: none; z-index: 1050; }
    .modal-header { padding: 0.875rem 1.25rem; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between; }
    .modal-body { padding: 1rem 1.25rem; max-height: 75vh; overflow: auto; }
    .modal-footer { padding: 0.75rem 1rem; border-top: 1px solid #eee; text-align: right; }
    .spinner { display: inline-block; width: 1rem; height: 1rem; border: 2px solid #ccc; border-top-color: #333; border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    #export-status-bar { display:none; }
    /* Client search suggestions overlay */
    .client-search-wrapper { position: relative; }
    #client-suggestions, #company-suggestions { position: absolute; top: calc(100% + 4px); left: 0; width: 100%; max-height: 280px; overflow-y: auto; box-shadow: 0 10px 20px rgba(0,0,0,0.15); border: 1px solid #e5e7eb; border-radius: 6px; background: #fff; z-index: 1035; }
    /* Selected client chip */
    .selected-client-chip { display: inline-flex; align-items: center; gap: 6px; background: #eaf2ff; color: #0b5ed7; border: 1px solid #cfe2ff; border-radius: 999px; padding: 4px 10px; font-size: 0.875rem; margin-top: 6px; }
    .selected-client-chip .chip-close { background: transparent; border: 0; color: #0b5ed7; cursor: pointer; font-size: 1rem; line-height: 1; }

    /* Keep pagination buttons side-by-side */
    #pagination-bar .pagination-controls { display: inline-flex; gap: 10px; }
    #pagination-bar .pagination-controls .btn { width: auto; display: inline-flex; align-items: center; justify-content: center; }

    /* Filters actions: arrange neatly side-by-side */
    .filters-actions { display: flex; gap: 12px; align-items: flex-end; height: 100%; }
    @media (max-width: 767px) {
        .filters-actions { margin-top: 6px; }
    }

    /* Filter pills/styles are now shared via SCSS (resources/sass/components/_filters.scss) */
</style>
@endpush

@push('scripts')
<script>
    const state = { page: 1, per_page: 20, total: 0, has_more: false };
    let selectedClientId = '';
    let selectedClientLabel = '';
    let selectedCompanyId = '';
    let selectedCompanyLabel = '';
    // Map of column keys to human-friendly labels (from Blade)
    // Read JSON from the dedicated script tag to avoid inline Blade JSON directives inside JS
    const COLUMN_LABELS = (() => {
        try {
            const el = document.getElementById('column-labels-json');
            return el ? JSON.parse(el.textContent) : {};
        } catch (e) {
            console.error('Failed to parse COLUMN_LABELS JSON', e);
            return {};
        }
    })();

    function getSelectedColumns() {
        const checks = document.querySelectorAll('.column-check');
        const cols = [];
        checks.forEach(ch => { if (ch.checked) cols.push(ch.value); });
        return cols;
    }

    function applyFilters(page=1) {
        state.page = page;
        const mode = document.getElementById('mode').value;
        const status = document.getElementById('status').value;
        const job_type = document.getElementById('job_type').value;
        const search = document.getElementById('search').value;
        const client_id = selectedClientId;
        const company_id = selectedCompanyId;
        const date_from = document.getElementById('date_from').value;
        const date_to = document.getElementById('date_to').value;
        const baseDataUrl = document.getElementById('base-data-url').value;
        const url = new URL(baseDataUrl, window.location.origin);
        url.searchParams.set('mode', mode);
        url.searchParams.set('status', status);
        url.searchParams.set('job_type', job_type);
        url.searchParams.set('search', search);
        if (client_id) url.searchParams.set('client_id', client_id);
        if (company_id) url.searchParams.set('company_id', company_id);
        if (date_from) url.searchParams.set('date_from', date_from);
        if (date_to) url.searchParams.set('date_to', date_to);
        url.searchParams.set('page', state.page);
        url.searchParams.set('per_page', state.per_page);

        const loading = document.getElementById('loading');
        loading.style.display = 'block';
        fetch(url.toString(), { credentials: 'same-origin' })
            .then(r => r.json())
            .then(payload => {
                loading.style.display = 'none';
                renderTable(payload.data || []);
                const pag = payload.pagination || { page: 1, per_page: 20, total: 0, has_more: false };
                state.page = pag.page; state.per_page = pag.per_page; state.total = pag.total; state.has_more = pag.has_more;
                document.getElementById('pagination-info').innerText = `Page ${state.page} • Showing ${state.per_page} • Total ${state.total}`;
            })
            .catch(err => { loading.style.display = 'none'; console.error(err); alert('Failed to load data'); });
    }

    function renderTable(rows) {
        const tbody = document.getElementById('results-body');
        tbody.innerHTML = '';
        rows.forEach(r => {
            const tr = document.createElement('tr');
            const badgeClass = r.payment_status === 'Paid' ? 'badge-paid' : 'badge-unpaid';
            tr.innerHTML = `
                <td><input type="checkbox" class="row-check" data-job-id="${r.job_id}" data-job-type="${r.job_type}"></td>
                <td class="col-job_id">${r.job_id ?? ''}</td>
                <td class="col-job_type">${r.job_type ?? ''}</td>
                <td class="col-client_name">${r.client_name ?? ''}</td>
                <td class="col-agent_name">${r.agent_name ?? ''}</td>
                <td class="col-language">${r.language ?? ''}</td>
                <td class="col-appointment_date">${r.appointment_date ?? ''}</td>
                <td class="col-invoice_number">${r.invoice_number ?? ''}</td>
                <td class="col-invoice_total">${r.invoice_total ?? ''}</td>
                <td class="col-paid_amount">${r.paid_amount ?? ''}</td>
                <td class="col-balance_amount">${r.balance_amount ?? ''}</td>
                <td class="col-payment_status"><span class="badge ${badgeClass}">${r.payment_status}</span></td>
                <td class="col-last_payment_date">${r.last_payment_date ?? ''}</td>
                <td class="col-mode">${r.mode ?? ''}</td>
            `;
            tbody.appendChild(tr);
        });
        updateColumnVisibility();
    }

    // Show/hide table columns based on selected checkboxes
    function updateColumnVisibility() {
        const selected = new Set(getSelectedColumns());
        const allKeys = ['job_id','job_type','client_name','agent_name','language','appointment_date','invoice_number','invoice_total','paid_amount','balance_amount','payment_status','last_payment_date','mode'];
        allKeys.forEach(key => {
            const visible = selected.has(key);
            document.querySelectorAll('.col-' + key).forEach(el => {
                el.style.display = visible ? '' : 'none';
            });
        });
    }

    function exportAction(targetStatus, selectedOnly=false) {
        const mode = document.getElementById('mode').value;
        const job_type = document.getElementById('job_type').value;
        const search = document.getElementById('search').value;
        const client_id = selectedClientId;
        const company_id = selectedCompanyId;
        const date_from = document.getElementById('date_from').value;
        const date_to = document.getElementById('date_to').value;
        const cols = getSelectedColumns();
        const ids = selectedOnly ? Array.from(document.querySelectorAll('.row-check')).filter(ch => ch.checked).map(ch => ch.getAttribute('data-job-id')) : [];

        const baseStartUrl = document.getElementById('base-export-start-url').value;
        const statusBase = document.getElementById('base-export-status-url').value; // ends with /0
        const downloadBase = document.getElementById('base-export-download-url').value; // ends with /0
        const csrf = document.querySelector('#export-form input[name=_token]')?.value;

        const statusBar = document.getElementById('export-status-bar');
        const statusText = document.getElementById('export-status-text');
        const statusSpinner = document.getElementById('export-status-spinner');
        const downloadLink = document.getElementById('export-download-link');
        statusText.innerText = 'Preparing export…';
        statusSpinner.style.display = 'inline-block';
        downloadLink.style.display = 'none';
        statusBar.style.display = 'block';

        fetch(baseStartUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ mode, status: targetStatus, job_type: job_type, search, client_id, company_id, date_from, date_to, columns: cols, selected_job_ids: ids })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.export_id) throw new Error('Failed to start export');
            const exportId = data.export_id;
            const pollUrl = statusBase.replace(/\/(\d+)$/, '/0').replace(/0$/, exportId);
            const dlUrl = downloadBase.replace(/\/(\d+)$/, '/0').replace(/0$/, exportId);
            let timer = null;
            const poll = () => {
                fetch(pollUrl)
                    .then(r => r.json())
                    .then(s => {
                        if (s.status === 'completed') {
                            statusText.innerText = 'Export ready.';
                            statusSpinner.style.display = 'none';
                            downloadLink.href = dlUrl;
                            downloadLink.style.display = 'inline-block';
                            clearInterval(timer);
                        } else if (s.status === 'failed') {
                            statusText.innerText = 'Export failed.';
                            statusSpinner.style.display = 'none';
                            clearInterval(timer);
                        } else {
                            statusText.innerText = 'Export in progress…';
                            statusSpinner.style.display = 'inline-block';
                        }
                    })
                    .catch(err => { statusText.innerText = 'Status check failed.'; statusSpinner.style.display = 'none'; clearInterval(timer); console.error(err); });
            };
            timer = setInterval(poll, 1000);
            poll();
        })
        .catch(err => {
            console.error(err);
            // Fallback to direct download via form submit
            const form = document.getElementById('export-form');
            const baseExportUrl = document.getElementById('base-export-url').value;
            form.action = baseExportUrl;
            document.getElementById('export-mode').value = mode;
            document.getElementById('export-status').value = targetStatus;
            document.getElementById('export-search').value = search;
            document.getElementById('export-client-id').value = client_id;
            document.getElementById('export-company-id').value = company_id;
            // company added via startExport; fallback export uses form-only CSV endpoint
            document.getElementById('export-date-from').value = date_from;
            document.getElementById('export-date-to').value = date_to;
            document.getElementById('export-job-type').value = job_type;
            document.getElementById('export-columns').value = JSON.stringify(cols);
            document.getElementById('export-selected-ids').value = JSON.stringify(ids);
            form.submit();
        });
    }

    document.getElementById('apply-filters').addEventListener('click', () => applyFilters(1));
    document.getElementById('prev-page').addEventListener('click', () => { if (state.page > 1) applyFilters(state.page - 1); });
    document.getElementById('next-page').addEventListener('click', () => { if (state.has_more) applyFilters(state.page + 1); });
    document.getElementById('export-unpaid').addEventListener('click', () => exportAction('unpaid', false));
    document.getElementById('export-paid').addEventListener('click', () => exportAction('paid', false));
    document.getElementById('export-selected').addEventListener('click', () => exportAction(document.getElementById('status').value, true));
    document.getElementById('select-all').addEventListener('change', (e) => {
        document.querySelectorAll('.row-check').forEach(ch => ch.checked = e.target.checked);
    });
    // Reflect column selection instantly on the table
    document.querySelectorAll('.column-check').forEach(ch => ch.addEventListener('change', updateColumnVisibility));
    // Column visibility bulk controls
    const colSelectAllBtn = document.getElementById('columns-select-all');
    const colClearAllBtn = document.getElementById('columns-clear-all');
    if (colSelectAllBtn) {
        colSelectAllBtn.addEventListener('click', () => {
            document.querySelectorAll('.column-check').forEach(ch => ch.checked = true);
            updateColumnVisibility();
        });
    }
    if (colClearAllBtn) {
        colClearAllBtn.addEventListener('click', () => {
            document.querySelectorAll('.column-check').forEach(ch => ch.checked = false);
            updateColumnVisibility();
        });
    }

    // Preview modal behavior
    function openPreview() {
        const previewUrl = document.getElementById('base-preview-url').value;
        const mode = document.getElementById('mode').value;
        const status = document.getElementById('status').value;
        const job_type = document.getElementById('job_type').value;
        const search = document.getElementById('search').value;
        const client_id = selectedClientId;
        const company_id = selectedCompanyId;
        const date_from = document.getElementById('date_from').value;
        const date_to = document.getElementById('date_to').value;
        const cols = getSelectedColumns();

        const panel = document.getElementById('preview-panel');
        const backdrop = document.getElementById('preview-backdrop');
        const loading = document.getElementById('preview-loading');
        const head = document.getElementById('preview-head');
        const body = document.getElementById('preview-body');

        head.innerHTML = '';
        body.innerHTML = '';
        loading.style.display = 'block';
        backdrop.style.display = 'block';
        panel.style.display = 'block';

        const url = new URL(previewUrl, window.location.origin);
        url.searchParams.set('mode', mode);
        url.searchParams.set('status', status);
        url.searchParams.set('job_type', job_type);
        url.searchParams.set('search', search);
        if (client_id) url.searchParams.set('client_id', client_id);
        if (company_id) url.searchParams.set('company_id', company_id);
        if (date_from) url.searchParams.set('date_from', date_from);
        if (date_to) url.searchParams.set('date_to', date_to);
        url.searchParams.set('columns', JSON.stringify(cols));
        url.searchParams.set('limit', 25);

        fetch(url.toString())
            .then(r => r.json())
            .then(payload => {
                loading.style.display = 'none';
                const columns = payload.columns || cols;
                // Render headers
                const labelFor = (key) => COLUMN_LABELS[key] || key;
                columns.forEach(c => {
                    const key = (typeof c === 'string') ? c : c;
                    const th = document.createElement('th');
                    th.textContent = labelFor(key);
                    head.appendChild(th);
                });
                // Render rows
                (payload.data || []).forEach(r => {
                    const tr = document.createElement('tr');
                    let html = '';
                    columns.forEach(c => {
                        const key = (typeof c === 'string') ? c : c;
                        html += `<td>${r[key] ?? ''}</td>`;
                    });
                    tr.innerHTML = html;
                    body.appendChild(tr);
                });
            })
            .catch(err => { loading.style.display = 'none'; console.error(err); alert('Failed to load preview'); });
    }
    function closePreview() {
        document.getElementById('preview-backdrop').style.display = 'none';
        document.getElementById('preview-panel').style.display = 'none';
    }
    document.getElementById('preview-export').addEventListener('click', openPreview);
    document.getElementById('preview-close').addEventListener('click', closePreview);
    document.getElementById('preview-close-2').addEventListener('click', closePreview);
    document.getElementById('preview-backdrop').addEventListener('click', closePreview);
    // Allow Esc to close preview
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closePreview(); });

    // Client search/selection handlers (unified)
    const clientSearch = document.getElementById('client-search');
    const clientSuggestions = document.getElementById('client-suggestions');
    const clientClear = document.getElementById('client-clear');
    const clientChip = document.getElementById('selected-client-chip');
    const clientChipLabel = document.getElementById('selected-client-label');
    const clientChipClear = document.getElementById('selected-client-clear');
    let clientTimer = null;
    function renderClientSuggestions(results) {
        if (!results || results.length === 0) {
            clientSuggestions.style.display = 'none';
            clientSuggestions.innerHTML = '';
            return;
        }
        clientSuggestions.innerHTML = '';
        results.forEach(item => {
            const label = item.label || item.name || '';
            const div = document.createElement('button');
            div.type = 'button';
            div.className = 'list-group-item list-group-item-action';
            div.textContent = label;
            div.setAttribute('data-id', item.id);
            div.addEventListener('click', () => {
                selectedClientId = String(item.id);
                selectedClientLabel = label;
                clientSearch.value = label;
                clientSuggestions.style.display = 'none';
                clientSuggestions.innerHTML = '';
                // Show selected client chip
                clientChipLabel.textContent = selectedClientLabel;
                clientChip.style.display = 'inline-flex';
                // Auto-apply filter on selection
                applyFilters(1);
            });
            clientSuggestions.appendChild(div);
        });
        clientSuggestions.style.display = 'block';
    }
    function fetchClients(term) {
        const baseClientsUrl = document.getElementById('base-clients-url').value;
        const url = new URL(baseClientsUrl, window.location.origin);
        if (term) url.searchParams.set('q', term);
        url.searchParams.set('limit', 20);
        fetch(url.toString())
            .then(r => r.json())
            .then(payload => {
                const results = (payload.results || []);
                renderClientSuggestions(results);
            })
            .catch(err => console.error('Client lookup failed', err));
    }
    clientSearch.addEventListener('input', () => {
        const term = clientSearch.value.trim();
        selectedClientId = ''; // typing resets current selection until a suggestion is chosen
        selectedClientLabel = '';
        clientChip.style.display = 'none';
        clearTimeout(clientTimer);
        clientTimer = setTimeout(() => fetchClients(term), 250);
    });
    // Pressing Enter in client search applies filters with current selection (or no client if none selected)
    clientSearch.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); applyFilters(1); }
    });
    // Pressing Enter in general search applies filters
    const generalSearch = document.getElementById('search');
    if (generalSearch) {
        generalSearch.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); applyFilters(1); }
        });
    }
    // Hide suggestions when focus leaves the search input (with small delay to allow click)
    clientSearch.addEventListener('blur', () => setTimeout(() => { clientSuggestions.style.display = 'none'; }, 150));
    clientClear.addEventListener('click', () => {
        selectedClientId = '';
        selectedClientLabel = '';
        clientSearch.value = '';
        clientSuggestions.style.display = 'none';
        clientSuggestions.innerHTML = '';
        clientChip.style.display = 'none';
        // Auto-apply filter to show all clients
        applyFilters(1);
    });
    clientChipClear.addEventListener('click', () => {
        selectedClientId = '';
        selectedClientLabel = '';
        clientSearch.value = '';
        clientSuggestions.style.display = 'none';
        clientSuggestions.innerHTML = '';
        clientChip.style.display = 'none';
        // Auto-apply filter to show all clients
        applyFilters(1);
    });

    // Company search/selection handlers
    const companySearch = document.getElementById('company-search');
    const companySuggestions = document.getElementById('company-suggestions');
    const companyClear = document.getElementById('company-clear');
    const companyChip = document.getElementById('selected-company-chip');
    const companyChipLabel = document.getElementById('selected-company-label');
    const companyChipClear = document.getElementById('selected-company-clear');
    let companyTimer = null;
    function renderCompanySuggestions(results) {
        if (!results || results.length === 0) {
            companySuggestions.style.display = 'none';
            companySuggestions.innerHTML = '';
            return;
        }
        companySuggestions.innerHTML = '';
        results.forEach(item => {
            const label = item.label || item.name || '';
            const div = document.createElement('button');
            div.type = 'button';
            div.className = 'list-group-item list-group-item-action';
            div.textContent = label;
            div.setAttribute('data-id', item.id);
            div.addEventListener('click', () => {
                selectedCompanyId = String(item.id);
                selectedCompanyLabel = label;
                companySearch.value = label;
                companySuggestions.style.display = 'none';
                companySuggestions.innerHTML = '';
                companyChipLabel.textContent = selectedCompanyLabel;
                companyChip.style.display = 'inline-flex';
                applyFilters(1);
            });
            companySuggestions.appendChild(div);
        });
        companySuggestions.style.display = 'block';
    }
    function fetchCompanies(term) {
        const baseCompaniesUrl = document.getElementById('base-companies-url').value;
        const url = new URL(baseCompaniesUrl, window.location.origin);
        if (term) url.searchParams.set('q', term);
        url.searchParams.set('limit', 20);
        fetch(url.toString())
            .then(r => r.json())
            .then(payload => {
                const results = (payload.results || []);
                renderCompanySuggestions(results);
            })
            .catch(err => console.error('Company lookup failed', err));
    }
    companySearch.addEventListener('input', () => {
        const term = companySearch.value.trim();
        selectedCompanyId = '';
        selectedCompanyLabel = '';
        companyChip.style.display = 'none';
        clearTimeout(companyTimer);
        companyTimer = setTimeout(() => fetchCompanies(term), 250);
    });
    companySearch.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); applyFilters(1); } });
    companySearch.addEventListener('blur', () => setTimeout(() => { companySuggestions.style.display = 'none'; }, 150));
    companyClear.addEventListener('click', () => {
        selectedCompanyId = '';
        selectedCompanyLabel = '';
        companySearch.value = '';
        companySuggestions.style.display = 'none';
        companySuggestions.innerHTML = '';
        companyChip.style.display = 'none';
        applyFilters(1);
    });
    companyChipClear.addEventListener('click', () => {
        selectedCompanyId = '';
        selectedCompanyLabel = '';
        companySearch.value = '';
        companySuggestions.style.display = 'none';
        companySuggestions.innerHTML = '';
        companyChip.style.display = 'none';
        applyFilters(1);
    });

    // Create Client Invoice from selected jobs (only valid when Perspective=client and Status=not_invoiced)
    document.getElementById('create-client-invoice').addEventListener('click', () => {
        const mode = document.getElementById('mode').value;
        const status = document.getElementById('status').value;
        if (mode !== 'client' || status !== 'not_invoiced') {
            alert('Please switch to Perspective: Against Client and Status: Not Invoiced, then select rows.');
            return;
        }
        const selected = Array.from(document.querySelectorAll('.row-check'))
            .filter(ch => ch.checked)
            .map(ch => ({ job_id: parseInt(ch.getAttribute('data-job-id'), 10), job_type: (ch.getAttribute('data-job-type') || '').toLowerCase() }));
        if (selected.length === 0) {
            alert('No rows selected. Please select at least one job.');
            return;
        }
        const csrf = document.querySelector('#export-form input[name=_token]')?.value;
        const endpoint = document.getElementById('base-data-url').value.replace(/\/data$/, '/client/invoice');
        // Optional VAT controls: Defaults from backend; could be enhanced with UI
        const payload = { jobs: selected, include_vat: true, vat_rate: 20 };
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        })
        .then(r => {
            if (r.redirected) { window.location.href = r.url; return null; }
            return r.json();
        })
        .then(data => {
            if (!data) return; // handled via redirect
            if (data.error) {
                alert(data.error);
            } else if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                alert('Unexpected response from server.');
            }
        })
        .catch(err => { console.error(err); alert('Failed to create invoice.'); });
    });

    // Set default date range to last 90 days if empty
    (function setDefaultDates() {
        const from = document.getElementById('date_from');
        const to = document.getElementById('date_to');
        if (!from.value && !to.value) {
            const today = new Date();
            const prior = new Date();
            prior.setDate(today.getDate() - 90);
            const fmt = (d) => d.toISOString().slice(0,10);
            from.value = fmt(prior);
            to.value = fmt(today);
        }
    })();

    // Initial load
    applyFilters(1);
</script>
@endpush
