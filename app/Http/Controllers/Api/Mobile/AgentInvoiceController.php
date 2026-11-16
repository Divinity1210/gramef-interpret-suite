<?php

namespace App\Http\Controllers\Api\Mobile;

use App\AgentInvoice;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;

class AgentInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $agent = $user->agent;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'User is not an agent'
            ], 403);
        }

        $perPage = (int) $request->get('per_page', 20);

        try {
            $query = AgentInvoice::query()
                ->where('agent_id', $agent->id)
                ->withCount('items')
                ->orderBy('invoice_date', 'desc')
                ->orderBy('id', 'desc');

            if ($request->filled('status')) {
                $query->where('status', $this->agentStatusCode($request->get('status')));
            }

            if ($request->filled('from_date')) {
                $query->whereDate('date_from', '>=', $request->get('from_date'));
            }

            if ($request->filled('to_date')) {
                $query->whereDate('date_to', '<=', $request->get('to_date'));
            }

            $invoices = $query->paginate($perPage > 0 ? $perPage : 20);

            $invoices->getCollection()->transform(function ($invoice) {
                return [
                    'id' => (int) $invoice->id,
                    'invoice_number' => (string) ($invoice->invoice_number ?: $invoice->id),
                    'invoice_date' => optional($invoice->invoice_date)->toDateString(),
                    'date_from' => optional($invoice->date_from)->toDateString(),
                    'date_to' => optional($invoice->date_to)->toDateString(),
                    'subtotal' => $this->toFloat($invoice->subtotal ?? 0),
                    'vat_amount' => $this->toFloat($invoice->vat_amount ?? 0),
                    'total_amount' => $this->toFloat($invoice->total_amount ?? 0),
                    'status' => (string) $invoice->status,
                    'items_count' => (int) ($invoice->items_count ?? 0),
                    'submitted_at' => optional($invoice->submitted_at)->toIso8601String(),
                    'approved_at' => optional($invoice->approved_at)->toIso8601String(),
                    'has_pdf' => true,
                ];
            });
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR agent-invoices.index ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Payslips are unavailable'
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => $invoices
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $agent = $user->agent;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'User is not an agent'
            ], 403);
        }

        try {
            $invoice = AgentInvoice::query()
                ->where('id', $id)
                ->where('agent_id', $agent->id)
                ->with('items')
                ->withCount('items')
                ->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payslip not found'
                ], 404);
            }

            $items = $invoice->items->map(function ($item) {
                $amount = $this->toFloat($item->total_amount ?? 0);
                return [
                    'id' => (int) $item->id,
                    'description' => (string) ($item->description ?? ''),
                    'quantity' => 1,
                    'rate' => $amount,
                    'amount' => $amount,
                ];
            })->values();

            $data = [
                'id' => (int) $invoice->id,
                'invoice_number' => (string) ($invoice->invoice_number ?: $invoice->id),
                'invoice_date' => optional($invoice->invoice_date)->toDateString(),
                'date_from' => optional($invoice->date_from)->toDateString(),
                'date_to' => optional($invoice->date_to)->toDateString(),
                'subtotal' => $this->toFloat($invoice->subtotal ?? 0),
                'vat_amount' => $this->toFloat($invoice->vat_amount ?? 0),
                'total_amount' => $this->toFloat($invoice->total_amount ?? 0),
                'status' => (string) $invoice->status,
                'items_count' => (int) ($invoice->items_count ?? 0),
                'submitted_at' => optional($invoice->submitted_at)->toIso8601String(),
                'approved_at' => optional($invoice->approved_at)->toIso8601String(),
                'has_pdf' => true,
                'items' => $items,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR agent-invoices.show ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Payslip details are unavailable'
            ], 503);
        }
    }

    public function pdf(Request $request, $id)
    {
        $user = $request->user();
        $agent = $user->agent;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'User is not an agent'
            ], 403);
        }

        try {
            $invoice = AgentInvoice::query()
                ->where('id', $id)
                ->where('agent_id', $agent->id)
                ->with(['items', 'agent.user'])
                ->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payslip not found'
                ], 404);
            }

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_top' => 10,
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_bottom' => 10,
            ]);

            $mpdf->WriteHTML($this->renderPdfHtml($invoice));
            $content = $mpdf->Output('', 'S');

            $filename = 'payslip-' . ($invoice->invoice_number ?: $invoice->id) . '.pdf';

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR agent-invoices.pdf ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Payslip PDF is unavailable'
            ], 503);
        }
    }

    private function renderPdfHtml(AgentInvoice $invoice): string
    {
        $esc = function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };

        $rows = '';
        foreach ($invoice->items as $item) {
            $desc = $esc($item->description ?? '');
            $amount = number_format($this->toFloat($item->total_amount ?? 0), 2);
            $rows .= '<tr><td style="padding:8px 6px;border-bottom:1px solid #e5e7eb;">' . $desc . '</td><td style="padding:8px 6px;border-bottom:1px solid #e5e7eb;text-align:right;">£' . $amount . '</td></tr>';
        }

        $invoiceNumber = $esc($invoice->invoice_number ?: $invoice->id);
        $invoiceDate = $esc(optional($invoice->invoice_date)->toDateString());
        $periodFrom = $esc(optional($invoice->date_from)->toDateString());
        $periodTo = $esc(optional($invoice->date_to)->toDateString());
        $status = $esc($invoice->status);
        $subtotal = number_format($this->toFloat($invoice->subtotal ?? 0), 2);
        $vat = number_format($this->toFloat($invoice->vat_amount ?? 0), 2);
        $total = number_format($this->toFloat($invoice->total_amount ?? 0), 2);
        $agentName = $esc(optional(optional($invoice->agent)->user)->full_name ?? optional(optional($invoice->agent)->user)->fullName ?? '');

        return '<!doctype html>'
            . '<html><head><meta charset="utf-8" />'
            . '<style>body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;color:#111827;}h1{font-size:18px;margin:0 0 10px;}table{width:100%;border-collapse:collapse;}th{padding:8px 6px;text-align:left;border-bottom:2px solid #111827;} .muted{color:#6b7280;} .grid{width:100%;} .k{width:25%;} .v{width:75%;} .totals td{padding:6px;} .right{text-align:right;}</style>'
            . '</head><body>'
            . '<h1>Payslip</h1>'
            . '<table class="grid" style="margin-bottom:14px;">'
            . '<tr><td class="k muted">Invoice Number</td><td class="v">' . $invoiceNumber . '</td></tr>'
            . '<tr><td class="k muted">Invoice Date</td><td class="v">' . $invoiceDate . '</td></tr>'
            . '<tr><td class="k muted">Period</td><td class="v">' . $periodFrom . ' - ' . $periodTo . '</td></tr>'
            . '<tr><td class="k muted">Status</td><td class="v">' . $status . '</td></tr>'
            . ($agentName ? '<tr><td class="k muted">Agent</td><td class="v">' . $agentName . '</td></tr>' : '')
            . '</table>'
            . '<table style="margin-bottom:14px;">'
            . '<thead><tr><th>Description</th><th style="text-align:right;">Amount</th></tr></thead>'
            . '<tbody>' . ($rows ?: '<tr><td style="padding:8px 6px;border-bottom:1px solid #e5e7eb;" class="muted">No items</td><td style="padding:8px 6px;border-bottom:1px solid #e5e7eb;text-align:right;">£0.00</td></tr>') . '</tbody>'
            . '</table>'
            . '<table class="totals" style="width:50%;margin-left:auto;">'
            . '<tr><td class="muted">Subtotal</td><td class="right">£' . $subtotal . '</td></tr>'
            . '<tr><td class="muted">VAT</td><td class="right">£' . $vat . '</td></tr>'
            . '<tr><td style="border-top:2px solid #111827;font-weight:bold;">Total</td><td style="border-top:2px solid #111827;font-weight:bold;" class="right">£' . $total . '</td></tr>'
            . '</table>'
            . '</body></html>';
    }

    private function toFloat($value): float
    {
        if ($value === null) {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    }

    private function agentStatusCode($status)
    {
        static $isNumeric = null;
        if ($isNumeric === null) {
            try {
                $res = DB::select("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agent_invoices' AND COLUMN_NAME = 'status'");
                if (!empty($res)) {
                    $dataType = strtolower($res[0]->DATA_TYPE ?? '');
                    $isNumeric = in_array($dataType, ['tinyint', 'smallint', 'int', 'bigint', 'integer']);
                }
            } catch (\Throwable $e) {
                $isNumeric = null;
            }

            if ($isNumeric === null) {
                try {
                    $sample = AgentInvoice::orderByDesc('id')->value('status');
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
        }

        if (is_numeric($status)) {
            $rev = array_flip($map);
            $key = (int) $status;
            return $rev[$key] ?? 'draft';
        }

        return (string) $status;
    }
}
