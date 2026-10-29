@extends('layouts.app')
@section('title', __('score.title'))
@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">{{ __('score.title') }}</h1>

    @if($score)
    {{-- Score Overview Card --}}
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <div class="w-20 h-20 rounded-full bg-gradient-to-br from-indigo-100 to-indigo-200 flex items-center justify-center">
                    <span class="text-3xl font-bold text-indigo-700">{{ round($score->composite_score) }}</span>
                </div>
                <div>
                    <span class="px-3 py-1 text-sm font-semibold rounded-full border {{ \App\Models\DeveloperScore::tierBadgeClass($score->tier) }}">
                        {{ $score->tier_label }}
                    </span>
                    <p class="text-xs text-gray-400 mt-1">{{ __('score.last_updated') }}: {{ $score->last_calculated_at?->diffForHumans() ?? '-' }}</p>
                </div>
            </div>
            <a href="{{ route('projects.recommendations') }}" class="text-sm text-indigo-600 hover:underline">
                {{ __('score.view_recommendations') }} &rarr;
            </a>
        </div>

        {{-- Score Breakdown --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-green-600">{{ $score->completion_rate }}%</div>
                <div class="text-xs text-gray-500">{{ __('score.completion_rate') }}</div>
                <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                    <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ $score->completion_rate }}%"></div>
                </div>
            </div>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-yellow-600">{{ $score->avg_rating }}/5</div>
                <div class="text-xs text-gray-500">{{ __('score.avg_rating') }}</div>
                <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                    <div class="bg-yellow-500 h-1.5 rounded-full" style="width: {{ ($score->avg_rating / 5) * 100 }}%"></div>
                </div>
            </div>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-blue-600">{{ $score->response_speed }}</div>
                <div class="text-xs text-gray-500">{{ __('score.response_speed') }}</div>
                <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                    <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ $score->response_speed }}%"></div>
                </div>
            </div>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-indigo-600">{{ $score->project_count }}</div>
                <div class="text-xs text-gray-500">{{ __('score.completed_projects') }}</div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4">
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-lg font-bold text-gray-700">{{ $score->currency ?? '$' }}{{ number_format($score->total_earned, 2) }}</div>
                <div class="text-xs text-gray-500">{{ __('score.total_earned') }}</div>
            </div>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-lg font-bold text-{{ $score->dispute_count > 0 ? 'red' : 'green' }}-600">{{ $score->dispute_count }}</div>
                <div class="text-xs text-gray-500">{{ __('score.disputes') }}</div>
            </div>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-lg font-bold text-purple-600">{{ $score->composite_score }}/100</div>
                <div class="text-xs text-gray-500">{{ __('score.composite') }}</div>
            </div>
        </div>
    </div>

    {{-- Tier Explanation --}}
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('score.tier_progress') }}</h2>
        <div class="flex items-center gap-2">
            @foreach(\App\Models\DeveloperScore::tierThresholds() as $tier => $min)
                @php $isActive = $score->tier === $tier; @endphp
                <div class="flex-1 text-center">
                    <div class="h-2 rounded-full {{ $isActive ? 'bg-indigo-500' : ($score->composite_score >= $min ? 'bg-green-400' : 'bg-gray-200') }}"></div>
                    <span class="text-xs mt-1 {{ $isActive ? 'font-bold text-indigo-600' : 'text-gray-400' }}">
                        {{ __('score.tier_' . $tier) }}
                    </span>
                    <span class="text-xs text-gray-400 block">{{ $min }}+</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Recent Reviews --}}
    @if($reviews->count() > 0)
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('score.recent_reviews') }}</h2>
        <div class="space-y-4">
            @foreach($reviews as $review)
            <div class="border-b last:border-b-0 pb-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-yellow-500">
                            @for($i = 1; $i <= 5; $i++)&#{{ $i <= $review->rating ? '9733' : '9734' }};@endfor
                        </span>
                        <span class="text-sm text-gray-600">{{ $review->reviewer->name ?? '-' }}</span>
                    </div>
                    <span class="text-xs text-gray-400">{{ $review->created_at->diffForHumans() }}</span>
                </div>
                @if($review->contract?->project)
                    <p class="text-xs text-gray-400 mt-1">{{ __('score.project') }}: {{ $review->contract->project->title }}</p>
                @endif
                @if($review->comment)
                    <p class="text-sm text-gray-600 mt-1">{{ $review->comment }}</p>
                @endif
                <div class="flex gap-3 mt-2">
                    <span class="text-xs text-gray-500">{{ __('score.communication') }}: {{ $review->communication }}/5</span>
                    <span class="text-xs text-gray-500">{{ __('score.quality') }}: {{ $review->quality }}/5</span>
                    <span class="text-xs text-gray-500">{{ __('score.timeliness') }}: {{ $review->timeliness }}/5</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
    @else
    <div class="bg-white rounded-xl shadow-sm p-6 text-center py-12">
        <p class="text-gray-500">{{ __('score.no_data') }}</p>
    </div>
    @endif
</div>
@endsection
