<?php
// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\ClientInvoice;
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

    // Prepare reasonable defaults for v2 verification
    $invoice->template = 'v2';
    $invoice->interpreting_time_minutes = 135; // 2h 15m
    $invoice->travel_time_minutes = 30;       // 0h 30m
    $invoice->payment_terms_text = $invoice->payment_terms_text ?: '28 days from invoice date';
    $invoice->file_reference = $invoice->file_reference ?: 'PREVIEW-REF-001';
    $invoice->client_reference = $invoice->client_reference ?: 'CLIENT-REF-001';
    $invoice->patient_number = $invoice->patient_number ?: 'PN-0001';
    $invoice->pid = $invoice->pid ?: 'PID-0001';
    $invoice->invoice_date = $invoice->invoice_date ?: Carbon::now()->toDateString();
    $invoice->save();

    echo json_encode([
        'success' => true,
        'id' => $invoice->id,
        'invoice_number' => $invoice->invoice_number,
        'template' => $invoice->template,
        'interpreting_time_minutes' => $invoice->interpreting_time_minutes,
        'travel_time_minutes' => $invoice->travel_time_minutes,
    ]);
    exit(0);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit(1);
}