<?php

namespace App\Mail;

use App\TranslatorJob;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TranslatorJobCommented extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $translatorJob;
    public $view;
    public $subject;

    public function __construct(TranslatorJob $translatorJob, $view, $subject)
    {
        $this->translatorJob = $translatorJob;
        $this->view = $view;
        $this->subject = $subject;
    }

    public function build()
    {
        $mailable = $this->subject($this->subject)->view($this->view);

        try {
            $mailable->withSwiftMessage(function ($message) {
                $message->getHeaders()
                    ->addTextHeader('x-mailgun-native-send', 'true');
            });
        } catch (\Throwable $e) {
            // withSwiftMessage not available (Symfony Mailer); header is optional
        }

        return $mailable;
    }
}
