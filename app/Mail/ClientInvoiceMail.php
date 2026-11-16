<?php

namespace App\Mail;

use App\ClientInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Mpdf\Mpdf;

class ClientInvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ClientInvoice $invoice;
    protected ?string $subjectOverride;
    protected ?string $customMessage;

    /**
     * Create a new message instance.
     */
    public function __construct(ClientInvoice $invoice, ?string $subjectOverride = null, ?string $customMessage = null)
    {
        $this->invoice = $invoice;
        $this->subjectOverride = $subjectOverride;
        $this->customMessage = $customMessage;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $defaultSubject = 'Invoice ' . ($this->invoice->invoice_number ?? '') . ' from ' . (config('bank.account_name') ?? config('app.name'));
        $subject = $this->subjectOverride ?: $defaultSubject;

        // Render invoice HTML according to template (mirror AdminInvoiceController::pdfClient logic)
        // Updated: Only use v3 when there are multiple DISTINCT jobs.
        $distinctJobs = $this->invoice->items->pluck('job_id')->filter()->unique()->count();
        $hasMultipleDistinctJobs = $distinctJobs > 1;
        if ($hasMultipleDistinctJobs) {
            $effectiveTemplate = 'v3';
        } else {
            // Single job or standalone items: respect explicit v3 override, otherwise prefer v2
            $effectiveTemplate = (($this->invoice->template ?? null) === 'v3') ? 'v3' : 'v2';
        }
        $viewName = ($effectiveTemplate === 'v3')
            ? 'invoices.client.pdf_v3'
            : (($effectiveTemplate === 'v2') ? 'invoices.client.pdf_v2' : 'invoices.client.pdf');
        $html = view($viewName, ['invoice' => $this->invoice])->render();

        // Generate PDF bytes using mPDF and attach to email
        try {
            $orientation = in_array($effectiveTemplate, ['v2','v3']) ? 'L' : 'P';
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => $orientation,
            ]);
            $mpdf->WriteHTML($html);
            $pdfContent = $mpdf->Output('', 'S'); // Return as string
        } catch (\Throwable $e) {
            // If PDF generation fails, fall back to link-only email
            $pdfContent = null;
        }

        $mail = $this->subject($subject)
            ->view('emails.invoices.client')
            ->with([
                'invoice' => $this->invoice,
                'pdfUrl' => route('invoices.admin.client.pdf', $this->invoice->id),
                'customMessage' => $this->customMessage,
                'subjectText' => $subject,
            ]);

        if ($pdfContent) {
            $mail->attachData($pdfContent, 'client-invoice-' . ($this->invoice->invoice_number ?? $this->invoice->id) . '.pdf', [
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
