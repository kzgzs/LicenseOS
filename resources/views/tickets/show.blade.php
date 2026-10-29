@extends('layouts.app')
@section('title', __('ticket.ticket_detail') . ' #' . $ticket->id)
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('tickets.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; {{ __('ticket.back_to_list') }}</a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    {{-- Ticket Header --}}
    <div class="bg-white rounded-lg shadow-sm p-5 mb-4">
        <div class="flex items-start justify-between gap-4 mb-3">
            <h1 class="text-xl font-bold text-gray-900">#{{ $ticket->id }} {{ $ticket->subject }}</h1>
            @php
                $statusColors = [
                    'open' => 'bg-blue-100 text-blue-700',
                    'in_progress' => 'bg-yellow-100 text-yellow-700',
                    'resolved' => 'bg-green-100 text-green-700',
                    'closed' => 'bg-gray-100 text-gray-500',
                ];
            @endphp
            <span class="shrink-0 inline-flex px-2.5 py-1 text-xs font-medium rounded-full {{ $statusColors[$ticket->status] ?? 'bg-gray-100 text-gray-500' }}">
                {{ __('ticket.status_' . $ticket->status) }}
            </span>
        </div>
        <div class="flex flex-wrap gap-3 text-xs text-gray-500">
            <span>{{ __('ticket.' . $ticket->category) }}</span>
            <span>·</span>
            <span>{{ __('ticket.priority_' . $ticket->priority) }}</span>
            <span>·</span>
            <span>{{ $ticket->created_at->format('Y-m-d H:i') }}</span>
            @if($ticket->isOpen())
            <form method="POST" action="{{ route('tickets.close', $ticket) }}" class="ml-auto" onsubmit="return confirm('{{ __('ticket.confirm_close') }}')">
                @csrf
                <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-medium">{{ __('ticket.close_ticket') }}</button>
            </form>
            @endif
        </div>
    </div>

    {{-- Original Message --}}
    <div class="bg-white rounded-lg shadow-sm p-5 mb-2">
        <div class="flex items-center gap-2 mb-3">
            <span class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-semibold text-xs">
                {{ strtoupper(mb_substr($ticket->user->name ?? 'U', 0, 1)) }}
            </span>
            <div>
                <p class="text-sm font-medium text-gray-900">{{ $ticket->user->name }}</p>
                <p class="text-xs text-gray-400">{{ $ticket->created_at->diffForHumans() }}</p>
            </div>
        </div>
        <div class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $ticket->message }}</div>
    </div>

    {{-- Replies --}}
    @foreach($ticket->replies as $reply)
    <div class="{{ $reply->is_admin ? 'ml-6 border-l-4 border-indigo-200' : '' }} bg-white rounded-lg shadow-sm p-5 mb-2">
        <div class="flex items-center gap-2 mb-3">
            @if($reply->is_admin)
                <span class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-semibold text-xs">
                    {{ __('ticket.staff') }}
                </span>
                <div>
                    <p class="text-sm font-medium text-indigo-700">{{ __('ticket.support_team') }}</p>
                    <p class="text-xs text-gray-400">{{ $reply->created_at->diffForHumans() }}</p>
                </div>
            @else
                <span class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center font-semibold text-xs">
                    {{ strtoupper(mb_substr($reply->user->name ?? 'U', 0, 1)) }}
                </span>
                <div>
                    <p class="text-sm font-medium text-gray-900">{{ $reply->user->name ?? __('ticket.user') }}</p>
                    <p class="text-xs text-gray-400">{{ $reply->created_at->diffForHumans() }}</p>
                </div>
            @endif
        </div>
        <div class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $reply->message }}</div>
    </div>
    @endforeach

    {{-- Reply Form --}}
    @if($ticket->isOpen())
    <form method="POST" action="{{ route('tickets.reply', $ticket) }}" class="bg-white rounded-lg shadow-sm p-5 mt-4">
        @csrf
        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('ticket.your_reply') }}</label>
        <textarea name="message" rows="4" required maxlength="10000"
                  class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="{{ __('ticket.reply_placeholder') }}"></textarea>
        <div class="flex justify-end mt-3">
            <button type="submit" class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                {{ __('ticket.send_reply') }}
            </button>
        </div>
    </form>
    @elseif($ticket->status === 'closed')
    <div class="bg-gray-50 rounded-lg p-4 mt-4 text-center text-sm text-gray-400">
        {{ __('ticket.closed_no_reply') }}
    </div>
    @endif
</div>
@endsection
