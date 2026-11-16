<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class JobMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $job;
    public $view;
    public $subject;
    public $role;
    public $wasSentToAdmin;
    public $jobLink;
    public $extraData = [];
    public $ccEmails = [];

    public function __construct($job, $view, $subject, $role = null, $adminQuote = null)
    {
        $this->job = $job;
        $this->view = $view;
        $this->subject = $subject;
        $this->role = $role;

        $jobModel = null;
        if (is_array($job)) {
            $jobModel = $job['updated'] ?? ($job['original'] ?? null);
        } else {
            $jobModel = $job;
        }

        $jobType = (is_object($jobModel) && get_class($jobModel) === 'App\\InterpreterJob')
            ? 'interpreter-jobs'
            : 'translator-jobs';

        $jobId = is_object($jobModel) ? ($jobModel->id ?? null) : $jobModel;

        $routeName = $role === 'admin' ? "$jobType.edit" : "$jobType.show";
        $this->jobLink = $jobId ? route($routeName, $jobId) : route("$jobType.index");
    }

    /**
     * Pass extra data to the email view (e.g. agentName for unassignment emails).
     */
    public function withData(array $data): self
    {
        $this->extraData = array_merge($this->extraData, $data);
        return $this;
    }

    /**
     * Add CC email addresses.
     */
    public function withCc($emails): self
    {
        if (is_string($emails)) {
            $this->ccEmails[] = $emails;
        } elseif (is_array($emails)) {
            $this->ccEmails = array_merge($this->ccEmails, $emails);
        }
        $this->ccEmails = array_unique(array_filter($this->ccEmails));
        return $this;
    }

    public function build()
    {
        $this->wasSentToAdmin = $this->role === 'admin';

        $mailable = $this->subject($this->subject)->view($this->view);

        // Add Mailgun native-send header (safe for both SwiftMailer and Symfony Mailer)
        try {
            $mailable->withSwiftMessage(function ($message) {
                try {
                    if (is_object($message) && method_exists($message, 'getHeaders')) {
                        $message->getHeaders()->addTextHeader('x-mailgun-native-send', 'true');
                    }
                } catch (\Throwable $e) {
                    // Safe fallback if header cannot be added
                }
            });
        } catch (\Throwable $e) {
            // withSwiftMessage not available (Symfony Mailer); header is optional
        }

        if (!empty($this->extraData)) {
            $mailable->with($this->extraData);
        }

        if (!empty($this->ccEmails)) {
            foreach ($this->ccEmails as $cc) {
                if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                    $mailable->cc($cc);
                }
            }
        }

        return $mailable;
    }
}
