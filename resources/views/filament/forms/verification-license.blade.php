@php
    $path = $getRecord()?->business_license;
@endphp

@if($path)
    <div class="space-y-2">
        <a href="{{ asset('storage/' . $path) }}" target="_blank" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:underline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            View License File
        </a>
        @if(str_ends_with($path, '.pdf'))
            <iframe src="{{ asset('storage/' . $path) }}" class="w-full h-64 border rounded" style="min-height: 300px;"></iframe>
        @else
            <img src="{{ asset('storage/' . $path) }}" class="max-h-64 border rounded" alt="Business License">
        @endif
    </div>
@else
    <p class="text-sm text-gray-500">No license file uploaded</p>
@endif
