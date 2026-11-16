<?php

namespace App\Services;

use App\PushSubscription;
use App\User;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private const JOBS_CHANNEL_ID = 'jobs_v2';
    private const MESSAGES_CHANNEL_ID = 'messages_v2';
    private const DEFAULT_CHANNEL_ID = 'default_v2';

    public static function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        return self::notifyUser((int) $user->id, $title, $body, $data);
    }

    public static function notifyUser(int $userId, string $title, string $body, array $data = []): bool
    {
        $report = self::notifyUserDetailed($userId, $title, $body, $data);
        return ($report['sent'] ?? 0) > 0;
    }

    public static function notifyUserDetailed(int $userId, string $title, string $body, array $data = []): array
    {
        $subscriptions = self::selectDeliverySubscriptions(
            PushSubscription::where('user_id', $userId)->get()
        );
        if ($subscriptions->isEmpty()) {
            Log::info('Push skipped: no subscriptions', ['user_id' => $userId]);
            return [
                'user_id' => $userId,
                'attempted' => 0,
                'sent' => 0,
                'results' => [],
            ];
        }

        $sent = 0;
        $results = [];
        foreach ($subscriptions as $subscription) {
            try {
                $result = self::sendForSubscription($subscription, $title, $body, $data);
                $deliveryResult = [
                    'subscription_id' => $subscription->id,
                    'platform' => $subscription->platform,
                    'provider' => self::shouldUseExpo((string) $subscription->platform, (string) $subscription->endpoint) ? 'expo' : 'webpush',
                    'success' => !empty($result['success']),
                    'http_code' => $result['http_code'] ?? null,
                    'error' => $result['error'] ?? null,
                    'cleaned' => (bool) ($result['cleaned'] ?? false),
                ];
                if (!empty($result['success'])) {
                    $sent++;
                    $results[] = $deliveryResult;
                    continue;
                }

                $results[] = $deliveryResult;
                Log::warning('Push delivery failed', [
                    'user_id' => $userId,
                    'subscription_id' => $subscription->id,
                    'platform' => $subscription->platform,
                    'endpoint' => substr((string) $subscription->endpoint, 0, 120),
                    'error' => $result['error'] ?? null,
                    'http_code' => $result['http_code'] ?? null,
                ]);
            } catch (\Throwable $e) {
                $results[] = [
                    'subscription_id' => $subscription->id,
                    'platform' => $subscription->platform,
                    'provider' => self::shouldUseExpo((string) $subscription->platform, (string) $subscription->endpoint) ? 'expo' : 'webpush',
                    'success' => false,
                    'error' => $e->getMessage(),
                    'cleaned' => false,
                ];
                error_log('PUSH_ERROR user=' . $userId . ' endpoint=' . substr((string) $subscription->endpoint, 0, 80) . ' err=' . $e->getMessage());
            }
        }

        return [
            'user_id' => $userId,
            'attempted' => $subscriptions->count(),
            'sent' => $sent,
            'results' => $results,
        ];
    }

    public static function notifyUsers(array $userIds, string $title, string $body, array $data = []): void
    {
        foreach ($userIds as $userId) {
            self::notifyUser((int) $userId, $title, $body, $data);
        }
    }

    private static function sendForSubscription(PushSubscription $sub, string $title, string $body, array $data): array
    {
        $normalizedData = self::normalizePayloadData($data);

        if (self::shouldUseExpo((string) $sub->platform, (string) $sub->endpoint)) {
            return self::sendExpoPush($sub, $title, $body, $normalizedData);
        }

        return self::sendWebPush($sub, $title, $body, $normalizedData);
    }

    private static function shouldUseExpo(string $platform, string $endpoint): bool
    {
        if (in_array($platform, ['ios', 'android'], true)) {
            return true;
        }

        if (strpos($endpoint, 'ExponentPushToken[') === 0) {
            return true;
        }

        return strpos($endpoint, 'exp.host') !== false;
    }

    private static function sendWebPush(PushSubscription $sub, string $title, string $body, array $data): array
    {
        $vapidPublicKey = config('services.vapid.public_key') ?: env('VAPID_PUBLIC_KEY', '');
        $vapidPrivateKey = config('services.vapid.private_key') ?: env('VAPID_PRIVATE_KEY', '');
        $vapidSubject = config('services.vapid.subject') ?: env('VAPID_SUBJECT', 'mailto:admin@atozinterpreting.com');

        if (!$vapidPublicKey || !$vapidPrivateKey) {
            return ['success' => false, 'error' => 'VAPID keys not configured'];
        }

        if (empty($sub->p256dh_key) || empty($sub->auth_key)) {
            return ['success' => false, 'error' => 'Missing web push subscription keys'];
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => '/icon-192.png',
            'badge' => '/icon-96.png',
            'data' => $data,
            'url' => $data['url'] ?? '/',
            'tag' => $data['type'] ?? 'general',
        ]);

        // Prefer the minishlink/web-push library (correct RFC 8291 encryption)
        if (class_exists('\\Minishlink\\WebPush\\WebPush')) {
            return self::sendWebPushViaLibrary($sub, $payload, $vapidPublicKey, $vapidPrivateKey, $vapidSubject);
        }

        // Fallback: custom VAPID + encryption (for production without composer library)
        return self::sendWebPushManual($sub, $payload, $vapidPublicKey, $vapidPrivateKey, $vapidSubject);
    }

    private static function sendWebPushViaLibrary(PushSubscription $sub, string $payload, string $vapidPublicKey, string $vapidPrivateKey, string $vapidSubject): array
    {
        try {
            $auth = [
                'VAPID' => [
                    'subject' => $vapidSubject,
                    'publicKey' => $vapidPublicKey,
                    'privateKey' => $vapidPrivateKey,
                ],
            ];

            $webPush = new \Minishlink\WebPush\WebPush($auth, [], 30, ['verify' => false]);

            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => (string) $sub->endpoint,
                'publicKey' => (string) $sub->p256dh_key,
                'authToken' => (string) $sub->auth_key,
            ]);

            $report = $webPush->sendOneNotification($subscription, $payload, ['TTL' => 86400, 'urgency' => 'high']);

            $httpCode = 0;
            if (method_exists($report, 'getResponse') && $report->getResponse()) {
                $httpCode = $report->getResponse()->getStatusCode();
            }

            if ($report->isSuccess()) {
                return ['success' => true, 'http_code' => $httpCode];
            }

            $reason = $report->getReason();

            if ($report->isSubscriptionExpired()) {
                $sub->delete();
                return ['success' => false, 'error' => 'Subscription expired: ' . $reason, 'cleaned' => true, 'http_code' => $httpCode];
            }

            return ['success' => false, 'http_code' => $httpCode, 'error' => $reason];
        } catch (\Throwable $e) {
            Log::error('WebPush library error', [
                'subscription_id' => $sub->id,
                'endpoint' => substr((string) $sub->endpoint, 0, 80),
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'WebPush exception: ' . $e->getMessage()];
        }
    }

    private static function sendWebPushManual(PushSubscription $sub, string $payload, string $vapidPublicKey, string $vapidPrivateKey, string $vapidSubject): array
    {
        $audience = self::getAudience((string) $sub->endpoint);
        $expiration = time() + 86400;
        $header = self::base64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::base64url(json_encode([
            'aud' => $audience,
            'exp' => $expiration,
            'sub' => $vapidSubject,
        ]));
        $signingInput = "{$header}.{$claims}";
        $privateKeyPem = self::vapidPrivateKeyToPem((string) $vapidPrivateKey, (string) $vapidPublicKey);
        $key = openssl_pkey_get_private($privateKeyPem);
        if (!$key) {
            return ['success' => false, 'error' => 'Invalid VAPID private key'];
        }

        openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256);
        $signature = self::derToRaw($signature);
        $jwt = "{$signingInput}." . self::base64url($signature);
        $encrypted = self::encryptPayload($payload, $sub->p256dh_key, $sub->auth_key);
        if (!$encrypted) {
            return ['success' => false, 'error' => 'Payload encryption failed'];
        }

        $ch = curl_init((string) $sub->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encrypted['ciphertext'],
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'Content-Length: ' . strlen($encrypted['ciphertext']),
                'TTL: 86400',
                'Urgency: high',
                "Authorization: vapid t={$jwt}, k={$vapidPublicKey}",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 410 || $httpCode === 404 || $httpCode === 403) {
            $sub->delete();
            return ['success' => false, 'error' => 'Subscription expired/invalid (HTTP ' . $httpCode . ')', 'cleaned' => true, 'http_code' => $httpCode];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'http_code' => $httpCode];
        }

        return ['success' => false, 'http_code' => $httpCode, 'error' => $error ?: $response];
    }

    private static function sendExpoPush(PushSubscription $sub, string $title, string $body, array $data): array
    {
        $payloadData = self::normalizePayloadData($data);
        $type = (string) ($payloadData['type'] ?? '');
        $channelId = self::resolveExpoChannelId($type);
        $priority = self::resolveExpoPriority($type);

        $ch = curl_init('https://exp.host/--/api/v2/push/send');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'to' => $sub->endpoint,
                'title' => $title,
                'body' => $body,
                'data' => $payloadData,
                'sound' => 'default',
                'priority' => 'high', // Force high priority for background delivery
                'channelId' => $channelId,
                'badge' => 1,
                'ttl' => 86400, // Increase TTL to 24 hours
                'interruptionLevel' => 'active',
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $result = json_decode($response, true);
                
                // Log the full response for diagnostics
                Log::debug('Expo push response', ['subscription_id' => $sub->id, 'response' => $result]);

                $messages = [];
            if (isset($result['data'][0]) && is_array($result['data'][0])) {
                $messages = $result['data'];
            } elseif (isset($result['data']) && is_array($result['data'])) {
                $messages = [$result['data']];
            }
            if (empty($messages) && isset($result['status'])) {
                $messages = [$result];
            }

            foreach ($messages as $message) {
                $status = $message['status'] ?? null;
                if ($status !== 'error') {
                    continue;
                }
                $expoError = $message['details']['error'] ?? ($message['message'] ?? 'Expo rejected notification');
                if (in_array($message['details']['error'] ?? '', ['DeviceNotRegistered', 'InvalidCredentials'], true)) {
                    $sub->delete();
                    return ['success' => false, 'error' => (string) $expoError, 'cleaned' => true, 'http_code' => $httpCode];
                }

                return ['success' => false, 'error' => (string) $expoError, 'http_code' => $httpCode];
            }

            return ['success' => true, 'http_code' => $httpCode];
        }

        return ['success' => false, 'http_code' => $httpCode, 'error' => $error ?: $response];
    }

    private static function resolveExpoChannelId(string $type): string
    {
        if ($type === 'message') {
            return self::MESSAGES_CHANNEL_ID;
        }

        if (in_array($type, ['job_assigned', 'job_update', 'new_job', 'job_cancelled', 'reminder', 'timesheet_sign'], true)) {
            return self::JOBS_CHANNEL_ID;
        }

        return self::DEFAULT_CHANNEL_ID;
    }

    private static function resolveExpoPriority(string $type): string
    {
        if (in_array($type, ['message', 'job_assigned', 'job_update', 'new_job', 'reminder', 'job_cancelled', 'timesheet_sign'], true)) {
            return 'high';
        }

        return 'default';
    }

    private static function selectDeliverySubscriptions($subscriptions)
    {
        $native = $subscriptions
            ->filter(function (PushSubscription $sub) {
                return self::shouldUseExpo((string) $sub->platform, (string) $sub->endpoint);
            })
            ->sortByDesc(function (PushSubscription $sub) {
                return optional($sub->updated_at)->getTimestamp() ?? 0;
            })
            ->unique(function (PushSubscription $sub) {
                return (string) ($sub->platform ?: 'native');
            });

        $web = $subscriptions
            ->reject(function (PushSubscription $sub) {
                return self::shouldUseExpo((string) $sub->platform, (string) $sub->endpoint);
            })
            ->sortByDesc(function (PushSubscription $sub) {
                return optional($sub->created_at)->getTimestamp() ?? 0;
            });

        // Only send to the most recent web subscription to avoid duplicate
        // notifications from stale "Add to Homescreen" registrations
        $latestWeb = $web->take(1);

        return $native->concat($latestWeb)->values();
    }

    private static function normalizeType(string $type): string
    {
        $aliases = [
            'new_message' => 'message',
            'chat_message' => 'message',
            'job_reminder' => 'reminder',
            'job_updated' => 'job_update',
            'new_job_match' => 'job_assigned',
        ];

        return $aliases[$type] ?? $type;
    }

    private static function normalizePayloadData(array $data): array
    {
        $payloadData = $data;
        $type = self::normalizeType((string) ($payloadData['type'] ?? ''));
        if ($type !== '') {
            $payloadData['type'] = $type;
        }
        if (!isset($payloadData['jobId']) && isset($payloadData['extra']['jobId'])) {
            $payloadData['jobId'] = $payloadData['extra']['jobId'];
        }
        if (!isset($payloadData['jobType']) && isset($payloadData['extra']['jobType'])) {
            $payloadData['jobType'] = $payloadData['extra']['jobType'];
        }
        if (!isset($payloadData['conversationId']) && isset($payloadData['conversation_id'])) {
            $payloadData['conversationId'] = $payloadData['conversation_id'];
        }

        return $payloadData;
    }

    private static function getAudience(string $endpoint): string
    {
        $parsed = parse_url($endpoint);
        return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    private static function vapidPrivateKeyToPem(string $privateKeyB64, string $publicKeyB64): string
    {
        $privateKeyRaw = self::base64urlDecode($privateKeyB64);
        $publicKeyRaw = self::base64urlDecode($publicKeyB64);

        $der = "\x30" . chr(119)
            . "\x02\x01\x01"
            . "\x04\x20" . $privateKeyRaw
            . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
            . "\xa1\x44\x03\x42\x00" . $publicKeyRaw;

        return "-----BEGIN EC PRIVATE KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END EC PRIVATE KEY-----\n";
    }

    private static function derToRaw(string $der): string
    {
        $offset = 3;
        $rLen = ord($der[$offset]);
        $offset++;
        $r = substr($der, $offset, $rLen);
        $offset += $rLen + 1;
        $sLen = ord($der[$offset]);
        $offset++;
        $s = substr($der, $offset, $sLen);

        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    private static function encryptPayload(string $payload, ?string $p256dhB64, ?string $authB64): ?array
    {
        if (!$p256dhB64 || !$authB64) {
            return null;
        }

        $userPublicKey = self::base64urlDecode($p256dhB64);
        $userAuth = self::base64urlDecode($authB64);
        $localKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'private_key_bits' => 384,
        ]);
        if (!$localKey) {
            return null;
        }

        $localDetails = openssl_pkey_get_details($localKey);
        $localPublicKey = chr(4)
            . str_pad(self::base64urlDecode(self::base64url($localDetails['ec']['x'])), 32, "\x00", STR_PAD_LEFT)
            . str_pad(self::base64urlDecode(self::base64url($localDetails['ec']['y'])), 32, "\x00", STR_PAD_LEFT);

        $sharedSecret = openssl_pkey_derive(
            openssl_pkey_get_public(self::rawPublicKeyToPem($userPublicKey)),
            $localKey,
            32
        );
        if (!$sharedSecret) {
            return null;
        }

        $salt = random_bytes(16);
        $info = "WebPush: info\x00" . $userPublicKey . $localPublicKey;
        $ikm = self::hkdf($userAuth, $sharedSecret, $info, 32);
        $cek = self::hkdf($salt, $ikm, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = self::hkdf($salt, $ikm, "Content-Encoding: nonce\x00", 12);
        $paddedPayload = $payload . "\x02";

        $tag = '';
        $encrypted = openssl_encrypt(
            $paddedPayload,
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );
        if ($encrypted === false) {
            return null;
        }

        $rs = pack('N', 4096);
        $header = $salt . $rs . chr(65) . $localPublicKey;
        $ciphertext = $header . $encrypted . $tag;

        return ['ciphertext' => $ciphertext];
    }

    private static function hkdf(string $salt, string $ikm, string $info, int $length): string
    {
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        return substr(hash_hmac('sha256', $info . chr(1), $prk, true), 0, $length);
    }

    private static function rawPublicKeyToPem(string $rawKey): string
    {
        $der = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"
            . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
            . "\x03\x42\x00" . $rawKey;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }
}
