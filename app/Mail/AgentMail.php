<?php

namespace App\Mail;

use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AgentMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $view;
    public $subject;
    public $documents;
    
    public function __construct(User $user, $view, $subject, $updatedDocuments = null)
    {
        $this->user = $user;
        $this->view = $view;
        $this->subject = $subject;
        $this->documents = $updatedDocuments;
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
