@extends('layouts.app')
@section('title', __('notification.title'))
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('notification.title') }}</h1>
        @if($notifications->count() > 0)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                {{ __('notification.mark_all_read') }}
            </button>
        </form>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow-sm divide-y">
        @forelse($notifications as $notification)
        @php
            $data = $notification->data;
            $isUnread = is_null($notification->read_at);
            $iconMap = [
                'shopping-cart' => 'text-green-500',
                'banknotes'     => 'text-blue-500',
                'star'          => 'text-yellow-500',
                'exclamation-triangle' => 'text-orange-500',
                'megaphone'     => 'text-purple-500',
                'bell'          => 'text-indigo-500',
            ];
            $iconColor = $iconMap[$data['icon'] ?? ''] ?? 'text-gray-400';
        @endphp
        <div class="flex items-start gap-4 p-4 {{ $isUnread ? 'bg-indigo-50/40' : '' }} hover:bg-gray-50 transition-colors">
            {{-- Icon --}}
            <div class="shrink-0 mt-0.5">
                @if(($data['icon'] ?? '') === 'shopping-cart')
                <svg class="w-6 h-6 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
                @elseif(($data['icon'] ?? '') === 'banknotes')
                <svg class="w-6 h-6 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/></svg>
                @elseif(($data['icon'] ?? '') === 'star')
                <svg class="w-6 h-6 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/></svg>
                @elseif(($data['icon'] ?? '') === 'exclamation-triangle')
                <svg class="w-6 h-6 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                @elseif(($data['icon'] ?? '') === 'megaphone')
                <svg class="w-6 h-6 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46"/></svg>
                @elseif(($data['icon'] ?? '') === 'bell')
                <svg class="w-6 h-6 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                @else
                <svg class="w-6 h-6 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                @endif
            </div>

            {{-- Content --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-gray-900 text-sm">{{ $data['title'] ?? '' }}</p>
                        <p class="text-gray-600 text-sm mt-0.5">{{ $data['message'] ?? '' }}</p>
                    </div>
                    @if($isUnread)
                    <span class="shrink-0 w-2 h-2 rounded-full bg-indigo-500 mt-1.5"></span>
                    @endif
                </div>
                <div class="flex items-center gap-3 mt-2">
                    <span class="text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</span>
                    @if($isUnread)
                    <button onclick="markAsRead('{{ $notification->id }}', this)" class="text-xs text-indigo-600 hover:text-indigo-800">
                        {{ __('notification.mark_read') }}
                    </button>
                    @endif
                    @if(!empty($data['url']))
                    <a href="{{ $data['url'] }}" class="text-xs text-gray-500 hover:text-gray-700">
                        {{ __('notification.view_detail') }} &rarr;
                    </a>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="py-16 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-200 mb-4" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
            <p class="text-gray-400 text-sm">{{ __('notification.empty') }}</p>
        </div>
        @endforelse
    </div>

    @if($notifications->hasPages())
    <div class="mt-6">
        {{ $notifications->links() }}
    </div>
    @endif
</div>

<script>
function markAsRead(id, btn) {
    fetch('/notifications/' + id + '/read', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        }
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            var row = btn.closest('.flex.items-start');
            row.classList.remove('bg-indigo-50/40');
            // Remove dot
            var dot = row.querySelector('.bg-indigo-500');
            if (dot) dot.remove();
            // Remove mark-read button
            btn.remove();
            // Update navbar badge
            var badge = document.getElementById('notif-badge');
            if (badge) {
                var count = parseInt(badge.textContent) - 1;
                if (count <= 0) { badge.classList.add('hidden'); }
                else { badge.textContent = count > 9 ? '9+' : count; }
            }
        }
    })
    .catch(function() {});
}
</script>
@endsection
