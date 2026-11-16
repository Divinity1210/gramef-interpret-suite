<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\SmsService;

class SmsTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:test {number?} {--message=Test SMS from AtoZ}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test SMS using the configured SMS driver and log the outcome.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $enabled = (bool) config('sms.enabled');
        $driver = config('sms.driver');

        $number = $this->argument('number') ?? env('SMS_TEST_NUMBER', '+15005550006');
        $message = (string) $this->option('message');

        $this->info("SMS enabled: " . ($enabled ? 'true' : 'false'));
        $this->info("Driver: {$driver}");
        $this->info("Sending to: {$number}");
        $this->info("Message: {$message}");

        if (!$enabled) {
            Log::debug("[SmsTest] SMS disabled; skipping send to {$number}");
            $this->warn('SMS is disabled (SMS_ENABLED=false). No message sent.');
            return self::SUCCESS;
        }

        $sent = SmsService::send($number, $message);

        if ($sent) {
            $this->info('SMS send reported success. Check logs for provider response.');
            return self::SUCCESS;
        }

        $this->error('SMS send reported failure. Check logs for detailed error.');
        return self::FAILURE;
    }
}