@extends('layouts.app')

@push('styles')
    <style>
        .chat-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px;
        }

        .chat-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .chat-header-bar h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }

        .new-chat-btn {
            background: #4A90D9;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .new-chat-btn:hover {
            background: #3A7BC8;
        }

        .conversation-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            transition: background 0.15s;
            border-bottom: 1px solid #eee;
        }

        .conversation-item:hover {
            background: #f5f7fa;
        }

        .conversation-item.unread {
            background: #f0f5ff;
        }

        .conv-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #4A90D9;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
            margin-right: 14px;
        }

        .conv-info {
            flex: 1;
            min-width: 0;
        }

        .conv-name {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 3px;
        }

        .conv-name.unread {
            font-weight: 700;
        }

        .conv-last-msg {
            font-size: 13px;
            color: #888;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conv-last-msg.unread {
            color: #333;
            font-weight: 500;
        }

        .conv-time {
            font-size: 12px;
            color: #aaa;
            white-space: nowrap;
            margin-left: 12px;
        }

        .conv-time.unread {
            color: #4A90D9;
            font-weight: 600;
        }

        .unread-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #4A90D9;
            margin-left: 8px;
            flex-shrink: 0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            color: #666;
            margin-bottom: 8px;
        }

        .chat-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1100;
            align-items: center;
            justify-content: center;
        }

        .chat-modal-overlay.active {
            display: flex;
        }

        .chat-modal-box {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            width: 440px;
            max-width: 90vw;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .chat-modal-box h2 {
            margin: 0 0 16px;
            font-size: 18px;
        }

        .chat-modal-box select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            margin-bottom: 16px;
        }

        .chat-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .chat-modal-actions button {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }

        .chat-btn-cancel {
            background: #eee;
            color: #666;
        }

        .chat-btn-start {
            background: #4A90D9;
            color: #fff;
        }

        .chat-btn-start:hover {
            background: #3A7BC8;
        }
    </style>
@endpush

@section('content')
    <div class="chat-container">
        <div class="chat-header-bar">
            <h1><i class="fas fa-comments"></i> Messages</h1>
            <button class="new-chat-btn" id="openNewChatBtn">
                <i class="fas fa-plus"></i> New Conversation
            </button>
        </div>

        @if($conversations->count())
            <ul class="conversation-list">
                @foreach($conversations as $conv)
                    <a href="{{ route('admin.chat.show', $conv['id']) }}"
                        class="conversation-item {{ $conv['has_unread'] ? 'unread' : '' }}">
                        <div class="conv-avatar">
                            {{ strtoupper(substr(optional($conv['other_user'])->first_name ?? '?', 0, 1) . substr(optional($conv['other_user'])->last_name ?? '', 0, 1)) }}
                        </div>
                        <div class="conv-info">
                            <div class="conv-name {{ $conv['has_unread'] ? 'unread' : '' }}">
                                {{ optional($conv['other_user'])->first_name }} {{ optional($conv['other_user'])->last_name }}
                            </div>
                            <div class="conv-last-msg {{ $conv['has_unread'] ? 'unread' : '' }}">
                                @if($conv['last_message'])
                                    {{ \Illuminate\Support\Str::limit($conv['last_message']->body, 60) }}
                                @else
                                    <em>No messages yet</em>
                                @endif
                            </div>
                        </div>
                        @if($conv['last_message'])
                            <span class="conv-time {{ $conv['has_unread'] ? 'unread' : '' }}">
                                {{ $conv['last_message']->created_at->diffForHumans(null, true, true) }}
                            </span>
                        @endif
                        @if($conv['has_unread'])
                            <div class="unread-dot"></div>
                        @endif
                    </a>
                @endforeach
            </ul>
        @else
            <div class="empty-state">
                <div><i class="fas fa-comments"></i></div>
                <h3>No conversations yet</h3>
                <p>Start a new conversation with an agent using the button above.</p>
            </div>
        @endif
    </div>

    <!-- New Conversation Modal -->
    <div class="chat-modal-overlay" id="newChatModal">
        <div class="chat-modal-box">
            <h2><i class="fas fa-user-plus"></i> New Conversation</h2>
            <form method="POST" action="{{ route('admin.chat.store') }}">
                @csrf
                <select name="user_id" required>
                    <option value="">Select an agent...</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent['id'] }}">{{ $agent['name'] }}</option>
                    @endforeach
                </select>
                <div class="chat-modal-actions">
                    <button type="button" class="chat-btn-cancel" id="closeNewChatBtn">Cancel</button>
                    <button type="submit" class="chat-btn-start">Start Chat</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('newChatModal');
            var openBtn = document.getElementById('openNewChatBtn');
            var closeBtn = document.getElementById('closeNewChatBtn');
            if (openBtn) openBtn.addEventListener('click', function () { modal.classList.add('active'); });
            if (closeBtn) closeBtn.addEventListener('click', function () { modal.classList.remove('active'); });
            if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) modal.classList.remove('active'); });
        });
    </script>
@endpush