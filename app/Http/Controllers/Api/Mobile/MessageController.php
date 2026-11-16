<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Message;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class MessageController extends Controller
{
    public function markAsRead(Request $request, $id)
    {
        try {
            $user = $request->user();

            $message = Message::query()
                ->where('id', $id)
                ->with('conversation.participants:id')
                ->first();

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found'
                ], 404);
            }

            $isParticipant = (bool) optional($message->conversation)->participants
                ->first(function ($p) use ($user) {
                    return (int) $p->id === (int) $user->id;
                });

            if (!$isParticipant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden'
                ], 403);
            }

            if ((int) $message->user_id === (int) $user->id) {
                return response()->json([
                    'success' => true,
                    'message' => 'OK'
                ]);
            }

            if ($message->read_at === null) {
                $message->read_at = now();
                $message->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'OK'
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR messages.markAsRead ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Messaging is unavailable'
            ], 503);
        }
    }
}
