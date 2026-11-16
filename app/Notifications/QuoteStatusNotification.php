<?php

namespace App\Notifications;

use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuoteStatusNotification extends Notification
{
    use Queueable;

    protected $job;
    protected $status; // 'accepted' or 'rejected'

    public function __construct($job, string $status = 'accepted')
    {
        $this->job = $job;
        $this->status = $status;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $title = 'Quote ' . ucfirst($this->status);
        $body = "Your quote for job #{$this->job->id} has been {$this->status}.";

        $data = [
            'type' => 'quote_' . $this->status,
            'title' => $title,
            'body' => $body,
            'url' => "/(tabs)/jobs/interpreter-{$this->job->id}",
            'icon' => '/icon-192.png',
            'extra' => [
                'jobId' => $this->job->id,
                'status' => $this->status,
            ],
            'tag' => "quote-status-{$this->job->id}",
        ];

        try {
            PushNotificationService::sendToUser($notifiable, $title, $body, $data);
        } catch (\Throwable $e) {
            error_log('PUSH_SEND_ERROR QuoteStatus user=' . $notifiable->id . ' err=' . $e->getMessage());
        }

        return $data;
    }
}
