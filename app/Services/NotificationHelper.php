<?php

namespace App\Services;

use App\User;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper for sending in-app + push notifications.
 * All calls are wrapped in try/catch so they NEVER break existing flows.
 */
class NotificationHelper
{
    /**
     * Safely send a notification to a user.
     * If notification fails, logs the error and continues.
     */
    public static function send(?User $user, $notification): void
    {
        if (!$user)
            return;

        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('NotificationHelper: Failed to send notification to user ' . $user->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Safely send a notification to multiple users.
     */
    public static function sendToMany($users, $notificationFactory): void
    {
        foreach ($users as $user) {
            if ($user instanceof User) {
                self::send($user, $notificationFactory($user));
            } elseif ($user && method_exists($user, 'getAttribute')) {
                // Might be a relation like $matchedAgent->agent->user
                $actualUser = null;
                if (isset($user->user)) {
                    $actualUser = $user->user;
                }
                if ($actualUser instanceof User) {
                    self::send($actualUser, $notificationFactory($actualUser));
                }
            }
        }
    }
}
