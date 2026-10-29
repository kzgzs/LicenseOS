@extends('layouts.app')
@section('title', __('project.marketplace'))
@section('content')
<div class="max-w-6xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ __('project.marketplace') }}</h1>
        @auth
        <a href="{{ route('projects.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + {{ __('project.post_project') }}
        </a>
        @endauth
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
        <form method="GET" action="{{ route('projects.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('project.search_placeholder') }}"
                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <select name="category" class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">{{ __('project.all_categories') }}</option>
                    @foreach($categories as $key => $label)
                        <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="status" class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="open" {{ $status === 'open' ? 'selected' : '' }}>{{ __('project.status_open') }}</option>
                    <option value="in_progress" {{ $status === 'in_progress' ? 'selected' : '' }}>{{ __('project.status_in_progress') }}</option>
                    <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>{{ __('project.status_completed') }}</option>
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>{{ __('project.all') }}</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                {{ __('project.filter') }}
            </button>
        </form>
    </div>

    {{-- Project List --}}
    @forelse($projects as $project)
    <a href="{{ route('projects.show', $project) }}" class="block bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 mb-3 hover:shadow-md transition border border-transparent hover:border-indigo-100 dark:hover:border-indigo-900">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $project->title }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">{{ Str::limit(strip_tags($project->description), 150) }}</p>
                <div class="flex items-center flex-wrap gap-2 mt-3">
                    <span class="inline-flex px-2 py-0.5 text-xs bg-indigo-50 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-300 rounded-full font-medium">{{ __($categories[$project->category] ?? $project->category) }}</span>
                    @if($project->skills && is_array($project->skills))
                        @foreach(array_slice($project->skills, 0, 4) as $skill)
                            <span class="inline-flex px-2 py-0.5 text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-full">{{ $skill }}</span>
                        @endforeach
                    @endif
                    @if($project->duration)
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('project.duration') }}: {{ __(Project::durations()[$project->duration] ?? $project->duration) }}</span>
                    @endif
                </div>
            </div>
            <div class="text-right shrink-0">
                @if($project->budget_min && $project->budget_max)
                    <div class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $project->currency }} {{ number_format($project->budget_min) }} - {{ number_format($project->budget_max) }}</div>
                @elseif($project->budget_max)
                    <div class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $project->currency }} {{ number_format($project->budget_max) }}</div>
                @else
                    <div class="text-sm text-gray-400">{{ __('project.budget_negotiable') }}</div>
                @endif
                <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                    {{ $project->bid_count }} {{ __('project.bids') }}
                </div>
                <span class="inline-block mt-2 px-2 py-0.5 text-xs rounded-full font-medium
                    @if($project->status === 'open') bg-green-50 text-green-600 dark:bg-green-900/50 dark:text-green-300
                    @elseif($project->status === 'in_progress') bg-yellow-50 text-yellow-600 dark:bg-yellow-900/50 dark:text-yellow-300
                    @else bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400
                    @endif">{{ __('project.status_' . $project->status) }}</span>
            </div>
        </div>
    </a>
    @empty
    <div class="text-center py-16 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <p class="text-gray-400 dark:text-gray-500 text-lg">{{ __('project.no_projects') }}</p>
    </div>
    @endforelse

    {{-- Pagination --}}
    <div class="mt-6">{{ $projects->links() }}</div>
</div>
@endsection
