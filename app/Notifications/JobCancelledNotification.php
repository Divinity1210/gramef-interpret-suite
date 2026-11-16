<?php

namespace App\Notifications;

use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class JobCancelledNotification extends Notification
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
        $title = 'Job Cancelled';
        $body = "Job #{$this->job->id} has been cancelled.";

        try {
            if ($this->job->appointment_date) {
                $body = "The {$this->jobType} job on " . $this->job->appointment_date->format('d M Y') . " has been cancelled.";
            }
        } catch (\Throwable $e) {
        }

        $data = [
            'type' => 'job_cancelled',
            'title' => $title,
            'body' => $body,
            'url' => '/(tabs)/jobs',
            'icon' => '/icon-192.png',
            'extra' => [
                'jobId' => $this->job->id,
                'jobType' => $this->jobType,
            ],
            'tag' => "job-cancel-{$this->job->id}",
        ];

        try {
            PushNotificationService::sendToUser($notifiable, $title, $body, $data);
        } catch (\Throwable $e) {
            error_log('PUSH_SEND_ERROR JobCancelled user=' . $notifiable->id . ' err=' . $e->getMessage());
        }

        return $data;
    }
}
