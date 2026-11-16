<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\AgentInvoice;
use Illuminate\Support\Facades\DB;

class AgentInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $agent = optional($user)->agent;

        $filters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'status' => $request->query('status'),
            'tab' => $request->query('tab', 'interpreter'), // Default to interpreter tab
        ];

        $baseQuery = AgentInvoice::where('agent_id', optional($agent)->id);

        // Filter by invoice period (date_from/date_to)
        if ($filters['start_date']) {
            $baseQuery->whereDate('date_from', '>=', $filters['start_date']);
        }
        if ($filters['end_date']) {
            $baseQuery->whereDate('date_to', '<=', $filters['end_date']);
        }
        if ($filters['status']) {
            $baseQuery->where('status', $this->agentStatusCode($filters['status']));
        }

        // Separate queries for interpreter and translation invoices
        $interpreterQuery = clone $baseQuery;
        $interpreterQuery->whereHas('items', function($q) {
            $q->whereJsonContains('metadata->job_type', 'App\\InterpreterJob');
        });

        $translationQuery = clone $baseQuery;
        $translationQuery->whereHas('items', function($q) {
            $q->whereJsonContains('metadata->job_type', 'App\\TranslatorJob');
        });

        $interpreterInvoices = $interpreterQuery->with('items')->orderByDesc('id')->paginate(15, ['*'], 'interpreter_page')->withQueryString();
        $translationInvoices = $translationQuery->with('items')->orderByDesc('id')->paginate(15, ['*'], 'translation_page')->withQueryString();

        // Count totals for badges
        $interpreterCount = $interpreterQuery->count();
        $translationCount = $translationQuery->count();

        return view('invoices.agent.index', compact(
            'interpreterInvoices', 
            'translationInvoices', 
            'interpreterCount', 
            'translationCount', 
            'filters'
        ));
    }

    /**
     * Show detailed view of an invoice with all job items.
     */
    public function show($id)
    {
        $invoice = AgentInvoice::with(['agent.user', 'items'])->findOrFail($id);
        $user = auth()->user();
        if ((int) optional($user->agent)->id !== (int) $invoice->agent_id) {
            abort(403);
        }
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
            $dateStr = null;
            $language = null;
            $duration = null;
            if (strpos($jt, 'InterpreterJob') !== false) {
                $job = $interpreterJobsMap->get($jid);
                $date = optional($job)->appointment_date;
                $dateStr = $date ? \Carbon\Carbon::parse($date)->format('Y-m-d') : null;
                $language = optional(optional($job)->toLanguage)->name;
                if ($job) {
                    $h = (int) ($job->duration_hours ?? 0);
                    $m = (int) ($job->duration_minutes ?? 0);
                    if ($h > 0 || $m > 0) {
                        $duration = $h . 'h ' . $m . 'm';
                    }
                }
            } elseif (strpos($jt, 'TranslatorJob') !== false) {
                $job = $translatorJobsMap->get($jid);
                $date = optional($job)->target_date;
                $dateStr = $date ? \Carbon\Carbon::parse($date)->format('Y-m-d') : null;
                $language = trim(((string) optional(optional($job)->fromLanguage)->name) . ' → ' . ((string) optional(optional($job)->toLanguage)->name));
            } else {
                $job = $interpreterJobsMap->get($jid) ?: $translatorJobsMap->get($jid);
                if ($job) {
                    $isInterpreter = property_exists($job, 'appointment_date') || method_exists($job, 'isCancelledSameDay');
                    $dateRaw = $isInterpreter ? optional($job)->appointment_date : optional($job)->target_date;
                    $dateStr = $dateRaw ? \Carbon\Carbon::parse($dateRaw)->format('Y-m-d') : null;
                    $language = $isInterpreter
                        ? optional(optional($job)->toLanguage)->name
                        : trim(((string) optional(optional($job)->fromLanguage)->name) . ' → ' . ((string) optional(optional($job)->toLanguage)->name));
                    if ($isInterpreter) {
                        $h = (int) ($job->duration_hours ?? 0);
                        $m = (int) ($job->duration_minutes ?? 0);
                        if ($h > 0 || $m > 0) {
                            $duration = $h . 'h ' . $m . 'm';
                        }
                    }
                }
            }
            $computed[$it->id] = [
                'date' => $dateStr,
                'language' => $language,
                'duration' => $duration,
            ];
        }

        return view('invoices.agent.show', ['invoice' => $invoice, 'computed' => $computed]);
    }

    /**
     * Show form to create a new invoice from pending job entries.
     */
    public function create(Request $request)
    {
        $user = auth()->user();
        $agent = optional($user)->agent;

        $start = $request->query('start_date') ?? now()->startOfMonth()->toDateString();
        $end = $request->query('end_date') ?? now()->endOfMonth()->toDateString();

        $interpreterEntries = \App\AgentJobEntry::select('agent_job_entries.*')
            ->join('interpreter_jobs', 'agent_job_entries.job_id', '=', 'interpreter_jobs.id')
            ->where('agent_job_entries.agent_id', optional($agent)->id)
            ->where('agent_job_entries.invoiced', false)
            ->where('agent_job_entries.job_type', 'App\\InterpreterJob')
            ->whereBetween('interpreter_jobs.appointment_date', [$start, $end])
            ->orderBy('interpreter_jobs.appointment_date', 'desc')
            ->get();

        $translatorEntries = \App\AgentJobEntry::select('agent_job_entries.*')
            ->join('translator_jobs', 'agent_job_entries.job_id', '=', 'translator_jobs.id')
            ->where('agent_job_entries.agent_id', optional($agent)->id)
            ->where('agent_job_entries.invoiced', false)
            ->where('agent_job_entries.job_type', 'App\\TranslatorJob')
            ->whereBetween('translator_jobs.target_date', [$start, $end])
            ->orderBy('translator_jobs.target_date', 'desc')
            ->get();

        $entries = $interpreterEntries->merge($translatorEntries)->load('job');

        return view('invoices.agent.new', [
            'start_date' => $start,
            'end_date' => $end,
            'entries' => $entries,
        ]);
    }

    /**
     * Generate an invoice from pending job entries in the given date range.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'entry_ids' => 'sometimes|array',
            'entry_ids.*' => 'integer'
        ]);

        $user = auth()->user();
        $agent = optional($user)->agent;
        abort_unless($agent, 403);

        $start = $request->input('start_date');
        $end = $request->input('end_date');

        $selected = collect($request->input('entry_ids', []))->filter()->map('intval');
        if ($selected->isNotEmpty()) {
            $entries = \App\AgentJobEntry::where('agent_id', $agent->id)
                ->whereIn('id', $selected->all())
                ->where('invoiced', false)
                ->orderBy('created_at', 'asc')
                ->get();
        } else {
            $interpreterEntries = \App\AgentJobEntry::select('agent_job_entries.*')
                ->join('interpreter_jobs', 'agent_job_entries.job_id', '=', 'interpreter_jobs.id')
                ->where('agent_job_entries.agent_id', $agent->id)
                ->where('agent_job_entries.invoiced', false)
                ->where('agent_job_entries.job_type', 'App\\InterpreterJob')
                ->whereBetween('interpreter_jobs.appointment_date', [$start, $end])
                ->orderBy('interpreter_jobs.appointment_date', 'asc')
                ->get();

            $translatorEntries = \App\AgentJobEntry::select('agent_job_entries.*')
                ->join('translator_jobs', 'agent_job_entries.job_id', '=', 'translator_jobs.id')
                ->where('agent_job_entries.agent_id', $agent->id)
                ->where('agent_job_entries.invoiced', false)
                ->where('agent_job_entries.job_type', 'App\\TranslatorJob')
                ->whereBetween('translator_jobs.target_date', [$start, $end])
                ->orderBy('translator_jobs.target_date', 'asc')
                ->get();

            $entries = $interpreterEntries->merge($translatorEntries);
        }

        if ($entries->isEmpty()) {
            return redirect()->route('invoices.agent.create', ['start_date' => $start, 'end_date' => $end])
                ->with('error', 'No pending entries found in the selected period.');
        }

        $subtotal = $entries->sum('base_amount');
        $expensesTotal = $entries->sum(function ($e) { return ($e->expenses + $e->surcharge + $e->other_charges); });
        $vatAmount = 0.00; // No VAT rate column in DB; store computed amount only
        $totalAmount = $subtotal + $expensesTotal + $vatAmount;

        DB::beginTransaction();
        try {
            // Use a temporary non-null unique invoice number to satisfy DB constraints on insert
            $tmpNumber = 'AI-TMP-' . uniqid();
            $invoice = \App\AgentInvoice::create([
                'agent_id' => $agent->id,
                'invoice_number' => $tmpNumber,
                'invoice_date' => now()->toDateString(),
                'date_from' => $start,
                'date_to' => $end,
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total_amount' => $totalAmount,
                'status' => $this->agentStatusCode('draft'),
            ]);

            // Now set the final formatted invoice number based on the assigned ID
            $invoice->invoice_number = 'AI-' . str_pad((string)$invoice->id, 6, '0', STR_PAD_LEFT);
            $invoice->save();

            foreach ($entries as $e) {
                \App\AgentInvoiceItem::create([
                    'agent_invoice_id' => $invoice->id,
                    'job_id' => $e->job_id,
                    'job_type' => $e->job_type,
                    'description' => $e->notes,
                    'base_amount' => $e->base_amount,
                    'expenses_amount' => $e->expenses,
                    'surcharge' => $e->surcharge,
                    'other_charges' => $e->other_charges,
                    'vat_applicable' => false,
                    'total_amount' => $e->total_amount,
                    'metadata' => [
                        'job_type' => $e->job_type,
                        'entry_id' => $e->id,
                        'created_at' => $e->created_at,
                    ],
                ]);

                $e->invoiced = true;
                $e->save();
            }

            DB::commit();
        } catch (\Throwable $t) {
            DB::rollBack();
            return redirect()->route('invoices.agent.create', ['start_date' => $start, 'end_date' => $end])
                ->with('error', 'Failed to generate invoice: ' . $t->getMessage());
        }

        // Notify admin that a new agent invoice has been submitted
        try {
            $to = trim((string) config('app.to.address'));
            if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $agentName = (string) (optional(optional($agent)->user)->full_name ?? '');
                $subject = config('app.name') . ' - New agent invoice ' . $invoice->invoice_number;
                $body = 'A new agent invoice has been created.'
                    . "\n\n"
                    . 'Invoice: ' . $invoice->invoice_number
                    . ($agentName !== '' ? ("\nAgent: " . $agentName) : '')
                    . "\nPeriod: " . $start . ' to ' . $end
                    . "\nTotal: " . number_format((float) $totalAmount, 2)
                    . "\n\n"
                    . 'View invoice: ' . route('invoices.admin.agent.show.legacy', $invoice->id);
                \Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($to, $subject) {
                    $message->to($to)->subject($subject);
                });
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to notify admin of new agent invoice: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
        }

        return redirect()->route('invoices.agent.index')
            ->with('success', 'Invoice #' . $invoice->invoice_number . ' created successfully.');
    }
    public function pdf($id)
    {
        $invoice = AgentInvoice::with(['agent.user', 'items'])->findOrFail($id);
        $user = auth()->user();
        if ((int) optional($user->agent)->id !== (int) $invoice->agent_id) {
            abort(403);
        }

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
            'margin_top' => 10,
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_bottom' => 10,
        ]);
        $mpdf->WriteHTML($html);
        $mpdf->Output('agent-invoice-'.$invoice->invoice_number.'.pdf', 'I');
        exit;
    }

    /**
     * Allow agents to delete their own draft invoices.
     * Reverts related job entries to uninvoiced so they can be re-invoiced later.
     */
    public function destroy($id)
    {
        $user = auth()->user();
        $agent = optional($user)->agent;
        abort_unless($agent, 403);

        try {
            $invoice = \App\AgentInvoice::with('items')->findOrFail($id);
            if ((int) $invoice->agent_id !== (int) $agent->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Only allow deletion when invoice is in draft status
            $isNumeric = is_numeric($invoice->status);
            $draftCode = $this->agentStatusCode('draft');
            $isDraft = $isNumeric ? ((int) $invoice->status === (int) $draftCode) : (strtolower((string) $invoice->status) === 'draft');
            if (!$isDraft) {
                return response()->json(['success' => false, 'message' => 'Only draft invoices can be deleted'], 409);
            }

            // Revert related agent job entries to uninvoiced
            foreach ($invoice->items()->get() as $item) {
                if ($item->job_id) {
                    \App\AgentJobEntry::where('agent_id', $invoice->agent_id)
                        ->where('job_id', $item->job_id)
                        ->when($item->job_type, function ($q) use ($item) {
                            $q->where('job_type', $item->job_type);
                        })
                        ->where('invoiced', true)
                        ->update(['invoiced' => false]);
                }
            }

            // Delete invoice items explicitly to avoid orphaned rows
            \App\AgentInvoiceItem::where('agent_invoice_id', $invoice->id)->delete();

            // Delete the invoice itself
            $invoice->delete();

            if (request()->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Invoice deleted successfully']);
            }
            return redirect()->back()->with('success', 'Invoice deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
        } catch (\Throwable $t) {
            \Illuminate\Support\Facades\Log::error('Agent invoice delete failed: ' . $t->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete invoice'], 500);
        }
    }

    /**
     * Update VAT setting for an invoice item
     */
    public function updateItemVat(Request $request, $itemId)
    {
        try {
            $item = \App\AgentInvoiceItem::findOrFail($itemId);
            
            // Check if the current user owns this invoice item through their agent relationship
            $user = auth()->user();
            if (!$user->agent || $item->agentInvoice->agent_id !== $user->agent->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $vatApplicable = $request->input('vat_applicable', false);
            $item->vat_applicable = $vatApplicable;
            $item->save();
            
            // Recalculate invoice totals
            $invoice = $item->agentInvoice;
            $this->recalculateInvoiceTotals($invoice);
            
            return response()->json([
                'success' => true,
                'message' => 'VAT setting updated successfully',
                'totals' => [
                    'subtotal' => number_format($invoice->subtotal, 2),
                    'vat_amount' => number_format($invoice->vat_amount, 2),
                    'total_amount' => number_format($invoice->total_amount, 2)
                ]
            ]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('VAT update error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error updating VAT setting: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Recalculate invoice totals based on items
     */
    private function recalculateInvoiceTotals($invoice)
    {
        // Calculate subtotal from base amounts (excluding VAT)
        $subtotal = $invoice->items->sum(function($item) {
            return $item->base_amount + $item->expenses_amount + $item->surcharge + $item->other_charges;
        });
        
        // Calculate VAT only on items where VAT is applicable (20% of their total)
        $vatAmount = $invoice->items->where('vat_applicable', true)->sum(function($item) {
            $itemTotal = $item->base_amount + $item->expenses_amount + $item->surcharge + $item->other_charges;
            return $itemTotal * 0.20;
        });
        
        // Total amount is subtotal plus VAT
        $totalAmount = $subtotal + $vatAmount;
        
        $invoice->update([
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'total_amount' => $totalAmount
        ]);
    }

    /**
     * Map human-readable status labels to the appropriate value for the
     * agent_invoices.status column, which may be numeric in legacy DBs
     * or string in newer schemas. Accepts either string labels or
     * numeric codes and returns the correct value to persist/query.
     */
    private function agentStatusCode($status)
    {
        // Determine column type once per request
        static $isNumeric = null;
        if ($isNumeric === null) {
            try {
                $res = DB::select("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agent_invoices' AND COLUMN_NAME = 'status'");
                if (!empty($res)) {
                    $dataType = strtolower($res[0]->DATA_TYPE ?? '');
                    $isNumeric = in_array($dataType, ['tinyint','smallint','int','bigint','integer']);
                }
            } catch (\Throwable $e) {
                $isNumeric = null; // we will infer from data below
            }
            if ($isNumeric === null) {
                try {
                    $sample = \App\AgentInvoice::orderByDesc('id')->value('status');
                    $isNumeric = $sample !== null ? is_numeric($sample) : false;
                } catch (\Throwable $e) {
                    $isNumeric = false;
                }
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
            if (is_string($status)) {
                return $map[strtolower($status)] ?? 0;
            }
            return (int) $status;
        } else {
            if (is_numeric($status)) {
                $rev = array_flip($map);
                $key = (int) $status;
                return $rev[$key] ?? 'draft';
            }
            return (string) $status;
        }
    }
}
