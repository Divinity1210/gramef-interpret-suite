<?php

namespace App\Mail;

use App\Remittance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RemittanceApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Remittance $remittance;

    public function __construct(Remittance $remittance)
    {
        $this->remittance = $remittance;
    }

    public function build(): self
    {
        $subject = 'Your Remittance Has Been Approved (' . ($this->remittance->remittance_number ?? '') . ')';

        return $this->subject($subject)
            ->view('emails.remittances.approved')
            ->with([
                'remittance' => $this->remittance,
                'downloadUrl' => route('remittances.download', $this->remittance->id),
                'showUrl' => route('remittances.show', $this->remittance->id),
            ]);
    }
}