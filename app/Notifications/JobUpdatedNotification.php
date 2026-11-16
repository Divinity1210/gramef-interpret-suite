<?php

namespace App\Notifications;

use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class JobUpdatedNotification extends Notification
{
    use Queueable;

    protected $job;
    protected $jobType;

    public function __construct($job, string $jobType = 'interpreter')
    {
        $this->job = $job;
        $this->jobType = $jobType;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $language = optional($this->job->toLanguage)->name ?? '';
        $title = 'Job Updated';
        $body = $language
            ? "{$language} {$this->jobType} job details were updated"
            : "Your {$this->jobType} job details were updated";

        $data = [
            'type' => 'job_updated',
            'title' => $title,
            'body' => $body,
            'url' => "/(tabs)/jobs/{$this->jobType}-{$this->job->id}",
            'icon' => '/icon-192.png',
            'extra' => [
                'jobId' => $this->job->id,
                'jobType' => $this->jobType,
            ],
            'tag' => "job-updated-{$this->job->id}",
        ];

        try {
            PushNotificationService::sendToUser($notifiable, $title, $body, $data);
        } catch (\Throwable $e) {
            error_log('PUSH_SEND_ERROR JobUpdated user=' . $notifiable->id . ' err=' . $e->getMessage());
        }

        return $data;
    }
}
