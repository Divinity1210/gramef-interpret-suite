<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class SmsService
{
    /**
     * Send an SMS message using the configured provider.
     * Returns true if the provider reports success, else false.
     */
    public static function send(string $phoneNumber, string $message): bool
    {
        // Basic validation
        $phoneNumber = trim($phoneNumber ?? '');
        if ($phoneNumber === '') {
            Log::warning('SMS not sent: empty phone number');
            return false;
        }

        $enabled = (bool) (Config::get('sms.enabled', false));
        if (!$enabled) {
            Log::info('SMS disabled via configuration. Skipping SMS send.');
            return false;
        }

        $driver = Config::get('sms.driver');
        switch ($driver) {
            case 'twilio':
                return self::sendViaTwilio($phoneNumber, $message);
            case 'aws_sns':
                return self::sendViaAwsSns($phoneNumber, $message);
            default:
                Log::warning('SMS driver not configured or unsupported: ' . ($driver ?: 'null'));
                return false;
        }
    }

    protected static function sendViaTwilio(string $phoneNumber, string $message): bool
    {
        $sid = Config::get('sms.twilio.sid');
        $token = Config::get('sms.twilio.token');
        $from = Config::get('sms.twilio.from');

        if (!$sid || !$token || !$from) {
            Log::warning('Twilio SMS missing configuration (sid/token/from).');
            return false;
        }

        try {
            $url = sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $sid);
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post($url, [
                    'From' => $from,
                    'To' => $phoneNumber,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                Log::info('Twilio SMS sent successfully to ' . $phoneNumber);
                return true;
            }

            Log::error('Twilio SMS failed: ' . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::error('Twilio SMS exception: ' . $e->getMessage());
            return false;
        }
    }

    protected static function sendViaAwsSns(string $phoneNumber, string $message): bool
    {
        // Using AWS SNS via HTTP API is not available; rely on SDK if present.
        try {
            if (!class_exists('Aws\\Sns\\SnsClient')) {
                Log::warning('AWS SNS SDK not available.');
                return false;
            }

            $config = [
                'version' => 'latest',
                'region' => Config::get('sms.aws_sns.region', 'eu-west-1'),
                'credentials' => [
                    'key' => Config::get('sms.aws_sns.key'),
                    'secret' => Config::get('sms.aws_sns.secret'),
                ],
            ];

            $client = new \Aws\Sns\SnsClient($config);
            $args = [
                'Message' => $message,
                'PhoneNumber' => $phoneNumber,
            ];

            // Optional: Sender ID and SMS type
            $senderId = Config::get('sms.aws_sns.sender_id');
            $smsType = Config::get('sms.aws_sns.sms_type', 'Transactional');
            $args['MessageAttributes'] = [
                'AWS.SNS.SMS.SenderID' => [
                    'DataType' => 'String',
                    'StringValue' => $senderId ?: 'AtoZ',
                ],
                'AWS.SNS.SMS.SMSType' => [
                    'DataType' => 'String',
                    'StringValue' => $smsType,
                ],
            ];

            $result = $client->publish($args);
            $messageId = $result['MessageId'] ?? null;
            if ($messageId) {
                Log::info('AWS SNS SMS sent successfully to ' . $phoneNumber . ' (MessageId: ' . $messageId . ')');
                return true;
            }
            Log::error('AWS SNS SMS failed: ' . json_encode($result));
            return false;
        } catch (\Throwable $e) {
            Log::error('AWS SNS SMS exception: ' . $e->getMessage());
            return false;
        }
    }
}