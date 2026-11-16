<?php

namespace App\Mail;

use App\Remittance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RemittanceRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Remittance $remittance;

    public function __construct(Remittance $remittance)
    {
        $this->remittance = $remittance;
    }

    public function build(): self
    {
        $subject = 'Remittance Rejected (' . ($this->remittance->remittance_number ?? '') . ')';

        return $this->subject($subject)
            ->view('emails.remittances.rejected')
            ->with([
                'remittance' => $this->remittance,
                'showUrl' => route('remittances.show', $this->remittance->id),
            ]);
    }
}