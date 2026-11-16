<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\BackfillInvoiceItemJobType::class,
        \App\Console\Commands\DiagnoseLogin::class,
        \App\Console\Commands\CleanupLocalStorage::class,
        \App\Console\Commands\MonitorStorage::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('notify_users_of_expiring_dbs')->daily();
        $schedule->command('send-interpreterjob-reminders')->everyMinute()->withoutOverlapping();
        // DISABLED: Sending old/excessive overdue emails — requested cancellation 2026-04-13
        // $schedule->command('send-overdue-invoice-reminders')->dailyAt('09:00');
        $schedule->command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping();
        $schedule->command('storage:cleanup')->dailyAt('02:00');
        $schedule->command('monitor:storage')->dailyAt('03:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
