<?php

namespace App\Http\Controllers;

use App\User;
use App\Mail\UserCreatedMail;
use App\Http\Requests\AdminRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Payslip;
use App\Receipt;
use App\Remittance;
use App\ClientInvoice;
use App\AgentInvoice;
use App\PaymentProof;
use App\InterpreterJob;
use App\TranslatorJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $pendingPayslips = Payslip::where('status', 'pending')->count();
        $pendingRemittances = Remittance::where('status', 'pending')->count();
        // Use UK timezone for "today" boundaries
        $approvedToday = Payslip::where('status', 'approved')
            ->whereDate('updated_at', Carbon::today('Europe/London'))
            ->count() + 
            Remittance::where('status', 'approved')
            ->whereDate('updated_at', Carbon::today('Europe/London'))
            ->count();
        $totalProcessed = Payslip::whereIn('status', ['approved', 'rejected'])->count() + 
            Remittance::whereIn('status', ['approved', 'rejected'])->count();

        $recentPayslips = Payslip::with('agent')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $recentRemittances = Remittance::with('clientInvoice.client')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Financial KPIs
        $clientInvoiceStats = [
            'total' => 0,
            'sent' => 0,
            'paid' => 0,
            'overdue' => 0,
        ];
        $agentInvoiceStats = [
            'total' => 0,
            'sent' => 0,
            'paid' => 0,
        ];
        $pendingPaymentProofs = 0;

        try {
            $clientInvoiceStats['total'] = ClientInvoice::count();
            $clientInvoiceStats['sent'] = ClientInvoice::where('status', 'sent')->count();
            $clientInvoiceStats['paid'] = ClientInvoice::where('status', 'paid')->count();
            $clientInvoiceStats['overdue'] = ClientInvoice::where('status', 'sent')
                ->whereNotNull('invoice_date')
                ->whereDate('invoice_date', '<', Carbon::today('Europe/London'))
                ->count();
        } catch (\Throwable $e) { /* ignore */ }

        try {
            $agentInvoiceStats['total'] = AgentInvoice::count();
            // Map 'sent' and 'paid' to handle numeric legacy statuses via accessors if present
            $agentInvoiceStats['sent'] = AgentInvoice::where(function($q){
                $q->where('status', 'sent')->orWhere('status', 2);
            })->count();
            $agentInvoiceStats['paid'] = AgentInvoice::where(function($q){
                $q->where('status', 'paid')->orWhere('status', 3);
            })->count();
        } catch (\Throwable $e) { /* ignore */ }

        try {
            $pendingPaymentProofs = PaymentProof::where('status', 'pending')->count();
        } catch (\Throwable $e) { /* ignore */ }

        // Jobs overview and quick-assign data
        $interpreterJobStats = [
            'pending' => 0,
            'assigned' => 0,
            'cancelled' => 0,
            'rejected' => 0,
            'completed' => 0,
            'quoted' => 0,
        ];

        $recentInterpreterJobs = collect();
        $jobsNeedingAssignment = collect();
        $urgentUnassigned = 0;

        try {
            $interpreterJobStats['pending'] = InterpreterJob::where('status', 0)->count();
            $interpreterJobStats['assigned'] = InterpreterJob::where('status', 1)->count();
            $interpreterJobStats['cancelled'] = InterpreterJob::where('status', 2)->count();
            $interpreterJobStats['rejected'] = InterpreterJob::where('status', 3)->count();
            $interpreterJobStats['completed'] = InterpreterJob::where('status', 4)->count();
            $interpreterJobStats['quoted'] = InterpreterJob::where('status', 5)->count();

            // Recent interpreter jobs for quick overview
            $recentInterpreterJobs = InterpreterJob::with(['client.user', 'toLanguage', 'timesheet'])
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();

            // Jobs needing assignment prioritised by earliest appointment date/time (closest to "now")
            // Window can be adjusted via ?window_days=, defaults to 30 (capped between 1 and 180)
            $windowDays = (int) $request->query('window_days', 30);
            if ($windowDays < 1) { $windowDays = 30; }
            if ($windowDays > 180) { $windowDays = 180; }

            $nowLondon = Carbon::now('Europe/London');
            $jobsNeedingAssignment = InterpreterJob::with(['client.user', 'toLanguage'])
                ->where('status', 0)
                // Only consider upcoming appointments within the window to keep it actionable
                ->whereDate('appointment_date', '>=', $nowLondon->copy()->startOfDay())
                ->whereDate('appointment_date', '<=', $nowLondon->copy()->addDays($windowDays))
                ->orderBy('appointment_date', 'ASC')
                ->orderBy('start_time', 'ASC')
                ->limit(8)
                ->get();

            // Approximate urgent window: appointments within next 48 hours and not yet assigned
            $urgentUnassigned = InterpreterJob::where('status', 0)
                ->whereDate('appointment_date', '>=', Carbon::today('Europe/London'))
                ->whereDate('appointment_date', '<=', Carbon::today('Europe/London')->addDays(2))
                ->count();
        } catch (\Throwable $e) { /* ignore */ }

        // Recent activity logs (limit for dashboard)
        $recentActivity = collect();
        try {
            $recentActivity = \App\AuditLog::with(['actor'])
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();
        } catch (\Throwable $e) { /* ignore */ }

        return view('admin.dashboard', compact(
            'pendingPayslips',
            'pendingRemittances', 
            'approvedToday',
            'totalProcessed',
            'recentPayslips',
            'recentRemittances',
            'clientInvoiceStats',
            'agentInvoiceStats',
            'pendingPaymentProofs',
            // jobs overview
            'interpreterJobStats',
            'recentInterpreterJobs',
            'jobsNeedingAssignment',
            'urgentUnassigned',
            'recentActivity'
        ));
    }

    /**
     * JSON data for Jobs Needing Assignment, ordered by nearest upcoming appointment date/time.
     * Provides lightweight payload for periodic refresh on the Admin dashboard.
     */
    public function jobsNeedingAssignmentData(Request $request)
    {
        if (!\App\Helpers\RoleHelper::hasRole(auth()->user(), 'admin')) {
            abort(403);
        }

        $limit = (int) $request->query('limit', 8);
        if ($limit < 1) { $limit = 8; }
        if ($limit > 100) { $limit = 100; }

        $windowDays = (int) $request->query('window_days', 30);
        if ($windowDays < 1) { $windowDays = 30; }
        if ($windowDays > 180) { $windowDays = 180; }

        $nowLondon = \Carbon\Carbon::now('Europe/London');
        $jobs = \App\InterpreterJob::with(['client.user', 'toLanguage'])
            ->where('status', 0)
            ->whereDate('appointment_date', '>=', $nowLondon->copy()->startOfDay())
            ->whereDate('appointment_date', '<=', $nowLondon->copy()->addDays($windowDays))
            ->orderBy('appointment_date', 'ASC')
            ->orderBy('start_time', 'ASC')
            ->limit($limit)
            ->get();

        $data = $jobs->map(function ($job) {
            return [
                'id' => $job->id,
                'reference' => $job->reference,
                'client_name' => optional(optional($job->client)->user)->getFullName() ?? 'N/A',
                'language' => optional($job->toLanguage)->name ?? 'N/A',
                'appointment_date_raw' => $job->appointment_date ? $job->appointment_date->toDateString() : null,
                'appointment_date' => $job->appointment_date ? $job->appointment_date->format('d/m/Y') : 'N/A',
                'start_time' => $job->start_time ?? 'N/A',
                'status' => $job->statusName ?? 'Pending',
                'assign_url' => route('interpreter-jobs.matched.index', $job->id),
                'view_url' => route('interpreter-jobs.show', $job->id),
            ];
        });

        return response()->json([
            'now' => $nowLondon->toDateTimeString(),
            'count' => $data->count(),
            'jobs' => $data,
        ]);
    }

    /**
     * Full Activity Log page with filters and pagination
     */
    public function activity(Request $request)
    {
        // Restrict to admins
        if (!\App\Helpers\RoleHelper::hasRole(auth()->user(), 'admin')) {
            abort(403);
        }

        $query = \App\AuditLog::with(['actor', 'user']);

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }
        if ($entityType = $request->query('entity_type')) {
            $query->where('entity_type', $entityType);
        }
        if ($actorId = $request->query('actor_id')) {
            $query->where('actor_id', (int)$actorId);
        }
        if ($userId = $request->query('user_id')) {
            $query->where('user_id', (int)$userId);
        }
        if ($startDate = $request->query('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate = $request->query('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        // Extra filters: HTTP method/status/path on JSON metadata
        if ($method = $request->query('method')) {
            $query->where('metadata->method', $method);
        }
        if ($status = $request->query('status')) {
            $query->where('metadata->status', (int)$status);
        }
        if ($path = $request->query('path')) {
            $query->where('metadata->uri', 'like', '%'.$path.'%');
        }

        $logs = $query->orderByDesc('created_at')->paginate(25)->appends($request->query());

        // Provide filter options from distinct values
        $actions = \App\AuditLog::select('action')->distinct()->orderBy('action')->pluck('action');
        $entities = \App\AuditLog::select('entity_type')->whereNotNull('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type');
        $actors = \App\User::select(['id','first_name','last_name','email'])->orderBy('first_name')->get();

        return view('admin.activity.index', [
            'logs' => $logs,
            'filters' => [
                'action' => $action,
                'entity_type' => $entityType,
                'actor_id' => $actorId,
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'method' => $method,
                'status' => $status,
                'path' => $path,
            ],
            'actions' => $actions,
            'entities' => $entities,
            'actors' => $actors,
        ]);
    }

    public function index()
    {
        return view('admins.index', [
            // Eager-load roles to avoid N+1 queries in getUserLevel()
            'admins' => User::role(['admin'])
                ->with('roles')
                ->paginate(10)
        ]);
    }

    public function create()
    {
        return view('admins.create');
    }

    public function store(AdminRequest $request)
    {
        $user = new User();

        $user
            ->fill($request->validated())
            ->fill(['enabled' => true, 'password' => Str::random(60)])
            ->save();

        $user->assignRole('admin');

        Mail::to($user)->send(new UserCreatedMail(
            app('auth.password.broker')->createToken($user),
            $user,
            'emails.users.created',
            'Account created'
        ));

        return redirect()->route('admins.index')->with('success', 'Admin created');
    }

    public function edit(User $admin)
    {
        return view('admins.edit', [
            'admin' => $admin,
        ]);
    }

    public function update(AdminRequest $request, User $admin)
    {
        $admin->update($request->validated());

        return back()->with('success', 'Admin updated');
    }

    public function destroy(User $admin)
    {
        $admin->delete();

        return redirect()->route('admins.index')->with('success', 'Admin deleted');
    }

    public function impersonate(User $user)
    {
        try {
            // Log the impersonation attempt
            Log::info('Admin impersonation started', [
                'admin_id' => auth()->id(),
                'admin_email' => auth()->user()->email,
                'target_user_id' => $user->id,
                'target_user_email' => $user->email,
                'target_user_roles' => \Illuminate\Support\Facades\DB::table('model_has_roles')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->where('model_has_roles.model_id', $user->id)
                    ->where('model_has_roles.model_type', get_class($user))
                    ->pluck('roles.name')
                    ->toArray()
            ]);
            // Ensure the authenticated user instance is the concrete App\User model
            /** @var \App\User $authUser */
            $authUser = User::findOrFail(auth()->id());
            $authUser->setImpersonating($user->id);
            
            return redirect()->route('index');
        } catch (\Exception $e) {
            Log::error('Impersonation failed', [
                'admin_id' => auth()->id(),
                'target_user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('error', 'Failed to impersonate user. Please try again.');
        }
    }

    public function stopImpersonate()
    {
        try {
            if (!auth()->check()) {
                Log::warning('Stop impersonation attempted without auth');
                return redirect()->route('login');
            }

            /** @var \App\User $authUser */
            $authUser = User::findOrFail(auth()->id());
            $wasImpersonating = session()->has('impersonate');
            $authUser->stopImpersonating();

            Log::info('Admin impersonation stopped', [
                'admin_id' => $authUser->id,
                'admin_email' => $authUser->email,
                'was_impersonating' => $wasImpersonating,
            ]);

            return redirect()->route('index');
        } catch (\Throwable $e) {
            Log::error('Stop impersonation failed', [
                'admin_id' => optional(auth()->user())->id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', 'Failed to stop impersonation. Please refresh and try again.');
        }
    }

    // Admin Payslip Management
    public function payslips(\Illuminate\Http\Request $request)
    {
        $query = Payslip::with(['agent.user', 'invoice']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->input('agent_id'));
        }
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $payslips = $query->orderBy('created_at', 'desc')->paginate(15);

        $stats = [
            'pending' => Payslip::where('status', 'pending')->count(),
            'approved' => Payslip::where('status', 'approved')->count(),
            'paid' => Payslip::where('status', 'paid')->count(),
            'rejected' => Payslip::where('status', 'rejected')->count(),
        ];

        $agents = \App\Agent::with('user')->get();

        return view('admin.payslips.index', compact('payslips', 'stats', 'agents'));
    }

    public function showPayslip(Payslip $payslip)
    {
        $payslip->load(['agent.user', 'invoice']);
        // Build bank details with snapshot fallback to current BankAccount (masked)
        $snapshot = is_array($payslip->bank_snapshot) ? $payslip->bank_snapshot : [];
        $account = \App\BankAccount::where('user_id', $payslip->agent->user_id)->first();

        $bankDetails = [
            'bank_name' => $snapshot['bank_name'] ?? optional($account)->bank_name ?? '',
            'account_holder' => $snapshot['account_holder'] ?? optional($account)->account_holder ?? data_get($payslip, 'agent.user.full_name', ''),
            'account_number' => $snapshot['account_number'] ?? optional($account)->account_number_full ?? '',
            'sort_code' => $snapshot['sort_code'] ?? optional($account)->sort_code_full ?? '',
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
                if ($row) { $jt = (string) $row->job_type; }
            }
            $jid = (int) ($i['job_id'] ?? 0);
            if ($jid === 0) {
                $ref = (string) ($i['job_ref'] ?? '');
                $desc = (string) ($i['description'] ?? '');
                $parsed = 0;
                if ($ref !== '') {
                    if (preg_match('/^(\d+)/', $ref, $m)) { $parsed = (int) $m[1]; }
                }
                if ($parsed === 0 && $desc !== '') {
                    if (preg_match('/\bJob\s+(\d+)(?:\/[^\s]+)?/i', $desc, $m)) { $parsed = (int) $m[1]; }
                    elseif (preg_match('/^(\d+)/', $desc, $m)) { $parsed = (int) $m[1]; }
                }
                if ($parsed > 0) { $jid = $parsed; }
            }
            if ($jid > 0) {
                if (strpos($jt, 'InterpreterJob') !== false) { $interpreterIds[] = $jid; }
                elseif (strpos($jt, 'TranslatorJob') !== false) { $translatorIds[] = $jid; }
                else { $interpreterIds[] = $jid; $translatorIds[] = $jid; }
            }
        }
        $interpreterIds = collect($interpreterIds)->unique()->values()->all();
        $translatorIds = collect($translatorIds)->unique()->values()->all();
        $interpreterJobsMap = empty($interpreterIds) ? collect() : \App\InterpreterJob::with(['toLanguage'])->whereIn('id', $interpreterIds)->get()->keyBy('id');
        $translatorJobsMap = empty($translatorIds) ? collect() : \App\TranslatorJob::with(['fromLanguage','toLanguage'])->whereIn('id', $translatorIds)->get()->keyBy('id');
        $itemsDisplay = array_map(function($item) use ($interpreterJobsMap, $translatorJobsMap, $invoiceItemsMap) {
            $jt = (string) ($item['job_type'] ?? '');
            $jid = (int) ($item['job_id'] ?? 0);
            $invId = (int) ($item['invoice_item_id'] ?? 0);
            if ($jt === '' && $invId) {
                $row = $invoiceItemsMap->get($invId);
                if ($row) { $jt = (string) $row->job_type; }
            }
            if ($jid === 0) {
                $ref = (string) ($item['job_ref'] ?? '');
                $desc = (string) ($item['description'] ?? '');
                $parsed = 0;
                if ($ref !== '') {
                    if (preg_match('/^(\d+)/', $ref, $m)) { $parsed = (int) $m[1]; }
                }
                if ($parsed === 0 && $desc !== '') {
                    if (preg_match('/\bJob\s+(\d+)(?:\/[^\s]+)?/i', $desc, $m)) { $parsed = (int) $m[1]; }
                    elseif (preg_match('/^(\d+)/', $desc, $m)) { $parsed = (int) $m[1]; }
                }
                if ($parsed > 0) { $jid = $parsed; }
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
                if (!$dateVal) { $dateVal = $isInterpreter ? optional($job)->appointment_date : optional($job)->target_date; }
                if (!$langVal) {
                    $langVal = $isInterpreter
                        ? optional(optional($job)->toLanguage)->name
                        : trim(((string) optional(optional($job)->fromLanguage)->name) . ' → ' . ((string) optional(optional($job)->toLanguage)->name));
                }
                if ($isInterpreter && $job) {
                    $h = (int) ($job->duration_hours ?? 0);
                    $m = (int) ($job->duration_minutes ?? 0);
                    if ($h > 0 || $m > 0) {
                        $durationVal = $h . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
                    }
                }
            } else {
                $job = $interpreterJobsMap->get($jid) ?: $translatorJobsMap->get($jid);
                if ($job) {
                    $jobClass = get_class($job);
                    $isInterpreter = (strpos($jobClass, 'InterpreterJob') !== false);
                    $typeLabel = $isInterpreter ? 'Interpreter' : 'Translator';
                    if (!$dateVal) { $dateVal = $isInterpreter ? optional($job)->appointment_date : optional($job)->target_date; }
                    if (!$langVal) {
                        $langVal = $isInterpreter
                            ? optional(optional($job)->toLanguage)->name
                            : trim(((string) optional(optional($job)->fromLanguage)->name) . ' → ' . ((string) optional(optional($job)->toLanguage)->name));
                    }
                    if ($isInterpreter) {
                        $h = (int) ($job->duration_hours ?? 0);
                        $m = (int) ($job->duration_minutes ?? 0);
                        if ($h > 0 || $m > 0) {
                            $durationVal = $h . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
                        }
                    }
                }
            }

            if ((!$dateVal || !$langVal || $typeLabel === 'N/A') && $invId) {
                $row = $invoiceItemsMap->get($invId);
                if ($row) {
                    try { $candidate = $row->job; } catch (\Throwable $e) { $candidate = null; }
                    if ($candidate) {
                        $job = $candidate;
                        $isInterpreter = (strpos(get_class($job), 'InterpreterJob') !== false);
                        if ($typeLabel === 'N/A') { $typeLabel = $isInterpreter ? 'Interpreter' : 'Translator'; }
                        if (!$dateVal) { $dateVal = $isInterpreter ? optional($job)->appointment_date : optional($job)->target_date; }
                        if (!$langVal) {
                            $langVal = $isInterpreter
                                ? optional(optional($job)->toLanguage)->name
                                : trim(((string) optional(optional($job)->fromLanguage)->name) . ' → ' . ((string) optional(optional($job)->toLanguage)->name));
                        }
                        if ($isInterpreter && !$durationVal) {
                            $h = (int) ($job->duration_hours ?? 0);
                            $m = (int) ($job->duration_minutes ?? 0);
                            if ($h > 0 || $m > 0) {
                                $durationVal = $h . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
                            }
                        }
                    }
                }
            }

            $amount = (float)($item['amount'] ?? ($item['total'] ?? $item['total_amount'] ?? (($item['base_amount'] ?? 0) + ($item['expenses_amount'] ?? 0) + ($item['surcharge'] ?? 0) + ($item['other_charges'] ?? 0))));
            $adjustment = (float)($item['adjustment'] ?? 0);
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

    public function updatePayslipStatus(Request $request, Payslip $payslip)
    {
        // Only admins can update payslip status via this endpoint
        if (!\App\Helpers\RoleHelper::hasRole(auth()->user(), 'admin')) {
            abort(403);
        }
        $request->validate([
            'status' => 'required|in:approved,rejected,paid',
            'notes' => 'nullable|string|max:1000'
        ]);

        $updateData = [
            'status' => $request->status,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ];

        if ($request->status === 'paid') {
            $updateData['paid_at'] = now();
        } else {
            $updateData['paid_at'] = null;
        }

        if ($request->notes) {
            $updateData['admin_notes'] = $request->notes;
        }

        // Capture agent's bank details at time of approval/paid using BankAccount (masked in snapshot)
        if (in_array($request->status, ['approved', 'paid'])) {
            $agent = $payslip->agent;
            $bankAccount = \App\BankAccount::where('user_id', $agent->user_id)->first();
            $bankSnapshot = [
                'bank_name' => optional($bankAccount)->bank_name ?? '',
                'account_number' => optional($bankAccount)->account_number_masked ?? '',
                'sort_code' => optional($bankAccount)->sort_code_masked ?? '',
                'account_holder' => optional($bankAccount)->account_holder ?? data_get($agent, 'user.full_name', ''),
                'captured_at' => now()->toDateTimeString(),
            ];
            $updateData['bank_snapshot'] = $bankSnapshot;

            // Audit log bank snapshot access
            try {
                \App\AuditLog::create([
                    'user_id' => $agent->user_id,
                    'actor_id' => auth()->id(),
                    'action' => 'snapshot',
                    'entity_type' => 'Payslip',
                    'entity_id' => $payslip->id,
                    'metadata' => [ 'status' => $request->status ],
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Throwable $e) { /* ignore audit failures */ }
        }

        DB::transaction(function () use ($payslip, $updateData) {
            $payslip->update($updateData);
        });

        $this->notifyAgentPayslipStatus($payslip, (string) $request->status);

        return response()->json([
            'success' => true,
            'message' => 'Payslip status updated successfully.'
        ]);
    }

    private function mapAgentInvoiceStatusValue($value)
    {
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
        }

        if (is_numeric($value)) {
            $rev = array_flip($map);
            $key = (int) $value;
            return $rev[$key] ?? 'draft';
        }

        return (string) $value;
    }

    /**
     * Recall an approved payslip back to pending so it can be edited and re-approved.
     */
    public function recallPayslip(Request $request, Payslip $payslip)
    {
        $this->authorize('recall', $payslip);

        // Optional reason for audit purposes
        $reason = (string) $request->input('reason');

        // Reset approval-related fields and status
        $payslip->update([
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
            'paid_at' => null,
            'payment_reference' => null,
            'payment_note' => null,
            'bank_snapshot' => null,
        ]);

        $this->notifyAgentPayslipStatus($payslip, 'pending');

        // Lightweight audit log if available
        try {
            \App\AuditLog::create([
                'user_id' => auth()->id(),
                'actor_id' => auth()->id(),
                'action' => 'recall',
                'entity_type' => 'Payslip',
                'entity_id' => $payslip->id,
                'metadata' => [ 'reason' => $reason ],
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $e) { /* ignore audit failures */ }

        return redirect()
            ->route('payslips.edit', $payslip)
            ->with('success', 'Payslip recalled to pending. You can now edit and re-approve it.');
    }

    private function notifyAgentPayslipStatus(Payslip $payslip, string $status): void
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

            $subject = config('app.name') . ' - Payslip ' . $payslipNumber . ' ' . $status;
            $body = trim(
                ($agentName !== '' ? ('Hello ' . $agentName . ',') : 'Hello,')
                . "\n\n"
                . 'Your payslip ' . $payslipNumber . ' status is now: ' . $status . '.'
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
            Log::warning('Failed to notify agent payslip status: ' . $e->getMessage(), [
                'payslip_id' => $payslip->id,
                'status' => $status,
            ]);
        }
    }

    // Admin Remittance Management
    public function remittances(Request $request)
    {
        // Filters: status, client, date range
        $status = $request->query('status');
        $clientId = $request->query('client_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = Remittance::with([
            'clientInvoice.client.user',
            'clientInvoice.items',
            'clientInvoice.paymentProofs',
            'clientInvoice.receipts',
            'uploadedBy',
            'approvedBy'
        ]);

        if (!empty($status)) {
            $query->where('status', $status);
        }
        if (!empty($clientId)) {
            $query->whereHas('clientInvoice', function ($q) use ($clientId) {
                $q->where('client_id', $clientId);
            });
        }
        if (!empty($startDate)) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if (!empty($endDate)) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $remittances = $query->orderBy('created_at', 'desc')->paginate(15)->appends($request->query());

        // Client options for filter dropdown
        $clients = \App\Client::with('user')->orderBy('id', 'asc')->get();

        $stats = [
            'pending' => Remittance::where('status', 'pending')->count(),
            'approved' => Remittance::where('status', 'approved')->count(),
            'processed' => Remittance::where('status', 'processed')->count(),
            'rejected' => Remittance::where('status', 'rejected')->count(),
        ];

        return view('admin.remittances.index', compact('remittances', 'stats', 'clients'));
    }

    /**
     * Admin Receipts listing with filters (client, date range)
     */
    public function receipts(Request $request)
    {
        $clientId = $request->query('client_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = Receipt::with(['clientInvoice.client.user', 'clientInvoice.company']);

        if (!empty($clientId)) {
            $query->whereHas('clientInvoice', function ($q) use ($clientId) {
                $q->where('client_id', $clientId);
            });
        }
        if (!empty($startDate)) {
            $query->whereDate('issued_at', '>=', $startDate);
        }
        if (!empty($endDate)) {
            $query->whereDate('issued_at', '<=', $endDate);
        }

        $receipts = $query->orderByDesc('issued_at')->paginate(15)->appends($request->query());

        // Client options for filter dropdown
        $clients = \App\Client::with('user')->orderBy('id', 'asc')->get();

        return view('admin.receipts.index', compact('receipts', 'clients'));
    }

    public function updateRemittanceStatus(Request $request, Remittance $remittance)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,processed',
            'notes' => 'nullable|string|max:1000',
            'processed_date' => 'required_if:status,processed|nullable|date',
            'reference_number' => 'required_if:status,processed|nullable|string|max:255'
        ]);

        $updateData = [
            'status' => $request->status,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ];

        if ($request->notes) {
            $updateData['admin_notes'] = $request->notes;
        }

        if ($request->status === 'processed') {
            $updateData['processed_date'] = $request->processed_date;
            $updateData['reference_number'] = $request->reference_number;
        }

        $remittance->update($updateData);

        // Audit remittance status change (ignore failures)
        try {
            \App\AuditLog::create([
                'user_id' => optional(auth()->user())->id,
                'actor_id' => optional(auth()->user())->id,
                'action' => 'remittance_status_updated',
                'entity_type' => 'Remittance',
                'entity_id' => $remittance->id,
                'metadata' => [
                    'status' => $request->status,
                    'notes' => $request->notes,
                    'processed_date' => $request->processed_date,
                    'reference_number' => $request->reference_number,
                ],
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $e) { /* ignore */ }

        return response()->json([
            'success' => true,
            'message' => 'Remittance status updated successfully.'
        ]);
    }
}
