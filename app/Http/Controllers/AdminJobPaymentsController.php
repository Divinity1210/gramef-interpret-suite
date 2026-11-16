<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Helpers\RoleHelper;
use App\ClientInvoiceItem;
use App\AgentInvoiceItem;
use App\ClientInvoice;
use App\Payslip;
use App\AuditLog;

class AdminJobPaymentsController extends Controller
{
    // Removed earlier duplicate index() method. The consolidated index() below
    // includes preview and export progress URLs and passes all necessary data to the view.

    /**
     * Dev preview endpoint (no auth gating) to quickly view the UI locally.
     */
    public function indexDev(Request $request)
    {
        $defaultMode = $request->get('mode', 'client');
        $baseDataUrl = route('dev.preview.admin.job-payments.data');
        $baseExportUrl = route('dev.preview.admin.job-payments.export');
        $basePreviewUrl = route('dev.preview.admin.job-payments.preview');
        $baseClientsUrl = route('dev.preview.admin.job-payments.clients');
        $baseCompaniesUrl = route('dev.preview.admin.job-payments.companies');
        $baseExportStartUrl = route('dev.preview.admin.job-payments.export.start');
        $baseExportStatusUrl = route('dev.preview.admin.job-payments.export.status', ['export' => 0]);
        $baseExportDownloadUrl = route('dev.preview.admin.job-payments.export.download', ['export' => 0]);
        $columns = [
            'job_id' => 'Job ID',
            'job_type' => 'Job Type',
            'client_name' => 'Client',
            'agent_name' => 'Agent',
            'client_reference' => 'Client Ref',
            'file_reference' => 'File Ref',
            'language' => 'Language',
            'appointment_date' => 'Appointment Date',
            'invoice_number' => 'Invoice #',
            'invoice_date' => 'Invoice Date',
            'amount_before_vat' => 'Amount Before VAT',
            'vat_amount' => 'VAT Amount',
            'invoice_total' => 'Invoice Total',
            'paid_amount' => 'Paid Amount',
            'balance_amount' => 'Balance',
            'last_payment_date' => 'Last Payment',
            'payment_status' => 'Payment Status',
            'mode' => 'Perspective',
        ];
        $devMode = true;
        return view('admin.job_payments.index', compact('defaultMode', 'baseDataUrl', 'baseExportUrl', 'basePreviewUrl', 'baseClientsUrl', 'baseCompaniesUrl', 'baseExportStartUrl', 'baseExportStatusUrl', 'baseExportDownloadUrl', 'columns', 'devMode'));
    }

    /**
     * Server-side data for the payments table (admin-only).
     */
    public function data(Request $request)
    {
        if (!RoleHelper::hasRole(auth()->user(), 'admin')) {
            abort(403);
        }
        return $this->computeData($request);
    }

    /**
     * Dev preview data (no auth gating).
     */
    public function dataDev(Request $request)
    {
        return $this->computeData($request);
    }

    /**
     * Export CSV (admin-only) with customizable columns.
     */
    public function export(Request $request)
    {
        if (!RoleHelper::hasRole(auth()->user(), 'admin')) {
            abort(403);
        }
        return $this->performExport($request);
    }

    /**
     * Dev preview export (no auth gating).
     */
    public function exportDev(Request $request)
    {
        return $this->performExport($request);
    }

    /**
     * Show the dashboard (admin-only) and provide base URLs for preview and export progress.
     */
    public function index(Request $request)
    {
        if (!RoleHelper::hasRole(auth()->user(), 'admin')) {
            abort(403);
        }
        $defaultMode = $request->get('mode', 'client');
        $baseDataUrl = route('admin.job-payments.data');
        $baseExportUrl = route('admin.job-payments.export');
        $basePreviewUrl = route('admin.job-payments.preview');
        $baseClientsUrl = route('admin.job-payments.clients');
        $baseCompaniesUrl = route('admin.job-payments.companies');
        // For status/download we will pass route patterns; JS will replace trailing /0
        $baseExportStartUrl = route('admin.job-payments.export.start');
        $baseExportStatusUrl = route('admin.job-payments.export.status', ['export' => 0]);
        $baseExportDownloadUrl = route('admin.job-payments.export.download', ['export' => 0]);
        $columns = [
            'job_id' => 'Job ID',
            'job_type' => 'Job Type',
            'client_name' => 'Client',
            'agent_name' => 'Agent',
            'client_reference' => 'Client Ref',
            'file_reference' => 'File Ref',
            'language' => 'Language',
            'appointment_date' => 'Appointment Date',
            'invoice_number' => 'Invoice #',
            'invoice_date' => 'Invoice Date',
            'amount_before_vat' => 'Amount Before VAT',
            'vat_amount' => 'VAT Amount',
            'invoice_total' => 'Invoice Total',
            'paid_amount' => 'Paid Amount',
            'balance_amount' => 'Balance',
            'last_payment_date' => 'Last Payment',
            'payment_status' => 'Payment Status',
            'mode' => 'Perspective',
        ];
        $devMode = false;
        return view('admin.job_payments.index', compact('defaultMode', 'baseDataUrl', 'baseExportUrl', 'basePreviewUrl', 'baseClientsUrl', 'baseCompaniesUrl', 'baseExportStartUrl', 'baseExportStatusUrl', 'baseExportDownloadUrl', 'columns', 'devMode'));
    }

