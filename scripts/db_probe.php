<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $count = DB::table('remittances')->count();
    $exists = DB::table('remittances')->where('client_invoice_id', 21)->exists();
    echo json_encode([
        'remittances_count' => $count,
        'has_remittance_for_invoice_21' => $exists,
    ], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    echo 'error: ' . $e->getMessage() . PHP_EOL;
}