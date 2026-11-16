<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\AgentInvoice;
use App\ClientInvoice;
use App\ClientInvoiceItem;
use App\AgentInvoiceItem;
use App\AgentJobEntry;
use App\Remittance;
use App\Client;
use App\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

class AdminInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'type' => $request->query('type', 'agent'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'status' => $request->query('status'),
            'search' => $request->query('search'),
            'ref' => $request->query('ref'),
        ];

        if ($filters['type'] === 'client') {
            $query = ClientInvoice::with(['client.user', 'company', 'items']);
            // Date filtering should be based on Service/Appointment date from invoice items (metadata->date)
            if ($filters['start_date'] || $filters['end_date']) {
                $query->where(function($wrap) use ($filters) {
                    // Primary: filter by per-item service date stored in metadata
                    $wrap->whereHas('items', function($itemQuery) use ($filters) {
                        $expr = DB::getDriverName() === 'sqlite'
                            ? "json_extract(metadata, '$.date')"
                            : "JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.date'))";
                        if ($filters['start_date']) {
                            $itemQuery->whereRaw("{$expr} >= ?", [$filters['start_date']]);
                        }
                        if ($filters['end_date']) {
                            $itemQuery->whereRaw("{$expr} <= ?", [$filters['end_date']]);
                        }
                    })
                    // Fallback: include invoices whose invoice-level date range INTERSECTS the filter window
                    // Intersection logic: [date_range_start, date_range_end] overlaps with [start_date, end_date]
                    // i.e., (date_range_start <= end_date) AND (date_range_end >= start_date)
                    ->orWhere(function($rangeQ) use ($filters) {
                        $start = $filters['start_date'] ?? null;
                        $end = $filters['end_date'] ?? null;
                        if ($start && $end) {
                            $rangeQ->whereDate('date_range_start', '<=', $end)
                                   ->whereDate('date_range_end', '>=', $start);
                        } elseif ($start) {
                            $rangeQ->whereDate('date_range_end', '>=', $start);
                        } elseif ($end) {
                            $rangeQ->whereDate('date_range_start', '<=', $end);
                        }
                    });
                });
            }
            if ($filters['status']) {
                $query->where('status', $filters['status']);
            }
            if ($filters['search']) {
                $query->where(function($q) use ($filters) {
                    $q->whereHas('company', function($companyQuery) use ($filters) {
                        $companyQuery->where('name', 'like', '%' . $filters['search'] . '%');
                    })->orWhereHas('client.user', function($userQuery) use ($filters) {
                        $userQuery->where('first_name', 'like', '%' . $filters['search'] . '%')
                                 ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                                 ->orWhere('email', 'like', '%' . $filters['search'] . '%');
                    });
                });
            }
            if (!empty($filters['ref'])) {
                $ref = trim((string)$filters['ref']);
                $query->where(function($q) use ($ref) {
                    $q->where('invoice_number', 'like', '%' . $ref . '%');
                    if (is_numeric($ref)) {
                        $q->orWhere('id', (int)$ref)
                          ->orWhereHas('items', function($itemQ) use ($ref) {
                              $itemQ->where('job_id', (int)$ref);
                          });
                    }
                });
            }
        } else {
            $query = AgentInvoice::with(['agent.user', 'items']);
            // Agent invoices use date_from/date_to in the schema
            if ($filters['start_date']) {
                $query->whereDate('date_from', '>=', $filters['start_date']);
            }
            if ($filters['end_date']) {
                $query->whereDate('date_to', '<=', $filters['end_date']);
            }
            if ($filters['status']) {
                // Map string status to numeric code if the column is numeric in legacy DB
                $query->where('status', $this->mapAgentStatusValue($filters['status']));
            }
            if ($filters['search']) {
                $query->whereHas('agent.user', function($userQuery) use ($filters) {
                    $userQuery->where('first_name', 'like', '%' . $filters['search'] . '%')
                             ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                             ->orWhere('email', 'like', '%' . $filters['search'] . '%');
                });
            }
            if (!empty($filters['ref'])) {
                $ref = trim((string)$filters['ref']);
                $query->where(function($q) use ($ref) {
                    $q->where('invoice_number', 'like', '%' . $ref . '%');
                    if (is_numeric($ref)) {
                        $q->orWhere('id', (int)$ref)
                          ->orWhereHas('items', function($itemQ) use ($ref) {
                              $itemQ->where('job_id', (int)$ref);
                          });
                    }
                });
            }
        }

        $invoices = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return view('invoices.admin.index', compact('invoices', 'filters'));
    }

    public function pdfAgent($id)
    {
        $invoice = AgentInvoice::with(['agent.user', 'items'])->findOrFail($id);

        $items = $invoice->items()->get();
        $interpreterIds = [];
        $translatorIds = [];
        foreach ($items as $i) {
            $jt = (string) ($i->job_type ?? data_get($i, 'metadata.job_type', ''));
            $jid = (int) ($i->job_id ?? 0);
            if ($jid > 0) {
                if (strpos($jt, 'InterpreterJob') !== false) { $interpreterIds[] = $jid; }
                elseif (strpos($jt, 'TranslatorJob') !== false) { $translatorIds[] = $jid; }
            }
        }
        $interpreterIds = collect($interpreterIds)->unique()->values()->all();
        $translatorIds = collect($translatorIds)->unique()->values()->all();
        $interpreterJobsMap = empty($interpreterIds) ? collect() : \App\InterpreterJob::with(['toLanguage'])->whereIn('id', $interpreterIds)->get()->keyBy('id');
        $translatorJobsMap = empty($translatorIds) ? collect() : \App\TranslatorJob::with(['fromLanguage','toLanguage'])->whereIn('id', $translatorIds)->get()->keyBy('id');

        $computed = [];
        foreach ($items as $it) {
            $jt = (string) ($it->job_type ?? data_get($it, 'metadata.job_type', ''));
            $jid = (int) ($it->job_id ?? 0);
            $dateStr = '-';
            $language = '-';
            $duration = '-';
            if (strpos($jt, 'InterpreterJob') !== false) {
                $job = $interpreterJobsMap->get($jid);
                $date = optional($job)->appointment_date;
                $dateStr = $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '-';
                $language = optional(optional($job)->toLanguage)->name ?? '-';
                if ($job) {
                    $h = (int) ($job->duration_hours ?? 0);
                    $m = (int) ($job->duration_minutes ?? 0);
                    $duration = ($h > 0 || $m > 0) ? ($h . 'h ' . $m . 'm') : '-';
                }
            } elseif (strpos($jt, 'TranslatorJob') !== false) {
                $job = $translatorJobsMap->get($jid);
                $date = optional($job)->target_date;
                $dateStr = $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '-';
                $language = trim(((string) optional(optional($job)->fromLanguage)->name) . ' → ' . ((string) optional(optional($job)->toLanguage)->name));
            } else {
                $job = $interpreterJobsMap->get($jid) ?: $translatorJobsMap->get($jid);
                if ($job) {
                    $isInterpreter = property_exists($job, 'appointment_date') || method_exists($job, 'isCancelledSameDay');
                    $dateRaw = $isInterpreter ? optional($job)->appointment_date : optional($job)->target_date;
                    $dateStr = $dateRaw ? \Carbon\Carbon::parse($dateRaw)->format('d/m/Y') : '-';
                    $language = $isInterpreter
                        ? (optional(optional($job)->toLanguage)->name ?? '-')
                        : trim(((string) optional(optional($job)->fromLanguage)->name) . ' → ' . ((string) optional(optional($job)->toLanguage)->name));
                    if ($isInterpreter) {
                        $h = (int) ($job->duration_hours ?? 0);
                        $m = (int) ($job->duration_minutes ?? 0);
                        $duration = ($h > 0 || $m > 0) ? ($h . 'h ' . $m . 'm') : '-';
                    }
                }
            }
            $computed[$it->id] = [
                'date' => $dateStr,
                'language' => $language,
                'duration' => $duration,
            ];
        }

        $html = view('invoices.agent.pdf', ['invoice' => $invoice, 'computed' => $computed])->render();

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
        ]);
        $mpdf->WriteHTML($html);
        $mpdf->Output('agent-invoice-'.$invoice->invoice_number.'.pdf', 'I');
        exit;
    }

    public function pdfClient($id)
    {
        $invoice = ClientInvoice::with(['client.user', 'company', 'items.job'])->findOrFail($id);
        // Determine effective template for rendering.
        // Previous behaviour forced v3 whenever there were 2+ items OR jobs.
        // Requested update: Only switch to v3 when there are multiple DISTINCT jobs.
        // Additional standalone items (job_id null) or multiple items relating to the same job should still use v2.
        $mode = session('invoice_template_mode_' . $invoice->id) ?? 'auto';
        $distinctJobs = $invoice->items()->pluck('job_id')->filter()->unique()->count();
        $hasMultipleDistinctJobs = $distinctJobs > 1;

        if ($hasMultipleDistinctJobs) {
            $effectiveTemplate = 'v3';
        } else {
            // When in Auto mode, ignore persisted invoice->template; only allow explicit manual override
            $effectiveTemplate = ($mode === 'v3') ? 'v3' : (($mode === 'v2') ? 'v2' : 'v2');
        }

        // Support v3 template (multi-job grouping) with landscape layout
        $viewName = ($effectiveTemplate === 'v3')
            ? 'invoices.client.pdf_v3'
            : (($effectiveTemplate === 'v2') ? 'invoices.client.pdf_v2' : 'invoices.client.pdf');
        $html = view($viewName, compact('invoice'))->render();

        // Landscape orientation for v2/v3 to match requested horizontal A4 layout
        $orientation = in_array($effectiveTemplate, ['v2','v3']) ? 'L' : 'P';
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => $orientation,
        ]);
        $mpdf->WriteHTML($html);
        $mpdf->Output('client-invoice-'.$invoice->invoice_number.'.pdf', 'I');
        exit;
    }

    public function previewClient($id)
    {
        $invoice = ClientInvoice::with(['client.user', 'client.organisation', 'company', 'items.job', 'paymentProofs.documents', 'paymentProofs.submitter', 'receipts', 'receipts.generator'])->findOrFail($id);
        // Decide the effective template for preview UI (keep in sync with pdfClient)
        $mode = session('invoice_template_mode_' . $invoice->id) ?? 'auto';
        $distinctJobs = $invoice->items()->pluck('job_id')->filter()->unique()->count();
        if ($distinctJobs > 1) {
            // Force v3 for multi-job invoices for a better UX, regardless of manual v2 selection
            $effectiveTemplate = 'v3';
        } else {
            // Auto mode should render v2 for single-job invoices; allow explicit manual 'v3' selection only
            $effectiveTemplate = ($mode === 'v3') ? 'v3' : (($mode === 'v2') ? 'v2' : 'v2');
        }
        // Build job options for dropdown (last 100 per type for this client)
        $jobOptions = [];
        $existingJobIds = $invoice->items()->pluck('job_id')->filter()->unique()->values()->all();
        $clientId = optional($invoice->client)->id;
        try {
            if ($clientId) {
                // Eager-load language to avoid N+1 when building labels
                $interpreterJobs = \App\InterpreterJob::where('client_id', $clientId)
                    ->with(['toLanguage'])
                    ->orderByDesc('appointment_date')->limit(100)->get();
                foreach ($interpreterJobs as $j) {
                    $jobOptions[] = [
                        'id' => $j->id,
                        'type' => 'App\\InterpreterJob',
                        'label' => sprintf('#%d • %s • %s • Ref:%s',
                            $j->id,
                            optional($j->appointment_date)->format('Y-m-d'),
                            optional($j->toLanguage ?? $j->to_language)->name,
                            $j->client_reference ?? 'N/A'
                        ),
                        'date' => optional($j->appointment_date)->format('Y-m-d'),
                        'ref' => $j->client_reference,
                    ];
                }
                $translatorJobs = \App\TranslatorJob::where('client_id', $clientId)
                    ->with(['fromLanguage', 'toLanguage'])
                    ->orderByDesc('target_date')->limit(100)->get();
                foreach ($translatorJobs as $j) {
                    $jobOptions[] = [
                        'id' => $j->id,
                        'type' => 'App\\TranslatorJob',
                        'label' => sprintf('#%d • %s • %s → %s • Ref:%s',
                            $j->id,
                            optional($j->target_date)->format('Y-m-d'),
                            optional($j->fromLanguage)->name,
                            optional($j->toLanguage)->name,
                            $j->client_reference ?? 'N/A'
                        ),
                        'date' => optional($j->target_date)->format('Y-m-d'),
                        'ref' => $j->client_reference,
                    ];
                }
            }
        } catch (\Throwable $e) { /* ignore job fetch failures */ }
        // Compute default invoice email for the Send Email modal
        // Priority: Company email → Organisation invoice email → Client account email
        $defaultInvoiceEmail = null;
        try {
            $client = $invoice->client;
            $companyEmail = optional($invoice->company)->email;
            $accountEmail = optional(optional($client)->user)->email;
            $orgEmail = optional(optional($client)->organisation)->organisation_email;

            // Prefer company email if available and valid
            if ($companyEmail && filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
                $defaultInvoiceEmail = $companyEmail;
            } else {
                // Fall back to organisation vs account based on client's preference
                if ($client) {
                    if ((int) ($client->invoice_email_same_as_account ?? 0) === 1) {
                        $defaultInvoiceEmail = $accountEmail;
                    } else {
                        $defaultInvoiceEmail = $orgEmail ?: $accountEmail;
                    }
                } else {
                    $defaultInvoiceEmail = $accountEmail;
                }
            }
        } catch (\Throwable $e) {
            $defaultInvoiceEmail = null;
        }

        return view('invoices.admin.client.preview', compact('invoice', 'jobOptions', 'existingJobIds', 'effectiveTemplate', 'distinctJobs', 'defaultInvoiceEmail'));
    }

    /**
     * Display a preview page for an agent invoice with an embedded PDF iframe.
     * This mirrors the client preview flow but without template toggles or job-detail modals.
     */
    public function previewAgent($id)
    {
        $invoice = AgentInvoice::with(['agent.user', 'items'])->findOrFail($id);
        return view('invoices.admin.agent.preview', compact('invoice'));
    }

    public function export(Request $request)
    {
        $filters = [
            'type' => $request->query('type', 'agent'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'status' => $request->query('status'),
            'search' => $request->query('search'),
            'selected' => $request->query('selected'),
        ];

        if ($filters['type'] === 'client') {
            $query = ClientInvoice::with(['client.user', 'company']);
            if ($filters['start_date']) {
                $query->whereDate('date_range_start', '>=', $filters['start_date']);
            }
            if ($filters['end_date']) {
                $query->whereDate('date_range_end', '<=', $filters['end_date']);
            }
            if ($filters['status']) {
                $query->where('status', $filters['status']);
            }
            if ($filters['search']) {
                $query->where(function($q) use ($filters) {
                    $q->whereHas('company', function($companyQuery) use ($filters) {
                        $companyQuery->where('name', 'like', '%' . $filters['search'] . '%');
                    })->orWhereHas('client.user', function($userQuery) use ($filters) {
                        $userQuery->where('first_name', 'like', '%' . $filters['search'] . '%')
                                 ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                                 ->orWhere('email', 'like', '%' . $filters['search'] . '%');
                    });
                });
            }
            if ($filters['selected']) {
                $selectedIds = explode(',', $filters['selected']);
                $query->whereIn('id', $selectedIds);
            }
        } else {
            $query = AgentInvoice::with(['agent.user', 'items']);
            if ($filters['start_date']) {
                $query->whereDate('date_from', '>=', $filters['start_date']);
            }
            if ($filters['end_date']) {
                $query->whereDate('date_to', '<=', $filters['end_date']);
            }
            if ($filters['status']) {
                $query->where('status', $filters['status']);
            }
            if ($filters['search']) {
                $query->whereHas('agent.user', function($userQuery) use ($filters) {
                    $userQuery->where('first_name', 'like', '%' . $filters['search'] . '%')
                             ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                             ->orWhere('email', 'like', '%' . $filters['search'] . '%');
                });
            }
            if ($filters['selected']) {
                $selectedIds = explode(',', $filters['selected']);
                $query->whereIn('id', $selectedIds);
            }
        }

        $invoices = $query->orderByDesc('id')->get();

        // Generate CSV export
        $filename = ($filters['type'] === 'client' ? 'client' : 'agent') . '_invoices_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($invoices, $filters) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            if ($filters['type'] === 'client') {
                fputcsv($file, ['Invoice Number', 'Company', 'Client Name', 'Email', 'Period Start', 'Period End', 'Subtotal', 'VAT', 'Total', 'Status', 'Created Date']);
            } else {
                fputcsv($file, ['Invoice Number', 'Agent Name', 'Email', 'Period Start', 'Period End', 'Subtotal', 'Expenses', 'VAT', 'Total', 'Status', 'Created Date']);
            }

            foreach ($invoices as $invoice) {
                if ($filters['type'] === 'client') {
                    $expensesTotal = 0; // Client invoices don't have expenses in the same way
                    fputcsv($file, [
                        $invoice->invoice_number,
                        optional($invoice->company)->name ?? '—',
                        optional(optional($invoice->client)->user)->getFullName() ?? '—',
                        optional(optional($invoice->client)->user)->email ?? '—',
                        optional($invoice->date_range_start)->format('Y-m-d') ?? '—',
                        optional($invoice->date_range_end)->format('Y-m-d') ?? '—',
                        number_format($invoice->subtotal, 2),
                        number_format($invoice->vat_amount, 2),
                        number_format($invoice->total, 2),
                        ucfirst($invoice->status ?? 'draft'),
                        $invoice->created_at->format('Y-m-d H:i:s')
                    ]);
                } else {
                    $expensesTotal = $invoice->items()->get()->sum(function($i){ return ($i->expenses_amount + $i->surcharge + $i->other_charges); });
                    fputcsv($file, [
                        $invoice->invoice_number,
                        optional(optional($invoice->agent)->user)->getFullName() ?? '—',
                        optional(optional($invoice->agent)->user)->email ?? '—',
                        optional($invoice->date_from)->format('Y-m-d') ?? '—',
                        optional($invoice->date_to)->format('Y-m-d') ?? '—',
                        number_format($invoice->subtotal, 2),
                        number_format($expensesTotal, 2),
                        number_format($invoice->vat_amount, 2),
                        number_format($invoice->total_amount, 2),
                        ucfirst($invoice->status_label ?? ($invoice->status ?? 'draft')),
                        $invoice->created_at->format('Y-m-d H:i:s')
                    ]);
                }
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function bulkAction(Request $request)
    {
        $action = $request->input('action');
        $invoiceIds = $request->input('invoice_ids', []);
        $type = $request->input('type', 'agent');

        if (empty($invoiceIds)) {
            return response()->json(['error' => 'No invoices selected'], 400);
        }

        $model = $type === 'client' ? ClientInvoice::class : AgentInvoice::class;
        
        // For legacy databases, agent_invoices.status might be stored as an INT.
        // Detect the column type at runtime and map to the appropriate value.
        $isAgentStatusNumeric = false;
        if ($type === 'agent') {
            try {
                $result = \Illuminate\Support\Facades\DB::select(
                    "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agent_invoices' AND COLUMN_NAME = 'status'"
                );
                if (!empty($result)) {
                    $dataType = strtolower($result[0]->DATA_TYPE ?? '');
                    $isAgentStatusNumeric = in_array($dataType, ['tinyint','smallint','int','bigint','integer']);
                }
            } catch (\Throwable $e) {
                $isAgentStatusNumeric = false;
            }
            // Fallback: infer by sampling an invoice status value
            if (!$isAgentStatusNumeric) {
                try {
                    $sample = AgentInvoice::whereIn('id', (array)$invoiceIds)->value('status');
                    if ($sample === null) {
                        $sample = AgentInvoice::orderByDesc('id')->value('status');
                    }
                    if ($sample !== null) {
                        $isAgentStatusNumeric = is_numeric($sample);
                    }
                } catch (\Throwable $e) {
                    // keep default false
                }
            }
        }

        $agentInvoicesToNotify = collect();
        if ($type === 'agent' && in_array($action, ['approve', 'mark_paid', 'cancel'], true)) {
            $agentInvoicesToNotify = AgentInvoice::with(['agent.user'])
                ->whereIn('id', $invoiceIds)
                ->get();
        }
        
        switch ($action) {
            case 'approve':
                if ($type === 'agent') {
                    // Use helper to map according to schema (numeric vs string)
                    $statusValue = $this->mapAgentStatusValue('sent');
                    $model::whereIn('id', $invoiceIds)->update(['status' => $statusValue]);

                    foreach ($agentInvoicesToNotify as $invoice) {
                        $this->notifyAgentInvoiceAction($invoice, 'approved');
                    }
                } else {
                    $model::whereIn('id', $invoiceIds)->update(['status' => 'sent']);
                }
                $msg = 'Invoices approved successfully';
                if ($request->wantsJson()) {
                    return response()->json(['success' => true, 'message' => $msg]);
                }
                return redirect()->back()->with('success', $msg);
                
            case 'mark_paid':
                if ($type === 'agent') {
                    $statusValue = $this->mapAgentStatusValue('paid');
                    $model::whereIn('id', $invoiceIds)->update(['status' => $statusValue]);

                    foreach ($agentInvoicesToNotify as $invoice) {
                        $this->notifyAgentInvoiceAction($invoice, 'paid');
                    }
                } else {
                    $model::whereIn('id', $invoiceIds)->update(['status' => 'paid']);
                }
                $msg = 'Invoices marked as paid';
                if ($request->wantsJson()) {
                    return response()->json(['success' => true, 'message' => $msg]);
                }
                return redirect()->back()->with('success', $msg);
                
            case 'cancel':
                if ($type === 'agent') {
                    $statusValue = $this->mapAgentStatusValue('cancelled');
                    $model::whereIn('id', $invoiceIds)->update(['status' => $statusValue]);

                    foreach ($agentInvoicesToNotify as $invoice) {
                        $this->notifyAgentInvoiceAction($invoice, 'cancelled');
                    }
                } else {
                    $model::whereIn('id', $invoiceIds)->update(['status' => 'cancelled']);
                }
                $msg = 'Invoices cancelled successfully';
                if ($request->wantsJson()) {
                    return response()->json(['success' => true, 'message' => $msg]);
                }
                return redirect()->back()->with('success', $msg);
                
            case 'delete':
                if ($type === 'agent') {
                    // Load invoices with items
                    $invoices = AgentInvoice::with('items')->whereIn('id', $invoiceIds)->get();
                    foreach ($invoices as $invoice) {
                        foreach ($invoice->items()->get() as $item) {
                            if ($item->job_id) {
                                AgentJobEntry::where('agent_id', $invoice->agent_id)
                                    ->where('job_id', $item->job_id)
                                    ->where('invoiced', true)
                                    ->update(['invoiced' => false]);
                            }
                        }
                    }
                    // Delete items then invoices
                    AgentInvoiceItem::whereIn('agent_invoice_id', $invoiceIds)->delete();
                    AgentInvoice::whereIn('id', $invoiceIds)->delete();
                    $msg = 'Agent invoices deleted successfully';
                    if ($request->wantsJson()) {
                        return response()->json(['success' => true, 'message' => $msg]);
                    }
                    return redirect()->back()->with('success', $msg);
                } else {
                    // Client invoices: guard against remittance-linked invoices
                    $blocked = Remittance::whereIn('client_invoice_id', $invoiceIds)->pluck('client_invoice_id')->unique()->values()->all();
                    $allowed = array_values(array_diff($invoiceIds, $blocked));
                    if (!empty($allowed)) {
                        ClientInvoiceItem::whereIn('client_invoice_id', $allowed)->delete();
                        ClientInvoice::whereIn('id', $allowed)->delete();
                    }
                    $message = 'Client invoices deleted successfully';
                    if (!empty($blocked)) {
                        $message .= '. Skipped invoices with remittances: ' . implode(', ', $blocked);
                    }
                    if ($request->wantsJson()) {
                        return response()->json(['success' => true, 'message' => $message, 'skipped' => $blocked]);
                    }
                    return redirect()->back()->with('success', $message);
                }
                
            default:
                return response()->json(['error' => 'Invalid action'], 400);
        }
    }

    // Admin Invoice Actions
    public function showAgent($id)
    {
        $invoice = AgentInvoice::with(['agent.user', 'items.job'])->findOrFail($id);
        return view('invoices.agent.show', compact('invoice'));
    }

    public function showClient($id)
    {
        $invoice = ClientInvoice::with(['client.user', 'company', 'items.job'])->findOrFail($id);
        return view('invoices.client.show', compact('invoice'));
    }

    public function approveAgent($id)
    {
        $invoice = AgentInvoice::with(['agent.user'])->findOrFail($id);
        $statusValue = is_numeric($invoice->status) ? 2 : 'sent';
        $invoice->update(['status' => $statusValue]);

        $this->notifyAgentInvoiceAction($invoice, 'approved');

        return response()->json(['message' => 'Agent invoice approved successfully']);
    }

    public function markPaidAgent($id)
    {
        $invoice = AgentInvoice::with(['agent.user'])->findOrFail($id);
        $statusValue = $this->mapAgentStatusValue('paid');

        DB::transaction(function () use ($invoice, $statusValue) {
            $invoice->update(['status' => $statusValue]);

            $linkedPayslips = \App\Payslip::where('agent_invoice_id', $invoice->id)
                ->where('status', 'approved')
                ->whereNull('paid_at')
                ->get();

            foreach ($linkedPayslips as $ps) {
                $ps->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }
        });

        $this->notifyAgentInvoiceAction($invoice, 'paid');

        return response()->json(['success' => true, 'message' => 'Agent invoice marked as paid']);
    }

    public function approveClient($id)
    {
        $invoice = ClientInvoice::findOrFail($id);
        $invoice->update(['status' => 'sent']);
        return response()->json(['message' => 'Client invoice approved successfully']);
    }

    public function markPaidClient($id)
    {
        $invoice = ClientInvoice::findOrFail($id);
        $invoice->update(['status' => 'paid']);
        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Client invoice marked as paid']);
        }
        return redirect()->back()->with('success', 'Client invoice marked as paid');
    }

    public function editAgent($id)
    {
        $invoice = AgentInvoice::with(['agent.user', 'items'])->findOrFail($id);
        return view('invoices.agent.edit', compact('invoice'));
    }

    public function editClient($id)
    {
        $invoice = ClientInvoice::with(['client.user', 'company', 'items'])->findOrFail($id);
        return view('invoices.client.edit', compact('invoice'));
    }

    public function duplicateAgent($id)
    {
        $originalInvoice = AgentInvoice::with(['items'])->findOrFail($id);
        
        $newInvoice = $originalInvoice->replicate();
        $newInvoice->invoice_number = 'INV-' . date('Y') . '-' . str_pad(AgentInvoice::count() + 1, 6, '0', STR_PAD_LEFT);
        $newInvoice->status = $this->mapAgentStatusValue('draft');
        $newInvoice->save();

        // Duplicate items if they exist
        foreach ($originalInvoice->items()->get() as $item) {
            $newItem = $item->replicate();
            $newItem->invoice_id = $newInvoice->id;
            $newItem->save();
        }

        return response()->json(['message' => 'Agent invoice duplicated successfully']);
    }

    public function duplicateClient($id)
    {
        $originalInvoice = ClientInvoice::with(['items'])->findOrFail($id);
        
        $newInvoice = $originalInvoice->replicate();
        $newInvoice->invoice_number = 'INV-' . date('Y') . '-' . str_pad(ClientInvoice::count() + 1, 6, '0', STR_PAD_LEFT);
        $newInvoice->status = 'draft';
        $newInvoice->save();

        // Duplicate items if they exist
        foreach ($originalInvoice->items()->get() as $item) {
            $newItem = $item->replicate();
            $newItem->invoice_id = $newInvoice->id;
            $newItem->save();
        }

        return response()->json(['message' => 'Client invoice duplicated successfully']);
    }

    public function deleteAgent($id)
    {
        try {
            $invoice = AgentInvoice::with('items')->findOrFail($id);

            // Revert related agent job entries to uninvoiced
            foreach ($invoice->items()->get() as $item) {
                if ($item->job_id) {
                    AgentJobEntry::where('agent_id', $invoice->agent_id)
                        ->where('job_id', $item->job_id)
                        ->where('invoiced', true)
                        ->update(['invoiced' => false]);
                }
            }

            // Delete invoice items explicitly to avoid orphaned rows
            AgentInvoiceItem::where('agent_invoice_id', $invoice->id)->delete();

            // Delete the invoice itself
            $invoice->delete();

            return response()->json(['success' => true, 'message' => 'Agent invoice deleted successfully']);
        } catch (\Throwable $t) {
            Log::error('Failed to delete agent invoice: ' . $t->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete agent invoice'], 500);
        }
    }

    public function deleteClient($id)
    {
        try {
            $invoice = ClientInvoice::with('items')->findOrFail($id);

            // Guard: Do not allow deletion if a remittance exists for this invoice
            $hasRemittance = Remittance::where('client_invoice_id', $invoice->id)->exists();
            if ($hasRemittance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete invoice: a remittance is linked to this invoice. Remove the remittance first.'
                ], 409);
            }

            // Delete related client invoice items so jobs can be invoiced again
            ClientInvoiceItem::where('client_invoice_id', $invoice->id)->delete();

            // Delete the invoice itself
            $invoice->delete();

            return response()->json(['success' => true, 'message' => 'Client invoice deleted successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Client invoice not found'], 404);
        } catch (QueryException $e) {
            // Typically SQLSTATE[23000] indicates FK constraint violation
            $code = $e->getCode();
            Log::error('QueryException deleting client invoice '.$id.': '.$e->getMessage());
            if ((string)$code === '23000') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete invoice because related records exist (e.g., remittance or payments).',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ], 409);
            }
            return response()->json([
                'success' => false,
                'message' => 'Database error while deleting client invoice',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        } catch (\Throwable $t) {
            Log::error('Failed to delete client invoice: ' . $t->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete client invoice',
                'details' => config('app.debug') ? $t->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get invoice data for payslip generation
     */
    public function getPayslipData($id)
    {
        try {
            $invoice = \App\AgentInvoice::with(['agent.user', 'items'])->findOrFail($id);

            // Prefetch jobs and documents to avoid N+1 queries
            $translatorIds = [];
            $interpreterIds = [];
            foreach ($invoice->items()->get() as $it) {
                $meta = is_array($it->metadata) ? $it->metadata : (is_string($it->metadata) ? (json_decode($it->metadata, true) ?: []) : []);
                $type = $meta['job_type'] ?? null;
                if ($type === 'App\\TranslatorJob') { $translatorIds[] = (int) $it->job_id; }
                elseif ($type === 'App\\InterpreterJob') { $interpreterIds[] = (int) $it->job_id; }
                else { // unknown: try both later
                    $translatorIds[] = (int) $it->job_id;
                    $interpreterIds[] = (int) $it->job_id;
                }
            }
            $translatorIds = array_values(array_unique(array_filter($translatorIds)));
            $interpreterIds = array_values(array_unique(array_filter($interpreterIds)));

            $docTypes = [config('enums.document_types')["agent_invoice"], config('enums.document_types')["translated_file"]];
            $translatorJobs = collect();
            if (!empty($translatorIds)) {
                $translatorJobs = \App\TranslatorJob::with(['client.user', 'fromLanguage', 'toLanguage', 'documents' => function($q) use ($docTypes){
                    $q->whereIn('type', $docTypes)->orderByDesc('created_at');
                }])->whereIn('id', $translatorIds)->get()->keyBy('id');
            }
            $interpreterJobs = collect();
            if (!empty($interpreterIds)) {
                $interpreterJobs = \App\InterpreterJob::with(['client.user', 'toLanguage'])->whereIn('id', $interpreterIds)->get()->keyBy('id');
            }

            // Prepare enriched items with job details expected by the frontend
            $preparedItems = [];
            foreach ($invoice->items()->get() as $item) {
                $meta = is_array($item->metadata) ? $item->metadata : (is_string($item->metadata) ? (json_decode($item->metadata, true) ?: []) : []);
                $jobType = $meta['job_type'] ?? null;
                $job = null;

                if ($jobType === 'App\\TranslatorJob') {
                    $job = $translatorJobs->get((int) $item->job_id);
                } elseif ($jobType === 'App\\InterpreterJob') {
                    $job = $interpreterJobs->get((int) $item->job_id);
                } else {
                    // Fallback: check both maps
                    $job = $interpreterJobs->get((int) $item->job_id) ?: $translatorJobs->get((int) $item->job_id);
                }

                $clientName = null;
                $language = null;
                $date = null;
                $postal = null;
                $jobRef = $item->job_id;

                if ($job) {
                    $clientName = optional(optional($job->client)->user)->getFullName();
                    $jobRef = method_exists($job, 'getReferenceAttribute') ? $job->reference : (string)$job->id;

                    if ($job instanceof \App\InterpreterJob) {
                        $language = optional($job->toLanguage ?? $job->to_language)->name;
                        $date = $job->appointment_date ? Carbon::parse($job->appointment_date)->toDateString() : null;
                        $postal = trim(implode(', ', array_filter([
                            $job->address_line_1,
                            $job->address_line_2,
                            $job->county,
                            $job->postcode,
                        ])), ', ');
                    } else {
                        $language = trim(sprintf('%s → %s', optional($job->fromLanguage)->name, optional($job->toLanguage)->name), ' → ');
                        $date = $job->target_date ? Carbon::parse($job->target_date)->toDateString() : null;
                    }
                }

                // Use correct amount field from AgentInvoiceItem schema
                $amount = (float) ($item->total_amount ?? 0);

                // Translator job documents enrichment via preloaded relation
                $agentInvoiceDoc = null;
                $translatedFileDoc = null;
                $resolvedJobType = null;
                if ($job instanceof \App\InterpreterJob) {
                    $resolvedJobType = 'App\\InterpreterJob';
                } elseif ($job instanceof \App\TranslatorJob) {
                    $resolvedJobType = 'App\\TranslatorJob';
                    try {
                        $docs = $job->documents ?? collect();
                        $agentDocModel = $docs->firstWhere('type', config('enums.document_types')["agent_invoice"]);
                        if ($agentDocModel) { $agentInvoiceDoc = ['name' => $agentDocModel->name, 'url' => $agentDocModel->fullUrl]; }
                        $translatedDocModel = $docs->firstWhere('type', config('enums.document_types')["translated_file"]);
                        if ($translatedDocModel) { $translatedFileDoc = ['name' => $translatedDocModel->name, 'url' => $translatedDocModel->fullUrl]; }
                    } catch (\Throwable $t) {
                        Log::warning('Translator job document lookup failed for job '.$item->job_id.': '.$t->getMessage());
                    }
                }

                $preparedItems[] = [
                    'id' => $item->id,
                    'description' => $item->description,
                    'amount' => $amount,
                    'surcharge' => (float) $item->surcharge,
                    'other_charges' => (float) $item->other_charges,
                    'vat_applicable' => (bool) $item->vat_applicable,
                    'job_id' => $item->job_id,
                    'job_ref' => $jobRef,
                    'client_name' => $clientName,
                    'language' => $language,
                    'date' => $date,
                    'postal_code' => $postal,
                    'job_type' => $resolvedJobType,
                    'agent_invoice_document' => $agentInvoiceDoc,
                    'translated_file_document' => $translatedFileDoc,
                    'job' => [
                        'id' => $item->job_id,
                        'ref' => $jobRef,
                        'client_name' => $clientName,
                        'language' => $language,
                        'date' => $date,
                        'postal' => $postal,
                        'type' => $resolvedJobType,
                        'documents' => [
                            'agent_invoice' => $agentInvoiceDoc,
                            'translated_file' => $translatedFileDoc,
                        ],
                    ],
                ];
            }

            return response()->json([
                'invoice' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'subtotal' => (float) $invoice->subtotal,
                    'total_amount' => (float) $invoice->total_amount,
                    'created_at' => optional($invoice->created_at)->toDateTimeString(),
                    // Include minimal agent info for display
                    'agent' => [
                        'id' => optional($invoice->agent)->id,
                        'user' => [
                            'first_name' => optional(optional($invoice->agent)->user)->first_name,
                            'last_name' => optional(optional($invoice->agent)->user)->last_name,
                            'full_name' => optional(optional($invoice->agent)->user)->full_name,
                        ]
                    ],
                ],
                'items' => $preparedItems,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function generatePayslip(Request $request, $id)
    {
        try {
            $invoice = \App\AgentInvoice::with(['agent.user', 'items'])->findOrFail($id);

            // Check if payslip already exists
            $existingPayslip = \App\Payslip::where('agent_invoice_id', $id)->first();
            if ($existingPayslip) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payslip already exists for this invoice'
                ]);
            }

            // Enhanced data from request
            $adminTotalAmount = (float) $request->input('admin_total_amount', $invoice->total_amount);
            $itemUpdates = (array) $request->input('items', []);
            $auditTrail = (array) $request->input('audit_trail', []); // not persisted in DB for now

            // Map updates by invoice item id
            $updatesById = [];
            foreach ($itemUpdates as $u) {
                $key = data_get($u, 'id');
                if ($key) {
                    $updatesById[$key] = $u;
                }
            }

            // Prefetch jobs and documents to avoid N+1 queries when enriching items
            $existingJobIds = [];
            foreach ($invoice->items()->get() as $it) { if ($it->job_id) { $existingJobIds[] = (int) $it->job_id; } }
            $newItemJobIds = [];
            foreach ($itemUpdates as $u) {
                if (data_get($u, 'is_new') && data_get($u, 'job_id')) {
                    $newItemJobIds[] = (int) data_get($u, 'job_id');
                }
            }
            $allJobIds = array_values(array_unique(array_filter(array_merge($existingJobIds, $newItemJobIds))));
            $translatorJobsMap = collect();
            $interpreterJobsMap = collect();
            if (!empty($allJobIds)) {
                $docTypes = [config('enums.document_types')["agent_invoice"], config('enums.document_types')["translated_file"]];
                try {
                    $translatorJobsMap = \App\TranslatorJob::with(['client.user', 'fromLanguage', 'toLanguage', 'documents' => function($q) use ($docTypes){
                        $q->whereIn('type', $docTypes)->orderByDesc('created_at');
                    }])->whereIn('id', $allJobIds)->get()->keyBy('id');
                } catch (\Throwable $e) { $translatorJobsMap = collect(); }
                try {
                    $interpreterJobsMap = \App\InterpreterJob::with(['client.user', 'toLanguage'])->whereIn('id', $allJobIds)->get()->keyBy('id');
                } catch (\Throwable $e) { $interpreterJobsMap = collect(); }
            }

            $itemsForPayslip = [];
            $subtotal = 0.0;
            $adjustmentsTotal = 0.0;

            $periodStart = $invoice->date_from ? \Carbon\Carbon::parse($invoice->date_from)->toDateString() : ($request->input('start_date') ?: null);
            $periodEnd = $invoice->date_to ? \Carbon\Carbon::parse($invoice->date_to)->toDateString() : ($request->input('end_date') ?: null);

            // Build payslip items from existing invoice items
        foreach ($invoice->items()->get() as $invItem) {
                $original = (float) ($invItem->total_amount ?? 0);
                $adminAmt = $original;
                if (isset($updatesById[$invItem->id])) {
                    $adminAmt = (float) ($updatesById[$invItem->id]['admin_amount'] ?? $original);
                }
                $adjustment = $adminAmt - $original;
                $includeByPeriod = true;

                // Enrich job info for description & display
                $jobRef = null; $clientName = null; $language = null; $date = null;
                $jobType = null;
                $meta = $invItem->metadata;
                if (is_array($meta)) { $jobType = $meta['job_type'] ?? null; }
                elseif (is_string($meta)) { $tmp = json_decode($meta, true); $jobType = is_array($tmp) ? ($tmp['job_type'] ?? null) : null; }
                // Resolve job from prefetched maps
                $job = null;
                if ($jobType === 'App\\TranslatorJob') {
                    $job = $translatorJobsMap->get((int) $invItem->job_id);
                } elseif ($jobType === 'App\\InterpreterJob') {
                    $job = $interpreterJobsMap->get((int) $invItem->job_id);
                } else {
                    $job = $interpreterJobsMap->get((int) $invItem->job_id) ?: $translatorJobsMap->get((int) $invItem->job_id);
                }
                if ($job) {
                    $clientName = optional(optional($job->client)->user)->getFullName();
                    $jobRef = method_exists($job, 'getReferenceAttribute') ? $job->reference : (string) $job->id;
                    if ($job instanceof \App\InterpreterJob) {
                        $language = optional($job->toLanguage)->name;
                        $date = $job->appointment_date ? \Carbon\Carbon::parse($job->appointment_date)->toDateString() : null;
                    } else {
                        $language = trim(sprintf('%s → %s', optional($job->fromLanguage)->name, optional($job->toLanguage)->name), ' → ');
                        $date = $job->target_date ? \Carbon\Carbon::parse($job->target_date)->toDateString() : null;
                    }
                }

                if ($periodStart && $periodEnd && $date) {
                    $includeByPeriod = ($date >= $periodStart && $date <= $periodEnd);
                }
                if (!$includeByPeriod) {
                    continue;
                }

                $subtotal += $original;
                $adjustmentsTotal += $adjustment;

                // Include document metadata (translator jobs)
                $agentInvoiceDoc = null; $translatedFileDoc = null; $resolvedJobType = null;
                if ($job instanceof \App\InterpreterJob) {
                    $resolvedJobType = 'App\\InterpreterJob';
                } elseif ($job instanceof \App\TranslatorJob) {
                    $resolvedJobType = 'App\\TranslatorJob';
                    try {
                        $docs = $job->documents ?? collect();
                        $agentDocModel = $docs->firstWhere('type', config('enums.document_types')["agent_invoice"]);
                        if ($agentDocModel) {
                            $agentInvoiceDoc = ['name' => $agentDocModel->name, 'url' => $agentDocModel->fullUrl];
                        }
                        $translatedDocModel = $docs->firstWhere('type', config('enums.document_types')["translated_file"]);
                        if ($translatedDocModel) {
                            $translatedFileDoc = ['name' => $translatedDocModel->name, 'url' => $translatedDocModel->fullUrl];
                        }
                    } catch (\Throwable $t) {
                        Log::warning('Translator doc lookup failed (existing item) job '.$invItem->job_id.': '.$t->getMessage());
                    }
                }

                $desc = $invItem->description ?: ($jobRef ? "Job {$jobRef} - {$clientName}" : "Invoice item #{$invItem->id}");
                $itemsForPayslip[] = [
                    'description' => $desc,
                    'amount' => round($original, 2),
                    'adjustment' => round($adjustment, 2),
                    'total' => round($original + $adjustment, 2),
                    'invoice_item_id' => $invItem->id,
                    'job_id' => $invItem->job_id,
                    'job_ref' => $jobRef,
                    'client_name' => $clientName,
                    'language' => $language,
                    'date' => $date,
                    'job_type' => $resolvedJobType,
                    'agent_invoice_document' => $agentInvoiceDoc,
                    'translated_file_document' => $translatedFileDoc,
                ];
            }

            // Handle new items added by admin (not in the original invoice)
            foreach ($itemUpdates as $u) {
                $isNew = (bool) data_get($u, 'is_new', false);
                if (!$isNew) { continue; }

                $adminAmt = (float) data_get($u, 'admin_amount', 0);
                $jobId = data_get($u, 'job_id');
                $jobRef = data_get($u, 'job_ref');
                $isUninvoiced = (bool) data_get($u, 'is_uninvoiced', false);

                $original = 0.0; // since it wasn't part of the invoice
                $clientName = null; $language = null; $date = null;
                // Resolve job from prefetched maps for new items
                $job = $interpreterJobsMap->get((int) $jobId) ?: $translatorJobsMap->get((int) $jobId);
                if ($job) {
                    $clientName = optional(optional($job->client)->user)->getFullName();
                    if ($job instanceof \App\InterpreterJob) {
                        $language = optional($job->toLanguage)->name;
                        $date = $job->appointment_date ? \Carbon\Carbon::parse($job->appointment_date)->toDateString() : null;
                    } else {
                        $language = trim(sprintf('%s → %s', optional($job->fromLanguage)->name, optional($job->toLanguage)->name), ' → ');
                        $date = $job->target_date ? \Carbon\Carbon::parse($job->target_date)->toDateString() : null;
                    }
                }

                // Document metadata for newly added item
                $agentInvoiceDoc = null; $translatedFileDoc = null; $resolvedJobType = null;
                if ($job instanceof \App\InterpreterJob) {
                    $resolvedJobType = 'App\\InterpreterJob';
                } elseif ($job instanceof \App\TranslatorJob) {
                    $resolvedJobType = 'App\\TranslatorJob';
                    try {
                        $docs = $job->documents ?? collect();
                        $agentDocModel = $docs->firstWhere('type', config('enums.document_types')["agent_invoice"]);
                        if ($agentDocModel) {
                            $agentInvoiceDoc = ['name' => $agentDocModel->name, 'url' => $agentDocModel->fullUrl];
                        }
                        $translatedDocModel = $docs->firstWhere('type', config('enums.document_types')["translated_file"]);
                        if ($translatedDocModel) {
                            $translatedFileDoc = ['name' => $translatedDocModel->name, 'url' => $translatedDocModel->fullUrl];
                        }
                    } catch (\Throwable $t) {
                        Log::warning('Translator doc lookup failed (new item) job '.$jobId.': '.$t->getMessage());
                    }
                }

                $adjustment = $adminAmt - $original; // adminAmt itself when original is 0
                $adjustmentsTotal += $adjustment;

                $desc = ($jobRef ? "Added job {$jobRef}" : "Added job") . ($clientName ? " ({$clientName})" : "");
                $itemsForPayslip[] = [
                    'description' => $desc,
                    'amount' => round($original, 2),
                    'adjustment' => round($adjustment, 2),
                    'total' => round($original + $adjustment, 2),
                    'job_id' => $jobId,
                    'job_ref' => $jobRef,
                    'client_name' => $clientName,
                    'language' => $language,
                    'date' => $date,
                    'is_uninvoiced' => $isUninvoiced,
                    'is_new' => true,
                    'job_type' => $resolvedJobType,
                    'agent_invoice_document' => $agentInvoiceDoc,
                    'translated_file_document' => $translatedFileDoc,
                ];
            }

            // Create payslip record (align to schema)
            $payslipNumber = $this->generatePayslipNumber();
            $payslip = new \App\Payslip();
            $payslip->payslip_number = $payslipNumber;
            $payslip->agent_id = $invoice->agent_id;
            $payslip->agent_invoice_id = $invoice->id;
            $payslip->items = $itemsForPayslip;
            $payslip->subtotal = round($subtotal, 2);
            $payslip->adjustments_total = round($adjustmentsTotal, 2);
            $payslip->total = round($adminTotalAmount ?: ($subtotal + $adjustmentsTotal), 2);
            $payslip->status = 'pending';
            if ($periodStart) { $payslip->period_start = $periodStart; }
            if ($periodEnd) { $payslip->period_end = $periodEnd; }
            $payslip->save();

            $this->notifyAgentPayslipAction($payslip, 'generated');

            return response()->json([
                'success' => true,
                'message' => 'Payslip generated successfully',
                'payslip_id' => $payslip->id,
                'payslip_url' => route('payslips.show', $payslip->id),
                'redirect_url' => route('payslips.index'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating payslip: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generatePayslipNumber()
    {
        $year = date('Y');
        $month = date('m');
        $prefix = "PS{$year}{$month}";
        
        $lastPayslip = \App\Payslip::where('payslip_number', 'like', $prefix . '%')
            ->orderBy('payslip_number', 'desc')
            ->first();
        
        if ($lastPayslip) {
            $lastNumber = intval(substr($lastPayslip->payslip_number, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Show builder form to create a Client Invoice (Admin)
     */
    public function createClient(Request $request)
    {
        $clients = Client::with('user')->orderByDesc('id')->limit(100)->get();
        $companies = Company::orderBy('name')->get();

        $defaults = [
            'invoice_date' => $request->query('invoice_date', now()->toDateString()),
            'include_vat' => (bool) $request->query('include_vat', true),
            'vat_rate' => (float) $request->query('vat_rate', 20),
            'payment_terms_text' => $request->query('payment_terms_text', '28 days from invoice date'),
        ];

        return view('invoices.admin.client.create', compact('clients', 'companies', 'defaults'));
    }

    /**
     * Persist a new Client Invoice from builder form
     */
    public function storeClient(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'invoice_date' => ['required', 'date'],
            'payment_terms_text' => ['nullable', 'string', 'max:255'],
            'file_reference' => ['nullable', 'string', 'max:255'],
            'client_reference' => ['nullable', 'string', 'max:255'],
            'patient_number' => ['nullable', 'string', 'max:255'],
            'pid' => ['nullable', 'string', 'max:255'],
            'interpreting_time_minutes' => ['nullable', 'integer', 'min:0'],
            'travel_time_minutes' => ['nullable', 'integer', 'min:0'],
            'parking_amount' => ['nullable', 'numeric', 'min:0'],
            'include_vat' => ['nullable', 'boolean'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            // Single service line (simple builder)
            'service_description' => ['required', 'string', 'max:255'],
            'service_date' => ['required', 'date'],
            'service_amount' => ['required', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($data) {
            $subtotal = (float) ($data['service_amount'] ?? 0);
            $parking = (float) ($data['parking_amount'] ?? 0);
            $subtotal += $parking;

            $includeVat = array_key_exists('include_vat', $data) ? (bool) $data['include_vat'] : true;
            $vatRate = (float) ($data['vat_rate'] ?? 20);
            $vatAmount = $includeVat ? round($subtotal * ($vatRate / 100), 2) : 0.0;
            $total = round($subtotal + $vatAmount, 2);

            $invoice = ClientInvoice::create([
                'client_id' => $data['client_id'],
                'company_id' => $data['company_id'] ?? null,
                'invoice_number' => 'CI-TMP-' . uniqid(),
                'invoice_date' => $data['invoice_date'],
                'date_range_start' => $data['service_date'],
                'date_range_end' => $data['service_date'],
                'template' => 'v2',
                'include_vat' => $includeVat,
                'vat_rate' => $vatRate,
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total' => $total,
                'status' => 'draft',
                'payment_terms_text' => $data['payment_terms_text'] ?? null,
                'file_reference' => $data['file_reference'] ?? null,
                'client_reference' => $data['client_reference'] ?? null,
                'patient_number' => $data['patient_number'] ?? null,
                'pid' => $data['pid'] ?? null,
                'interpreting_time_minutes' => $data['interpreting_time_minutes'] ?? null,
                'travel_time_minutes' => $data['travel_time_minutes'] ?? null,
                'parking_amount' => $parking,
            ]);

            // Assign formatted invoice number after ID is known
            $invoice->invoice_number = 'CI-' . str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT);
            $invoice->save();

            ClientInvoiceItem::create([
                'client_invoice_id' => $invoice->id,
                'description' => $data['service_description'],
                'amount' => (float) $data['service_amount'],
                'surcharge' => 0,
                'other_charges' => 0,
                'vat_applicable' => $includeVat,
                'metadata' => [
                    'date' => $data['service_date'],
                ],
            ]);

            return redirect()->route('invoices.admin.client.pdf', $invoice->id)
                ->with('success', 'Client invoice #' . $invoice->invoice_number . ' created.');
        });
    }

    /**
     * Update an existing Client Invoice
     */
    public function updateClient(Request $request, $id)
    {
        $invoice = ClientInvoice::with('items')->findOrFail($id);

        $data = $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'invoice_date' => ['nullable', 'date'],
            'payment_terms_text' => ['nullable', 'string', 'max:255'],
            'file_reference' => ['nullable', 'string', 'max:255'],
            'client_reference' => ['nullable', 'string', 'max:255'],
            'patient_number' => ['nullable', 'string', 'max:255'],
            'pid' => ['nullable', 'string', 'max:255'],
            'interpreting_time_minutes' => ['nullable', 'integer', 'min:0'],
            'travel_time_minutes' => ['nullable', 'integer', 'min:0'],
            'parking_amount' => ['nullable', 'numeric', 'min:0'],
            'translation_cost' => ['nullable', 'numeric', 'min:0'],
            'include_vat' => ['nullable', 'boolean'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'template' => ['nullable', 'in:v2,v3,auto'],
            // New items added from modal
            'new_item_descriptions' => ['nullable', 'array'],
            'new_item_descriptions.*' => ['nullable', 'string', 'max:255'],
            'new_item_dates' => ['nullable', 'array'],
            // Allow optional dates as strings; we'll normalize below
            'new_item_dates.*' => ['nullable', 'string'],
            'new_item_amounts' => ['nullable', 'array'],
            'new_item_amounts.*' => ['nullable', 'numeric', 'min:0'],
            // Job associations
            'new_item_job_ids' => ['nullable', 'array'],
            'new_item_job_ids.*' => ['nullable', 'integer'],
            'new_item_job_types' => ['nullable', 'array'],
            'new_item_job_types.*' => ['nullable', 'string'],
            // Header summary items (top-right of PDF)
            'header_item_labels' => ['nullable', 'array'],
            'header_item_labels.*' => ['nullable', 'string', 'max:255'],
            'header_item_values' => ['nullable', 'array'],
            'header_item_values.*' => ['nullable', 'string', 'max:255'],
            // Bulk per-item detail updates (arrays keyed by item ID)
            'item_client_reference' => ['nullable', 'array'],
            'item_client_reference.*' => ['nullable', 'string', 'max:255'],
            'item_file_reference' => ['nullable', 'array'],
            'item_file_reference.*' => ['nullable', 'string', 'max:255'],
            'item_patient_number' => ['nullable', 'array'],
            'item_patient_number.*' => ['nullable', 'string', 'max:255'],
            'item_pid' => ['nullable', 'array'],
            'item_pid.*' => ['nullable', 'string', 'max:255'],
            'item_interpreting_minutes' => ['nullable', 'array'],
            'item_interpreting_minutes.*' => ['nullable', 'integer', 'min:0'],
            'item_travel_minutes' => ['nullable', 'array'],
            'item_travel_minutes.*' => ['nullable', 'integer', 'min:0'],
            'item_parking_amount' => ['nullable', 'array'],
            'item_parking_amount.*' => ['nullable', 'numeric', 'min:0'],
            'item_translation_cost' => ['nullable', 'array'],
            'item_translation_cost.*' => ['nullable', 'numeric', 'min:0'],
            'item_dates' => ['nullable', 'array'],
            'item_dates.*' => ['nullable', 'string'],
            'bill_to_name' => ['nullable', 'string', 'max:255'],
            'bill_to_email' => ['nullable', 'email'],
            'bill_to_phone' => ['nullable', 'string', 'max:255'],
            'bill_to_address_line_1' => ['nullable', 'string', 'max:255'],
            'bill_to_address_line_2' => ['nullable', 'string', 'max:255'],
            'bill_to_county' => ['nullable', 'string', 'max:255'],
            'bill_to_postcode' => ['nullable', 'string', 'max:255'],
        ]);

        // Persist basic field updates first
        $invoice->fill($data);
        // Allow overriding invoice number directly (e.g., Job ID with optional prefix/suffix)
        if (array_key_exists('invoice_number', $data)) {
            $val = trim((string)($data['invoice_number'] ?? ''));
            if ($val !== '') { $invoice->invoice_number = $val; }
        }

        // Create any newly added items from the modal
        $descArr = (array) ($data['new_item_descriptions'] ?? []);
        $dateArr = (array) ($data['new_item_dates'] ?? []);
        $amountArr = (array) ($data['new_item_amounts'] ?? []);
        $jobIdArr = (array) ($data['new_item_job_ids'] ?? []);
        $jobTypeArr = (array) ($data['new_item_job_types'] ?? []);

        // Resolve VAT flag for items
        $includeVatFlag = array_key_exists('include_vat', $data)
            ? (bool) $data['include_vat']
            : (bool) ($invoice->include_vat ?? true);

        foreach ($descArr as $idx => $desc) {
            $desc = is_string($desc) ? trim($desc) : '';
            $amount = isset($amountArr[$idx]) ? (float) $amountArr[$idx] : null;
            $date = $dateArr[$idx] ?? null;
            $jobId = isset($jobIdArr[$idx]) ? (int)$jobIdArr[$idx] : null;
            $jobType = isset($jobTypeArr[$idx]) ? (string)$jobTypeArr[$idx] : null;

            // Normalize date string safely to Y-m-d
            $dateString = null;
            if ($date) {
                try {
                    $dateString = \Carbon\Carbon::parse($date)->toDateString();
                } catch (\Throwable $e) {
                    $dateString = null;
                }
            }
            // Do NOT default to invoice date; allow additional items to have no date
            if (!$dateString) {
                $dateString = null;
            }

            if ($desc !== '' && $amount !== null && $amount >= 0) {
                // Try to resolve job type if not provided
                if ($jobId && !$jobType) {
                    $jobType = \App\InterpreterJob::find($jobId) ? 'App\\InterpreterJob' : (\App\TranslatorJob::find($jobId) ? 'App\\TranslatorJob' : null);
                }

                ClientInvoiceItem::create([
                    'client_invoice_id' => $invoice->id,
                    'description' => $desc,
                    'amount' => $amount,
                    'surcharge' => 0,
                    'other_charges' => 0,
                    'vat_applicable' => $includeVatFlag,
                    'job_id' => $jobId,
                    'job_type' => $jobType,
                    'metadata' => [
                        'date' => $dateString,
                        'job_type' => $jobType,
                        'source' => 'admin_invoice_detail_modal',
                    ],
                ]);
            }
        }

        // Edit or remove existing standalone items (created via modal earlier)
        $existingDesc = (array) ($request->input('existing_item_descriptions', []) ?? []);
        $existingDate = (array) ($request->input('existing_item_dates', []) ?? []);
        $existingAmount = (array) ($request->input('existing_item_amounts', []) ?? []);
        $existingRemove = (array) ($request->input('existing_item_remove', []) ?? []);

        foreach ($invoice->items()->get() as $item) {
            $md = is_array($item->metadata) ? $item->metadata : (is_string($item->metadata) ? (json_decode($item->metadata, true) ?: []) : []);
            $isStandalone = isset($md['source']) && $md['source'] === 'admin_invoice_detail_modal' && empty($item->job_id);
            if (!$isStandalone) { continue; }

            // Handle removal first
            if (array_key_exists($item->id, $existingRemove) && (string)$existingRemove[$item->id] !== '' && (int)$existingRemove[$item->id] === 1) {
                $item->delete();
                continue;
            }

            // Update description/date/amount if provided
            $changed = false;
            if (array_key_exists($item->id, $existingDesc)) {
                $val = trim((string) $existingDesc[$item->id]);
                $item->description = $val;
                $changed = true;
            }
            if (array_key_exists($item->id, $existingAmount)) {
                $val = $existingAmount[$item->id];
                $item->amount = ($val !== null && $val !== '') ? (float)$val : 0.0;
                $changed = true;
            }
            if (array_key_exists($item->id, $existingDate)) {
                $val = $existingDate[$item->id];
                if ($val === '' || $val === null) {
                    $md['date'] = null;
                } else {
                    try { $md['date'] = \Carbon\Carbon::parse($val)->toDateString(); }
                    catch (\Throwable $e) { $md['date'] = null; }
                }
                $item->metadata = $md;
                $changed = true;
            }
            if ($changed) { $item->save(); }
        }

        // Apply bulk per-item detail updates (metadata)
        $perItemFields = [
            'client_reference' => (array) ($data['item_client_reference'] ?? []),
            'file_reference' => (array) ($data['item_file_reference'] ?? []),
            'patient_number' => (array) ($data['item_patient_number'] ?? []),
            'pid' => (array) ($data['item_pid'] ?? []),
            'interpreting_time_minutes' => (array) ($data['item_interpreting_minutes'] ?? []),
            'travel_time_minutes' => (array) ($data['item_travel_minutes'] ?? []),
            'parking_amount' => (array) ($data['item_parking_amount'] ?? []),
            'date' => (array) ($data['item_dates'] ?? []),
            'translation_cost' => (array) ($data['item_translation_cost'] ?? []),
        ];

        foreach ($invoice->items()->get() as $item) {
            $meta = is_array($item->metadata) ? $item->metadata : (is_string($item->metadata) ? (json_decode($item->metadata, true) ?: []) : []);
            $changed = false;

            foreach ($perItemFields as $key => $map) {
                if (array_key_exists($item->id, $map)) {
                    $value = $map[$item->id];
                    // Normalize types
                    if (in_array($key, ['interpreting_time_minutes','travel_time_minutes'])) {
                        $value = $value !== null && $value !== '' ? (int) $value : null;
                    } elseif ($key === 'parking_amount') {
                        $value = $value !== null && $value !== '' ? (float) $value : null;
                    } elseif ($key === 'date') {
                        if ($value === '' || $value === null) {
                            $value = null; // explicit clear
                        } else {
                            try { $value = Carbon::parse($value)->toDateString(); } catch (\Throwable $e) { $value = null; }
                        }
                    } else {
                        $value = $value !== '' ? $value : null;
                    }
                    $meta[$key] = $value;
                    $changed = true;
                }
            }

            if ($changed) {
                $item->metadata = $meta;
                $item->save();
            }

            $tVal = null;
            if (array_key_exists($item->id, $perItemFields['translation_cost'])) {
                $tv = $perItemFields['translation_cost'][$item->id];
                $tVal = ($tv !== null && $tv !== '') ? (float) $tv : null;
            }
            if ($tVal !== null || array_key_exists($item->id, $perItemFields['translation_cost'])) {
                $labels = isset($meta['charge_labels']) && is_array($meta['charge_labels']) ? $meta['charge_labels'] : [];
                $values = isset($meta['charge_values']) && is_array($meta['charge_values']) ? $meta['charge_values'] : [];
                $idx = null;
                foreach ($labels as $i => $lbl) {
                    $norm = strtolower(trim((string) $lbl));
                    if ($norm === 'translation') { $idx = $i; break; }
                }
                if ($tVal === null) {
                    if ($idx !== null) {
                        unset($labels[$idx]);
                        unset($values[$idx]);
                        $labels = array_values($labels);
                        $values = array_values($values);
                    }
                } else {
                    if ($idx === null) {
                        $labels[] = 'Translation';
                        $values[] = $tVal;
                    } else {
                        $values[$idx] = $tVal;
                    }
                }
                $meta['charge_labels'] = $labels;
                $meta['charge_values'] = $values;
                $item->metadata = $meta;
                $item->save();
            }
        }

        $postedParkingEdited = array_key_exists('parking_amount', $data);
        $postedParkingVal = $postedParkingEdited ? (float) ($data['parking_amount'] ?? 0) : null;
        if ($postedParkingEdited) {
            foreach ($invoice->items()->get() as $it) {
                $md = is_array($it->metadata) ? $it->metadata : (is_string($it->metadata) ? (json_decode($it->metadata, true) ?: []) : []);
                $labels = isset($md['charge_labels']) && is_array($md['charge_labels']) ? $md['charge_labels'] : [];
                $values = isset($md['charge_values']) && is_array($md['charge_values']) ? $md['charge_values'] : [];
                $mdChanged = false; $itemChanged = false;

                if (isset($md['parking_amount'])) { unset($md['parking_amount']); $mdChanged = true; }
                $newLabels = []; $newValues = [];
                foreach ($labels as $idx => $lbl) {
                    $norm = strtolower(trim((string) $lbl));
                    if ($norm === 'parking') { $mdChanged = true; continue; }
                    $newLabels[] = $lbl;
                    $newValues[] = $values[$idx] ?? null;
                }
                if ($mdChanged) {
                    $md['charge_labels'] = $newLabels;
                    $md['charge_values'] = $newValues;
                }

                $labels2 = isset($md['charge_labels']) && is_array($md['charge_labels']) ? $md['charge_labels'] : [];
                $values2 = isset($md['charge_values']) && is_array($md['charge_values']) ? $md['charge_values'] : [];
                $sum = 0.0;
                foreach ($labels2 as $i => $lbl) {
                    $norm = strtolower(trim((string) $lbl));
                    if ($norm === 'parking') { continue; }
                    $val = isset($values2[$i]) ? (float) $values2[$i] : 0.0;
                    if ($val >= 0) { $sum += $val; }
                }
                $sum = round($sum, 2);
                if ((float) $it->other_charges !== $sum) { $it->other_charges = $sum; $itemChanged = true; }

                if ($mdChanged) { $it->metadata = $md; $itemChanged = true; }
                if ($itemChanged) { $it->save(); }
            }
        }

        $postedTranslationEdited = array_key_exists('translation_cost', $data);
        $postedTranslationVal = $postedTranslationEdited ? (float) ($data['translation_cost'] ?? 0) : null;
        if ($postedTranslationEdited && $invoice->items()->count()) {
            $firstItem = $invoice->items()->first();
            $md = is_array($firstItem->metadata) ? $firstItem->metadata : (is_string($firstItem->metadata) ? (json_decode($firstItem->metadata, true) ?: []) : []);
            $labels = isset($md['charge_labels']) && is_array($md['charge_labels']) ? $md['charge_labels'] : [];
            $values = isset($md['charge_values']) && is_array($md['charge_values']) ? $md['charge_values'] : [];
            $idx = null;
            foreach ($labels as $i => $lbl) {
                $norm = strtolower(trim((string) $lbl));
                if ($norm === 'translation') { $idx = $i; break; }
            }
            if ($postedTranslationVal === null || $postedTranslationVal <= 0) {
                if ($idx !== null) {
                    unset($labels[$idx]);
                    unset($values[$idx]);
                    $labels = array_values($labels);
                    $values = array_values($values);
                }
            } else {
                if ($idx === null) {
                    $labels[] = 'Translation';
                    $values[] = $postedTranslationVal;
                } else {
                    $values[$idx] = $postedTranslationVal;
                }
            }
            $md['charge_labels'] = $labels;
            $md['charge_values'] = $values;
            $firstItem->metadata = $md;
            $firstItem->save();
        }

        foreach ($invoice->items()->get() as $it) {
            $md = is_array($it->metadata) ? $it->metadata : (is_string($it->metadata) ? (json_decode($it->metadata, true) ?: []) : []);
            $labels2 = isset($md['charge_labels']) && is_array($md['charge_labels']) ? $md['charge_labels'] : [];
            $values2 = isset($md['charge_values']) && is_array($md['charge_values']) ? $md['charge_values'] : [];
            $sum = 0.0;
            foreach ($labels2 as $i => $lbl) {
                $norm = strtolower(trim((string) $lbl));
                if ($norm === 'parking') { continue; }
                $val = isset($values2[$i]) ? (float) $values2[$i] : 0.0;
                if ($val >= 0) { $sum += $val; }
            }
            $sum = round($sum, 2);
            if ((float) $it->other_charges !== $sum) { $it->other_charges = $sum; $it->save(); }
        }

        // Persist header summary items (stored on the first invoice item metadata)
        $labels = (array) ($data['header_item_labels'] ?? []);
        $values = (array) ($data['header_item_values'] ?? []);
        $headerItems = [];
        foreach ($labels as $i => $lbl) {
            $lbl = is_string($lbl) ? trim($lbl) : '';
            $val = isset($values[$i]) ? trim((string) $values[$i]) : '';
            if ($lbl !== '' || $val !== '') {
                $headerItems[] = ['label' => $lbl, 'value' => $val];
            }
        }
        
        // Always persist header_items (even when empty) so that removals take effect
        if ($invoice->items()->count()) {
            $firstItem = $invoice->items()->first();
            $md = is_array($firstItem->metadata) ? $firstItem->metadata : (is_string($firstItem->metadata) ? (json_decode($firstItem->metadata, true) ?: []) : []);
            $md['header_items'] = $headerItems;
            $firstItem->metadata = $md;
            $firstItem->save();
        }

        if ($invoice->items()->count()) {
            $firstItem = $invoice->items()->first();
            $md = is_array($firstItem->metadata) ? $firstItem->metadata : (is_string($firstItem->metadata) ? (json_decode($firstItem->metadata, true) ?: []) : []);
            $override = [
                'name' => trim((string)($data['bill_to_name'] ?? '')),
                'email' => trim((string)($data['bill_to_email'] ?? '')),
                'phone' => trim((string)($data['bill_to_phone'] ?? '')),
                'address_line_1' => trim((string)($data['bill_to_address_line_1'] ?? '')),
                'address_line_2' => trim((string)($data['bill_to_address_line_2'] ?? '')),
                'county' => trim((string)($data['bill_to_county'] ?? '')),
                'postcode' => trim((string)($data['bill_to_postcode'] ?? '')),
            ];
            $hasAny = false;
            foreach ($override as $v) { if ($v !== '') { $hasAny = true; break; } }
            if ($hasAny) {
                $md['bill_to_override'] = $override;
            } else {
                if (isset($md['bill_to_override'])) { unset($md['bill_to_override']); }
            }
            $firstItem->metadata = $md;
            $firstItem->save();
        }

        // Recalculate totals after adding items
        $invoice->load('items');

        // Decide template mode:
        // - 'auto' or null: choose v2/v3 based ONLY on number of DISTINCT jobs
        //   (Standalone admin-added items or multiple items for the same job should still use v2)
        // - 'v2' or 'v3': manual override from the dropdown
        $templateMode = $data['template'] ?? null;
        if ($templateMode === 'auto' || $templateMode === null) {
            $distinctJobs = $invoice->items()->pluck('job_id')->filter()->unique()->count();
            $invoice->template = ($distinctJobs > 1) ? 'v3' : 'v2';
            // Persist mode in session so the Preview dropdown reflects 'Auto'
            session()->put('invoice_template_mode_' . $invoice->id, 'auto');
        } elseif (in_array($templateMode, ['v2','v3'], true)) {
            $invoice->template = $templateMode;
            session()->put('invoice_template_mode_' . $invoice->id, $templateMode);
        }
        $subtotalItems = $invoice->items()->get()->sum(function ($i) {
            return (float) $i->amount + (float) $i->surcharge + (float) $i->other_charges;
        });
        // Sum of per-item parking (from explicit metadata or charge labels)
        $itemParkingTotal = 0.0;
        foreach ($invoice->items()->get() as $it) {
            $md = is_array($it->metadata) ? $it->metadata : (is_string($it->metadata) ? (json_decode($it->metadata, true) ?: []) : []);
            if (isset($md['parking_amount']) && is_numeric($md['parking_amount'])) {
                $val = (float) $md['parking_amount'];
                if ($val >= 0) { $itemParkingTotal += $val; }
            } else {
                $labels = isset($md['charge_labels']) && is_array($md['charge_labels']) ? $md['charge_labels'] : [];
                $values = isset($md['charge_values']) && is_array($md['charge_values']) ? $md['charge_values'] : [];
                foreach ($labels as $idx => $lbl) {
                    $norm = strtolower(trim((string) $lbl));
                    if ($norm === 'parking') {
                        $v = isset($values[$idx]) ? (float) $values[$idx] : null;
                        if ($v !== null && $v >= 0) { $itemParkingTotal += $v; }
                        break;
                    }
                }
            }
        }
        // Parking subtotal policy: v3 template uses per-item parking only; v2 uses invoice-level + per-item
        if (($invoice->template ?? 'v2') === 'v3') {
            $invoice->parking_amount = round($itemParkingTotal, 2);
            $subtotal = round($subtotalItems + $itemParkingTotal, 2);
        } else {
            $postedParking = (float) ($data['parking_amount'] ?? ($invoice->parking_amount ?? 0));
            $parking = round($postedParking + $itemParkingTotal, 2);
            $invoice->parking_amount = $parking;
            $subtotal = round($subtotalItems + $parking, 2);
        }
        $includeVat = (bool) ($invoice->include_vat ?? true);
        $vatRate = (float) ($invoice->vat_rate ?? 20);
        $vatAmount = $includeVat ? round($subtotal * ($vatRate / 100), 2) : 0.0;
        $total = round($subtotal + $vatAmount, 2);

        $invoice->subtotal = $subtotal;
        $invoice->vat_amount = $vatAmount;
        $invoice->total = $total;
        $invoice->save();

        $redirect = $request->input('redirect');
        if ($redirect === 'preview') {
            return redirect()->route('invoices.admin.client.preview', $invoice->id)
                ->with('success', 'Client invoice #' . $invoice->invoice_number . ' updated.');
        } elseif ($redirect === 'pdf') {
            return redirect()->route('invoices.admin.client.pdf', $invoice->id)
                ->with('success', 'Client invoice #' . $invoice->invoice_number . ' updated.');
        }

        return redirect()->route('invoices.admin.index', ['type' => 'client'])
            ->with('success', 'Client invoice #' . $invoice->invoice_number . ' updated.');
    }

    /**
     * Send client invoice email & mark as sent
     */
    public function sendClient($id)
    {
        // Accept optional recipient and overrides from request
        /** @var Request $request */
        $request = request();

        $invoice = ClientInvoice::with(['client.user', 'client.organisation', 'company', 'items'])->findOrFail($id);

        // Determine recipient BEFORE marking as sent
        $to = trim((string) $request->input('to_email', ''));
        if ($to === '') {
            $client = $invoice->client;
            $companyEmail = optional($invoice->company)->email;
            $accountEmail = optional(optional($client)->user)->email;
            $orgEmail = optional(optional($client)->organisation)->organisation_email;
            // Prefer company email if present and valid; otherwise follow client preference
            if ($companyEmail && filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
                $to = $companyEmail;
            } else {
                if ($client && (int) ($client->invoice_email_same_as_account ?? 0) !== 1 && $orgEmail) {
                    $to = $orgEmail;
                } else {
                    $to = $accountEmail;
                }
            }
        }
        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Invalid recipient email provided.'], 422);
        }

        // Optional CC/BCC parsing (comma/semicolon separated)
        $parseList = function ($value) {
            if (!$value) return [];
            if (is_array($value)) $list = $value; else $list = preg_split('/[;,]/', (string) $value);
            $clean = [];
            foreach ($list as $e) {
                $e = trim((string) $e);
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) $clean[] = $e;
            }
            return array_values(array_unique($clean));
        };
        $cc = $parseList($request->input('cc'));
        $bcc = $parseList($request->input('bcc'));

        $subjectOverride = trim((string) $request->input('subject', '')) ?: null;
        $customMessage = trim((string) $request->input('message', '')) ?: null;
        $remember = (bool) $request->input('remember_email', false);

        // Mark as sent before sending. If email fails, keep status but log.
        $invoice->status = 'sent';
        $invoice->sent_at = now();
        $invoice->save();

        // Prepare the mailable with overrides
        $mailable = new \App\Mail\ClientInvoiceMail($invoice, $subjectOverride, $customMessage);

        try {
            $mailer = Mail::to($to);
            if (!empty($cc)) { $mailer->cc($cc); }
            if (!empty($bcc)) { $mailer->bcc($bcc); }
            $mailer->send($mailable);
        } catch (\Throwable $t) {
            Log::error('Failed to send client invoice email: ' . $t->getMessage());
        }

        // Persist remembered email preference
        $remembered = false;
        if ($remember) {
            $client = $invoice->client;
            if ($client) {
                try {
                    // If client has an organisation, prefer storing invoice email there
                    $org = $client->organisation;
                    if ($org) {
                        $org->organisation_email = $to;
                        $org->save();
                        $client->invoice_email_same_as_account = 0; // use organisation email
                        $client->save();
                        $remembered = true;
                    } else {
                        // Without an organisation we avoid mutating the account email by default.
                        // But we can set the flag to same-as-account so future default is user's email
                        $client->invoice_email_same_as_account = 1;
                        $client->save();
                        $remembered = false; // no separate storage updated
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to remember invoice email for client ID '.$client->id.': '.$e->getMessage());
                }
            }
        }

        $msg = 'Client invoice sent successfully';
        if ($remember && $remembered) {
            $msg .= ' and the email has been saved for this client.';
        }

        return response()->json(['message' => $msg]);
    }

    private function notifyAgentInvoiceAction(AgentInvoice $invoice, string $action): void
    {
        try {
            $user = optional(optional($invoice->agent)->user);
            $email = (string) optional($user)->email;
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            $invoiceNumber = (string) ($invoice->invoice_number ?? $invoice->id);
            $agentName = (string) (optional($user)->full_name ?? '');
            $url = route('invoices.agent.show', $invoice->id);

            $subject = config('app.name') . ' - Invoice ' . $invoiceNumber . ' ' . $action;
            $body = trim(
                ($agentName !== '' ? ('Hello ' . $agentName . ',') : 'Hello,')
                . "\n\n"
                . 'Your invoice ' . $invoiceNumber . ' has been ' . $action . '.'
                . "\n\n"
                . 'View invoice: ' . $url
                . "\n\n"
                . 'Regards,'
                . "\n"
                . config('app.name')
            );

            Mail::raw($body, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
                try {
                    $message->getHeaders()->addTextHeader('x-mailgun-native-send', true);
                } catch (\Throwable $e) {
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to notify agent invoice status change: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
        }
    }

    private function notifyAgentPayslipAction(\App\Payslip $payslip, string $action): void
    {
        try {
            $payslip->loadMissing(['agent.user']);

            $user = optional(optional($payslip->agent)->user);
            $email = (string) optional($user)->email;
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            $payslipNumber = (string) ($payslip->payslip_number ?? $payslip->id);
            $agentName = (string) (optional($user)->full_name ?? '');
            $url = route('payslips.show', $payslip->id);

            $subject = config('app.name') . ' - Payslip ' . $payslipNumber . ' ' . $action;
            $body = trim(
                ($agentName !== '' ? ('Hello ' . $agentName . ',') : 'Hello,')
                . "\n\n"
                . 'Your payslip ' . $payslipNumber . ' has been ' . $action . '.'
                . "\n\n"
                . 'View payslip: ' . $url
                . "\n\n"
                . 'Regards,'
                . "\n"
                . config('app.name')
            );

            Mail::raw($body, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
                try {
                    $message->getHeaders()->addTextHeader('x-mailgun-native-send', true);
                } catch (\Throwable $e) {
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to notify agent payslip status change: ' . $e->getMessage(), [
                'payslip_id' => $payslip->id,
            ]);
        }
    }

    /**
     * Determine if agent_invoices.status is numeric and map provided
     * value accordingly for filtering.
     */
    private function mapAgentStatusValue($value)
    {
        // Use information_schema detection first
        $isNumeric = false;
        try {
            $result = DB::select("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agent_invoices' AND COLUMN_NAME = 'status'");
            if (!empty($result)) {
                $dataType = strtolower($result[0]->DATA_TYPE ?? '');
                $isNumeric = in_array($dataType, ['tinyint','smallint','int','bigint','integer']);
            }
        } catch (\Throwable $e) {
            $isNumeric = false;
        }
        if (!$isNumeric) {
            // Fallback to sampling a value from the table
            try {
                $sample = AgentInvoice::orderByDesc('id')->value('status');
                if ($sample !== null) {
                    $isNumeric = is_numeric($sample);
                }
            } catch (\Throwable $e) {
                $isNumeric = false;
            }
        }

        $map = [
            'draft' => 0,
            'submitted' => 1,
            'sent' => 2,
            'paid' => 3,
            'cancelled' => 4,
        ];
        if ($isNumeric) {
            if (is_string($value)) {
                return $map[strtolower($value)] ?? 0;
            }
            return (int) $value;
        } else {
            if (is_numeric($value)) {
                $rev = array_flip($map);
                $key = (int) $value;
                return $rev[$key] ?? 'draft';
            }
            return (string) $value;
        }
    }
}
