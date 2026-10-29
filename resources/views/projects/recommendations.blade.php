@extends('layouts.app')
@section('title', __('recommend.title'))
@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('recommend.title') }}</h1>
        <a href="{{ route('projects.index') }}" class="text-sm text-indigo-600 hover:underline">
            {{ __('recommend.browse_all') }} &rarr;
        </a>
    </div>

    <p class="text-sm text-gray-500">{{ __('recommend.description') }}</p>

    @if(count($recommendations) > 0)
    <div class="space-y-4">
        @foreach($recommendations as $rec)
            @php $project = $rec['project']; @endphp
            <div class="bg-white rounded-xl shadow-sm border hover:shadow-md transition-shadow p-5">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold text-gray-900">
                            <a href="{{ route('projects.show', $project) }}" class="hover:text-indigo-600">{{ $project->title }}</a>
                        </h3>
                        @if($rec['match_score'] >= 50)
                            <span class="px-2 py-0.5 text-xs bg-green-50 text-green-600 rounded-full font-medium">
                                {{ __('recommend.high_match') }} {{ $rec['match_score'] }}%
                            </span>
                        @elseif($rec['match_score'] >= 20)
                            <span class="px-2 py-0.5 text-xs bg-yellow-50 text-yellow-600 rounded-full font-medium">
                                {{ __('recommend.good_match') }} {{ $rec['match_score'] }}%
                            </span>
                        @else
                            <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-500 rounded-full">
                                {{ $rec['match_score'] }}%
                            </span>
                        @endif
                    </div>
                    <span class="text-sm font-bold text-gray-900">
                        {{ $project->currency }} {{ number_format($project->budget_min) }} - {{ number_format($project->budget_max) }}
                    </span>
                </div>

                <p class="text-sm text-gray-500 mb-3">{{ Str::limit($project->description, 200) }}</p>

                @if(!empty($rec['matched_skills']))
                <div class="flex items-center gap-1 mb-3">
                    <span class="text-xs text-gray-400">{{ __('recommend.matched_skills') }}:</span>
                    @foreach($rec['matched_skills'] as $skill)
                        <span class="px-2 py-0.5 text-xs bg-indigo-50 text-indigo-600 rounded">{{ $skill }}</span>
                    @endforeach
                </div>
                @endif

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 text-xs text-gray-400">
                        <span>{{ __($categories[$project->category] ?? $project->category) }}</span>
                        <span>{{ __('project.bids') }}: {{ $project->bid_count ?? 0 }}</span>
                        <span>{{ $project->created_at->diffForHumans() }}</span>
                    </div>
                    <a href="{{ route('projects.show', $project) }}"
                       class="px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                        {{ __('recommend.view_project') }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>
    @else
    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
        <p class="text-gray-500 mb-4">{{ __('recommend.empty') }}</p>
        <a href="{{ route('projects.skills') }}" class="text-indigo-600 hover:underline text-sm">
            {{ __('recommend.update_skills') }}
        </a>
    </div>
    @endif
</div>
@endsection
