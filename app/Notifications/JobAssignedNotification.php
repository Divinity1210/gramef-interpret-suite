<?php

namespace App\Notifications;

use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class JobAssignedNotification extends Notification
{
    use Queueable;

    protected $job;
    protected $jobType; // 'interpreter' or 'translator'

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
        $language = '';
        try {
            $language = optional($this->job->toLanguage)->name ?? '';
        } catch (\Throwable $e) {
        }

        $title = 'New Job Available';
        $body = $language
            ? "{$language} {$this->jobType} job is available"
            : "A new {$this->jobType} job is available";

        if ($this->job->appointment_date) {
            try {
                $body .= ' on ' . $this->job->appointment_date->format('d M Y');
            } catch (\Throwable $e) {
            }
        }

        $data = [
            'type' => 'job_assigned',
            'title' => $title,
            'body' => $body,
            'url' => "/(tabs)/jobs/{$this->jobType}-{$this->job->id}",
            'icon' => '/icon-192.png',
            'extra' => [
                'jobId' => $this->job->id,
                'jobType' => $this->jobType,
            ],
            'tag' => "job-{$this->job->id}",
        ];

        // Also send push notification
        try {
            PushNotificationService::sendToUser($notifiable, $title, $body, $data);
        } catch (\Throwable $e) {
            error_log('PUSH_SEND_ERROR JobAssigned user=' . $notifiable->id . ' err=' . $e->getMessage());
        }

        return $data;
    }
}