    /**
     * Core data computation shared by admin and dev endpoints.
     * NOTE: Partial payments are treated as UNPAID per product requirement.
     */
    private function computeData(Request $request)
    {
        $mode = in_array($request->get('mode'), ['client', 'agent']) ? $request->get('mode') : 'client';
        $statusFilter = in_array($request->get('status'), ['paid', 'unpaid', 'not_invoiced', 'all']) ? $request->get('status') : 'unpaid';
        $clientId = $request->get('client_id');
        $agentId = $request->get('agent_id');
        $companyId = $request->get('company_id');
        $search = trim((string) $request->get('search', ''));
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $jtRaw = strtolower((string) $request->get('job_type'));
        $jobTypeFilter = in_array($jtRaw, ['interpreter','translator']) ? $jtRaw : null;
        // Default date range: last 90 days if none provided (skip for not_invoiced)
        if ($statusFilter !== 'not_invoiced' && !$dateFrom && !$dateTo) {
            try {
                $dateFrom = now()->subDays(90)->toDateString();
                $dateTo = now()->toDateString();
            } catch (\Throwable $e) {
                // Fallback silently if now() fails for any reason
            }
        }
        $page = max(1, (int) $request->get('page', 1));
        $perPageInput = (int) $request->get('per_page', 20);
        $isExport = (bool) ($request->has('for_export') || $request->boolean('for_export') || $perPageInput > 100);
        $perPage = $isExport ? max(1, min(50000, $perPageInput)) : max(1, min(100, $perPageInput));

        // Special branch: Not Invoiced jobs (no invoice items yet)
        if ($statusFilter === 'not_invoiced') {
            $page = max(1, (int) $page);
            $perPage = max(1, (int) $perPage);
            $rowsAll = [];

            if ($mode === 'client') {
                // Interpreter jobs without client invoice items
                if ($jobTypeFilter === null || $jobTypeFilter === 'interpreter') {
                    try {
                        $ijQuery = \App\InterpreterJob::query();
                        if ($clientId) { $ijQuery->where('client_id', $clientId); }
                        if ($companyId) {
                            $ijQuery->whereHas('client.organisation', function($q) use ($companyId) {
                                $q->where('company_id', $companyId);
                            });
                        }
                        if ($agentId) { $ijQuery->where('agent_id', $agentId); }
                        if ($dateFrom) { $ijQuery->whereDate('appointment_date', '>=', $dateFrom); }
                        if ($dateTo) { $ijQuery->whereDate('appointment_date', '<=', $dateTo); }
                        if ($search !== '') {
                            if (is_numeric($search)) {
                                $ijQuery->where('id', (int) $search);
                            } else {
                                $ijQuery->where(function($q) use ($search) {
                                    $q->whereHas('client.user', function($u) use ($search) {
                                        $u->where('first_name', 'like', "%$search%")
                                          ->orWhere('last_name', 'like', "%$search%");
                                    })->orWhereHas('agent.user', function($u) use ($search) {
                                        $u->where('first_name', 'like', "%$search%")
                                          ->orWhere('last_name', 'like', "%$search%");
                                    });
                                });
                            }
                        }
                        $ijQuery->whereNotExists(function($q){
                            $q->select(DB::raw(1))
                              ->from('client_invoice_items')
                              ->whereRaw('client_invoice_items.job_id = interpreter_jobs.id')
                              ->where(function($qq){
                                  $qq->whereRaw("JSON_EXTRACT(client_invoice_items.metadata, '$.job_type') = 'App\\\\InterpreterJob'")
                                     ->orWhere('client_invoice_items.metadata', 'like', "%App\\\\InterpreterJob%");
                              });
                        });
                        $interpreterJobs = $ijQuery->with(['client.user','agent.user','toLanguage'])->orderByDesc('id')->get();
                    } catch (\Throwable $e) { $interpreterJobs = collect(); }

                    foreach ($interpreterJobs as $job) {
                        $rowsAll[] = [
                            'job_id' => (int) $job->id,
                            'job_type' => 'interpreter',
                            'client_name' => optional(optional($job)->client)->user ? optional($job->client->user)->getFullName() : null,
                            'agent_name' => optional(optional($job)->agent)->user ? optional($job->agent->user)->getFullName() : null,
                            'client_reference' => $job->client_reference ?? null,
                            'file_reference' => $job->file_reference ?? null,
                            'language' => optional($job->toLanguage ?? $job->to_language)->name,
                            'appointment_date' => optional($job->appointment_date)->toDateString(),
                            'invoice_number' => null,
                            'invoice_date' => null,
                            'amount_before_vat' => null,
                            'vat_amount' => null,
                            'invoice_total' => null,
                            'paid_amount' => null,
                            'balance_amount' => null,
                            'last_payment_date' => null,
                            'payment_status' => 'Not Invoiced',
                            'mode' => 'client',
                        ];
                    }
                }

                // Translator jobs without client invoice items
                if ($jobTypeFilter === null || $jobTypeFilter === 'translator') {
                    try {
                        $tjQuery = \App\TranslatorJob::query();
                        if ($clientId) { $tjQuery->where('client_id', $clientId); }
                        if ($companyId) {
                            $tjQuery->whereHas('client.organisation', function($q) use ($companyId) {
                                $q->where('company_id', $companyId);
                            });
                        }
                        if ($agentId) { $tjQuery->where('agent_id', $agentId); }
                        if ($dateFrom) { $tjQuery->whereDate('target_date', '>=', $dateFrom); }
                        if ($dateTo) { $tjQuery->whereDate('target_date', '<=', $dateTo); }
                        if ($search !== '') {
                            if (is_numeric($search)) {
                                $tjQuery->where('id', (int) $search);
                            } else {
                                $tjQuery->where(function($q) use ($search) {
                                    $q->whereHas('client.user', function($u) use ($search) {
                                        $u->where('first_name', 'like', "%$search%")
                                          ->orWhere('last_name', 'like', "%$search%");
                                    })->orWhereHas('agent.user', function($u) use ($search) {
                                        $u->where('first_name', 'like', "%$search%")
                                          ->orWhere('last_name', 'like', "%$search%");
                                    });
                                });
                            }
                        }
                        $tjQuery->whereNotExists(function($q){
                            $q->select(DB::raw(1))
                              ->from('client_invoice_items')
                              ->whereRaw('client_invoice_items.job_id = translator_jobs.id')
                              ->where(function($qq){
                                  $qq->whereRaw("JSON_EXTRACT(client_invoice_items.metadata, '$.job_type') = 'App\\\\TranslatorJob'")
                                     ->orWhere('client_invoice_items.metadata', 'like', "%App\\\\TranslatorJob%");
                              });
                        });
                        $translatorJobs = $tjQuery->with(['client.user','agent.user','fromLanguage','toLanguage'])->orderByDesc('id')->get();
                    } catch (\Throwable $e) { $translatorJobs = collect(); }

                    foreach ($translatorJobs as $job) {
                        $rowsAll[] = [
                            'job_id' => (int) $job->id,
                            'job_type' => 'translator',
                            'client_name' => optional(optional($job)->client)->user ? optional($job->client->user)->getFullName() : null,
                            'agent_name' => optional(optional($job)->agent)->user ? optional($job->agent->user)->getFullName() : null,
                            'client_reference' => $job->client_reference ?? null,
                            'file_reference' => null,
                            'language' => optional($job->toLanguage)->name,
                            'appointment_date' => optional($job->target_date)->toDateString(),
                            'invoice_number' => null,
                            'invoice_date' => null,
                            'amount_before_vat' => null,
                            'vat_amount' => null,
                            'invoice_total' => null,
                            'paid_amount' => null,
                            'balance_amount' => null,
                            'last_payment_date' => null,
                            'payment_status' => 'Not Invoiced',
                            'mode' => 'client',
                        ];
                    }
                }
            } else { // agent perspective
                // Interpreter jobs without agent invoice items
                try {
                    $ijQuery = \App\InterpreterJob::query();
                    if ($clientId) { $ijQuery->where('client_id', $clientId); }
                    if ($companyId) {
                        $ijQuery->whereHas('client.organisation', function($q) use ($companyId) {
                            $q->where('company_id', $companyId);
                        });
                    }
                    if ($agentId) { $ijQuery->where('agent_id', $agentId); }
                    if ($dateFrom) { $ijQuery->whereDate('appointment_date', '>=', $dateFrom); }
                    if ($dateTo) { $ijQuery->whereDate('appointment_date', '<=', $dateTo); }
                    if ($search !== '') {
                        if (is_numeric($search)) {
                            $ijQuery->where('id', (int) $search);
                        } else {
                            $ijQuery->where(function($q) use ($search) {
                                $q->whereHas('agent.user', function($u) use ($search) {
                                    $u->where('first_name', 'like', "%$search%")
                                      ->orWhere('last_name', 'like', "%$search%");
                                })->orWhereHas('client.user', function($u) use ($search) {
                                    $u->where('first_name', 'like', "%$search%")
                                      ->orWhere('last_name', 'like', "%$search%");
                                });
                            });
                        }
                    }
                    $ijQuery->whereNotExists(function($q){
                        $q->select(DB::raw(1))
                          ->from('agent_invoice_items')
                          ->whereRaw('agent_invoice_items.job_id = interpreter_jobs.id')
                          ->where(function($qq){
                              $qq->whereRaw("JSON_EXTRACT(agent_invoice_items.metadata, '$.job_type') = 'App\\\\InterpreterJob'")
                                 ->orWhere('agent_invoice_items.metadata', 'like', "%App\\\\InterpreterJob%");
                          });
                    });
                    // Eager load relations to avoid N+1 in row building
                    $interpreterJobs = $ijQuery->with(['client.user','agent.user','toLanguage'])->orderByDesc('id')->get();
                } catch (\Throwable $e) { $interpreterJobs = collect(); }

                foreach ($interpreterJobs as $job) {
                    $rowsAll[] = [
                        'job_id' => (int) $job->id,
                        'job_type' => 'interpreter',
                        'client_name' => optional(optional($job)->client)->user ? optional($job->client->user)->getFullName() : null,
                        'agent_name' => optional(optional($job)->agent)->user ? optional($job->agent->user)->getFullName() : null,
                        'client_reference' => $job->client_reference ?? null,
                        'file_reference' => $job->file_reference ?? null,
                        'language' => optional($job->toLanguage ?? $job->to_language)->name,
                        'appointment_date' => optional($job->appointment_date)->toDateString(),
                        'invoice_number' => null,
                        'invoice_date' => null,
                        'amount_before_vat' => null,
                        'vat_amount' => null,
                        'invoice_total' => null,
                        'paid_amount' => null,
                        'balance_amount' => null,
                        'last_payment_date' => null,
                        'payment_status' => 'Not Invoiced',
                        'mode' => 'agent',
                    ];
                }

                // Translator jobs without agent invoice items
                try {
                    $tjQuery = \App\TranslatorJob::query();
                    if ($clientId) { $tjQuery->where('client_id', $clientId); }
                    if ($companyId) {
                        $tjQuery->whereHas('client.organisation', function($q) use ($companyId) {
                            $q->where('company_id', $companyId);
                        });
                    }
                    if ($agentId) { $tjQuery->where('agent_id', $agentId); }
                    if ($dateFrom) { $tjQuery->whereDate('target_date', '>=', $dateFrom); }
                    if ($dateTo) { $tjQuery->whereDate('target_date', '<=', $dateTo); }
                    if ($search !== '') {
                        if (is_numeric($search)) {
                            $tjQuery->where('id', (int) $search);
                        } else {
                            $tjQuery->where(function($q) use ($search) {
                                $q->whereHas('agent.user', function($u) use ($search) {
                                    $u->where('first_name', 'like', "%$search%")
                                      ->orWhere('last_name', 'like', "%$search%");
                                })->orWhereHas('client.user', function($u) use ($search) {
                                    $u->where('first_name', 'like', "%$search%")
                                      ->orWhere('last_name', 'like', "%$search%");
                                });
                            });
                        }
                    }
                    $tjQuery->whereNotExists(function($q){
                        $q->select(DB::raw(1))
                          ->from('agent_invoice_items')
                          ->whereRaw('agent_invoice_items.job_id = translator_jobs.id')
                          ->where(function($qq){
                              $qq->whereRaw("JSON_EXTRACT(agent_invoice_items.metadata, '$.job_type') = 'App\\\\TranslatorJob'")
                                 ->orWhere('agent_invoice_items.metadata', 'like', "%App\\\\TranslatorJob%");
                          });
                    });
                    // Eager load relations to avoid N+1 in row building
                    $translatorJobs = $tjQuery->with(['client.user','agent.user','fromLanguage','toLanguage'])->orderByDesc('id')->get();
                } catch (\Throwable $e) { $translatorJobs = collect(); }

                foreach ($translatorJobs as $job) {
                    $rowsAll[] = [
                        'job_id' => (int) $job->id,
                        'job_type' => 'translator',
                        'client_name' => optional(optional($job)->client)->user ? optional($job->client->user)->getFullName() : null,
                        'agent_name' => optional(optional($job)->agent)->user ? optional($job->agent->user)->getFullName() : null,
                        'client_reference' => $job->client_reference ?? null,
                        'file_reference' => null,
                        'language' => optional($job->toLanguage)->name,
                        'appointment_date' => optional($job->target_date)->toDateString(),
                        'invoice_number' => null,
                        'invoice_date' => null,
                        'amount_before_vat' => null,
                        'vat_amount' => null,
                        'invoice_total' => null,
                        'paid_amount' => null,
                        'balance_amount' => null,
                        'last_payment_date' => null,
                        'payment_status' => 'Not Invoiced',
                        'mode' => 'agent',
                    ];
                }
            }

            // Sort by job_id desc for consistency
            usort($rowsAll, function($a,$b){ return ($b['job_id'] <=> $a['job_id']); });
            $total = count($rowsAll);
            $offset = ($page - 1) * $perPage;
            $rowsPage = array_slice($rowsAll, $offset, $perPage);
            return response()->json([
                'data' => $rowsPage,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'has_more' => ($offset + $perPage) < $total,
                ],
            ]);
        }

