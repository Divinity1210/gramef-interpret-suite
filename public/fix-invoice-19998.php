<?php
/**
 * Temporary script to recalculate Invoice 19998 (ID 1987) with correct amounts.
 * DELETE THIS FILE after use.
 *
 * Access via: https://portal.atozinterpreting.com/fix-invoice-19998.php
 */

require __DIR__.'/../bootstrap/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle($request = Illuminate\Http\Request::capture());

use App\ClientInvoice;
use App\ClientInvoiceItem;

// Find the invoice — URL was /invoices/admin/client/1987/preview
$invoice = ClientInvoice::find(1987);

if (!$invoice) {
    die("Invoice ID 1987 not found.\n");
}

echo "<h2>Invoice #{$invoice->invoice_number} (ID: {$invoice->id})</h2>";
echo "<h3>BEFORE fix:</h3>";
echo "<pre>";
echo "Subtotal:   £" . number_format($invoice->subtotal, 2) . "\n";
echo "VAT:        £" . number_format($invoice->vat_amount, 2) . "\n";
echo "Total:      £" . number_format($invoice->total, 2) . "\n";
echo "Parking:    £" . number_format($invoice->parking_amount ?? 0, 2) . "\n";
echo "</pre>";

// Recalculate each item's other_charges from its charge_labels metadata
$items = $invoice->items()->get();
foreach ($items as $item) {
    $md = is_array($item->metadata) ? $item->metadata : (is_string($item->metadata) ? (json_decode($item->metadata, true) ?: []) : []);
    $labels = isset($md['charge_labels']) && is_array($md['charge_labels']) ? $md['charge_labels'] : [];
    $values = isset($md['charge_values']) && is_array($md['charge_values']) ? $md['charge_values'] : [];

    echo "<h4>Item #{$item->id} (Job: {$item->job_id})</h4>";
    echo "<pre>";
    echo "  amount (interpreting): £" . number_format($item->amount, 2) . "\n";
    echo "  surcharge:             £" . number_format($item->surcharge, 2) . "\n";
    echo "  other_charges BEFORE:  £" . number_format($item->other_charges, 2) . "\n";

    // Recalculate other_charges from charge_labels only (excluding parking)
    $newOtherCharges = 0.0;
    foreach ($labels as $idx => $lbl) {
        $norm = strtolower(trim((string) $lbl));
        $val = isset($values[$idx]) ? (float) $values[$idx] : 0.0;
        if ($norm === 'parking') {
            continue; // parking is tracked separately
        }
        if ($val >= 0) {
            $newOtherCharges += $val;
        }
        echo "  charge_label: '{$lbl}' = £" . number_format($val, 2) . ($norm === 'parking' ? ' (excluded - parking)' : '') . "\n";
    }
    $newOtherCharges = round($newOtherCharges, 2);

    echo "  other_charges AFTER:   £" . number_format($newOtherCharges, 2) . "\n";
    echo "</pre>";

    // Update the item
    $item->other_charges = $newOtherCharges;
    $item->save();
}

// Recalculate invoice totals
$invoice->load('items');
$subtotalItems = $invoice->items()->get()->sum(function ($i) {
    return (float) $i->amount + (float) $i->surcharge + (float) $i->other_charges;
});

// Sum per-item parking from metadata
$itemParkingTotal = 0.0;
foreach ($invoice->items()->get() as $it) {
    $md = is_array($it->metadata) ? $it->metadata : (is_string($it->metadata) ? (json_decode($it->metadata, true) ?: []) : []);
    if (isset($md['parking_amount']) && is_numeric($md['parking_amount'])) {
        $val = (float) $md['parking_amount'];
        if ($val >= 0) { $itemParkingTotal += $val; }
    } else {
        $labels = isset($md['charge_labels']) && is_array($md['charge_labels']) ? $md['charge_labels'] : [];
        $values = isset($md['charge_values']) && is_array($md['charge_values']) ? $md['charge_values'] : [];
        foreach ($labels as $idx => $lbl) {
            $norm = strtolower(trim((string) $lbl));
            if ($norm === 'parking') {
                $v = isset($values[$idx]) ? (float) $values[$idx] : null;
                if ($v !== null && $v >= 0) { $itemParkingTotal += $v; }
                break;
            }
        }
    }
}

// Use same logic as updateClient for v2 template
$postedParking = (float) ($invoice->parking_amount ?? 0);
$parking = round(max($postedParking, $itemParkingTotal), 2);
$invoice->parking_amount = $parking;
$subtotal = round($subtotalItems + $parking, 2);

$includeVat = (bool) ($invoice->include_vat ?? true);
$vatRate = (float) ($invoice->vat_rate ?? 20);
$vatAmount = $includeVat ? round($subtotal * ($vatRate / 100), 2) : 0.0;
$total = round($subtotal + $vatAmount, 2);

$invoice->subtotal = $subtotal;
$invoice->vat_amount = $vatAmount;
$invoice->total = $total;
$invoice->save();

echo "<h3>AFTER fix:</h3>";
echo "<pre>";
echo "Subtotal:   £" . number_format($subtotal, 2) . "\n";
echo "VAT ({$vatRate}%): £" . number_format($vatAmount, 2) . "\n";
echo "Total:      £" . number_format($total, 2) . "\n";
echo "Parking:    £" . number_format($parking, 2) . "\n";
echo "</pre>";

echo "<p style='color: green; font-weight: bold;'>✅ Invoice recalculated successfully!</p>";
echo "<p style='color: red;'>⚠️ DELETE this file from the server after use: public/fix-invoice-19998.php</p>";
echo "<p><a href='/invoices/admin/client/{$invoice->id}/preview'>→ View Invoice Preview</a></p>";
