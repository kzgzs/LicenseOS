@extends('layouts.app')
@section('title', __('project.my_bids'))
@section('content')
<div class="max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('project.my_bids') }}</h1>

    <div class="flex gap-4 mb-6">
        <a href="{{ route('projects.contracts') }}" class="text-sm text-indigo-600 hover:text-indigo-800">{{ __('project.my_contracts') }}</a>
        <a href="{{ route('projects.my-projects') }}" class="text-sm text-indigo-600 hover:text-indigo-800">{{ __('project.my_posted_projects') }}</a>
    </div>

    @forelse($bids as $bid)
    <a href="{{ route('projects.show', $bid->project) }}" class="block bg-white rounded-lg shadow-sm p-5 mb-3 hover:shadow-md transition border border-transparent hover:border-indigo-100">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-900">{{ $bid->project->title }}</h3>
                <div class="text-sm text-gray-500 mt-1">{{ __('project.your_bid') }}: {{ $bid->currency }} {{ number_format($bid->amount) }}</div>
                <div class="text-xs text-gray-400 mt-1">{{ $bid->created_at->diffForHumans() }}</div>
            </div>
            <div class="text-right">
                <span class="inline-block px-2 py-0.5 text-xs rounded-full font-medium
                    @if($bid->status === 'pending') bg-gray-100 text-gray-500
                    @elseif($bid->status === 'accepted') bg-green-50 text-green-600
                    @elseif($bid->status === 'rejected') bg-red-50 text-red-600
                    @else bg-gray-100 text-gray-500
                    @endif">{{ __('project.bid_status_' . $bid->status) }}</span>
            </div>
        </div>
    </a>
    @empty
    <div class="text-center py-16 bg-white rounded-lg shadow-sm">
        <p class="text-gray-400 text-lg">{{ __('project.no_bids') }}</p>
        <a href="{{ route('projects.index') }}" class="inline-block mt-4 text-indigo-600 hover:text-indigo-800 text-sm">
            {{ __('project.browse_projects') }}
        </a>
    </div>
    @endforelse

    <div class="mt-6">{{ $bids->links() }}</div>
</div>
@endsection
