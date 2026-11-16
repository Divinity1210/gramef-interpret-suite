<?php

namespace App\Mail;

use App\ClientInvoice;
use App\PaymentProof;
use App\Receipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PaymentProofVerifiedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ClientInvoice $invoice;
    public PaymentProof $proof;
    public ?Receipt $receipt;

    public function __construct(ClientInvoice $invoice, PaymentProof $proof, ?Receipt $receipt = null)
    {
        $this->invoice = $invoice;
        $this->proof = $proof;
        $this->receipt = $receipt;
    }

    public function build(): self
    {
        $subject = 'Payment verified for invoice ' . ($this->invoice->invoice_number ?? $this->invoice->id);

        $mail = $this->subject($subject)
            ->view('emails.payment_proofs.verified')
            ->with([
                'invoice' => $this->invoice,
                'proof' => $this->proof,
                'receipt' => $this->receipt,
                'invoicePreviewUrl' => route('invoices.client.show', $this->invoice->id),
            ]);

        if ($this->receipt && $this->receipt->pdf_path) {
            $disk = env('RECEIPTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
            $path = $this->receipt->pdf_path;
            if (Storage::disk($disk)->exists($path)) {
                $filename = 'receipt-' . $this->receipt->receipt_number . '.pdf';
                if ($disk === 'public') {
                    $abs = storage_path('app/public/' . $path);
                    $mail->attach($abs, [
                        'as' => $filename,
                        'mime' => 'application/pdf',
                    ]);
                } else {
                    $content = Storage::disk($disk)->get($path);
                    $mail->attachData($content, $filename, [
                        'mime' => 'application/pdf',
                    ]);
                }
            }
        }

        return $mail;
    }
}
