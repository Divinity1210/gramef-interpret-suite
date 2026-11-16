<?php

namespace App\Services;

use App\ClientInvoice;

class SmartRemittanceMatcherService
{
    /**
     * Parse raw remittance text (or CSV lines) for CI-XXXXXX / CLI-XXXX invoice numbers and £ amounts.
     */
    public function parseAndMatch(string $rawText): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($rawText));
        $matches = [];
        $seen = [];

        foreach ($lines as $line) {
            if (preg_match_all('/\b((?:CI|CLI|INV)-[A-Z0-9-]+)\b/i', $line, $invFound)) {
                $amount = null;
                if (preg_match('/£?\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})|[0-9]+\.[0-9]{2})/', $line, $amtMatch)) {
                    $amount = (float) str_replace(',', '', $amtMatch[1]);
                }

                foreach ($invFound[1] as $invNum) {
                    $normalized = strtoupper(trim($invNum));
                    if (isset($seen[$normalized])) {
                        continue;
                    }
                    $seen[$normalized] = true;

                    $invoice = ClientInvoice::with(['client.user', 'client.organisation'])
                        ->where('invoice_number', $normalized)
                        ->first();

                    $matches[] = [
                        'invoice_number' => $normalized,
                        'found' => (bool) $invoice,
                        'invoice_id' => optional($invoice)->id,
                        'client_name' => $invoice ? (optional(optional($invoice->client)->organisation)->organisation_company ?: optional(optional($invoice->client)->user)->getFullName()) : 'Unmatched',
                        'invoice_total' => $invoice ? (float) $invoice->total : 0.0,
                        'remittance_amount' => $amount ?? ($invoice ? (float) $invoice->total : 0.0),
                        'status' => optional($invoice)->status ?? 'not_found',
                        'exact_match' => $invoice && ($amount === null || abs((float) $invoice->total - $amount) < 0.05),
                    ];
                }
            }
        }

        return $matches;
    }
}
