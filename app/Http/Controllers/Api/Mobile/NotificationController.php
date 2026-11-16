<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List user's notifications (paginated, newest first).
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $perPage = min((int) $request->get('per_page', 20), 50);

            $notifications = $user->notifications()
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $items = $notifications->getCollection()->map(function ($n) {
                $data = is_string($n->data) ? json_decode($n->data, true) : $n->data;
                return [
                    'id' => $n->id,
                    'type' => $data['type'] ?? 'general',
                    'title' => $data['title'] ?? '',
                    'body' => $data['body'] ?? '',
                    'url' => $data['url'] ?? null,
                    'icon' => $data['icon'] ?? null,
                    'data' => $data['extra'] ?? null,
                    'read' => $n->read_at !== null,
                    'read_at' => $n->read_at ? $n->read_at->toIso8601String() : null,
                    'created_at' => $n->created_at->toIso8601String(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $items,
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR notifications.index ' . get_class($e) . ' msg=' . substr($e->getMessage(), 0, 300));
            return response()->json(['success' => false, 'message' => 'Unable to load notifications'], 503);
        }
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(Request $request)
    {
        try {
            $count = $request->user()->unreadNotifications()->count();
            return response()->json(['success' => true, 'count' => $count]);
        } catch (\Throwable $e) {
            return response()->json(['success' => true, 'count' => 0]);
        }
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            $notification = $request->user()->notifications()->where('id', $id)->first();
            if (!$notification) {
                return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
            }
            $notification->markAsRead();
            return response()->json(['success' => true, 'message' => 'Notification marked as read']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to mark notification'], 500);
        }
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $request->user()->unreadNotifications->markAsRead();
            return response()->json(['success' => true, 'message' => 'All notifications marked as read']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to mark notifications'], 500);
        }
    }
}