        if ($mode === 'client') {
            $query = ClientInvoiceItem::with(['invoice.receipts', 'invoice.client.user'])
                ->whereNotNull('job_id');
            if ($clientId) {
                $query->whereHas('invoice', function ($q) use ($clientId) {
                    $q->where('client_id', $clientId);
                });
            }
            if ($companyId) {
                $query->where(function($master) use ($companyId) {
                    $master->whereHas('invoice', function ($q) use ($companyId) {
                        $q->where('company_id', $companyId)
                          ->orWhereHas('client.organisation', function($inner) use ($companyId) {
                              $inner->where('company_id', $companyId);
                          });
                    })->orWhereExists(function ($q) use ($companyId) {
                        $q->select(DB::raw(1))
                          ->from('interpreter_jobs')
                          ->join('clients', 'clients.id', '=', 'interpreter_jobs.client_id')
                          ->join('organisations', 'organisations.client_id', '=', 'clients.id')
                          ->whereRaw('interpreter_jobs.id = client_invoice_items.job_id')
                          ->where('organisations.company_id', $companyId);
                    })->orWhereExists(function ($q) use ($companyId) {
                        $q->select(DB::raw(1))
                          ->from('translator_jobs')
                          ->join('clients', 'clients.id', '=', 'translator_jobs.client_id')
                          ->join('organisations', 'organisations.client_id', '=', 'clients.id')
                          ->whereRaw('translator_jobs.id = client_invoice_items.job_id')
                          ->where('organisations.company_id', $companyId);
                    });
                });
            }
            if ($jobTypeFilter === 'interpreter') {
                $query->where(function($q){
                    $q->whereRaw("JSON_EXTRACT(client_invoice_items.metadata, '$.job_type') = 'App\\\\InterpreterJob'")
                      ->orWhere('client_invoice_items.metadata', 'like', "%App\\\\InterpreterJob%");
                });
            } elseif ($jobTypeFilter === 'translator') {
                $query->where(function($q){
                    $q->whereRaw("JSON_EXTRACT(client_invoice_items.metadata, '$.job_type') = 'App\\\\TranslatorJob'")
                      ->orWhere('client_invoice_items.metadata', 'like', "%App\\\\TranslatorJob%");
                });
            }
            if ($dateFrom) {
                $query->whereHas('invoice', function ($q) use ($dateFrom) {
                    $q->whereDate('invoice_date', '>=', $dateFrom);
                });
            }
            if ($dateTo) {
                $query->whereHas('invoice', function ($q) use ($dateTo) {
                    $q->whereDate('invoice_date', '<=', $dateTo);
                });
            }
            if ($search !== '') {
                if (is_numeric($search)) {
                    $query->where('job_id', (int) $search);
                } else {
                    // Simple name search on client user full name
                    $query->whereHas('invoice.client.user', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%$search%")
                          ->orWhere('last_name', 'like', "%$search%");
                    });
                }
            }

