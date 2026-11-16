<?php

namespace App\Notifications;

use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class JobReminder extends Notification
{
    use Queueable;

    /**
     * Hours remaining until the job starts (used to customize messaging)
     *
     * @var int
     */
    protected $hoursToGo = 24;


    public function __construct(int $hoursToGo = 24)
    {
        $this->hoursToGo = $hoursToGo;
    }


    public function via($notifiable)
    {
        return ['database'];
    }


    public function toArray($notifiable)
    {
        $job = $notifiable->interpreter_job ?? null;
        $language = optional(optional($job)->toLanguage)->name ?? 'Interpreting';
        $title = $this->hoursToGo === 24 ? 'Job Tomorrow' : 'Job Starting Soon';
        $body = $this->hoursToGo === 24
            ? "Reminder: {$language} job starts tomorrow"
            : "Reminder: {$language} job starts in 3 hours";

        $data = [
            'type' => 'reminder',
            'title' => $title,
            'body' => $body,
            'url' => $job ? "/(tabs)/jobs/interpreter-{$job->id}" : '/(tabs)/jobs',
            'icon' => '/icon-192.png',
            'extra' => [
                'jobId' => optional($job)->id,
                'hoursToGo' => $this->hoursToGo,
            ],
            'tag' => $job ? "reminder-{$this->hoursToGo}h-{$job->id}" : "reminder-{$this->hoursToGo}h",
        ];

        return $data;
    }
}
