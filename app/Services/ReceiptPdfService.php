<?php

namespace App\Services;

use App\Receipt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;

class ReceiptPdfService
{
    /**
     * Generate PDF for a receipt and save it to storage
     *
     * @param Receipt $receipt
     * @return string|null The path to the generated PDF, or null on failure
     */
    public function generateReceiptPdf(Receipt $receipt): ?string
    {
        try {
            // Load necessary relationships
            $receipt->load(['clientInvoice.client.user', 'clientInvoice.company', 'generator']);

            // Generate HTML from blade template
            $html = view('receipts.pdf', compact('receipt'))->render();

            // Create mPDF instance
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_top' => 10,
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_bottom' => 10,
            ]);

            $mpdf->WriteHTML($html);
            
            // Generate PDF content as string
            $pdfContent = $mpdf->Output('', 'S');

            // Define storage path
            $filename = 'receipt-' . $receipt->receipt_number . '.pdf';
            $path = 'receipts/' . $filename;

            // Save to configured disk (prefer S3 in production)
            $disk = env('RECEIPTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
            Storage::disk($disk)->put($path, $pdfContent);

            // Update receipt with PDF path
            $receipt->update(['pdf_path' => $path]);

            return $path;

        } catch (\Throwable $e) {
            // Log error but don't throw to avoid breaking the verification flow
            Log::error('Receipt PDF generation failed', [
                'receipt_id' => $receipt->id,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Get the full storage path for a receipt PDF
     *
     * @param Receipt $receipt
     * @return string|null
     */
    public function getReceiptPdfPath(Receipt $receipt): ?string
    {
        if (!$receipt->pdf_path) {
            return null;
        }

        $disk = env('RECEIPTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        if ($disk === 'public') {
            return $this->receiptPdfExists($receipt) ? storage_path('app/public/' . $receipt->pdf_path) : null;
        }
        return $this->receiptPdfExists($receipt) ? $receipt->pdf_path : null;
    }

    /**
     * Check if receipt PDF exists
     *
     * @param Receipt $receipt
     * @return bool
     */
    public function receiptPdfExists(Receipt $receipt): bool
    {
        $disk = env('RECEIPTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        return $receipt->pdf_path && Storage::disk($disk)->exists($receipt->pdf_path);
    }
}