            $query->orderByDesc('id');
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);
            $items = $paginator->items();

            $rows = [];
            foreach ($items as $item) {
                $invoice = $item->invoice; // ClientInvoice

                // Resolve job details via metadata->job_type with fallback to both tables
                $job = null; $jobType = null; $clientName = null; $agentName = null; $language = null; $appointmentDate = null;
                $meta = is_array($item->metadata) ? $item->metadata : (is_string($item->metadata) ? json_decode($item->metadata, true) : []);
                $jobType = is_array($meta) ? ($meta['job_type'] ?? null) : null;
                try {
                    if ($jobType && class_exists($jobType)) {
                        $job = $jobType::find($item->job_id);
                    } else {
                        $job = \App\InterpreterJob::find($item->job_id) ?? \App\TranslatorJob::find($item->job_id);
                    }
                } catch (\Throwable $e) { $job = null; }
                if ($job) {
                    try {
                        $clientName = optional(optional($job)->client)->user ? optional(optional($job)->client)->user->getFullName() : null;
                    } catch (\Throwable $e) { $clientName = null; }
                    try {
                        $agentName = optional(optional($job)->agent)->user ? optional(optional($job)->agent)->user->getFullName() : null;
                    } catch (\Throwable $e) { $agentName = null; }
                    try {
                        // Language accessor differs per model
                        if ($job instanceof \App\InterpreterJob) {
                            $language = optional($job->to_language)->name;
                            $appointmentDate = optional($job->appointment_date)->toDateString();
                            $jobTypeLabel = 'interpreter';
                        } elseif ($job instanceof \App\TranslatorJob) {
                            $language = optional($job->toLanguage)->name;
                            $appointmentDate = optional($job->target_date)->toDateString();
                            $jobTypeLabel = 'translator';
                        } else {
                            $jobTypeLabel = 'job';
                        }
                    } catch (\Throwable $e) {
                        $jobTypeLabel = 'job';
                    }
                } else {
                    $jobTypeLabel = 'job';
                }

                // References from metadata with fallback to job properties
                $clientRef = null; $fileRef = null;
                try { $clientRef = is_array($meta) ? ($meta['client_reference'] ?? null) : null; } catch (\Throwable $e) { $clientRef = null; }
                try { $fileRef = is_array($meta) ? ($meta['file_reference'] ?? null) : null; } catch (\Throwable $e) { $fileRef = null; }
                if (!$clientRef && $job) { try { $clientRef = $job->client_reference ?? null; } catch (\Throwable $e) { /* ignore */ } }
                if (!$fileRef && $job) { try { $fileRef = property_exists($job, 'file_reference') ? ($job->file_reference ?? null) : null; } catch (\Throwable $e) { /* ignore */ } }

                // Payment status: strictly rely on invoice.status being 'paid'.
                // Business rule: no part payments — invoices are either paid or unpaid.
                $invoiceTotal = (float) ($invoice->total ?? 0.0);
                $isPaid = ($invoice && strtolower((string) $invoice->status) === 'paid');
                $status = $isPaid ? 'Paid' : 'Unpaid';
                
                // Filter by status if needed
                if ($statusFilter === 'paid' && $status !== 'Paid') { continue; }
                if ($statusFilter === 'unpaid' && $status !== 'Unpaid') { continue; }

                // Compute paid amount, balance, and last payment date for client perspective
                $paidAmount = $isPaid ? $invoiceTotal : 0.0;
                $balanceAmount = $isPaid ? 0.0 : $invoiceTotal;
                $lastPaymentDate = null;
                try {
                    if ($isPaid) {
                        $lastReceipt = $invoice ? $invoice->receipts->sortByDesc('created_at')->first() : null;
                        $lastPaymentDate = $lastReceipt ? optional($lastReceipt->created_at)->toDateString() : optional($invoice->updated_at)->toDateString();
                    }
                } catch (\Throwable $e) { $lastPaymentDate = $lastPaymentDate; }

                $rows[] = [
                    'job_id' => (int) $item->job_id,
                    'job_type' => $jobTypeLabel,
                    'client_name' => $clientName,
                    'agent_name' => $agentName,
                    'client_reference' => $clientRef,
                    'file_reference' => $fileRef,
                    'language' => $language,
                    'appointment_date' => $appointmentDate,
                    'invoice_number' => $invoice ? (string) $invoice->invoice_number : null,
                    'invoice_date' => $invoice ? optional($invoice->invoice_date ?? $invoice->created_at)->format('Y-m-d') : null,
                    'amount_before_vat' => $invoice ? number_format((float) ($invoice->subtotal ?? 0), 2) : null,
                    'vat_amount' => $invoice ? number_format((float) ($invoice->vat_amount ?? 0), 2) : null,
                    'invoice_total' => $invoiceTotal,
                    'paid_amount' => $paidAmount,
                    'balance_amount' => $balanceAmount,
                    'last_payment_date' => $lastPaymentDate,
                    'payment_status' => $status,
                    'mode' => 'client',
                ];
            }

            return response()->json([
                'data' => $rows,
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ]);
        }

        // Agent perspective
        $query = AgentInvoiceItem::with(['invoice.agent.user'])
            ->whereNotNull('job_id');
        if ($agentId) {
            $query->whereHas('invoice', function ($q) use ($agentId) {
                $q->where('agent_id', $agentId);
            });
        }
        if ($companyId) {
            $query->where(function($w) use ($companyId) {
                $w->whereExists(function($q) use ($companyId) {
                    $q->select(DB::raw(1))
                      ->from('interpreter_jobs')
                      ->join('clients', 'clients.id', '=', 'interpreter_jobs.client_id')
                      ->join('organisations', 'organisations.client_id', '=', 'clients.id')
                      ->whereRaw('agent_invoice_items.job_id = interpreter_jobs.id')
                      ->where(function($qq){
                          $qq->whereRaw("JSON_EXTRACT(agent_invoice_items.metadata, '$.job_type') = 'App\\\\InterpreterJob'")
                             ->orWhere('agent_invoice_items.metadata', 'like', "%App\\\\InterpreterJob%");
                      })
                      ->where('organisations.company_id', $companyId);
                })->orWhereExists(function($q) use ($companyId) {
                    $q->select(DB::raw(1))
                      ->from('translator_jobs')
                      ->join('clients', 'clients.id', '=', 'translator_jobs.client_id')
                      ->join('organisations', 'organisations.client_id', '=', 'clients.id')
                      ->whereRaw('agent_invoice_items.job_id = translator_jobs.id')
                      ->where(function($qq){
                          $qq->whereRaw("JSON_EXTRACT(agent_invoice_items.metadata, '$.job_type') = 'App\\\\TranslatorJob'")
                             ->orWhere('agent_invoice_items.metadata', 'like', "%App\\\\TranslatorJob%");
                      })
                      ->where('organisations.company_id', $companyId);
                });
            });
        }
        if ($jobTypeFilter === 'interpreter') {
            $query->where(function($q){
                $q->whereRaw("JSON_EXTRACT(agent_invoice_items.metadata, '$.job_type') = 'App\\\\InterpreterJob'")
                  ->orWhere('agent_invoice_items.metadata', 'like', "%App\\\\InterpreterJob%");
            });
        } elseif ($jobTypeFilter === 'translator') {
            $query->where(function($q){
                $q->whereRaw("JSON_EXTRACT(agent_invoice_items.metadata, '$.job_type') = 'App\\\\TranslatorJob'")
                  ->orWhere('agent_invoice_items.metadata', 'like', "%App\\\\TranslatorJob%");
            });
        }
        if ($dateFrom) {
            $query->whereHas('invoice', function ($q) use ($dateFrom) {
                $q->whereDate('created_at', '>=', $dateFrom);
            });
        }
        if ($dateTo) {
            $query->whereHas('invoice', function ($q) use ($dateTo) {
                $q->whereDate('created_at', '<=', $dateTo);
            });
        }
        if ($search !== '') {
            if (is_numeric($search)) {
                $query->where('job_id', (int) $search);
            } else {
                // Simple name search on agent user full name
                $query->whereHas('invoice.agent.user', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%$search%")
                      ->orWhere('last_name', 'like', "%$search%");
                });
            }
        }

        $query->orderByDesc('id');
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $items = $paginator->items();

        $rows = [];
        foreach ($items as $item) {
            $agentInvoice = $item->invoice; // AgentInvoice

            // Resolve job details (metadata may specify job_type)
            $job = null; $jobTypeLabel = 'job'; $clientName = null; $agentName = null; $language = null; $appointmentDate = null;
            $meta = is_array($item->metadata) ? $item->metadata : (is_string($item->metadata) ? json_decode($item->metadata, true) : []);
            $jobType = is_array($meta) ? ($meta['job_type'] ?? null) : null;
            try {
                if ($jobType && class_exists($jobType)) {
                    $job = $jobType::find($item->job_id);
                } else {
                    $job = \App\InterpreterJob::find($item->job_id) ?? \App\TranslatorJob::find($item->job_id);
                }
            } catch (\Throwable $e) { $job = null; }
            if ($job) {
                try { $clientName = optional(optional($job)->client)->user ? optional(optional($job)->client)->user->getFullName() : null; } catch (\Throwable $e) { $clientName = null; }
                try { $agentName = optional(optional($job)->agent)->user ? optional(optional($job)->agent)->user->getFullName() : null; } catch (\Throwable $e) { $agentName = null; }
                try {
                    if ($job instanceof \App\InterpreterJob) {
                        $language = optional($job->toLanguage)->name;
                        $appointmentDate = optional($job->appointment_date)->toDateString();
                        $jobTypeLabel = 'interpreter';
                    } elseif ($job instanceof \App\TranslatorJob) {
                        $language = optional($job->toLanguage)->name;
                        $appointmentDate = optional($job->target_date)->toDateString();
                        $jobTypeLabel = 'translator';
                    }
                } catch (\Throwable $e) { /* ignore */ }
            }

            // References from metadata with fallback to job properties
            $clientRef = null; $fileRef = null;
            try { $clientRef = is_array($meta) ? ($meta['client_reference'] ?? null) : null; } catch (\Throwable $e) { $clientRef = null; }
            try { $fileRef = is_array($meta) ? ($meta['file_reference'] ?? null) : null; } catch (\Throwable $e) { $fileRef = null; }
            if (!$clientRef && $job) { try { $clientRef = $job->client_reference ?? null; } catch (\Throwable $e) { /* ignore */ } }
            if (!$fileRef && $job) { try { $fileRef = property_exists($job, 'file_reference') ? ($job->file_reference ?? null) : null; } catch (\Throwable $e) { /* ignore */ } }

            // Payment status: paid if agent invoice or payslip are paid; else unpaid
            $paid = false;
            try {
                $statusRaw = strtolower((string) optional($agentInvoice)->status ?? '');
                if ($statusRaw === 'paid' || $statusRaw === '3' || $statusRaw === 'paid ') {
                    $paid = true;
                }
            } catch (\Throwable $e) { /* ignore */ }

            if (!$paid && $agentInvoice) {
                try {
                    $ps = Payslip::where('agent_invoice_id', $agentInvoice->id)->orderByDesc('id')->first();
                    if ($ps && strtolower((string)$ps->status) === 'paid') {
                        $paid = true;
                    }
                } catch (\Throwable $e) { /* ignore */ }
            }
            $status = $paid ? 'Paid' : 'Unpaid';

            if ($statusFilter === 'paid' && $status !== 'Paid') { continue; }
            if ($statusFilter === 'unpaid' && $status !== 'Unpaid') { continue; }

            // Compute balance & last payment date for agent perspective
            $invoiceTotal = (float) ($agentInvoice->total_amount ?? 0.0);
            $paidAmount = $paid ? $invoiceTotal : 0.0;
            $balanceAmount = max(0, $invoiceTotal - $paidAmount);
            $lastPaymentDate = null;
            try {
                if ($paid) {
                    $ps = \App\Payslip::where('agent_invoice_id', optional($agentInvoice)->id)->orderByDesc('id')->first();
                    $lastPaymentDate = $ps ? optional($ps->updated_at)->toDateString() : (optional($agentInvoice->updated_at)->toDateString());
                }
            } catch (\Throwable $e) { $lastPaymentDate = $lastPaymentDate; }

            $rows[] = [
                'job_id' => (int) $item->job_id,
                'job_type' => $jobTypeLabel,
                'client_name' => $clientName,
                'agent_name' => $agentName,
                'client_reference' => $clientRef,
                'file_reference' => $fileRef,
                'language' => $language,
                'appointment_date' => $appointmentDate,
                'invoice_number' => $agentInvoice ? (string) $agentInvoice->invoice_number : null,
                'invoice_date' => $agentInvoice ? optional($agentInvoice->created_at)->format('Y-m-d') : null,
                'amount_before_vat' => $agentInvoice ? number_format((float) ($agentInvoice->subtotal ?? 0), 2) : null,
                'vat_amount' => $agentInvoice ? number_format((float) ($agentInvoice->vat_amount ?? 0), 2) : null,
                'invoice_total' => $invoiceTotal,
                'paid_amount' => $paidAmount,
                'balance_amount' => $balanceAmount,
                'last_payment_date' => $lastPaymentDate,
                'payment_status' => $status,
                'mode' => 'agent',
            ];
        }

        return response()->json([
            'data' => $rows,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    /**
     * Perform CSV export (streamed) using the same filters.
     * Partial payments are treated as unpaid.
     */
    private function performExport(Request $request)
    {
        $mode = in_array($request->get('mode'), ['client', 'agent']) ? $request->get('mode') : 'client';
        $statusFilter = in_array($request->get('status'), ['paid', 'unpaid', 'not_invoiced', 'all']) ? $request->get('status') : 'unpaid';
        $columns = (array) $request->get('columns', ['job_id','job_type','client_name','agent_name','client_reference','file_reference','language','appointment_date','invoice_number','invoice_date','amount_before_vat','vat_amount','invoice_total','paid_amount','balance_amount','last_payment_date','payment_status','mode']);
        $selectedIds = (array) $request->get('selected_job_ids', []);

        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        // Build rows by reusing computeData with per_page large enough for full exports (up to 50k)
        $req = Request::create('/admin/job-payments/data', 'GET', [
            'mode' => $mode,
            'status' => $statusFilter,
            'search' => $request->get('search'),
            'client_id' => $request->get('client_id'),
            'agent_id' => $request->get('agent_id'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'job_type' => $request->get('job_type'),
            'company_id' => $request->get('company_id'),
            'page' => 1,
            'per_page' => 50000,
            'for_export' => true,
        ]);
        $resp = $this->computeData($req);
        $payload = $resp->getData(true);
        $rows = (array) ($payload['data'] ?? []);

        // If specific selections requested, filter rows accordingly
        if (!empty($selectedIds)) {
            $selectedSet = array_map('intval', $selectedIds);
            $rows = array_values(array_filter($rows, function ($r) use ($selectedSet) {
                return in_array((int) ($r['job_id'] ?? 0), $selectedSet, true);
            }));
        }

        // Prepare filename
        $date = now()->format('Ymd');
        $titleMode = $mode === 'agent' ? 'Agent' : 'Client';
        $filename = sprintf('Jobs_%s_%s_%s.csv', ucfirst($statusFilter), $titleMode, $date);

        // Audit export (ignore failures)
        try {
            AuditLog::create([
                'user_id' => optional(auth()->user())->id,
                'actor_id' => optional(auth()->user())->id,
                'action' => 'job_payments_export',
                'entity_type' => 'JobPayments',
                'entity_id' => 0,
                'metadata' => [
                    'mode' => $mode,
                    'status' => $statusFilter,
                    'columns' => $columns,
                    'row_count' => count($rows),
                ],
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $e) { /* ignore audit failures */ }

        // Stream CSV response
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($rows, $columns) {
            // Add UTF-8 BOM for Excel
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            // Header row
            fputcsv($out, array_values($columns));
            // Data rows
            foreach ($rows as $r) {
                $line = [];
                foreach ($columns as $key => $label) {
                    // columns may be associative or numeric array; handle both
                    $colKey = is_string($key) ? $key : $label;
                    $line[] = $r[$colKey] ?? '';
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, 200, $headers);
    }

    /**
     * Preview endpoint: return first N rows for the current filters.
     */
    public function preview(Request $request)
    {
        if (!RoleHelper::hasRole(auth()->user(), 'admin')) {
            abort(403);
        }
        return $this->performPreview($request);
    }

    /**
     * Preview endpoint for dev (no auth).
     */
    public function previewDev(Request $request)
    {
        return $this->performPreview($request);
    }

    private function performPreview(Request $request)
    {
        $mode = in_array($request->get('mode'), ['client', 'agent']) ? $request->get('mode') : 'client';
        $statusFilter = in_array($request->get('status'), ['paid', 'unpaid', 'not_invoiced', 'all']) ? $request->get('status') : 'unpaid';
        // columns may come as JSON string; decode if necessary
        $columnsRaw = $request->get('columns');
        if (is_string($columnsRaw) && trim($columnsRaw) !== '') {
            $decoded = json_decode($columnsRaw, true);
            $columns = is_array($decoded) ? $decoded : ['job_id','job_type','client_name','agent_name','client_reference','file_reference','language','appointment_date','invoice_number','invoice_date','amount_before_vat','vat_amount','invoice_total','paid_amount','balance_amount','last_payment_date','payment_status','mode'];
        } else {
            $columns = (array) ($columnsRaw ?? ['job_id','job_type','client_name','agent_name','client_reference','file_reference','language','appointment_date','invoice_number','invoice_date','amount_before_vat','vat_amount','invoice_total','paid_amount','balance_amount','last_payment_date','payment_status','mode']);
        }
        $limit = max(1, (int) ($request->get('limit') ?? 25));

        // Build rows by reusing computeData with per_page limited
        $req = Request::create('/admin/job-payments/data', 'GET', [
            'mode' => $mode,
            'status' => $statusFilter,
            'search' => $request->get('search'),
            'client_id' => $request->get('client_id'),
            'agent_id' => $request->get('agent_id'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'job_type' => $request->get('job_type'),
            'company_id' => $request->get('company_id'),
            'page' => 1,
            'per_page' => $limit,
        ]);
        $resp = $this->computeData($req);
        $payload = $resp->getData(true);
        $rows = (array) ($payload['data'] ?? []);
        return response()->json([
            'columns' => $columns,
            'data' => $rows,
            'count' => count($rows),
        ]);
    }

    /**
     * Start export with progress tracking via Export model.
     */
    public function startExport(Request $request)
    {
        if (!RoleHelper::hasRole(auth()->user(), 'admin')) {
            abort(403);
        }
        return $this->performStartExport($request);
    }

    /**
     * Client lookup for filter dropdown/search.
     */
    public function clientsLookup(Request $request)
    {
        if (!RoleHelper::hasRole(auth()->user(), 'admin')) { abort(403); }
        return $this->performClientsLookup($request);
    }

    public function companiesLookup(Request $request)
    {
        if (!RoleHelper::hasRole(auth()->user(), 'admin')) { abort(403); }
        return $this->performCompaniesLookup($request);
    }

    /**
     * Dev preview variant for client lookup.
     */
    public function clientsLookupDev(Request $request)
    {
        return $this->performClientsLookup($request);
    }

    public function companiesLookupDev(Request $request)
    {
        return $this->performCompaniesLookup($request);
    }

    private function performClientsLookup(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $limit = max(1, min(50, (int) $request->get('limit', 20)));
        $query = \App\Client::with('user');
        if ($q !== '') {
            $query->whereHas('user', function($u) use ($q) {
                $u->where('first_name', 'like', "%$q%")
                  ->orWhere('last_name', 'like', "%$q%")
                  ->orWhere('email', 'like', "%$q%");
            });
        }
        $clients = $query->orderByDesc('id')->limit($limit)->get();
        $out = [];
        foreach ($clients as $c) {
            $name = optional($c->user)->getFullName();
            $email = optional($c->user)->email;
            $out[] = [
                'id' => $c->id,
                'name' => $name,
                'email' => $email,
                'label' => trim($name . ' • ' . ($email ?: '')), 'value' => $name,
            ];
        }
        return response()->json(['results' => $out]);
    }

    private function performCompaniesLookup(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $limit = max(1, min(100, (int) $request->get('limit', 30)));
        $query = \App\Company::query();
        if ($q !== '') {
            $query->where('name', 'like', "%$q%");
        }
        $companies = $query->orderBy('name')->limit($limit)->get();
        $out = [];
        foreach ($companies as $c) {
            $out[] = [
                'id' => $c->id,
                'name' => $c->name,
                'label' => $c->name,
                'value' => $c->name,
            ];
        }
        return response()->json(['results' => $out]);
    }

    /**
     * Start export for dev (no auth).
     */
    public function startExportDev(Request $request)
    {
        return $this->performStartExport($request);
    }

    private function performStartExport(Request $request)
    {
        $mode = in_array($request->get('mode'), ['client', 'agent']) ? $request->get('mode') : 'client';
        $statusFilter = in_array($request->get('status'), ['paid', 'unpaid', 'not_invoiced', 'all']) ? $request->get('status') : 'unpaid';
        $columns = (array) $request->get('columns', ['job_id','job_type','client_name','agent_name','client_reference','file_reference','language','appointment_date','invoice_number','invoice_date','amount_before_vat','vat_amount','invoice_total','paid_amount','balance_amount','last_payment_date','payment_status','mode']);
        $selectedIds = (array) $request->get('selected_job_ids', []);

        $filters = [
            'mode' => $mode,
            'status' => $statusFilter,
            'search' => $request->get('search'),
            'client_id' => $request->get('client_id'),
            'agent_id' => $request->get('agent_id'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'job_type' => $request->get('job_type'),
            'company_id' => $request->get('company_id'),
            'columns' => $columns,
            'selected_job_ids' => $selectedIds,
        ];

        $export = \App\Export::create([
            'type' => 'job_payments',
            'format' => 'csv',
            'filters' => $filters,
            'status' => 'processing',
            'user_id' => optional(auth()->user())->id,
        ]);

        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        try {
            // Build rows using computeData
            $req = Request::create('/admin/job-payments/data', 'GET', [
                'mode' => $mode,
                'status' => $statusFilter,
                'search' => $request->get('search'),
                'client_id' => $request->get('client_id'),
                'agent_id' => $request->get('agent_id'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'job_type' => $request->get('job_type'),
                'company_id' => $request->get('company_id'),
                'page' => 1,
                'per_page' => 50000,
                'for_export' => true,
            ]);
            $resp = $this->computeData($req);
            $payload = $resp->getData(true);
            $rows = (array) ($payload['data'] ?? []);

            if (!empty($selectedIds)) {
                $selectedSet = array_map('intval', $selectedIds);
                $rows = array_values(array_filter($rows, function ($r) use ($selectedSet) {
                    return in_array((int) ($r['job_id'] ?? 0), $selectedSet, true);
                }));
            }

            $date = now()->format('Ymd_His');
            $titleMode = $mode === 'agent' ? 'Agent' : 'Client';
            $filename = sprintf('Jobs_%s_%s_%s.csv', ucfirst($statusFilter), $titleMode, $date);

            // Write CSV file to storage/app/exports
            $path = storage_path('app/exports');
            if (!is_dir($path)) { @mkdir($path, 0755, true); }
            $filePath = $path . DIRECTORY_SEPARATOR . $filename;
            $fh = fopen($filePath, 'w');
            // UTF-8 BOM for Excel
            fwrite($fh, "\xEF\xBB\xBF");
            // Header row (labels)
            $header = [];
            foreach ($columns as $key => $label) {
                $header[] = is_string($key) ? $label : $label;
            }
            fputcsv($fh, $header);
            // Data rows
            foreach ($rows as $r) {
                $line = [];
                foreach ($columns as $key => $label) {
                    $colKey = is_string($key) ? $key : $label;
                    $line[] = $r[$colKey] ?? '';
                }
                fputcsv($fh, $line);
            }
            fclose($fh);

            $disk = env('EXPORTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
            $rel = 'exports/' . $filename;
            if ($disk === 's3') {
                try {
                    $content = file_exists($filePath) ? file_get_contents($filePath) : '';
                    if ($content !== '') {
                        Storage::disk('s3')->put($rel, $content);
                        @unlink($filePath);
                    }
                } catch (\Throwable $e) {
                }
            }

            // Update export record
            $export->update([
                'status' => 'completed',
                'file_path' => $filename,
            ]);

            return response()->json(['export_id' => $export->id, 'status' => $export->status]);
        } catch (\Throwable $e) {
            $export->update(['status' => 'failed']);
            return response()->json(['export_id' => $export->id, 'status' => 'failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Poll export status (admin-only).
     */
    public function statusExport(\App\Export $export)
    {
        if (!RoleHelper::hasRole(auth()->user(), 'admin')) {
            abort(403);
        }
        return response()->json([
            'id' => $export->id,
            'status' => $export->status,
            'file_path' => $export->file_path,
        ]);
    }

    /**
     * Poll export status (dev).
     */
    public function statusExportDev(\App\Export $export)
    {
        return response()->json([
            'id' => $export->id,
            'status' => $export->status,
            'file_path' => $export->file_path,
        ]);
    }

    /**
     * Download export (admin-only).
     */
    public function downloadExport(\App\Export $export)
    {
        if (!RoleHelper::hasRole(auth()->user(), 'admin')) {
            abort(403);
        }
        return $this->performDownloadExport($export);
    }

    /**
     * Download export (dev).
     */
    public function downloadExportDev(\App\Export $export)
    {
        return $this->performDownloadExport($export);
    }

    private function performDownloadExport(\App\Export $export)
    {
        if ($export->status !== 'completed' || !$export->file_path) {
            return response()->json(['error' => 'Export not completed'], 400);
        }
        $disk = env('EXPORTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        $rel = 'exports/' . $export->file_path;
        if ($disk === 's3') {
            try {
                $client = new \Aws\S3\S3Client([
                    'version' => 'latest',
                    'region' => env('AWS_DEFAULT_REGION'),
                    'credentials' => [
                        'key' => env('AWS_ACCESS_KEY_ID'),
                        'secret' => env('AWS_SECRET_ACCESS_KEY'),
                    ],
                ]);
                $key = trim(env('AWS_PREFIX', ''), '/') ? (trim(env('AWS_PREFIX', ''), '/') . '/' . $rel) : $rel;
                $cmd = $client->getCommand('GetObject', ['Bucket' => env('AWS_BUCKET'), 'Key' => $key]);
                $req = $client->createPresignedRequest($cmd, '+30 minutes');
                return redirect()->away((string) $req->getUri());
            } catch (\Throwable $e) {
                return response()->json(['error' => 'Export file not found'], 404);
            }
        }
        $filePath = storage_path('app/' . $rel);
        if (!file_exists($filePath)) { return response()->json(['error' => 'Export file not found'], 404); }
        return response()->download($filePath);
    }

    /**
     * Create a Client Invoice from selected jobs in the Job Payments dashboard (admin-only).
     * Expects JSON payload: { jobs: [{job_id, job_type}], include_vat: bool, vat_rate: number }
     */
    public function createClientInvoice(Request $request)
    {
        if (!RoleHelper::hasRole(auth()->user(), 'admin')) {
            abort(403);
        }
        return $this->performCreateClientInvoice($request);
    }

    /**
     * Dev variant (no auth gating) to allow local testing from the dev preview UI.
     */
    public function createClientInvoiceDev(Request $request)
    {
        return $this->performCreateClientInvoice($request);
    }

    /**
     * Shared implementation for creating a draft client invoice from selected jobs.
     */
    private function performCreateClientInvoice(Request $request)
    {
        // Validate basic payload
        $data = $request->validate([
            'jobs' => ['required', 'array', 'min:1'],
            'jobs.*.job_id' => ['required', 'integer', 'min:1'],
            'jobs.*.job_type' => ['required', 'string'],
            'include_vat' => ['nullable', 'boolean'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
        ]);

        $includeVat = array_key_exists('include_vat', $data) ? (bool)$data['include_vat'] : true;
        $vatRate = isset($data['vat_rate']) ? (float)$data['vat_rate'] : 20.0;

        // Resolve jobs and ensure they belong to a single client
        $resolved = [];
        $clientId = null;
        $dates = [];
        $duplicates = [];
        foreach ((array)$data['jobs'] as $j) {
            $jobId = (int)($j['job_id'] ?? 0);
            $jt = strtolower(trim((string)($j['job_type'] ?? '')));
            $class = null;
            if ($jt === 'interpreter') { $class = \App\InterpreterJob::class; }
            elseif ($jt === 'translator') { $class = \App\TranslatorJob::class; }
            else {
                return response()->json(['error' => 'Invalid job type for job ID '.$jobId], 422);
            }
            try {
                $job = $class::find($jobId);
            } catch (\Throwable $e) { $job = null; }
            if (!$job) {
                return response()->json(['error' => 'Job not found: '.$jobId], 404);
            }
            $jidClientId = optional($job->client)->id;
            if (!$jidClientId) {
                return response()->json(['error' => 'Job '.$jobId.' has no associated client.'], 422);
            }
            if ($clientId === null) { $clientId = (int)$jidClientId; }
            elseif ((int)$jidClientId !== (int)$clientId) {
                return response()->json(['error' => 'Selected jobs must belong to a single client.'], 422);
            }

            // Check duplicates: any ClientInvoiceItem for this job with matching job_type in metadata
            $existingItems = ClientInvoiceItem::where('job_id', $jobId)->get();
            foreach ($existingItems as $item) {
                $meta = is_array($item->metadata) ? $item->metadata : (is_string($item->metadata) ? json_decode($item->metadata, true) : []);
                $mt = is_array($meta) ? ($meta['job_type'] ?? null) : null;
                if ($mt === $class) { $duplicates[] = $jobId; break; }
            }

            // Collect date for invoice range
            try {
                if ($jt === 'interpreter') { $dates[] = optional($job->appointment_date)->toDateString(); }
                else { $dates[] = optional($job->target_date)->toDateString(); }
            } catch (\Throwable $e) { /* ignore date parse errors */ }

            $resolved[] = compact('jobId','jt','class','job');
        }

        if (!empty($duplicates)) {
            return response()->json(['error' => 'One or more selected jobs are already invoiced: ' . implode(', ', array_unique($duplicates))], 422);
        }

        if ($clientId === null) {
            return response()->json(['error' => 'Unable to resolve client from selected jobs.'], 422);
        }

        // Determine date range (start/end) from resolved job dates
        $dates = array_filter($dates);
        $dateStart = !empty($dates) ? min($dates) : now()->toDateString();
        $dateEnd = !empty($dates) ? max($dates) : now()->toDateString();

        try {
            return DB::transaction(function () use ($resolved, $clientId, $includeVat, $vatRate, $dateStart, $dateEnd) {
                // Subtotal: initialize to 0; amounts will be edited later in invoice builder
                $subtotal = 0.0;
                $vatAmount = $includeVat ? round($subtotal * ($vatRate / 100), 2) : 0.0;
                $total = round($subtotal + $vatAmount, 2);

                // Determine company from client's organisation if available
                $client = \App\Client::find($clientId);
                $companyId = optional(optional($client)->organisation)->company_id;

                $firstEntry = !empty($resolved) ? ($resolved[0] ?? null) : null;
                $firstJob = $firstEntry ? ($firstEntry['job'] ?? null) : null;

                $invoice = ClientInvoice::create([
                    'client_id' => $clientId,
                    'company_id' => $companyId,
                    'invoice_number' => 'CI-TMP-' . uniqid(),
                    'invoice_date' => now()->toDateString(),
                    'date_range_start' => $dateStart,
                    'date_range_end' => $dateEnd,
                    'template' => 'v2',
                    'include_vat' => $includeVat,
                    'vat_rate' => $vatRate,
                    'subtotal' => round($subtotal, 2),
                    'vat_amount' => $vatAmount,
                    'total' => $total,
                    'status' => 'draft',
                    'file_reference' => ($firstJob && property_exists($firstJob, 'file_reference')) ? ($firstJob->file_reference ?? null) : null,
                    'client_reference' => $firstJob ? ($firstJob->client_reference ?? null) : null,
                    'patient_number' => ($firstJob && property_exists($firstJob, 'patient_number')) ? ($firstJob->patient_number ?? null) : null,
                    'pid' => ($firstJob && property_exists($firstJob, 'personal_identity_number')) ? ($firstJob->personal_identity_number ?? null) : null,
                ]);
                $invoice->invoice_number = 'CI-' . str_pad((string)$invoice->id, 6, '0', STR_PAD_LEFT);
                $invoice->save();

                foreach ($resolved as $entry) {
                    $job = $entry['job'];
                    $jt = $entry['jt'];
                    $jobId = $entry['jobId'];
                    $class = $entry['class'];
                    $desc = $jt === 'interpreter' ? ('Interpreting service for Job #'.$jobId) : ('Translation service for Job #'.$jobId);
                    $dateVal = null;
                    try { $dateVal = $jt === 'interpreter' ? optional($job->appointment_date)->toDateString() : optional($job->target_date)->toDateString(); } catch (\Throwable $e) { $dateVal = null; }
                    $clientRef = null; $fileRef = null; $pidVal = null; $patientNo = null;
                    try { $clientRef = $job->client_reference ?? null; } catch (\Throwable $e) { $clientRef = null; }
                    try { $fileRef = property_exists($job, 'file_reference') ? ($job->file_reference ?? null) : null; } catch (\Throwable $e) { $fileRef = null; }
                    try { $pidVal = property_exists($job, 'personal_identity_number') ? ($job->personal_identity_number ?? null) : null; } catch (\Throwable $e) { $pidVal = null; }
                    try { $patientNo = property_exists($job, 'patient_number') ? ($job->patient_number ?? null) : null; } catch (\Throwable $e) { $patientNo = null; }

                    ClientInvoiceItem::create([
                        'client_invoice_id' => $invoice->id,
                        'job_id' => (int)$jobId,
                        'job_type' => $class,
                        'description' => $desc,
                        'amount' => 0.0,
                        'surcharge' => 0.0,
                        'other_charges' => 0.0,
                        'vat_applicable' => $includeVat,
                        'metadata' => [
                            'job_type' => $class,
                            'date' => $dateVal,
                            'client_reference' => $clientRef,
                            'file_reference' => $fileRef,
                            'pid' => $pidVal,
                            'patient_number' => $patientNo,
                            'source' => 'admin_job_payments',
                        ],
                    ]);
                }

                // Audit log (ignore failures)
                try {
                    AuditLog::create([
                        'user_id' => optional(auth()->user())->id,
                        'actor_id' => optional(auth()->user())->id,
                        'action' => 'client_invoice_created_from_job_payments',
                        'entity_type' => 'ClientInvoice',
                        'entity_id' => $invoice->id,
                        'metadata' => [
                            'client_id' => $clientId,
                            'items_count' => count($resolved),
                            'subtotal' => round($subtotal, 2),
                            'vat' => $vatAmount,
                            'total' => $total,
                        ],
                        'ip_address' => request()->ip(),
                    ]);
                } catch (\Throwable $e) { /* ignore */ }

                // Prefer redirect; if fetch handles redirects, frontend will follow.
                return redirect()->route('invoices.admin.client.preview', ['id' => $invoice->id]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to create client invoice from job payments: '.$e->getMessage());
            return response()->json(['error' => 'Failed to create invoice: '.$e->getMessage()], 500);
        }
    }
}
