<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DBSMail extends Mailable
{
    use Queueable, SerializesModels;

    public $agent;
    public $view;
    public $subject;

    public function __construct($agent, $view, $subject)
    {
        $this->agent = $agent;
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
