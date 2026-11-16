<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\PushSubscription;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Register or update a push subscription.
     *
     * Handles both web push (endpoint + p256dh + auth) and
     * native Expo push tokens (endpoint only).
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $endpoint = $request->input('endpoint');
            $platform = $request->input('platform', 'web');

            if (!$endpoint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint is required'
                ], 422);
            }

            // Validate platform
            if (!in_array($platform, ['web', 'ios', 'android'])) {
                $platform = 'web';
            }

            // Upsert: update if same user+endpoint, otherwise create
            $subscription = PushSubscription::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'endpoint' => $endpoint,
                ],
                [
                    'platform' => $platform,
                    'p256dh_key' => $request->input('keys.p256dh') ?? $request->input('p256dh_key'),
                    'auth_key' => $request->input('keys.auth') ?? $request->input('auth_key'),
                    'user_agent' => $request->input('user_agent', $request->userAgent()),
                ]
            );

            if (in_array($platform, ['ios', 'android'], true)) {
                PushSubscription::where('user_id', $user->id)
                    ->where('platform', $platform)
                    ->where('endpoint', '!=', $endpoint)
                    ->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Push subscription registered',
                'data' => [
                    'id' => $subscription->id,
                    'platform' => $subscription->platform,
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR push-subscriptions.store ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Failed to register push subscription'
            ], 500);
        }
    }

    /**
     * Remove a push subscription (logout / unsubscribe).
     */
    public function destroy(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $endpoint = $request->input('endpoint');

            if (!$endpoint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint is required'
                ], 422);
            }

            $deleted = PushSubscription::where('user_id', $user->id)
                ->where('endpoint', $endpoint)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => $deleted ? 'Subscription removed' : 'Subscription not found',
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR push-subscriptions.destroy ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove push subscription'
            ], 500);
        }
    }
}
