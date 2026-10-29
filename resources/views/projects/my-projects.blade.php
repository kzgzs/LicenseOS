@extends('layouts.app')
@section('title', __('project.my_posted_projects'))
@section('content')
<div class="max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('project.my_posted_projects') }}</h1>

    <div class="flex gap-4 mb-6">
        <a href="{{ route('projects.contracts') }}" class="text-sm text-indigo-600 hover:text-indigo-800">{{ __('project.my_contracts') }}</a>
        <a href="{{ route('projects.my-bids') }}" class="text-sm text-indigo-600 hover:text-indigo-800">{{ __('project.my_bids') }}</a>
    </div>

    @forelse($projects as $project)
    <a href="{{ route('projects.show', $project) }}" class="block bg-white rounded-lg shadow-sm p-5 mb-3 hover:shadow-md transition border border-transparent hover:border-indigo-100">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-900">{{ $project->title }}</h3>
                <div class="text-xs text-gray-400 mt-1">
                    {{ $project->bids_count }} {{ __('project.bids') }} |
                    {{ $project->created_at->diffForHumans() }}
                </div>
            </div>
            <div class="text-right">
                @if($project->budget_max)
                    <div class="font-bold text-gray-900">{{ $project->currency }} {{ number_format($project->budget_max) }}</div>
                @endif
                <span class="inline-block mt-1 px-2 py-0.5 text-xs rounded-full font-medium
                    @if($project->status === 'open') bg-green-50 text-green-600
                    @elseif($project->status === 'in_progress') bg-yellow-50 text-yellow-600
                    @elseif($project->status === 'completed') bg-green-50 text-green-600
                    @else bg-gray-100 text-gray-500
                    @endif">{{ __('project.status_' . $project->status) }}</span>
            </div>
        </div>
    </a>
    @empty
    <div class="text-center py-16 bg-white rounded-lg shadow-sm">
        <p class="text-gray-400 text-lg">{{ __('project.no_posted_projects') }}</p>
        <a href="{{ route('projects.create') }}" class="inline-block mt-4 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
            + {{ __('project.post_project') }}
        </a>
    </div>
    @endforelse

    <div class="mt-6">{{ $projects->links() }}</div>
</div>
@endsection
