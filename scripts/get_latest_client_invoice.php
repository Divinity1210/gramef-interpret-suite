<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

$invoice = \App\ClientInvoice::orderBy('id', 'desc')->first();
if ($invoice) {
    echo json_encode([
        'id' => $invoice->id,
        'number' => $invoice->invoice_number,
    ], JSON_PRETTY_PRINT) . "\n";
} else {
    echo json_encode(null) . "\n";
}