<?php

namespace App\Notifications;

use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TimesheetSignNotification extends Notification
{
    use Queueable;

    protected $job;
    protected $timesheet;
    protected $signedBy; // 'agent' or 'client'

    public function __construct($job, $timesheet, string $signedBy = 'agent')
    {
        $this->job = $job;
        $this->timesheet = $timesheet;
        $this->signedBy = $signedBy;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        if ($this->signedBy === 'agent') {
            $title = 'Timesheet Needs Your Signature';
            $body = "The interpreter has signed the timesheet for job #{$this->job->id}. Please review and sign.";
        } else {
            $title = 'Timesheet Signed';
            $body = "The client has signed the timesheet for job #{$this->job->id}.";
        }

        $data = [
            'type' => 'timesheet_sign',
            'title' => $title,
            'body' => $body,
            'url' => "/(tabs)/timesheets/{$this->timesheet->id}",
            'icon' => '/icon-192.png',
            'extra' => [
                'jobId' => $this->job->id,
                'timesheetId' => $this->timesheet->id,
                'signedBy' => $this->signedBy,
            ],
            'tag' => "timesheet-{$this->timesheet->id}",
        ];

        try {
            PushNotificationService::sendToUser($notifiable, $title, $body, $data);
        } catch (\Throwable $e) {
            error_log('PUSH_SEND_ERROR TimesheetSign user=' . $notifiable->id . ' err=' . $e->getMessage());
        }

        return $data;
    }
}
