<?php
// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\ClientInvoice;
use App\ClientInvoiceItem;
use App\InterpreterJob;
use App\TranslatorJob;
use Carbon\Carbon;

try {
    // Find the preview client invoice by known number
    $invoice = ClientInvoice::where('invoice_number', 'CLI-PREVIEW-0001')->first();

    if (!$invoice) {
        // Fallback: get latest client invoice
        $invoice = ClientInvoice::orderByDesc('id')->first();
    }

    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'No client invoice found']);
        exit(1);
    }

    // Ensure template v3 and sensible defaults
    $invoice->template = 'v3';
    $invoice->payment_terms_text = $invoice->payment_terms_text ?: '28 days from invoice date';
    $invoice->invoice_date = $invoice->invoice_date ?: Carbon::now()->toDateString();
    $invoice->include_vat = $invoice->include_vat ?? true;
    $invoice->vat_rate = $invoice->vat_rate ?? 20;
    $invoice->save();

    // Ensure there are at least two items linked to jobs for preview purposes
    $linkedCount = $invoice->items()->whereNotNull('job_id')->count();
    $clientId = $invoice->client_id;

    if ($linkedCount < 2 && $clientId) {
        // Fetch up to 2 recent jobs for this client
        $jobs = [];
        $ij = InterpreterJob::where('client_id', $clientId)->orderByDesc('appointment_date')->limit(2)->get();
        foreach ($ij as $j) { $jobs[] = ['model' => 'App\\InterpreterJob', 'id' => $j->id, 'date' => optional($j->appointment_date)->toDateString(), 'label' => 'Interpreting']; }
        if (count($jobs) < 2) {
            $tj = TranslatorJob::where('client_id', $clientId)->orderByDesc('target_date')->limit(2 - count($jobs))->get();
            foreach ($tj as $j) { $jobs[] = ['model' => 'App\\TranslatorJob', 'id' => $j->id, 'date' => optional($j->target_date)->toDateString(), 'label' => 'Translation']; }
        }

        $amounts = [100.00, 120.00];
        foreach ($jobs as $idx => $j) {
            // Skip if this job already has an item on invoice
            $exists = $invoice->items()->where('job_id', $j['id'])->exists();
            if ($exists) { continue; }
            ClientInvoiceItem::create([
                'client_invoice_id' => $invoice->id,
                'job_type' => $j['model'],
                'description' => ($j['label'] . ' for job #' . $j['id']),
                'amount' => $amounts[$idx % count($amounts)],
                'surcharge' => 0,
                'other_charges' => 0,
                'vat_applicable' => (bool) $invoice->include_vat,
                'job_id' => $j['id'],
                'metadata' => [
                    'date' => $j['date'],
                    'job_type' => $j['model'],
                    'source' => 'preview_v3_script',
                ],
            ]);
        }
    }

    echo json_encode([
        'success' => true,
        'id' => $invoice->id,
        'invoice_number' => $invoice->invoice_number,
        'template' => $invoice->template,
    ]);
    exit(0);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit(1);
}