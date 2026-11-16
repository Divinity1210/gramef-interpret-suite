<?php
/**
 * Fix ALL invoice items with duplicate charge_labels and wrong totals.
 *
 * Root cause: charge_labels can have duplicate entries (e.g. two "Travel Time")
 * and other_charges includes both the charge_labels sum AND timesheet extras.
 *
 * This script:
 * 1. Deduplicates charge_labels on each item (keeps first occurrence)
 * 2. Recalculates other_charges from the cleaned labels
 * 3. Recalculates invoice subtotal/vat/total
 *
 * Access: https://portal.atozinterpreting.com/fix-invoices-charges.php
 * Dry run (default): ?apply=1 to fix
 * DELETE THIS FILE after use.
 */

require __DIR__.'/../vendor/autoload.php';
if (PHP_VERSION_ID >= 80400) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle($request = Illuminate\Http\Request::capture());

use App\ClientInvoice;
use App\ClientInvoiceItem;

$dryRun = !isset($_GET['apply']);

echo "<h1>Invoice Charge Labels Fix</h1>";
if ($dryRun) {
    echo "<p style='color:orange;font-weight:bold;'>🔍 DRY RUN — showing what would change.</p>";
    echo "<p>To apply fixes: <a href='?apply=1'><strong>?apply=1</strong></a></p>";
} else {
    echo "<p style='color:green;font-weight:bold;'>🔧 APPLYING FIXES...</p>";
}

// ─── Step 1: Fix items with duplicate or inflated charge_labels ───
echo "<hr><h2>Step 1: Fix Item Charge Labels & Other Charges</h2>";

$allItems = ClientInvoiceItem::all();
$fixedItemIds = [];
$affectedInvoiceIds = [];

foreach ($allItems as $item) {
    $md = is_array($item->metadata) ? $item->metadata : (is_string($item->metadata) ? (json_decode($item->metadata, true) ?: []) : []);
    $labels = isset($md['charge_labels']) && is_array($md['charge_labels']) ? $md['charge_labels'] : [];
    $values = isset($md['charge_values']) && is_array($md['charge_values']) ? $md['charge_values'] : [];

    if (empty($labels)) continue;

    // Deduplicate labels: keep first occurrence of each normalized label
    $seen = [];
    $newLabels = [];
    $newValues = [];
    $hadDuplicates = false;
    foreach ($labels as $idx => $lbl) {
        $norm = strtolower(trim((string) $lbl));
        if (isset($seen[$norm])) {
            $hadDuplicates = true;
            continue; // skip duplicate
        }
        $seen[$norm] = true;
        $newLabels[] = $lbl;
        $newValues[] = isset($values[$idx]) ? $values[$idx] : 0;
    }

    // Recalculate correct other_charges from deduplicated labels (excluding parking)
    $correctOtherCharges = 0.0;
    foreach ($newLabels as $idx => $lbl) {
        $norm = strtolower(trim((string) $lbl));
        $val = (float) ($newValues[$idx] ?? 0);
        if ($norm === 'parking') continue;
        if ($val >= 0) $correctOtherCharges += $val;
    }
    $correctOtherCharges = round($correctOtherCharges, 2);
    $storedOtherCharges = round((float) $item->other_charges, 2);

    // Check if anything needs fixing
    $needsFix = $hadDuplicates || (abs($storedOtherCharges - $correctOtherCharges) > 0.01);

    if ($needsFix) {
        $affectedInvoiceIds[$item->client_invoice_id] = true;
        $fixedItemIds[] = $item->id;

        echo "<div style='margin:8px 0;padding:10px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;'>";
        echo "<strong>Item #{$item->id}</strong> (Invoice ID: {$item->client_invoice_id}, Job: {$item->job_id})<br>";
        echo "Description: {$item->description}<br>";
        echo "amount: £" . number_format($item->amount, 2) . "<br>";

        // Show old labels
        echo "<div style='color:red;'>OLD charge_labels:";
        foreach ($labels as $idx => $lbl) {
            $val = isset($values[$idx]) ? (float)$values[$idx] : 0;
            echo "<br>&nbsp;&nbsp;• {$lbl}: £" . number_format($val, 2);
        }
        echo "<br>OLD other_charges: £" . number_format($storedOtherCharges, 2) . "</div>";

        // Show new labels
        echo "<div style='color:green;'>NEW charge_labels (deduplicated):";
        foreach ($newLabels as $idx => $lbl) {
            $val = (float) ($newValues[$idx] ?? 0);
            echo "<br>&nbsp;&nbsp;• {$lbl}: £" . number_format($val, 2);
        }
        echo "<br>NEW other_charges: £" . number_format($correctOtherCharges, 2) . "</div>";
        echo "</div>";

        if (!$dryRun) {
            $md['charge_labels'] = array_values($newLabels);
            $md['charge_values'] = array_values($newValues);
            $item->metadata = $md;
            $item->other_charges = $correctOtherCharges;
            $item->save();
        }
    }
}

