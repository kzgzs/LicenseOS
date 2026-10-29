@extends('layouts.app')
@section('title', __('badge.my_badges'))
@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('badge.my_badges') }}</h1>

    @if($badges->isEmpty())
        <div class="bg-white rounded-lg shadow-sm p-12 text-center">
            <p class="text-gray-500 mb-4">{{ __('badge.empty') }}</p>
            <p class="text-sm text-gray-400">{{ __('badge.hint') }}</p>
        </div>
    @else
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            @foreach($badges as $badge)
                <div class="bg-white rounded-lg shadow-sm p-5 text-center {{ $badge->isActive() ? '' : 'opacity-50' }}">
                    <div class="w-14 h-14 mx-auto rounded-full bg-{{ $badge->color }}-100 flex items-center justify-center mb-3">
                        <span class="text-2xl">
                            @switch($badge->badge_type)
                                @case('verified') &#10003; @break
                                @case('top_rated') &#9733; @break
                                @case('fast_delivery') &#9889; @break
                                @case('specialist') &#127891; @break
                                @case('partner') &#129309; @break
                                @default &#127942;
                            @endswitch
                        </span>
                    </div>
                    <h3 class="font-semibold text-gray-900">{{ __($badge->label) }}</h3>
                    @if($badge->description)
                        <p class="text-xs text-gray-500 mt-1">{{ $badge->description }}</p>
                    @endif
                    <p class="text-xs text-gray-400 mt-2">
                        {{ __('badge.awarded') }}: {{ $badge->awarded_at?->format('Y-m-d') }}
                    </p>
                    @if($badge->expires_at && $badge->expires_at->isPast())
                        <span class="text-xs text-red-500">{{ __('badge.expired') }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Available Badge Types Info --}}
    <div class="mt-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ __('badge.available_types') }}</h2>
        <div class="bg-white rounded-lg shadow-sm p-5 space-y-3">
            @foreach($types as $type => $info)
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-{{ $info['color'] }}-100 flex items-center justify-center flex-shrink-0">
                        <span class="text-lg">
                            @switch($type)
                                @case('verified') &#10003; @break
                                @case('top_rated') &#9733; @break
                                @case('fast_delivery') &#9889; @break
                                @case('specialist') &#127891; @break
                                @case('partner') &#129309; @break
                                @default &#127942;
                            @endswitch
                        </span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-900">{{ __('badge.type_' . $type) }}</span>
                        <p class="text-xs text-gray-500">{{ __('badge.type_' . $type . '_desc') }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
