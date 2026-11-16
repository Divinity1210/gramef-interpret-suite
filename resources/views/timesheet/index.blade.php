@extends('layouts.app')

@section('content')
    <main class="section">
        <div class="section__top">
            <nav class="flex-container section__job-types">
                <div class="section__heading">TimeSheet</div>
                <ul class="nav nav-tabs" style="margin-left:auto; gap:8px;">
                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('timesheet.index') }}">Interpreter timesheet</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('translator-jobs.index') }}">Translation timesheet</a>
                    </li>
                </ul>
            </nav>

        </div>
        @include('timesheet.partials.filter')
        @hasanyrole('agent')
        <!-- Agent-focused banner -->
        <div class="alert alert-info" role="alert" style="margin:10px 0;">
            Column guide for agents: <strong>Payslip Amount</strong> shows your invoiced total for the job, while <strong>Amount (Agent Entry)</strong> is the total from your job entry before invoicing. Client invoice amounts are hidden for agents.
        </div>
        @endhasanyrole
        @hasanyrole('admin|super-admin')
        <!-- Visual feedback banner -->
        <div class="alert alert-info" role="alert" style="margin:10px 0;">
            All filtered timesheets shown below are marked ready for invoicing. Use "Send Invoice" to generate invoices for all filtered results.
        </div>
        <div class="alert alert-secondary" role="alert" style="margin:10px 0;">
            Column guide: <strong>Invoice Amount (Client)</strong> = latest client invoice amount for the job; <strong>Payslip Amount (Agent)</strong> = agent’s invoiced total for the job; <strong>Amount (Agent Entry)</strong> = agent’s job entry total (pre-invoice). Admin-added charges only affect the client invoice and do not modify the agent’s payslip.
        </div>
        <!-- Hidden batch form (avoids nested forms) -->
        <form id="batch-invoice-form" method="POST" action="{{ route('timesheet.send-invoice.batch') }}" style="display:none;">
            @csrf
            <!-- Pass current filter values to the batch endpoint -->
            <input type="hidden" name="client_name" value="{{ request('client_name') }}">
            <input type="hidden" name="agent_name" value="{{ request('agent_name') }}">
            <input type="hidden" name="ref" value="{{ request('ref') }}">
            <input type="hidden" name="start_date" value="{{ request('start_date') }}">
            <input type="hidden" name="end_date" value="{{ request('end_date') }}">
            <input type="hidden" name="company_id" value="{{ request('company_id') }}">
        </form>
        @endhasanyrole
    </main>

    <section class="content__table">
        <table class="table">
            <thead class="table__header">
                <tr>
                    <th class="table__heading">Ref#</th>
                    <th class="table__heading">Agent Name</th>
                    <th class="table__heading">Agent Signature</th>
                    <th class="table__heading">Client Name</th>
                    <th class="table__heading">Client Signature</th>
                    {{-- <th class="table__heading">Submittedt Date</th> --}}
                    <th class="table__heading">Booked Date</th>
                    <th class="table__heading">TimeSheet Assign Date</th>
                    {{-- <th class="table__heading">Bulk ID</th> --}}
                    <th class="table__heading">From Language</th>
                    <th class="table__heading">To Language</th>
                    <!-- Amount columns (role-based visibility) -->
                    @hasanyrole('admin|super-admin|client')
                        <th class="table__heading" title="Client-facing invoice created from timesheet and admin-added charges">Invoice Amount (Client)</th>
                    @endhasanyrole
                    @hasanyrole('admin|super-admin|agent')
                        <th class="table__heading" title="Agent invoiced total for this job">Payslip Amount (Agent)</th>
                        <th class="table__heading" title="Agent's job entry total (pre-invoice)">Amount (Agent Entry)</th>
                    @endhasanyrole
                    <th class="table__heading">Action</th>
                </tr>
            </thead>
            <tbody>
                @unlessrole('new-agent')
                    @forelse ($timesheets as $timesheet)
                        <tr class="table__row">
                            <td class="table__data">
                                <a href="{{ route('timesheet.show', $timesheet->id) }}" class="text-decoration-none" title="View Job Details">
                                    {{ optional($timesheet->interpreter)->id ? optional($timesheet->interpreter)->id . '/atoz' : '—' }}
                                </a>
                            </td>
                            <td class="table__data">
                                {{ optional(optional($timesheet->agentOne)->user)->getFullName() ?? '—' }}</td>
                            <td class="table__data"><img height="40px" width="140px" src="{{ $timesheet->agent_signature }}"
                                    alt="NA" /></td>
                            <td class="table__data">
                                {{ optional(optional(optional($timesheet->interpreter)->client)->userSheet)->first_name && optional(optional(optional($timesheet->interpreter)->client)->userSheet)->last_name ? (optional(optional(optional($timesheet->interpreter)->client)->userSheet)->first_name . ' ' . optional(optional(optional($timesheet->interpreter)->client)->userSheet)->last_name) : '—' }}
                            </td>
                            <td class="table__data"><img height="40px" width="140px" src="{{ $timesheet->client_signature }}"
                                    alt="NA" /></td>
                            {{-- <td class="table__data">{{ $timesheet->interpreter->created_at }} </td> --}}
                            <td class="table__data">{{ optional(optional($timesheet->interpreter)->appointment_date) ? optional(optional($timesheet->interpreter)->appointment_date)->toDateString() : '—' }} </td>
                            <td class="table__data">{{ $timesheet->created_at }} </td>
                            {{-- <td class="table__data">{{ $timesheet->interpreter->bulk_id }} </td> --}}
                            <td class="table__data">{{ optional(optional($timesheet->interpreter)->from_language)->name ?? '—' }} </td>
                            <td class="table__data">{{ optional(optional($timesheet->interpreter)->to_language)->name ?? '—' }} </td>

                            @php
                                $jobId = optional($timesheet->interpreter)->id ?? $timesheet->job_id;
                                $invoiceAmount = null;
                                $payslipAmount = null;
                                $agentAmount = null;
                                $agentEntry = null;

                                if ($jobId) {
                                    $clientItem = \App\ClientInvoiceItem::where('job_id', $jobId)->orderByDesc('id')->first();
                                    $invoiceAmount = $clientItem ? floatval($clientItem->amount) + floatval($clientItem->surcharge) + floatval($clientItem->other_charges) : null;

                                    $agentInvoiceItem = \App\AgentInvoiceItem::where('job_id', $jobId)->orderByDesc('id')->first();
                                    $payslipAmount = optional($agentInvoiceItem)->total_amount;

                                    $agentEntry = \App\AgentJobEntry::where('job_id', $jobId)
                                        ->where('agent_id', $timesheet->agent_id)
                                        ->first();
                                    $agentAmount = optional($agentEntry)->total_amount;
                                }
                            @endphp

                            <!-- Amount columns -->
                            @hasanyrole('admin|super-admin|client')
                                <td class="table__data">
                                    {{ $invoiceAmount !== null ? number_format($invoiceAmount, 2) : '--' }}
                                </td>
                            @endhasanyrole
                            @hasanyrole('admin|super-admin|agent|new-agent')
                                <td class="table__data">
                                    {{ $payslipAmount !== null ? number_format($payslipAmount, 2) : '--' }}
                                </td>
                                <td class="table__data">
                                    {{ $agentAmount !== null ? number_format($agentAmount, 2) : '--' }}
                                </td>
                            @endhasanyrole

                            <td class="table__data">
                                @php
                                    $tsJob = $timesheet->interpreterJob ?? $timesheet->interpreter;
                                    $isLateCancelled = $tsJob
                                        && ($tsJob->statusName === 'cancelled')
                                        && is_object($tsJob)
                                        && method_exists($tsJob, 'isLateCancellation')
                                        && $tsJob->isLateCancellation();
                                    $eligibleForInvoice = ($tsJob && ($tsJob->statusName === 'completed'))
                                        || $isLateCancelled;
                                @endphp
                                @if ($eligibleForInvoice)
                                    @php
                                        $clientInvoiceId = optional($clientItem)->client_invoice_id;
                                        // Prefer the agent-entered amount for the admin modal base to avoid any perception of altering payslip figures
                                        $baseForModal = $agentAmount ?? $payslipAmount ?? 0.00;
                                    @endphp
                                    <div class="d-flex flex-column align-items-center" style="gap:6px;">
                                        @php
                                            $inv = optional($clientItem)->invoice;
                                            $invStatus = $inv ? strtolower((string) $inv->status) : null;
                                            $statusLabel = $clientItem ? ('Invoiced' . ($invStatus ? (' (' . ucfirst($invStatus) . ')') : '')) : 'Ready to Invoice';
                                            $statusClass = $clientItem ? 'badge-secondary' : 'badge-success';
                                            $invNumber = $inv ? ($inv->invoice_number ?? $inv->id) : null;
                                        @endphp
                                        <span class="badge {{ $statusClass }}" style="align-self:flex-start;">{{ $statusLabel }}</span>
                                        @if($invNumber)
                                            @hasanyrole('admin|super-admin')
                                                <a href="{{ route('invoices.admin.client.preview', $clientInvoiceId) }}" class="text-decoration-none small" style="align-self:flex-start;">{{ $invNumber }}</a>
                                            @endhasanyrole
                                            @hasanyrole('client')
                                                <a href="{{ route('invoices.client.show', $clientInvoiceId) }}" class="text-decoration-none small" style="align-self:flex-start;">{{ $invNumber }}</a>
                                            @endhasanyrole
                                            @hasanyrole('agent|new-agent')
                                                @php
                                                    $agentInvoiceId = optional($agentInvoiceItem)->agent_invoice_id;
                                                    $agentInv = optional($agentInvoiceItem)->invoice;
                                                    $agentInvNumber = $agentInv ? ($agentInv->invoice_number ?? $agentInv->id) : null;
                                                @endphp
                                                @if($agentInvoiceId && $agentInvNumber)
                                                    <a href="{{ route('invoices.agent.show', $agentInvoiceId) }}" class="text-decoration-none small" style="align-self:flex-start;">{{ $agentInvNumber }}</a>
                                                @endif
                                            @endhasanyrole
                                        @endif
                                        @if ($isLateCancelled)
                                            <span class="badge badge-warning" style="align-self:flex-start;">Late cancellation</span>
                                        @endif
                                        <a href="{{ route('timesheet-pdf', ['id' => $timesheet->id]) }}"
                                            class="btn btn--primary table__btn">TimeSheet Print</a>
                                        @hasanyrole('agent|client|new-agent')
                                            @php
                                                $eligibleForSign = (($tsJob && ($tsJob->statusName == 'completed')) || $isLateCancelled);
                                                $isPaidTs = \App\ClientInvoiceItem::where('job_id', $jobId)->whereHas('invoice', function($q){ $q->where('status','paid'); })->exists();
                                            @endphp
                                            @if($eligibleForSign && !$isPaidTs)
                                                <a href="{{ route('timesheet.edit', $jobId) }}" class="btn btn--secondary table__btn">Sign Timesheet</a>
                                            @endif
                                        @endhasanyrole
                                        @hasanyrole('agent|new-agent')
                                            @if ((($tsJob && $tsJob->statusName == 'completed') || $isLateCancelled) && !$agentEntry)
                                                <a href="{{ $tsJob ? route('agent.job-entry.create', ['interpreter', $tsJob->id]) : '#' }}" class="btn btn--success table__btn" style="background-color: #28a745; color: white;">
                                                    Add Amount
                                                </a>
                                            @elseif ((($tsJob && $tsJob->statusName == 'completed') || $isLateCancelled) && $agentEntry)
                                                <a href="{{ route('agent.job-entry.edit', $agentEntry->id) }}" class="btn btn--secondary table__btn" style="background-color: #6c757d; color: white;">
                                                    Edit Amount
                                                </a>
                                            @endif
                                        @endhasanyrole
                                        @hasanyrole('admin|super-admin')
                                        @php $parentPaid = $inv && (strtolower((string)$inv->status) === 'paid'); @endphp
                                        @if(!$parentPaid)
                                            <form method="POST" action="{{ route('timesheet.send-invoice', $timesheet->id) }}" style="display:inline;">
                                                @csrf
                                                @php $sendLabel = $clientInvoiceId ? 'Create/Update Invoice' : 'Send Invoice'; @endphp
                                                <button type="submit" class="btn btn--secondary table__btn js-confirm" data-confirm-message="Generate a client invoice for this timesheet?">{{ $sendLabel }}</button>
                                            </form>
                                        @endif
                                        @php
                                            $existingLabels = [];
                                            $existingValues = [];
                                            $existingIntRate = '';
                                            $existingIntMinutes = '';
                                            $existingUseIntAsBase = '';
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
                                        <button type="button"
                                                class="btn btn--success table__btn js-admin-add-amount"
                                                style="background-color:#28a745; color:#fff;"
                                                data-timesheet-id="{{ $timesheet->id }}"
                                                data-base-amount="{{ number_format($baseForAdminModal, 2, '.', '') }}"
                                                data-charge-labels='{{ json_encode($existingLabels) }}'
                                                data-charge-values='{{ json_encode($existingValues) }}'
                                                data-int-rate='{{ $existingIntRate }}'
                                                data-int-minutes='{{ $existingIntMinutes }}'
                                                data-int-use-base='{{ $existingUseIntAsBase }}'
                                                data-travel-rate='{{ $existingTravelRate }}'
                                                data-travel-minutes='{{ $existingTravelMinutes }}'
                                                data-travel-from='{{ $existingTravelFrom }}'
                                                data-travel-to='{{ $existingTravelTo }}'
                                                data-mileage-miles='{{ $existingMileageMiles }}'
                                                data-mileage-rate='{{ $existingMileageRate }}'>{{ $adminBtnLabel }}</button>
                                        @if($clientInvoiceId)
                                            <a href="{{ route('invoices.admin.client.preview', $clientInvoiceId) }}" class="btn btn--secondary table__btn">Invoice Detail</a>
                                        @endif
                                        @endhasanyrole
                                        </div>
                                @endif
                            </td>
                        </tr>


                    @empty
                        <tr class="table__row">
                            <td class="table__data table__data--grey" colspan="2">

                                @if (Route::current()->getName() == 'timesheets.index')
                                    There are no timesheets
                                @else
                                    There are no archived timesheets
                                @endif

                            </td>
                        </tr>
                    @endforelse
                @endunlessrole
            </tbody>
        </table>
        @unlessrole('new-agent')
            <div class="pagination">
                {{ $timesheets->appends(request()->query())->links('custom.pagination') }}
            </div>
        @endunlessrole
    </section>

    @hasanyrole('admin|super-admin')
    <!-- Admin Add Amount Modal -->
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
                <!-- Structured breakdown to compute amounts like in client templates -->
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
                                <label>Calculated</label>
                                <div id="admin-int-cost" class="form-control" style="background:#f8f9fa;">0.00</div>
                            </div>
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
                    <div id="admin-charges-container" style="margin-top:8px;">
                        <!-- Charges rows injected here -->
                    </div>
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
    @endhasanyrole

    <!-- Hidden form used to submit overrides -->
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
        <input type="hidden" name="use_interpreting_as_base" id="admin-form-int-use-base" value="1" />
        <input type="hidden" name="travel_minutes" id="admin-form-travel-minutes" value="" />
        <input type="hidden" name="travel_rate_hour" id="admin-form-travel-rate" value="" />
        <input type="hidden" name="travel_from_postcode" id="admin-form-travel-from" value="" />
        <input type="hidden" name="travel_to_postcode" id="admin-form-travel-to" value="" />
    </form>
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
        // Breakdown inputs
        const intUseBaseChk = document.getElementById('admin-int-use-base');
        const intRateInput = document.getElementById('admin-int-rate');
        const intMinutesInput = document.getElementById('admin-int-minutes');
        const intCostEl = document.getElementById('admin-int-cost');
        const travelIncludeChk = document.getElementById('admin-travel-include');
        const travelRateInput = document.getElementById('admin-travel-rate');
        const travelMinutesInput = document.getElementById('admin-travel-minutes');
        const travelCostEl = document.getElementById('admin-travel-cost');
        const travelFromInput = document.getElementById('admin-travel-from');
        const travelToInput = document.getElementById('admin-travel-to');
        let activeTimesheetId = null;

        // If admin-specific modal elements are not present (e.g., non-admin users), exit early to avoid null bindings
        if (!modal) {
            return;
        }

        function format(n){
            return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2);
        }
        function recalc(){
            const base = parseFloat(baseInput.value || '0');
            // Calculate interpreting cost
            const intRate = parseFloat(intRateInput?.value || '0');
            const intMin = parseFloat(intMinutesInput?.value || '0');
            const intCost = (!isNaN(intRate) && !isNaN(intMin)) ? (intRate * (intMin/60)) : 0;
            if (intCostEl) intCostEl.textContent = format(intCost);
            if (intUseBaseChk?.checked) {
                baseInput.value = format(intCost);
            }
            const baseEff = parseFloat(baseInput.value || '0');
            // Calculate travel cost
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
                        addChargeRow(label || '', (lv !== undefined ? lv : ''));
                    });
                } catch (e) { addChargeRow(); }
                const mm = btn.getAttribute('data-mileage-miles') || '';
                const mr = btn.getAttribute('data-mileage-rate') || '';
                if (mm || mr) { addMileageRow(mm, mr); }
                if (intRateInput) intRateInput.value = btn.getAttribute('data-int-rate') || '';
                if (intMinutesInput) intMinutesInput.value = btn.getAttribute('data-int-minutes') || '';
                if (intUseBaseChk) intUseBaseChk.checked = (btn.getAttribute('data-int-use-base') === '1');
                if (travelRateInput) travelRateInput.value = btn.getAttribute('data-travel-rate') || '';
                if (travelMinutesInput) travelMinutesInput.value = btn.getAttribute('data-travel-minutes') || '';
                if (travelIncludeChk) travelIncludeChk.checked = !!(travelRateInput && travelRateInput.value || travelMinutesInput && travelMinutesInput.value);
                if (travelFromInput) travelFromInput.value = btn.getAttribute('data-travel-from') || '';
                if (travelToInput) travelToInput.value = btn.getAttribute('data-travel-to') || '';
                recalc();
                modal.style.display = 'flex';
            });
        });
        if (closeBtn) closeBtn.addEventListener('click', () => { modal.style.display = 'none'; });
        if (addChargeBtn) addChargeBtn.addEventListener('click', () => { addChargeRow(); });
        if (addMileageBtn) addMileageBtn.addEventListener('click', () => { addMileageRow(); });
        if (baseInput) baseInput.addEventListener('input', recalc);
        // Guard optional inputs before binding to avoid addEventListener on null
        if (intRateInput) intRateInput.addEventListener('input', recalc);
        if (intMinutesInput) intMinutesInput.addEventListener('input', recalc);
        if (intUseBaseChk) intUseBaseChk.addEventListener('change', recalc);
        if (travelRateInput) travelRateInput.addEventListener('input', recalc);
        if (travelMinutesInput) travelMinutesInput.addEventListener('input', recalc);
        if (travelIncludeChk) travelIncludeChk.addEventListener('change', recalc);

        if (submitBtn) submitBtn.addEventListener('click', () => {
            // Basic validation
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
                if (!include) return; // ignore excluded charges
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
            // Add Travel Time computed charge if included
            const trRateVal = parseFloat(travelRateInput?.value || '0');
            const trMinVal = parseFloat(travelMinutesInput?.value || '0');
            const trCostVal = (!isNaN(trRateVal) && !isNaN(trMinVal)) ? (trRateVal * (trMinVal/60)) : 0;
            if ((travelIncludeChk?.checked) && trCostVal > 0) {
                chargeLabels.push('Travel Time');
                chargeValues.push(trCostVal.toFixed(2));
            }
            // Capture travel metadata (for PDF display)
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
            // Extract Parking value if provided among charges
            let parkingVal = 0;
            chargeLabels.forEach((label, idx) => {
                if ((label || '').trim().toLowerCase() === 'parking') {
                    const v = parseFloat(chargeValues[idx] || '0');
                    if (!isNaN(v) && v >= 0) parkingVal = v;
                }
            });
            document.getElementById('admin-form-parking').value = parkingVal.toFixed(2);
            // Persist mileage breakdown if provided
            document.getElementById('admin-form-mileage-miles').value = mileageMiles;
            document.getElementById('admin-form-mileage-rate').value = mileageRate;
            // Persist interpreting and travel breakdown
            const intRateVal = parseFloat(intRateInput?.value || '0');
            const intMinVal = parseFloat(intMinutesInput?.value || '0');
            document.getElementById('admin-form-int-rate').value = (!isNaN(intRateVal) && intRateVal >= 0) ? intRateVal.toFixed(2) : '';
            document.getElementById('admin-form-int-minutes').value = (!isNaN(intMinVal) && intMinVal >= 0) ? intMinVal.toFixed(0) : '';
            document.getElementById('admin-form-int-use-base').value = intUseBaseChk?.checked ? '1' : '0';
            document.getElementById('admin-form-travel-rate').value = travelRate;
            document.getElementById('admin-form-travel-minutes').value = travelMinutes;
            document.getElementById('admin-form-travel-from').value = travelFrom;
            document.getElementById('admin-form-travel-to').value = travelTo;
            // Set dynamic action to the timesheet's send-invoice route
            form.setAttribute('action', `${window.location.origin}/timesheet/${activeTimesheetId}/send-invoice`);
            form.submit();
        });
        if (includeVatCheckbox) includeVatCheckbox.addEventListener('change', recalc);
    })();
</script>
@endpush
