<?php

namespace App\Mail;

use App\AdminQuote;
use App\ContactMethod;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AcceptedQuoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public $quote;
    public $job;
    public $view;
    public $subject;
    public $role;
    public $wasSentToAdmin;
    public $jobLink;

    public function __construct(AdminQuote $adminQuote, $view, $subject, $role = null)
    {
        $job =  $adminQuote->job;
        $this->quote = $adminQuote;
        
        $this->job = $adminQuote->job;
        $this->view = $view;
        $this->subject = $subject;
        $this->role = $role;
        
        $this->newContactMethods = request()->filled('contact_method') ? ContactMethod::whereIn('id', request()->contact_method)->get() : collect();


        if (is_array($job)) {
            $jobType = get_class($job['original']) === 'App\InterpreterJob' ? 'interpreter-jobs' : 'translator-jobs';
        } else {
            $jobType = get_class($job) === 'App\InterpreterJob' ? 'interpreter-jobs' : 'translator-jobs';
        }

        $this->jobLink = $role === 'admin' ?
            route("$jobType.edit", $job) :
            route("$jobType.show", $job);
    }

    public function build()
    {
        $this->wasSentToAdmin = $this->role === 'admin';

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