$fixedItemCount = count($fixedItemIds);
echo "<p>Scanned {$allItems->count()} items. Fixed: <strong>{$fixedItemCount}</strong></p>";

// ─── Step 2: Recalculate invoice totals for ALL invoices ───
echo "<hr><h2>Step 2: Recalculate Invoice Totals</h2>";

$invoices = ClientInvoice::with('items')->get();
$invoicesFixed = [];

foreach ($invoices as $invoice) {
    if ($invoice->items->isEmpty()) continue;

    // Recalculate subtotal from items
    $subtotalItems = 0.0;
    foreach ($invoice->items as $item) {
        $subtotalItems += (float) $item->amount + (float) $item->surcharge + (float) $item->other_charges;
    }

    // Sum per-item parking from metadata
    $itemParkingTotal = 0.0;
    foreach ($invoice->items as $it) {
        $md = is_array($it->metadata) ? $it->metadata : (is_string($it->metadata) ? (json_decode($it->metadata, true) ?: []) : []) ;
        if (isset($md['parking_amount']) && is_numeric($md['parking_amount'])) {
            $val = (float) $md['parking_amount'];
            if ($val >= 0) $itemParkingTotal += $val;
        } else {
            $lbls = isset($md['charge_labels']) && is_array($md['charge_labels']) ? $md['charge_labels'] : [];
            $vals = isset($md['charge_values']) && is_array($md['charge_values']) ? $md['charge_values'] : [];
            foreach ($lbls as $idx => $lbl) {
                if (strtolower(trim((string) $lbl)) === 'parking') {
                    $v = isset($vals[$idx]) ? (float) $vals[$idx] : null;
                    if ($v !== null && $v >= 0) $itemParkingTotal += $v;
                    break;
                }
            }
        }
    }

    $template = $invoice->template ?? 'v2';
    if ($template === 'v3') {
        $parking = round($itemParkingTotal, 2);
    } else {
        $postedParking = (float) ($invoice->parking_amount ?? 0);
        $parking = round(max($postedParking, $itemParkingTotal), 2);
    }

    $subtotal = round($subtotalItems + $parking, 2);
    $includeVat = (bool) ($invoice->include_vat ?? true);
    $vatRate = (float) ($invoice->vat_rate ?? 20);
    $vatAmount = $includeVat ? round($subtotal * ($vatRate / 100), 2) : 0.0;
    $total = round($subtotal + $vatAmount, 2);

    $storedSubtotal = round((float) $invoice->subtotal, 2);
    $storedTotal = round((float) $invoice->total, 2);

    if (abs($storedSubtotal - $subtotal) > 0.01 || abs($storedTotal - $total) > 0.01) {
        $invoicesFixed[] = [
            'invoice' => $invoice,
            'old_subtotal' => $storedSubtotal,
            'old_vat' => round((float) $invoice->vat_amount, 2),
            'old_total' => $storedTotal,
            'new_subtotal' => $subtotal,
            'new_vat' => $vatAmount,
            'new_total' => $total,
        ];

        if (!$dryRun) {
            $invoice->subtotal = $subtotal;
            $invoice->vat_amount = $vatAmount;
            $invoice->total = $total;
            $invoice->parking_amount = $parking;
            $invoice->save();
        }
    }
}

$invoicesFixedCount = count($invoicesFixed);
echo "<p>Scanned {$invoices->count()} invoices. Totals mismatch: <strong>{$invoicesFixedCount}</strong></p>";

if ($invoicesFixedCount > 0) {
    echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:13px;'>";
    echo "<tr style='background:#f0f0f0;'><th>ID</th><th>Invoice #</th><th>Status</th><th>Old Subtotal</th><th>Old Total</th><th>→ New Subtotal</th><th>→ New Total</th><th>Diff</th></tr>";
    foreach ($invoicesFixed as $row) {
        $inv = $row['invoice'];
        $diff = $row['old_total'] - $row['new_total'];
        echo "<tr>";
        echo "<td>{$inv->id}</td><td>{$inv->invoice_number}</td><td>{$inv->status}</td>";
        echo "<td>£" . number_format($row['old_subtotal'], 2) . "</td>";
        echo "<td>£" . number_format($row['old_total'], 2) . "</td>";
        echo "<td style='color:green;font-weight:bold;'>£" . number_format($row['new_subtotal'], 2) . "</td>";
        echo "<td style='color:green;font-weight:bold;'>£" . number_format($row['new_total'], 2) . "</td>";
        echo "<td style='color:red;'>£" . number_format($diff, 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    if ($dryRun) {
        echo "<p>To apply: <a href='?apply=1'><strong>?apply=1</strong></a></p>";
    } else {
        echo "<p style='color:green;font-weight:bold;'>✅ All fixes applied!</p>";
    }
} else {
    echo "<p style='color:green;'>✅ All invoice totals match their items.</p>";
}

echo "<hr><p style='color:red;font-weight:bold;'>⚠️ DELETE this file after use.</p>";
