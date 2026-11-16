<?php

namespace App\Mail;

use App\MatchedAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MatchedAgentMail extends Mailable
{
    use Queueable, SerializesModels;

    public $matchedAgent;
    public $jobType;

    public function __construct(MatchedAgent $matchedAgent)
    {
        $this->matchedAgent = $matchedAgent;
        $this->jobType = get_class($matchedAgent->job) === 'App\InterpreterJob' ? 'interpreter-jobs' : 'translator-jobs';
    }

    public function build()
    {
        $mailable = $this->subject('You have a new matched job')->view('emails.matched-agents.created');

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
