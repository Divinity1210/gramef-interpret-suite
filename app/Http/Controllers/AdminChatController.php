<?php

namespace App\Http\Controllers;

use App\Conversation;
use App\Message;
use App\User;
use App\Agent;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminChatController extends Controller
{
    /**
     * Show the admin messaging dashboard — list all conversations.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->with([
                'participants:id,first_name,last_name',
                'lastMessage.user:id,first_name,last_name',
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($c) use ($user) {
                $other = $c->participants->first(function ($p) use ($user) {
                    return (int) $p->id !== (int) $user->id;
                });
                return [
                    'id' => $c->id,
                    'other_user' => $other,
                    'last_message' => $c->lastMessage,
                    'has_unread' => $c->lastMessage
                        && (int) $c->lastMessage->user_id !== (int) $user->id
                        && $c->lastMessage->read_at === null,
                    'updated_at' => $c->updated_at,
                ];
            });

        // Get list of agents for "New Conversation" dropdown
        $agents = Agent::with('user:id,first_name,last_name')
            ->whereHas('user', function ($q) {
                $q->where('enabled', 1);
            })
            ->get()
            ->map(function ($a) {
                return [
                    'id' => optional($a->user)->id,
                    'name' => optional($a->user)->first_name . ' ' . optional($a->user)->last_name,
                ];
            })
            ->filter(function ($a) {
                return $a['id'] !== null;
            })
            ->sortBy('name')
            ->values();

        return view('admin.chat.index', compact('conversations', 'agents'));
    }

    /**
     * Show a single conversation.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $conversation = Conversation::query()
            ->where('id', $id)
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->with('participants:id,first_name,last_name')
            ->first();

        if (!$conversation) {
            return redirect()->route('admin.chat.index')->with('error', 'Conversation not found.');
        }

        $other = $conversation->participants->first(function ($p) use ($user) {
            return (int) $p->id !== (int) $user->id;
        });

        $messages = Message::where('conversation_id', $conversation->id)
            ->with('user:id,first_name,last_name')
            ->orderBy('id', 'asc')
            ->get();

        // Mark unread messages as read
        Message::where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Get list of agents for "New Conversation" dropdown
        $agents = Agent::with('user:id,first_name,last_name')
            ->whereHas('user', function ($q) {
                $q->where('enabled', 1);
            })
            ->get()
            ->map(function ($a) {
                return [
                    'id' => optional($a->user)->id,
                    'name' => optional($a->user)->first_name . ' ' . optional($a->user)->last_name,
                ];
            })
            ->filter(function ($a) {
                return $a['id'] !== null;
            })
            ->sortBy('name')
            ->values();

        // Also get conversations for sidebar
        $conversations = Conversation::query()
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->with([
                'participants:id,first_name,last_name',
                'lastMessage.user:id,first_name,last_name',
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($c) use ($user) {
                $otherP = $c->participants->first(function ($p) use ($user) {
                    return (int) $p->id !== (int) $user->id;
                });
                return [
                    'id' => $c->id,
                    'other_user' => $otherP,
                    'last_message' => $c->lastMessage,
                    'has_unread' => $c->lastMessage
                        && (int) $c->lastMessage->user_id !== (int) $user->id
                        && $c->lastMessage->read_at === null,
                    'updated_at' => $c->updated_at,
                ];
            });

        return view('admin.chat.show', compact('conversation', 'other', 'messages', 'agents', 'conversations'));
    }

    /**
     * Start a new conversation with an agent.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $otherUserId = (int) $request->get('user_id');

        if (!$otherUserId || $otherUserId === $user->id) {
            return redirect()->route('admin.chat.index')->with('error', 'Invalid user selected.');
        }

        // Check if conversation already exists
        $existing = Conversation::query()
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->whereHas('participants', function ($q) use ($otherUserId) {
                $q->where('users.id', $otherUserId);
            })
            ->first();

        if ($existing) {
            return redirect()->route('admin.chat.show', $existing->id);
        }

        DB::beginTransaction();
        try {
            $conversation = Conversation::create();
            $conversation->participants()->attach([$user->id, $otherUserId]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route('admin.chat.index')->with('error', 'Failed to create conversation.');
        }

        return redirect()->route('admin.chat.show', $conversation->id);
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(Request $request, $id)
    {
        $user = $request->user();

        $conversation = Conversation::where('id', $id)
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->first();

        if (!$conversation) {
            return redirect()->route('admin.chat.index')->with('error', 'Conversation not found.');
        }

        $request->validate(['body' => 'required|string|max:5000']);

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body' => $request->get('body'),
            'type' => 'text',
        ]);

        $conversation->touch();

        // Send push notification to the other participant(s)
        try {
            $senderName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Admin';
            $preview = \Illuminate\Support\Str::limit($request->get('body'), 80);
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
            error_log('PUSH_NOTIFY_ERROR admin_chat msg=' . substr($e->getMessage(), 0, 200));
        }

        return redirect()->route('admin.chat.show', $conversation->id);
    }

    /**
     * JSON endpoint for polling new messages (AJAX).
     */
    public function poll(Request $request, $id)
    {
        $user = $request->user();

        $conversation = Conversation::where('id', $id)
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->first();

        if (!$conversation) {
            return response()->json(['messages' => []], 404);
        }

        $after = (int) $request->get('after', 0);

        $messages = Message::where('conversation_id', $conversation->id)
            ->where('id', '>', $after)
            ->with('user:id,first_name,last_name')
            ->orderBy('id', 'asc')
            ->get();

        // Mark them as read
        Message::where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $user->id)
            ->where('id', '>', $after)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'messages' => $messages->map(function ($m) use ($user) {
                return [
                    'id' => $m->id,
                    'body' => $m->body,
                    'is_mine' => (int) $m->user_id === (int) $user->id,
                    'user_name' => optional($m->user)->first_name . ' ' . optional($m->user)->last_name,
                    'created_at' => optional($m->created_at) ? $m->created_at->timezone('Europe/London')->format('H:i') : null,
                    'created_at_full' => optional($m->created_at)->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * JSON endpoint: return total unread message count for the current user.
     * Used by the header notification badge (polls every 30s).
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();

        try {
            // Get all conversation IDs where this user is a participant
            $conversationIds = DB::table('conversation_user')
                ->where('user_id', $user->id)
                ->pluck('conversation_id');

            // Count unread messages (not sent by this user, not yet read)
            $count = Message::whereIn('conversation_id', $conversationIds)
                ->where('user_id', '!=', $user->id)
                ->whereNull('read_at')
                ->count();

            // Get latest unread message info for the banner
            $latest = null;
            if ($count > 0) {
                $latestMsg = Message::whereIn('conversation_id', $conversationIds)
                    ->where('user_id', '!=', $user->id)
                    ->whereNull('read_at')
                    ->with('user:id,first_name,last_name')
                    ->orderByDesc('id')
                    ->first();

                if ($latestMsg) {
                    $latest = [
                        'sender' => trim(optional($latestMsg->user)->first_name . ' ' . optional($latestMsg->user)->last_name),
                        'body' => \Illuminate\Support\Str::limit($latestMsg->body, 60),
                        'conversation_id' => $latestMsg->conversation_id,
                        'time' => optional($latestMsg->created_at)->diffForHumans(null, true, true),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Tables may not exist yet
            $count = 0;
            $latest = null;
        }

        return response()->json([
            'count' => $count,
            'latest' => $latest,
        ]);
    }
}
