<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\ClientInvoice;

class OverdueInvoiceReminder extends Notification
{
    use Queueable;

    /** @var \App\ClientInvoice */
    protected $invoice;

    public function __construct(ClientInvoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Invoice overdue notice')
            ->view('emails.clients.invoice-overdue', [
                'invoice' => $this->invoice,
                'notifiable' => $notifiable,
            ]);
    }

    public function toArray($notifiable)
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'status' => $this->invoice->status,
        ];
    }
}