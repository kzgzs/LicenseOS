@extends('layouts.app')
@section('title', __('message.title'))
@section('content')
<div class="max-w-4xl mx-auto flex flex-col" style="height: calc(100vh - 200px); min-height: 500px;">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div>
            <a href="{{ route('projects.contracts.show', $project) }}" class="text-sm text-indigo-600 hover:underline">&larr; {{ __('message.back_to_contract') }}</a>
            <h1 class="text-xl font-bold text-gray-900 mt-1">{{ $project->title }}</h1>
            @if($otherParty)
                <p class="text-sm text-gray-500">{{ __('message.chat_with') }}: {{ $otherParty->name }}</p>
            @endif
        </div>
        <span class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded">{{ __('message.private_notice') }}</span>
    </div>

    {{-- Messages Area --}}
    <div class="flex-1 bg-white rounded-lg shadow-sm flex flex-col overflow-hidden">
        <div id="messages-container" class="flex-1 overflow-y-auto p-4 space-y-4">
            @if($messages->isEmpty())
                <div class="text-center text-gray-400 py-12">
                    <p>{{ __('message.empty') }}</p>
                </div>
            @else
                @foreach($messages as $msg)
                    @php $isMine = $msg->sender_id === auth()->id(); @endphp
                    <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[70%] {{ $isMine ? 'order-2' : 'order-1' }}">
                            <div class="flex items-end gap-2 {{ $isMine ? 'flex-row-reverse' : '' }}">
                                <div class="w-7 h-7 rounded-full bg-{{ $isMine ? 'indigo' : 'gray' }}-200 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                    {{ strtoupper(substr($msg->sender->name ?? '?', 0, 1)) }}
                                </div>
                                <div class="{{ $isMine ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-800' }} rounded-lg px-3 py-2 text-sm">
                                    <p class="whitespace-pre-wrap">{!! nl2br(e($msg->body)) !!}</p>
                                    @if($msg->attachment)
                                        <a href="{{ Storage::url($msg->attachment) }}" target="_blank"
                                           class="inline-flex items-center gap-1 mt-1 text-xs {{ $isMine ? 'text-indigo-200 hover:text-white' : 'text-indigo-600 hover:underline' }}">
                                            &#128206; {{ basename($msg->attachment) }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                            <p class="text-xs text-gray-400 mt-1 {{ $isMine ? 'text-right' : '' }}">
                                {{ $msg->sender->name ?? '-' }} &middot; {{ $msg->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Input Area --}}
        <div class="border-t p-3">
            <form method="POST" action="{{ route('projects.messages.send', $project) }}" enctype="multipart/form-data" class="flex items-end gap-2">
                @csrf
                <div class="flex-1">
                    <textarea name="body" rows="2" required maxlength="5000"
                              class="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 resize-none"
                              placeholder="{{ __('message.input_placeholder') }}"></textarea>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="cursor-pointer px-2 py-1 text-xs text-gray-500 hover:text-indigo-600 border rounded">
                        &#128206;
                        <input type="file" name="attachment" class="hidden" onchange="this.parentElement.querySelector('span')?.remove(); this.parentElement.insertAdjacentHTML('beforeend', '<span class=\'text-indigo-600 text-xs ml-1\'>✓</span>')">
                    </label>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                        {{ __('message.send') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var POLL_URL = '{{ route('projects.messages.poll', $project) }}';
var lastMessageId = {{ $messages->last()?->id ?? 0 }};
var container = document.getElementById('messages-container');

function scrollToBottom() {
    if (container) container.scrollTop = container.scrollHeight;
}
document.addEventListener('DOMContentLoaded', scrollToBottom);

function renderMessage(msg) {
    var div = document.createElement('div');
    div.className = 'flex ' + (msg.is_mine ? 'justify-end' : 'justify-start');
    div.innerHTML = '<div class="max-w-[70%] ' + (msg.is_mine ? 'order-2' : 'order-1') + '">' +
        '<div class="flex items-end gap-2 ' + (msg.is_mine ? 'flex-row-reverse' : '') + '">' +
        '<div class="w-7 h-7 rounded-full bg-' + (msg.is_mine ? 'indigo' : 'gray') + '-200 flex items-center justify-center text-xs font-bold flex-shrink-0">' +
        (msg.sender_name ? msg.sender_name.charAt(0).toUpperCase() : '?') + '</div>' +
        '<div class="' + (msg.is_mine ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-800') + ' rounded-lg px-3 py-2 text-sm">' +
        '<p class="whitespace-pre-wrap">' + escapeHtml(msg.body) + '</p>' +
        (msg.attachment ? '<a href="/storage/' + msg.attachment + '" target="_blank" class="inline-flex items-center gap-1 mt-1 text-xs ' + (msg.is_mine ? 'text-indigo-200 hover:text-white' : 'text-indigo-600 hover:underline') + '">&#128206; ' + msg.attachment.split('/').pop() + '</a>' : '') +
        '</div></div>' +
        '<p class="text-xs text-gray-400 mt-1 ' + (msg.is_mine ? 'text-right' : '') + '">' + msg.sender_name + ' &middot; ' + msg.time + '</p>' +
        '</div>';
    return div;
}

function escapeHtml(text) {
    var d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

// AJAX polling every 5 seconds
setInterval(function() {
    fetch(POLL_URL + '?after_id=' + lastMessageId, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.messages && data.messages.length > 0) {
                var shouldScroll = container.scrollHeight - container.scrollTop - container.clientHeight < 100;
                data.messages.forEach(function(msg) {
                    container.appendChild(renderMessage(msg));
                    if (msg.id > lastMessageId) lastMessageId = msg.id;
                });
                if (shouldScroll) scrollToBottom();
            }
        })
        .catch(function() {});
}, 5000);
</script>
@endsection
