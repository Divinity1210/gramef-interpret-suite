<?php

namespace App\Mail;

use App\ContactMethod;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $view;
    public $subject;
    public $newContactMethods;

    public function __construct(User $user, $view, $subject)
    {
        $this->user = $user;
        $this->view = $view;
        $this->subject = $subject;
        $this->newContactMethods = request()->filled('contact_method') ? ContactMethod::whereIn('id', request()->contact_method)->get() : collect();
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
