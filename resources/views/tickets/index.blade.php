@extends('layouts.app')
@section('title', __('ticket.my_tickets'))
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('ticket.my_tickets') }}</h1>
        <a href="{{ route('tickets.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            {{ __('ticket.new_ticket') }}
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-500">#</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500">{{ __('ticket.subject') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500 hidden sm:table-cell">{{ __('ticket.category') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500">{{ __('ticket.status') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500 hidden md:table-cell">{{ __('ticket.created') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($tickets as $ticket)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-gray-400 font-mono text-xs">{{ $ticket->id }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('tickets.show', $ticket) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                            {{ $ticket->subject }}
                        </a>
                        <span class="sm:hidden text-xs text-gray-400 ml-2">{{ __('ticket.' . $ticket->category) }}</span>
                    </td>
                    <td class="px-4 py-3 hidden sm:table-cell">
                        <span class="text-xs text-gray-500">{{ __('ticket.' . $ticket->category) }}</span>
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $statusColors = [
                                'open' => 'bg-blue-100 text-blue-700',
                                'in_progress' => 'bg-yellow-100 text-yellow-700',
                                'resolved' => 'bg-green-100 text-green-700',
                                'closed' => 'bg-gray-100 text-gray-500',
                            ];
                        @endphp
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $statusColors[$ticket->status] ?? 'bg-gray-100 text-gray-500' }}">
                            {{ __('ticket.status_' . $ticket->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-400 text-xs hidden md:table-cell">{{ $ticket->created_at->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('tickets.show', $ticket) }}" class="text-gray-400 hover:text-indigo-600">
                            <span class="text-xs">{{ $ticket->replies_count }} {{ __('ticket.replies') }}</span>
                            <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-16 text-center text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z"/></svg>
                        {{ __('ticket.empty') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($tickets->hasPages())
    <div class="mt-6">{{ $tickets->links() }}</div>
    @endif
</div>
@endsection
