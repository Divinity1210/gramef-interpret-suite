<?php

namespace App\Mail;

use App\ClientInvoice;
use App\PaymentProof;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentProofRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ClientInvoice $invoice;
    public PaymentProof $proof;

    public function __construct(ClientInvoice $invoice, PaymentProof $proof)
    {
        $this->invoice = $invoice;
        $this->proof = $proof;
    }

    public function build(): self
    {
        $subject = 'Payment proof rejected for invoice ' . ($this->invoice->invoice_number ?? $this->invoice->id);

        return $this->subject($subject)
            ->view('emails.payment_proofs.rejected')
            ->with([
                'invoice' => $this->invoice,
                'proof' => $this->proof,
                'invoicePreviewUrl' => route('invoices.client.show', $this->invoice->id),
            ]);
    }
}
