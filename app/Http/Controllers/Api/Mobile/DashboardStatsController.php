<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Payslip;
use App\AgentInvoice;
use App\InterpreterJob;
use App\TranslatorJob;
use App\AgentJobEntry;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardStatsController extends Controller
{
    /**
     * Return dashboard statistics for the authenticated agent.
     *
     * - total_earnings: Sum of paid payslips + paid agent invoices
     * - earnings_updated_at: When the last payslip/invoice was paid
     * - total_jobs_this_month: Jobs assigned this calendar month
     * - uninvoiced_jobs: Completed jobs without an AgentJobEntry
     * - success_rate: completed / (completed + cancelled + dna) %
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user || !$user->agent) {
                return response()->json(['success' => false, 'message' => 'Agent profile not found'], 404);
            }

            $agentId = $user->agent->id;
            $userId = $user->id;
            $now = Carbon::now();
            $monthStart = $now->copy()->startOfMonth()->toDateString();
            $monthEnd = $now->copy()->endOfMonth()->toDateString();

            // ── Total Earnings ──────────────────────────────────────────
            // Sum of paid payslips (status = 'paid')
            $payslipEarnings = Payslip::where('agent_id', $agentId)
                ->whereIn('status', ['paid', 'Paid', 'PAID'])
                ->sum('total') ?? 0;

            // Also sum paid AgentInvoice records (status = 'paid' or 3)
            $invoiceEarnings = AgentInvoice::where('agent_id', $agentId)
                ->whereIn('status', ['paid', 'Paid', 'PAID', 3])
                ->sum('total_amount') ?? 0;

            // Use payslip earnings if there are payslip records,
            // otherwise fall back to invoice earnings
            // If both exist, use payslip earnings as the canonical source
            $totalEarnings = (float) $payslipEarnings > 0
                ? (float) $payslipEarnings
                : (float) $invoiceEarnings;

            // If there are BOTH payslips and invoices marked paid,
            // use the larger value (the payslip amount should be the
            // canonical one, but some older invoices might already be paid)
            if ((float) $payslipEarnings > 0 && (float) $invoiceEarnings > 0) {
                $totalEarnings = max((float) $payslipEarnings, (float) $invoiceEarnings);
            }

            // Last updated timestamp
            $lastPaidPayslip = Payslip::where('agent_id', $agentId)
                ->whereIn('status', ['paid', 'Paid', 'PAID'])
                ->orderByDesc('paid_at')
                ->first(['paid_at']);

            $lastPaidInvoice = AgentInvoice::where('agent_id', $agentId)
                ->whereIn('status', ['paid', 'Paid', 'PAID', 3])
                ->orderByDesc('approved_at')
                ->first(['approved_at']);

            // Use the most recent date from either source
            $paidDate = optional($lastPaidPayslip)->paid_at;
            $invoiceDate = optional($lastPaidInvoice)->approved_at;
            $earningsUpdatedAt = null;
            if ($paidDate && $invoiceDate) {
                $earningsUpdatedAt = $paidDate->greaterThan($invoiceDate) ? $paidDate->toIso8601String() : $invoiceDate->toIso8601String();
            } elseif ($paidDate) {
                $earningsUpdatedAt = $paidDate->toIso8601String();
            } elseif ($invoiceDate) {
                $earningsUpdatedAt = $invoiceDate->toIso8601String();
            }

            // ── Total Jobs This Month ───────────────────────────────────
            $interpreterJobsThisMonth = InterpreterJob::where('agent_id', $agentId)
                ->whereBetween('appointment_date', [$monthStart, $monthEnd])
                ->count();

            $translatorJobsThisMonth = TranslatorJob::where('agent_id', $userId)
                ->whereBetween('target_date', [$monthStart, $monthEnd])
                ->count();

            $totalJobsThisMonth = $interpreterJobsThisMonth + $translatorJobsThisMonth;

            // ── Un-invoiced Jobs ────────────────────────────────────────
            $completedInterpreterIds = InterpreterJob::where('agent_id', $agentId)
                ->where('status', 4) // completed
                ->pluck('id')
                ->toArray();

            $invoicedJobIds = !empty($completedInterpreterIds)
                ? AgentJobEntry::where('agent_id', $agentId)
                    ->where('job_type', 'App\\InterpreterJob')
                    ->whereIn('job_id', $completedInterpreterIds)
                    ->pluck('job_id')
                    ->toArray()
                : [];

            $uninvoicedCount = count(array_diff($completedInterpreterIds, $invoicedJobIds));

            // ── Success Rate ────────────────────────────────────────────
            $completedCount = InterpreterJob::where('agent_id', $agentId)->where('status', 4)->count();
            $cancelledCount = InterpreterJob::where('agent_id', $agentId)->where('status', 2)->count();
            $dnaCount = InterpreterJob::where('agent_id', $agentId)->where('dna', true)->count();

            $finishedTotal = $completedCount + $cancelledCount + $dnaCount;
            $successRate = $finishedTotal > 0
                ? (int) round(($completedCount / $finishedTotal) * 100)
                : 100;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_earnings' => round($totalEarnings, 2),
                    'earnings_updated_at' => $earningsUpdatedAt,
                    'total_jobs_this_month' => $totalJobsThisMonth,
                    'uninvoiced_jobs' => $uninvoicedCount,
                    'success_rate' => $successRate,
                    // Debug info (can be removed later)
                    '_debug' => [
                        'payslip_earnings' => round((float) $payslipEarnings, 2),
                        'invoice_earnings' => round((float) $invoiceEarnings, 2),
                        'agent_id' => $agentId,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR dashboard-stats ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to load dashboard stats: ' . $e->getMessage(),
            ], 503);
        }
    }
}
