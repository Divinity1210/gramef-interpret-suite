<?php

namespace App\Http\Controllers;

use App\Payslip;
use App\BankAccount;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use App\Agent;
use App\Helpers\RoleHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PayslipController extends Controller
{
    public function index(Request $request)
    {
        // Build base query
        $query = Payslip::with(['agent.user', 'invoice'])
            ->orderByDesc('created_at');

        $user = auth()->user();

        // Restrict agents to their own payslips
        if ($user && RoleHelper::hasRole($user, 'agent')) {
            $agentId = Agent::where('user_id', $user->id)->value('id');
            if ($agentId) {
                $query->where('agent_id', $agentId);
            }
        }

        // Apply filters for admins and other roles
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($agentFilter = $request->get('agent_id')) {
            $query->where('agent_id', $agentFilter);
        }
        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $payslips = $query->paginate(20)->appends($request->query());

        // Build agent list for filter dropdown
        if ($user && RoleHelper::hasRole($user, 'agent')) {
            // Single current agent option
            $agentId = Agent::where('user_id', $user->id)->value('id');
            $agents = $agentId ? [$agentId => $user->full_name] : [];
        } else {
            // Admins/others: list all agents
            $agents = Agent::with('user')->get()->mapWithKeys(function ($agent) {
                return [$agent->id => data_get($agent, 'user.full_name', 'Unknown')];
            })->toArray();
        }

        return view('payslips.index', compact('payslips', 'agents'));
    }

    public function create(Request $request)
    {
        if (!RoleHelper::hasRole(auth()->user(), 'admin')) {
            abort(403);
        }

        $agents = Agent::with('user')->get();
        $agentId = $request->query('agent_id');
        $start = $request->query('start_date') ?? now()->startOfMonth()->toDateString();
        $end = $request->query('end_date') ?? now()->endOfMonth()->toDateString();

        $items = collect();
        $dnaJobs = collect();
        if ($agentId) {
            $interpreterItems = \App\AgentInvoiceItem::select('agent_invoice_items.*')
                ->join('agent_invoices', 'agent_invoice_items.agent_invoice_id', '=', 'agent_invoices.id')
                ->join('interpreter_jobs', 'agent_invoice_items.job_id', '=', 'interpreter_jobs.id')
                ->where('agent_invoices.agent_id', $agentId)
                ->where(function ($q) {
                    $q->whereNotIn('agent_invoices.status', ['paid', 3])->orWhereNull('agent_invoices.status');
                })
                ->where('agent_invoice_items.job_type', 'App\\InterpreterJob')
                ->whereNull('agent_invoice_items.payslip_id')
                ->whereBetween('interpreter_jobs.appointment_date', [$start, $end])
                ->get();

            $translatorItems = \App\AgentInvoiceItem::select('agent_invoice_items.*')
                ->join('agent_invoices', 'agent_invoice_items.agent_invoice_id', '=', 'agent_invoices.id')
                ->join('translator_jobs', 'agent_invoice_items.job_id', '=', 'translator_jobs.id')
                ->where('agent_invoices.agent_id', $agentId)
                ->where(function ($q) {
                    $q->whereNotIn('agent_invoices.status', ['paid', 3])->orWhereNull('agent_invoices.status');
                })
                ->where('agent_invoice_items.job_type', 'App\\TranslatorJob')
                ->whereNull('agent_invoice_items.payslip_id')
                ->whereBetween('translator_jobs.target_date', [$start, $end])
                ->get();

            $items = $interpreterItems->merge($translatorItems);

            // Fetch DNA jobs assigned to this agent that don't already have invoice items
            $existingJobIds = $items->where('job_type', 'App\\InterpreterJob')->pluck('job_id')->toArray();
            $dnaJobs = \App\InterpreterJob::with(['toLanguage', 'client.user'])
                ->where('dna', true)
                ->where(function ($q) use ($agentId) {
                    $q->where('agent_id', $agentId)
                        ->orWhereHas('matchedAgents', function ($mq) use ($agentId) {
                            $mq->where('agent_id', $agentId)->whereIn('status', [2, 4]);
                        });
                })
                ->whereBetween('appointment_date', [$start, $end])
                ->when(!empty($existingJobIds), function ($q) use ($existingJobIds) {
                    $q->whereNotIn('id', $existingJobIds);
                })
                ->orderBy('appointment_date', 'desc')
                ->get();
        }

        return view('payslips.create', [
            'agents' => $agents,
            'selected_agent_id' => $agentId,
            'start_date' => $start,
            'end_date' => $end,
            'items' => $items,
            'dnaJobs' => $dnaJobs,
        ]);
    }

    public function store(Request $request)
    {
        if (!RoleHelper::hasRole(auth()->user(), 'admin')) {
            abort(403);
        }

        $request->validate([
            'agent_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer',
            'dna_job_ids' => 'nullable|array',
            'dna_job_ids.*' => 'integer',
        ]);

        // Must have at least one item or DNA job selected
        if (empty($request->input('item_ids')) && empty($request->input('dna_job_ids'))) {
            return redirect()->back()->with('error', 'No items selected');
        }

        $agentId = (int) $request->input('agent_id');
        $start = $request->input('start_date');
        $end = $request->input('end_date');
        $itemIds = collect($request->input('item_ids', []))->filter()->map('intval');
        $dnaJobIds = collect($request->input('dna_job_ids', []))->filter()->map('intval');

        $items = $itemIds->isNotEmpty()
            ? \App\AgentInvoiceItem::whereIn('id', $itemIds->all())
                ->whereHas('agentInvoice', function ($q) use ($agentId) {
                    $q->where('agent_id', $agentId);
                })
                ->whereNull('payslip_id')
                ->with(['job', 'agentInvoice'])->get()
            : collect();

        if ($itemIds->isNotEmpty() && $items->count() !== $itemIds->count()) {
            return redirect()->back()->with('error', 'Some selected items do not belong to this agent or are already on a payslip. Please reselect and try again.');
        }

        $firstInvoiceId = (int) optional($items->first())->agent_invoice_id;

        $subtotal = $items->sum('base_amount');
        $expensesTotal = $items->sum(function ($i) {
            return $i->expenses_amount + $i->surcharge + $i->other_charges; });
        $total = $subtotal + $expensesTotal;

        // Build DNA job line items (£0.00 amounts for admin to add deductions)
        $dnaLineItems = collect();
        if ($dnaJobIds->isNotEmpty()) {
            $dnaInterpreterJobs = \App\InterpreterJob::with(['toLanguage', 'client.user'])
                ->whereIn('id', $dnaJobIds->all())
                ->where('dna', true)
                ->get();
            foreach ($dnaInterpreterJobs as $dnaJob) {
                $date = $dnaJob->appointment_date;
                $dateStr = $date ? \Carbon\Carbon::parse($date)->format('Y-m-d') : null;
                $language = optional($dnaJob->toLanguage)->name;
                $clientName = $dnaJob->client ? optional($dnaJob->client->user)->getFullName() : null;
                $dnaLineItems->push([
                    'description' => 'DNA - Job #' . $dnaJob->id,
                    'amount' => 0.00,
                    'adjustment' => 0.00,
                    'total' => 0.00,
                    'invoice_item_id' => null,
                    'job_id' => $dnaJob->id,
                    'job_ref' => $dnaJob->id . '/atoz',
                    'client_name' => $clientName,
                    'language' => $language,
                    'date' => $dateStr,
                    'job_type' => 'App\\InterpreterJob',
                    'surcharge' => 0.00,
                    'is_dna' => true,
                ]);
            }
        }

        DB::beginTransaction();
        try {
            $payslipNumber = 'PS-' . str_pad((string) (\App\Payslip::max('id') + 1), 6, '0', STR_PAD_LEFT);

            // Build regular invoice line items
            $invoiceLineItems = $items->map(function ($invItem) {
                $job = $invItem->job;
                $date = $job ? ($job->appointment_date ?? $job->target_date) : null;
                $dateStr = $date ? \Carbon\Carbon::parse($date)->format('Y-m-d') : null;
                $language = $job && $job->toLanguage ? $job->toLanguage->name : null;
                $clientName = $job && $job->client ? optional($job->client->user)->getFullName() : null;
                $original = (float) $invItem->total_amount;
                return [
                    'description' => (string) ($invItem->description ?? ''),
                    'amount' => round($original, 2),
                    'adjustment' => 0.00,
                    'total' => round($original, 2),
                    'invoice_item_id' => $invItem->id,
                    'job_id' => $invItem->job_id,
                    'job_ref' => (string) ($invItem->job_id ? ($invItem->job_id . '/atoz') : ''),
                    'client_name' => $clientName,
                    'language' => $language,
                    'date' => $dateStr,
                    'job_type' => $invItem->job_type,
                    'surcharge' => (float) ($invItem->surcharge ?? 0),
                ];
            })->values();

            // Merge regular + DNA line items
            $allLineItems = $invoiceLineItems->merge($dnaLineItems)->values()->all();

            $payslip = \App\Payslip::create([
                'payslip_number' => $payslipNumber,
                'agent_id' => $agentId,
                'agent_invoice_id' => $firstInvoiceId ?: null,
                'period_start' => $start,
                'period_end' => $end,
                'items' => $allLineItems,
                'subtotal' => round($subtotal, 2),
                'adjustments_total' => 0.00,
                'total' => round($total, 2),
                'status' => 'pending',
            ]);

            \App\AgentInvoiceItem::whereIn('id', $itemIds->all())
                ->update(['payslip_id' => $payslip->id]);

            DB::commit();
        } catch (\Throwable $t) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create payslip');
        }

        return redirect()->route('payslips.show', $payslip->id)
            ->with('success', 'Payslip created successfully');
    }

    public function show(Payslip $payslip)
    {
        $this->authorize('view', $payslip);
        $payslip->load(['agent.user', 'invoice']);
        $snapshot = is_array($payslip->bank_snapshot) ? $payslip->bank_snapshot : [];
        $account = BankAccount::where('user_id', $payslip->agent->user_id)->first();
        $user = auth()->user();
        $isOwnerAgent = $user && \App\Helpers\RoleHelper::hasRole($user, 'agent') && ((int) $payslip->agent->user_id === (int) $user->id);
        $isAdmin = $user && \App\Helpers\RoleHelper::hasRole($user, 'admin');
        $canSeeFullBank = $isOwnerAgent || $isAdmin;
        $bankDetails = [
            'bank_name' => $snapshot['bank_name'] ?? optional($account)->bank_name ?? '',
            'account_holder' => $snapshot['account_holder'] ?? optional($account)->account_holder ?? data_get($payslip, 'agent.user.full_name', ''),
            'account_number' => $canSeeFullBank ? (optional($account)->account_number_full ?? '') : ($snapshot['account_number'] ?? optional($account)->account_number_masked ?? ''),
            'sort_code' => $canSeeFullBank ? (optional($account)->sort_code_full ?? '') : ($snapshot['sort_code'] ?? optional($account)->sort_code_masked ?? ''),
            'captured_at' => $snapshot['captured_at'] ?? null,
        ];

        $items = is_array($payslip->items) ? $payslip->items : [];
        $invoiceItemIds = collect($items)->pluck('invoice_item_id')->filter()->unique()->values()->all();
        $invoiceItemsMap = empty($invoiceItemIds) ? collect() : \App\AgentInvoiceItem::whereIn('id', $invoiceItemIds)->get()->keyBy('id');
        $interpreterIds = [];
        $translatorIds = [];
        foreach ($items as $i) {
            $invId = (int) ($i['invoice_item_id'] ?? 0);
            $jt = (string) ($i['job_type'] ?? '');
            if ($jt === '' && $invId) {
                $row = $invoiceItemsMap->get($invId);
                if ($row) {
                    $jt = (string) $row->job_type;
                }
            }
            $jid = (int) ($i['job_id'] ?? 0);
            if ($jid === 0) {
                $ref = (string) ($i['job_ref'] ?? '');
                $desc = (string) ($i['description'] ?? '');
                $parsed = 0;
                if ($ref !== '') {
                    if (preg_match('/^(\d+)/', $ref, $m)) {
                        $parsed = (int) $m[1];
                    }
                }
                if ($parsed === 0 && $desc !== '') {
                    if (preg_match('/\bJob\s+(\d+)(?:\/[^\s]+)?/i', $desc, $m)) {
                        $parsed = (int) $m[1];
                    } elseif (preg_match('/^(\d+)/', $desc, $m)) {
                        $parsed = (int) $m[1];
                    }
                }
                if ($parsed > 0) {
                    $jid = $parsed;
                }
            }
            if ($jid > 0) {
                if (strpos($jt, 'InterpreterJob') !== false) {
                    $interpreterIds[] = $jid;
                } elseif (strpos($jt, 'TranslatorJob') !== false) {
                    $translatorIds[] = $jid;
                } else {
                    $interpreterIds[] = $jid;
                    $translatorIds[] = $jid;
                }
            }
        }
        $interpreterIds = collect($interpreterIds)->unique()->values()->all();
        $translatorIds = collect($translatorIds)->unique()->values()->all();
        $interpreterJobsMap = empty($interpreterIds) ? collect() : \App\InterpreterJob::with(['toLanguage'])->whereIn('id', $interpreterIds)->get()->keyBy('id');
        $translatorJobsMap = empty($translatorIds) ? collect() : \App\TranslatorJob::with(['fromLanguage', 'toLanguage'])->whereIn('id', $translatorIds)->get()->keyBy('id');
        $itemsDisplay = array_map(function ($item) use ($interpreterJobsMap, $translatorJobsMap, $invoiceItemsMap) {
            $jt = (string) ($item['job_type'] ?? '');
            $jid = (int) ($item['job_id'] ?? 0);
            $invId = (int) ($item['invoice_item_id'] ?? 0);
            if ($jt === '' && $invId) {
                $row = $invoiceItemsMap->get($invId);
                if ($row) {
                    $jt = (string) $row->job_type;
                }
            }
            if ($jid === 0) {
                $ref = (string) ($item['job_ref'] ?? '');
                $desc = (string) ($item['description'] ?? '');
                $parsed = 0;
                if ($ref !== '') {
                    if (preg_match('/^(\d+)/', $ref, $m)) {
                        $parsed = (int) $m[1];
                    }
                }
                if ($parsed === 0 && $desc !== '') {
                    if (preg_match('/\bJob\s+(\d+)(?:\/[^\s]+)?/i', $desc, $m)) {
                        $parsed = (int) $m[1];
                    } elseif (preg_match('/^(\d+)/', $desc, $m)) {
                        $parsed = (int) $m[1];
                    }
                }
                if ($parsed > 0) {
                    $jid = $parsed;
                }
            }
            $dateVal = $item['date'] ?? null;
            $langVal = $item['language'] ?? null;
            $durationVal = null;
            $typeLabel = 'N/A';
            $job = null;
            $isInterpreter = false;

            if (strpos($jt, 'InterpreterJob') !== false || strpos($jt, 'TranslatorJob') !== false) {
                $isInterpreter = strpos($jt, 'InterpreterJob') !== false;
                $typeLabel = $isInterpreter ? 'Interpreter' : 'Translator';
                $job = $isInterpreter ? ($interpreterJobsMap->get($jid) ?: $translatorJobsMap->get($jid))
                    : ($translatorJobsMap->get($jid) ?: $interpreterJobsMap->get($jid));
                if (!$dateVal) {
                    $dateVal = $isInterpreter ? optional($job)->appointment_date : optional($job)->target_date;
                }
                if (!$langVal) {
                    $langVal = $isInterpreter
                        ? optional(optional($job)->toLanguage)->name
                        : trim(((string) optional(optional($job)->fromLanguage)->name) . ' → ' . ((string) optional(optional($job)->toLanguage)->name));
                }
                if ($isInterpreter && $job) {
                    $h = (int) ($job->duration_hours ?? 0);
                    $m = (int) ($job->duration_minutes ?? 0);
                    if ($h > 0 || $m > 0) {
                        $durationVal = $h . ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
                    }
                }
            } else {
                $job = $interpreterJobsMap->get($jid) ?: $translatorJobsMap->get($jid);
                if ($job) {
                    $jobClass = get_class($job);
                    $isInterpreter = (strpos($jobClass, 'InterpreterJob') !== false);
                    $typeLabel = $isInterpreter ? 'Interpreter' : 'Translator';
                    if (!$dateVal) {
                        $dateVal = $isInterpreter ? optional($job)->appointment_date : optional($job)->target_date;
                    }
                    if (!$langVal) {
                        $langVal = $isInterpreter
                            ? optional(optional($job)->toLanguage)->name
                            : trim(((string) optional(optional($job)->fromLanguage)->name) . ' → ' . ((string) optional(optional($job)->toLanguage)->name));
                    }
                    if ($isInterpreter) {
                        $h = (int) ($job->duration_hours ?? 0);
                        $m = (int) ($job->duration_minutes ?? 0);
                        if ($h > 0 || $m > 0) {
                            $durationVal = $h . ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
                        }
                    }
                }
            }

            if ((!$dateVal || !$langVal || $typeLabel === 'N/A') && $invId) {
                $row = $invoiceItemsMap->get($invId);
                if ($row) {
                    try {
                        $candidate = $row->job;
                    } catch (\Throwable $e) {
                        $candidate = null;
                    }
                    if ($candidate) {
                        $job = $candidate;
                        $isInterpreter = (strpos(get_class($job), 'InterpreterJob') !== false);
                        if ($typeLabel === 'N/A') {
                            $typeLabel = $isInterpreter ? 'Interpreter' : 'Translator';
                        }
                        if (!$dateVal) {
                            $dateVal = $isInterpreter ? optional($job)->appointment_date : optional($job)->target_date;
                        }
                        if (!$langVal) {
                            $langVal = $isInterpreter
                                ? optional(optional($job)->toLanguage)->name
                                : trim(((string) optional(optional($job)->fromLanguage)->name) . ' → ' . ((string) optional(optional($job)->toLanguage)->name));
                        }
                        if ($isInterpreter && !$durationVal) {
                            $h = (int) ($job->duration_hours ?? 0);
                            $m = (int) ($job->duration_minutes ?? 0);
                            if ($h > 0 || $m > 0) {
                                $durationVal = $h . ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
                            }
                        }
                    }
                }
            }

            $amount = (float) ($item['amount'] ?? ($item['total'] ?? $item['total_amount'] ?? (($item['base_amount'] ?? 0) + ($item['expenses_amount'] ?? 0) + ($item['surcharge'] ?? 0) + ($item['other_charges'] ?? 0))));
            $adjustment = (float) ($item['adjustment'] ?? 0);
            return [
                'date' => $dateVal,
                'language' => $langVal,
                'type' => $typeLabel,
                'description' => ($item['description'] ?? ($item['job_description'] ?? 'Item')),
                'amount' => $amount,
                'adjustment' => $adjustment,
                'total' => $amount + $adjustment,
                'duration' => $durationVal,
                'parking' => (float) ($item['surcharge'] ?? 0),
            ];
        }, $items);

        return view('payslips.show', compact('payslip', 'bankDetails', 'itemsDisplay'));
    }

    public function edit(Payslip $payslip)
    {
        $this->authorize('update', $payslip);
        // Only allow editing of pending payslips
        if ($payslip->status !== 'pending') {
            return redirect()->route('payslips.show', $payslip)
                ->with('error', 'Only pending payslips can be edited.');
        }

        $payslip->load(['agent.user', 'invoice']);
        $agents = \App\Agent::with('user')->get()->mapWithKeys(function ($agent) {
            return [$agent->id => $agent->user->full_name ?? 'Unknown'];
        });

        return view('payslips.edit', compact('payslip', 'agents'));
    }

    public function update(Request $request, Payslip $payslip)
    {
        $this->authorize('update', $payslip);
        if ($payslip->status !== 'pending') {
            return redirect()->route('payslips.show', $payslip)
                ->with('error', 'Only pending payslips can be updated.');
        }

        $request->validate([
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'items' => 'required|array',
            'items.*.description' => 'required|string',
            'items.*.amount' => 'required|numeric',
            'items.*.adjustment' => 'nullable|numeric',
        ]);

        $items = $request->input('items', []);
        $subtotal = 0.0;
        $adjustmentsTotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) ($item['amount'] ?? 0);
            $adjustmentsTotal += (float) ($item['adjustment'] ?? 0);
        }
        $total = $subtotal + $adjustmentsTotal;

        $payslip->update([
            'period_start' => $request->period_start,
            'period_end' => $request->period_end,
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'adjustments_total' => round($adjustmentsTotal, 2),
            'total' => round($total, 2),
        ]);

        return redirect()->route('payslips.show', $payslip)
            ->with('success', 'Payslip updated successfully.');
    }

    public function destroy(Payslip $payslip)
    {
        $this->authorize('delete', $payslip);
        if ($payslip->status !== 'pending') {
            return redirect()->route('payslips.show', $payslip)
                ->with('error', 'Only pending payslips can be deleted.');
        }

        DB::beginTransaction();
        try {
            \App\AgentInvoiceItem::where('payslip_id', $payslip->id)->update(['payslip_id' => null]);
            $payslip->delete();
            DB::commit();
        } catch (\Throwable $t) {
            DB::rollBack();
            return redirect()->route('payslips.show', $payslip)
                ->with('error', 'Failed to delete payslip.');
        }

        return redirect()->route('payslips.index')
            ->with('success', 'Payslip deleted successfully.');
    }

    public function approve(Payslip $payslip)
    {
        $this->authorize('approve', $payslip);
        if ($payslip->status !== 'pending') {
            return redirect()->route('payslips.show', $payslip)
                ->with('error', 'Only pending payslips can be approved.');
        }

        // Capture agent's bank details at time of approval using BankAccount (masked)
        $agent = $payslip->agent;
        $bankAccount = BankAccount::where('user_id', $agent->user_id)->first();
        $bankSnapshot = [
            'bank_name' => optional($bankAccount)->bank_name ?? '',
            'account_number' => optional($bankAccount)->account_number_masked ?? '',
            'sort_code' => optional($bankAccount)->sort_code_masked ?? '',
            'account_holder' => optional($bankAccount)->account_holder ?? data_get($agent, 'user.full_name', ''),
            'captured_at' => now()->toDateTimeString(),
        ];

        $payslip->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'bank_snapshot' => $bankSnapshot,
        ]);

        try {
            $payslip->loadMissing(['agent.user']);
            $user = optional(optional($payslip->agent)->user);
            $email = (string) optional($user)->email;
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $payslipNumber = (string) ($payslip->payslip_number ?? $payslip->id);
                $agentName = (string) (optional($user)->full_name ?? '');
                $url = route('payslips.show', $payslip->id);
                $subject = config('app.name') . ' - Payslip ' . $payslipNumber . ' approved';
                $body = trim(
                    ($agentName !== '' ? ('Hello ' . $agentName . ',') : 'Hello,')
                    . "\n\n"
                    . 'Your payslip ' . $payslipNumber . ' has been approved.'
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
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to notify agent on payslip approval: ' . $e->getMessage(), [
                'payslip_id' => $payslip->id,
            ]);
        }

        return redirect()->route('payslips.show', $payslip)
            ->with('success', 'Payslip approved successfully.');
    }

    public function pdf(Payslip $payslip)
    {
        $this->authorize('view', $payslip);
        $payslip->load(['agent.user', 'invoice']);
        // Build bank details with snapshot fallback to current BankAccount (masked)
        $snapshot = is_array($payslip->bank_snapshot) ? $payslip->bank_snapshot : [];
        $account = BankAccount::where('user_id', $payslip->agent->user_id)->first();
        $user = auth()->user();
        $isOwnerAgent = $user && \App\Helpers\RoleHelper::hasRole($user, 'agent') && ((int) $payslip->agent->user_id === (int) $user->id);
        $isAdmin = $user && \App\Helpers\RoleHelper::hasRole($user, 'admin');
        $canSeeFullBank = $isOwnerAgent || $isAdmin;
        $bankDetails = [
            'bank_name' => $snapshot['bank_name'] ?? optional($account)->bank_name ?? '',
            'account_holder' => $snapshot['account_holder'] ?? optional($account)->account_holder ?? data_get($payslip, 'agent.user.full_name', ''),
            'account_number' => $canSeeFullBank ? (optional($account)->account_number_full ?? '') : ($snapshot['account_number'] ?? optional($account)->account_number_masked ?? ''),
            'sort_code' => $canSeeFullBank ? (optional($account)->sort_code_full ?? '') : ($snapshot['sort_code'] ?? optional($account)->sort_code_masked ?? ''),
            'captured_at' => $snapshot['captured_at'] ?? null,
        ];
        $items = is_array($payslip->items) ? $payslip->items : [];
        $invoiceItemIds = collect($items)->pluck('invoice_item_id')->filter()->unique()->values()->all();
        $invoiceItemsMap = empty($invoiceItemIds) ? collect() : \App\AgentInvoiceItem::whereIn('id', $invoiceItemIds)->get()->keyBy('id');
        $interpreterIds = [];
        $translatorIds = [];
        foreach ($items as $i) {
            $invId = (int) ($i['invoice_item_id'] ?? 0);
            $jt = (string) ($i['job_type'] ?? '');
            if ($jt === '' && $invId) {
                $row = $invoiceItemsMap->get($invId);
                if ($row) {
                    $jt = (string) $row->job_type;
                }
            }
            $jid = (int) ($i['job_id'] ?? 0);
            if ($jid === 0) {
                $ref = (string) ($i['job_ref'] ?? '');
                $desc = (string) ($i['description'] ?? '');
                $parsed = 0;
                if ($ref !== '') {
                    if (preg_match('/^(\d+)/', $ref, $m)) {
                        $parsed = (int) $m[1];
                    }
                }
                if ($parsed === 0 && $desc !== '') {
                    if (preg_match('/\bJob\s+(\d+)(?:\/[^\s]+)?/i', $desc, $m)) {
                        $parsed = (int) $m[1];
                    } elseif (preg_match('/^(\d+)/', $desc, $m)) {
                        $parsed = (int) $m[1];
                    }
                }
                if ($parsed > 0) {
                    $jid = $parsed;
                }
            }
            if ($jid > 0) {
                if (strpos($jt, 'InterpreterJob') !== false) {
                    $interpreterIds[] = $jid;
                } elseif (strpos($jt, 'TranslatorJob') !== false) {
                    $translatorIds[] = $jid;
                } else {
                    $interpreterIds[] = $jid;
                    $translatorIds[] = $jid;
                }
            }
        }
        $interpreterIds = collect($interpreterIds)->unique()->values()->all();
        $translatorIds = collect($translatorIds)->unique()->values()->all();
        $interpreterJobsMap = empty($interpreterIds) ? collect() : \App\InterpreterJob::with(['toLanguage'])->whereIn('id', $interpreterIds)->get()->keyBy('id');
        $translatorJobsMap = empty($translatorIds) ? collect() : \App\TranslatorJob::with(['fromLanguage', 'toLanguage'])->whereIn('id', $translatorIds)->get()->keyBy('id');
        $itemsDisplay = array_map(function ($item) use ($interpreterJobsMap, $translatorJobsMap, $invoiceItemsMap) {
            $jt = (string) ($item['job_type'] ?? '');
            $jid = (int) ($item['job_id'] ?? 0);
            $invId = (int) ($item['invoice_item_id'] ?? 0);
            if ($jt === '' && $invId) {
                $row = $invoiceItemsMap->get($invId);
                if ($row) {
                    $jt = (string) $row->job_type;
                }
            }
            if ($jid === 0) {
                $ref = (string) ($item['job_ref'] ?? '');
                $desc = (string) ($item['description'] ?? '');
                $parsed = 0;
                if ($ref !== '') {
                    if (preg_match('/^(\d+)/', $ref, $m)) {
                        $parsed = (int) $m[1];
                    }
                }
                if ($parsed === 0 && $desc !== '') {
                    if (preg_match('/\bJob\s+(\d+)(?:\/[^\s]+)?/i', $desc, $m)) {
                        $parsed = (int) $m[1];
                    } elseif (preg_match('/^(\d+)/', $desc, $m)) {
                        $parsed = (int) $m[1];
                    }
                }
                if ($parsed > 0) {
                    $jid = $parsed;
                }
            }
            $typeLabel = $jt !== '' ? (strpos($jt, 'InterpreterJob') !== false ? 'Interpreter' : (strpos($jt, 'TranslatorJob') !== false ? 'Translator' : $jt)) : 'N/A';
            $dateVal = $item['date'] ?? null;
            $langVal = $item['language'] ?? null;
            $durationVal = null;
            $job = null;
            if (strpos($jt, 'InterpreterJob') !== false) {
                $job = $interpreterJobsMap->get($jid) ?: $translatorJobsMap->get($jid);
                if (!$dateVal) {
                    $dateVal = optional($job)->appointment_date;
                }
                if (!$langVal) {
                    $langVal = optional(optional($job)->toLanguage)->name;
                }
                if ($job) {
                    $h = (int) ($job->duration_hours ?? 0);
                    $m = (int) ($job->duration_minutes ?? 0);
                    if ($h > 0 || $m > 0) {
                        $durationVal = $h . ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
                    } elseif (!empty($job->start_time) && !empty($job->end_time)) {
                        try {
                            $start = \Carbon\Carbon::parse($job->start_time);
                            $end = \Carbon\Carbon::parse($job->end_time);
                            $mins = $end->diffInMinutes($start);
                            $durationVal = floor($mins / 60) . ':' . str_pad((string) ($mins % 60), 2, '0', STR_PAD_LEFT);
                        } catch (\Throwable $e) {
                        }
                    }
                }
            } elseif (strpos($jt, 'TranslatorJob') !== false) {
                $job = $translatorJobsMap->get($jid) ?: $interpreterJobsMap->get($jid);
                if (!$dateVal) {
                    $dateVal = optional($job)->target_date;
                }
                if (!$langVal) {
                    $langVal = trim(((string) optional(optional($job)->fromLanguage)->name) . ' → ' . ((string) optional(optional($job)->toLanguage)->name));
                }
            } else {
                $job = $interpreterJobsMap->get($jid) ?: $translatorJobsMap->get($jid);
                if ($job) {
                    $isInterpreter = property_exists($job, 'appointment_date') || method_exists($job, 'isCancelledSameDay');
                    if (!$dateVal) {
                        $dateVal = $isInterpreter ? optional($job)->appointment_date : optional($job)->target_date;
                    }
                    if (!$langVal) {
                        $langVal = $isInterpreter
                            ? optional(optional($job)->toLanguage)->name
                            : trim(((string) optional(optional($job)->fromLanguage)->name) . ' → ' . ((string) optional(optional($job)->toLanguage)->name));
                    }
                    if ($isInterpreter) {
                        $h = (int) ($job->duration_hours ?? 0);
                        $m = (int) ($job->duration_minutes ?? 0);
                        if ($h > 0 || $m > 0) {
                            $durationVal = $h . ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
                        }
                    }
                }
            }
            if ((!$dateVal || !$langVal || $typeLabel === 'N/A') && $invId) {
                $row = $invoiceItemsMap->get($invId);
                if ($row) {
                    try {
                        $candidate = $row->job;
                    } catch (\Throwable $e) {
                        $candidate = null;
                    }
                    if ($candidate) {
                        $job = $candidate;
                        $isInterpreter = (strpos(get_class($job), 'InterpreterJob') !== false);
                        if ($typeLabel === 'N/A') {
                            $typeLabel = $isInterpreter ? 'Interpreter' : 'Translator';
                        }
                        if (!$dateVal) {
                            $dateVal = $isInterpreter ? optional($job)->appointment_date : optional($job)->target_date;
                        }
                        if (!$langVal) {
                            $langVal = $isInterpreter
                                ? optional(optional($job)->toLanguage)->name
                                : trim(((string) optional(optional($job)->fromLanguage)->name) . ' → ' . ((string) optional(optional($job)->toLanguage)->name));
                        }
                        if ($isInterpreter && !$durationVal) {
                            $h = (int) ($job->duration_hours ?? 0);
                            $m = (int) ($job->duration_minutes ?? 0);
                            if ($h > 0 || $m > 0) {
                                $durationVal = $h . ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
                            }
                        }
                    }
                }
            }
            $amount = (float) ($item['amount'] ?? ($item['total'] ?? $item['total_amount'] ?? (($item['base_amount'] ?? 0) + ($item['expenses_amount'] ?? 0) + ($item['surcharge'] ?? 0) + ($item['other_charges'] ?? 0))));
            $adjustment = (float) ($item['adjustment'] ?? 0);
            $parking = null;
            try {
                $invItem = $invoiceItemsMap->get($invId);
                if ($invItem) {
                    $parking = (float) ($invItem->surcharge ?? 0);
                }
            } catch (\Throwable $e) {
            }
            if ($parking === null) {
                $parking = isset($item['surcharge']) ? (float) $item['surcharge'] : null;
            }
            return [
                'date' => $dateVal,
                'language' => $langVal,
                'type' => $typeLabel,
                'description' => ($item['description'] ?? ($item['job_description'] ?? 'Item')),
                'agent_invoice_document' => ($item['agent_invoice_document'] ?? null),
                'amount' => $amount,
                'adjustment' => $adjustment,
                'total' => $amount + $adjustment,
                'duration' => $durationVal,
                'parking' => $parking,
            ];
        }, $items);
        $pdf = Pdf::loadView('payslips.pdf', [
            'payslip' => $payslip,
            'bankDetails' => $bankDetails,
            'itemsDisplay' => $itemsDisplay,
        ]);
        // Stream the PDF inline so it opens in a new browser tab instead of downloading
        return $pdf->stream('payslip-' . $payslip->payslip_number . '.pdf');
    }

    public function markPaid(Payslip $payslip)
    {
        $this->authorize('markPaid', $payslip);
        $payslip->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        return redirect()->route('payslips.show', $payslip)->with('success', 'Payslip marked as paid.');
    }
}
