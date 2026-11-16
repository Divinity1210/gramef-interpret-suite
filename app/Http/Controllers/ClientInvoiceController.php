<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ClientInvoice;
use Illuminate\Support\Facades\Auth;

class ClientInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $client = $user->client; // assumes User has client relationship

        $filters = [
            'date_range_start' => $request->query('date_range_start'),
            'date_range_end' => $request->query('date_range_end'),
            'status' => $request->query('status'),
        ];

        $query = ClientInvoice::with(['company'])
            ->where('client_id', optional($client)->id);

        if ($filters['date_range_start']) {
            $query->whereDate('date_range_start', '>=', $filters['date_range_start']);
        }
        if ($filters['date_range_end']) {
            $query->whereDate('date_range_end', '<=', $filters['date_range_end']);
        }
        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        $invoices = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return view('invoices.client.index', compact('invoices', 'filters'));
    }

    public function show($id)
    {
        $user = Auth::user();
        $client = $user->client; // assumes User has client relationship

        $invoice = ClientInvoice::with(['client.user', 'company', 'items.job', 'receipts', 'paymentProofs.documents'])
            ->where('client_id', optional($client)->id)
            ->findOrFail($id);

        return view('invoices.client.show', compact('invoice'));
    }

    public function pdf($id)
    {
        $user = Auth::user();
        $client = $user->client; // assumes relation

        $invoice = ClientInvoice::with(['client.user', 'company', 'items.job'])
            ->where('client_id', optional($client)->id)
            ->findOrFail($id);

        // Determine effective template for rendering (mirror AdminInvoiceController::pdfClient logic)
        // Updated: Only switch to v3 when there are multiple DISTINCT jobs.
        // Additional standalone items (job_id null) or multiple items tied to the same job should still render using v2.
        $mode = session('invoice_template_mode_' . $invoice->id) ?? 'auto';
        $distinctJobs = $invoice->items->pluck('job_id')->filter()->unique()->count();
        $hasMultipleDistinctJobs = $distinctJobs > 1;
        if ($hasMultipleDistinctJobs) {
            $effectiveTemplate = 'v3';
        } else {
            // Auto mode should not be affected by a previously persisted invoice->template value.
            $effectiveTemplate = ($mode === 'v3') ? 'v3' : (($mode === 'v2') ? 'v2' : 'v2');
        }

        $viewName = ($effectiveTemplate === 'v3')
            ? 'invoices.client.pdf_v3'
            : (($effectiveTemplate === 'v2') ? 'invoices.client.pdf_v2' : 'invoices.client.pdf');
        $html = view($viewName, compact('invoice'))->render();

        // Use landscape for v2/v3 for better formatting
        $orientation = in_array($effectiveTemplate, ['v2','v3']) ? 'L' : 'P';
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => $orientation,
        ]);
        $mpdf->WriteHTML($html);
        $filename = 'client-invoice-' . $invoice->invoice_number . '.pdf';
        $mpdf->Output($filename, 'I');
        exit;
    }
}