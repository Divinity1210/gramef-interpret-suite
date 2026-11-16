<?php

/**
 * Test Push Notification endpoint.
 * 
 * Usage: POST /api/mobile/test-push (with Bearer token)
 * 
 * Sends a test push notification to the authenticated user.
 * DELETE this file after testing!
 */

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\PushSubscription;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

// Ensure web-push autoloader is loaded for diagnostics
require_once app_path('Services/webpush_autoload.php');

class TestPushController extends Controller
{
    public function send(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            $subscriptions = PushSubscription::where('user_id', $user->id)->get();

            if ($subscriptions->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No push subscriptions found for this user.',
                    'user_id' => $user->id,
                ]);
            }

            $title = '🔔 Test Notification';
            $body = 'Push notifications are working perfectly! (' . date('H:i:s') . ')';
            $data = [
                'type' => 'test_push',
                'url' => '/(tabs)',
                'timestamp' => time(),
            ];

            $report = PushNotificationService::notifyUserDetailed((int) $user->id, $title, $body, $data);

            return response()->json([
                'success' => true,
                'message' => 'Test push attempt completed',
                'report' => $report,
                'diagnostics' => [
                    'user_id' => $user->id,
                    'subscriptions_count' => $subscriptions->count(),
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('TEST_PUSH_ERROR: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ], 500);
        }
    }

    /**
     * Check push subscription status (GET).
     */
    public function status(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            $subscriptions = PushSubscription::where('user_id', $user->id)->get();

            return response()->json([
                'success' => true,
                'user_id' => $user->id,
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'subscriptions_count' => $subscriptions->count(),
                'subscriptions' => $subscriptions->map(function($s) {
                    return [
                        'id' => $s->id,
                        'platform' => $s->platform,
                        'has_p256dh' => !empty($s->p256dh_key),
                        'has_auth' => !empty($s->auth_key),
                        'user_agent' => $s->user_agent,
                        'created_at' => $s->created_at,
                        'endpoint_preview' => substr($s->endpoint, 0, 80) . '...',
                    ];
                }),
                'vapid_configured' => !empty(env('VAPID_PUBLIC_KEY')) && !empty(env('VAPID_PRIVATE_KEY')),
                'webpush_library' => class_exists('\Minishlink\WebPush\WebPush'),
                'php_version' => PHP_VERSION,
                'diagnostics' => [
                    'autoloader_path' => app_path('Services/webpush_autoload.php'),
                    'autoloader_exists' => file_exists(app_path('Services/webpush_autoload.php')),
                    'vendor_minishlink_exists' => is_dir(base_path('vendor/minishlink/web-push')),
                    'vendor_webpush_src' => is_dir(base_path('vendor/minishlink/web-push/src')),
                    'webpush_class_file' => file_exists(base_path('vendor/minishlink/web-push/src/WebPush.php')),
                    'spomky_base64url_exists' => is_dir(base_path('vendor/spomky-labs/base64url')),
                    'webtoken_exists' => is_dir(base_path('vendor/web-token/jwt-library')),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
