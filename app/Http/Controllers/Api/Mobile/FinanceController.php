<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\AgentInvoice;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    /**
     * Show finance summary for the authenticated agent
     *
     * Returns aggregated financial data including total earnings,
     * pending payments, and recent invoice summaries.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || !$user->agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agent profile not found',
                ], 404);
            }

            $agentId = $user->agent->id;

            // Get invoice stats using AgentInvoice model
            $invoices = AgentInvoice::where('agent_id', $agentId);

            $totalEarnings = (clone $invoices)->whereIn('status', ['paid', 3])->sum('total_amount') ?? 0;
            $pendingAmount = (clone $invoices)->whereIn('status', ['pending', 1])->sum('total_amount') ?? 0;
            $totalInvoices = (clone $invoices)->count();

            // Recent invoices (last 5)
            $recentInvoices = AgentInvoice::where('agent_id', $agentId)
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get()
                ->map(function ($inv) {
                    return [
                        'id' => $inv->id,
                        'reference' => $inv->reference ?? $inv->invoice_number ?? "INV-{$inv->id}",
                        'total' => (float) ($inv->total ?? 0),
                        'status' => $inv->status ?? 'unknown',
                        'created_at' => optional($inv->created_at)->toIso8601String(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'total_earnings' => (float) $totalEarnings,
                    'pending_amount' => (float) $pendingAmount,
                    'total_invoices' => (int) $totalInvoices,
                    'recent_invoices' => $recentInvoices,
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR finances.index ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to load financial data',
            ], 503);
        }
    }

    /**
     * Show a specific invoice
     */
    public function showInvoice(Request $request, $id)
    {
        try {
            $user = $request->user();
            $invoice = AgentInvoice::find($id);

            if (!$invoice) {
                return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
            }

            // Verify the invoice belongs to this agent
            if ($user->agent && (int) $invoice->agent_id !== (int) $user->agent->id) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $invoice->id,
                    'reference' => $invoice->reference ?? $invoice->invoice_number ?? "INV-{$invoice->id}",
                    'total' => (float) ($invoice->total ?? 0),
                    'status' => $invoice->status ?? 'unknown',
                    'created_at' => optional($invoice->created_at)->toIso8601String(),
                    'paid_at' => optional($invoice->paid_at)->toIso8601String(),
                    'items' => $invoice->items ?? [],
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR finances.showInvoice ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to load invoice',
            ], 503);
        }
    }
}
