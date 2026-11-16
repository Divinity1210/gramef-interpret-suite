@extends('layouts.app')

@push('styles')
    <style>
        .chat-layout {
            display: flex;
            max-width: 1200px;
            margin: 0 auto;
            height: calc(100vh - 120px);
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 8px rgba(0, 0, 0, 0.08);
        }

        .chat-sidebar-panel {
            width: 300px;
            border-right: 1px solid #eee;
            display: flex;
            flex-direction: column;
            background: #fafbfc;
        }

        .chat-sidebar-panel-header {
            padding: 16px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-sidebar-panel-header h3 {
            margin: 0;
            font-size: 16px;
        }

        .chat-sidebar-panel-list {
            flex: 1;
            overflow-y: auto;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .chat-sidebar-panel-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            text-decoration: none;
            color: inherit;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.15s;
        }

        .chat-sidebar-panel-item:hover {
            background: #f0f5ff;
        }

        .chat-sidebar-panel-item.active {
            background: #e8f0fe;
            border-left: 3px solid #4A90D9;
        }

        .sb-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #4A90D9;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
            margin-right: 10px;
        }

        .sb-info {
            flex: 1;
            min-width: 0;
        }

        .sb-name {
            font-weight: 600;
            font-size: 13px;
        }

        .sb-preview {
            font-size: 12px;
            color: #999;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-main-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .chat-main-panel-header {
            padding: 14px 20px;
            background: #fff;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-main-panel-header .cm-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4A90D9;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        .chat-main-panel-header .cm-info h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .chat-main-panel-header .cm-info span {
            font-size: 12px;
            color: #999;
        }

        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f5f7fa;
            display: flex;
            flex-direction: column;
        }

        .msg-bubble {
            max-width: 65%;
            padding: 10px 14px;
            border-radius: 16px;
            margin-bottom: 8px;
            position: relative;
            word-wrap: break-word;
        }

        .msg-bubble.mine {
            align-self: flex-end;
            background: #4A90D9;
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .msg-bubble.theirs {
            align-self: flex-start;
            background: #fff;
            color: #333;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
        }

        .msg-text {
            font-size: 14px;
            line-height: 1.45;
            margin-bottom: 4px;
        }

        .msg-meta {
            font-size: 11px;
            opacity: 0.7;
            text-align: right;
        }

        .msg-sender-name {
            font-size: 11px;
            font-weight: 600;
            color: #4A90D9;
            margin-bottom: 4px;
        }

        .date-divider {
            text-align: center;
            margin: 16px 0;
            font-size: 12px;
            color: #aaa;
        }

        .date-divider span {
            background: #eee;
            padding: 4px 12px;
            border-radius: 12px;
        }

        .messages-empty {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            font-size: 15px;
        }

        .chat-input-bar {
            padding: 12px 20px;
            background: #fff;
            border-top: 1px solid #eee;
            display: flex;
            align-items: flex-end;
            gap: 10px;
        }

        .chat-input-bar textarea {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 10px 16px;
            font-size: 14px;
            resize: none;
            max-height: 100px;
            outline: none;
            font-family: inherit;
        }

        .chat-input-bar textarea:focus {
            border-color: #4A90D9;
        }

        .chat-send-btn {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #4A90D9;
            color: #fff;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .chat-send-btn:hover {
            background: #3A7BC8;
        }

        .new-chat-sm-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #4A90D9;
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 14px;
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

        @media (max-width: 768px) {
            .chat-sidebar-panel {
                display: none;
            }

            .chat-layout {
                height: calc(100vh - 100px);
            }
        }
    </style>
@endpush

@section('content')
    <div class="chat-layout">
        <!-- Sidebar -->
        <div class="chat-sidebar-panel">
            <div class="chat-sidebar-panel-header">
                <h3><i class="fas fa-comments"></i> Chats</h3>
                <button class="new-chat-sm-btn" id="openNewChatBtn2" title="New conversation">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <ul class="chat-sidebar-panel-list">
                @foreach($conversations as $conv)
                    <a href="{{ route('admin.chat.show', $conv['id']) }}"
                        class="chat-sidebar-panel-item {{ $conv['id'] == $conversation->id ? 'active' : '' }}">
                        <div class="sb-avatar">
                            {{ strtoupper(substr(optional($conv['other_user'])->first_name ?? '?', 0, 1) . substr(optional($conv['other_user'])->last_name ?? '', 0, 1)) }}
                        </div>
                        <div class="sb-info">
                            <div class="sb-name">
                                {{ optional($conv['other_user'])->first_name }} {{ optional($conv['other_user'])->last_name }}
                            </div>
                            <div class="sb-preview">
                                @if($conv['last_message'])
                                    {{ \Illuminate\Support\Str::limit($conv['last_message']->body, 30) }}
                                @else
                                    <em>No messages</em>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </ul>
        </div>

        <!-- Main Chat -->
        <div class="chat-main-panel">
            <div class="chat-main-panel-header">
                <a href="{{ route('admin.chat.index') }}" class="cm-back-link">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="cm-avatar">
                    {{ strtoupper(substr(optional($other)->first_name ?? '?', 0, 1) . substr(optional($other)->last_name ?? '', 0, 1)) }}
                </div>
                <div class="cm-info">
                    <h3>{{ optional($other)->first_name }} {{ optional($other)->last_name }}</h3>
                    <span>Agent</span>
                </div>
            </div>

            <div class="messages-area" id="messagesArea">
                @if($messages->count())
                    @php $lastDate = null; @endphp
                    @foreach($messages as $msg)
                        @php $msgDate = $msg->created_at->timezone('Europe/London')->format('M d, Y'); @endphp
                        @if($msgDate !== $lastDate)
                            <div class="date-divider"><span>{{ $msgDate }}</span></div>
                            @php $lastDate = $msgDate; @endphp
                        @endif
                        <div class="msg-bubble {{ $msg->user_id == auth()->id() ? 'mine' : 'theirs' }}"
                            data-msg-id="{{ $msg->id }}">
                            @if($msg->user_id != auth()->id())
                                <div class="msg-sender-name">{{ optional($msg->user)->first_name }}</div>
                            @endif
                            <div class="msg-text">{{ $msg->body }}</div>
                            <div class="msg-meta">
                                {{ $msg->created_at->timezone('Europe/London')->format('H:i') }}
                                @if($msg->user_id == auth()->id())
                                    <i class="fas {{ $msg->read_at ? 'fa-check-double' : 'fa-check' }}"></i>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="messages-empty">
                        <div>
                            <i class="fas fa-comment-dots" style="font-size: 40px; margin-bottom: 12px; display: block;"></i>
                            <div>No messages yet. Say hello!</div>
                        </div>
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.chat.sendMessage', $conversation->id) }}" class="chat-input-bar"
                id="chatForm">
                @csrf
                <textarea name="body" id="chatInput" placeholder="Type a message..." rows="1" required
                    maxlength="5000"></textarea>
                <button type="submit" class="chat-send-btn" title="Send">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- New Conversation Modal -->
    <div class="chat-modal-overlay" id="newChatModal2">
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
                    <button type="button" class="chat-btn-cancel" id="closeNewChatBtn2">Cancel</button>
                    <button type="submit" class="chat-btn-start">Start Chat</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Scroll to bottom of messages
            var area = document.getElementById('messagesArea');
            if (area) area.scrollTop = area.scrollHeight;

            // Auto-resize textarea
            var input = document.getElementById('chatInput');
            if (input) {
                input.addEventListener('input', function () {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 100) + 'px';
                });
                // Enter to send (shift+enter for newline)
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        document.getElementById('chatForm').submit();
                    }
                });
            }

            // Modal toggle
            var modal2 = document.getElementById('newChatModal2');
            var openBtn2 = document.getElementById('openNewChatBtn2');
            var closeBtn2 = document.getElementById('closeNewChatBtn2');
            if (openBtn2) openBtn2.addEventListener('click', function () { modal2.classList.add('active'); });
            if (closeBtn2) closeBtn2.addEventListener('click', function () { modal2.classList.remove('active'); });
            if (modal2) modal2.addEventListener('click', function (e) { if (e.target === modal2) modal2.classList.remove('active'); });

            // Poll for new messages every 5 seconds
            var conversationId = {{ $conversation->id }};
            var lastMsgId = 0;
            document.querySelectorAll('[data-msg-id]').forEach(function (el) {
                var id = parseInt(el.getAttribute('data-msg-id'));
                if (id > lastMsgId) lastMsgId = id;
            });

            setInterval(function () {
                fetch('/admin/chat/' + conversationId + '/poll?after=' + lastMsgId, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.messages && data.messages.length) {
                            var msgArea = document.getElementById('messagesArea');
                            var empty = msgArea.querySelector('.messages-empty');
                            if (empty) empty.remove();

                            data.messages.forEach(function (msg) {
                                var bubble = document.createElement('div');
                                bubble.className = 'msg-bubble ' + (msg.is_mine ? 'mine' : 'theirs');
                                bubble.setAttribute('data-msg-id', msg.id);
                                var html = '';
                                if (!msg.is_mine) html += '<div class="msg-sender-name">' + msg.user_name + '</div>';
                                html += '<div class="msg-text">' + msg.body.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
                                html += '<div class="msg-meta">' + msg.created_at + '</div>';
                                bubble.innerHTML = html;
                                msgArea.appendChild(bubble);
                                lastMsgId = Math.max(lastMsgId, msg.id);
                            });
                            msgArea.scrollTop = msgArea.scrollHeight;
                        }
                    })
                    .catch(function () { });
            }, 5000);
        });
    </script>
@endpush