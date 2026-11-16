<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Conversation;
use App\Http\Controllers\Controller;
use App\Message;
use App\Services\PushNotificationService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        try {
            $conversations = Conversation::query()
                ->whereHas('participants', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->with([
                    'participants:id,first_name,last_name',
                    'lastMessage.user:id,first_name,last_name',
                ])
                ->orderByDesc('updated_at')
                ->get();

            $data = $conversations->map(function (Conversation $c) use ($user) {
                $other = $c->participants->first(function ($p) use ($user) {
                    return (int) $p->id !== (int) $user->id;
                });

                $last = $c->lastMessage;

                return [
                    'id' => (int) $c->id,
                    'other_user' => $other ? $this->userSummary($other) : null,
                    'last_message' => $last ? [
                        'id' => (int) $last->id,
                        'body' => (string) $last->body,
                        'type' => (string) ($last->type ?? 'text'),
                        'created_at' => optional($last->created_at)->toIso8601String(),
                        'is_mine' => (int) $last->user_id === (int) $user->id,
                    ] : null,
                    'has_unread' => $last ? ((int) $last->user_id !== (int) $user->id && $last->read_at === null) : false,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR conversations.index ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'data' => [],
            ]);
        }
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        try {
            $conversation = Conversation::query()
                ->where('id', $id)
                ->whereHas('participants', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->with('participants:id,first_name,last_name')
                ->first();
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR conversations.show.1 ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Messaging is unavailable'
            ], 503);
        }

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found'
            ], 404);
        }

        $other = $conversation->participants->first(function ($p) use ($user) {
            return (int) $p->id !== (int) $user->id;
        });

        try {
            $messages = Message::query()
                ->where('conversation_id', $conversation->id)
                ->with('user:id,first_name,last_name')
                ->orderBy('id', 'asc')
                ->get()
                ->map(function (Message $m) use ($user) {
                    return [
                        'id' => (int) $m->id,
                        'body' => (string) $m->body,
                        'type' => (string) ($m->type ?? 'text'),
                        'created_at' => optional($m->created_at)->toIso8601String(),
                        'read_at' => $m->read_at ? $m->read_at->toIso8601String() : null,
                        'is_mine' => (int) $m->user_id === (int) $user->id,
                        'user' => $m->user ? $this->userSummary($m->user) : null,
                    ];
                })
                ->values();
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR conversations.show.2 ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Messaging is unavailable'
            ], 503);
        }

        return response()->json([
            'conversation' => [
                'id' => (int) $conversation->id,
                'other_user' => $other ? $this->userSummary($other) : null,
            ],
            'messages' => $messages,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'user_id' => 'required|integer|exists:users,id|different:' . $user->id,
        ]);

        $otherUserId = (int) $request->get('user_id');

        $existing = Conversation::query()
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->whereHas('participants', function ($q) use ($otherUserId) {
                $q->where('users.id', $otherUserId);
            })
            ->whereDoesntHave('participants', function ($q) use ($user, $otherUserId) {
                $q->whereNotIn('users.id', [$user->id, $otherUserId]);
            })
            ->first();

        if ($existing) {
            return $this->show($request, $existing->id);
        }

        DB::beginTransaction();
        try {
            $conversation = Conversation::create();
            $conversation->participants()->attach([$user->id, $otherUserId]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            error_log('MOBILE_API_ERROR conversations.store ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Failed to create conversation'
            ], 503);
        }

        return $this->show($request, $conversation->id);
    }

    public function sendMessage(Request $request, $id)
    {
        $user = $request->user();

        try {
            $conversation = Conversation::query()
                ->where('id', $id)
                ->whereHas('participants', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->first();
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR conversations.sendMessage.1 ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Messaging is unavailable'
            ], 503);
        }

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found'
            ], 404);
        }

        $request->validate([
            'body' => 'required|string|max:5000',
            'type' => 'nullable|string|max:50',
        ]);

        try {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'body' => $request->get('body'),
                'type' => $request->get('type') ?: 'text',
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR conversations.sendMessage.2 ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));
            return response()->json([
                'success' => false,
                'message' => 'Messaging is unavailable'
            ], 503);
        }

        $conversation->touch();

        // Send push notification to other participants
        try {
            $senderName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Someone';
            $preview = \Illuminate\Support\Str::limit($message->body, 80);
            $otherParticipants = $conversation->participants()->where('users.id', '!=', $user->id)->get();
            foreach ($otherParticipants as $participant) {
                PushNotificationService::sendToUser(
                    $participant,
                    'New message from ' . $senderName,
                    $preview,
                    [
                        'type' => 'chat_message',
                        'url' => '/chat/' . $conversation->id,
                        'conversation_id' => $conversation->id,
                        'tag' => 'chat-' . $conversation->id,
                    ]
                );
            }
        } catch (\Throwable $e) {
            error_log('PUSH_NOTIFY_ERROR chat msg=' . substr($e->getMessage(), 0, 200));
        }

        return response()->json([
            'id' => (int) $message->id,
            'body' => (string) $message->body,
            'type' => (string) ($message->type ?? 'text'),
            'created_at' => optional($message->created_at)->toIso8601String(),
            'is_mine' => true,
            'user' => $this->userSummary($user),
        ], 201);
    }

    private function userSummary(User $u): array
    {
        return [
            'id' => (int) $u->id,
            'name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
        ];
    }
}
