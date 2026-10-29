@extends('layouts.app')
@section('title', __('project.my_contracts'))
@section('content')
<div class="max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('project.my_contracts') }}</h1>

    <div class="flex gap-4 mb-6">
        <a href="{{ route('projects.my-projects') }}" class="text-sm text-indigo-600 hover:text-indigo-800">{{ __('project.my_posted_projects') }}</a>
        <a href="{{ route('projects.my-bids') }}" class="text-sm text-indigo-600 hover:text-indigo-800">{{ __('project.my_bids') }}</a>
    </div>

    @forelse($contracts as $contract)
    <a href="{{ route('projects.contracts.show', $contract->project) }}" class="block bg-white rounded-lg shadow-sm p-5 mb-3 hover:shadow-md transition border border-transparent hover:border-indigo-100">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-900">{{ $contract->project->title }}</h3>
                <div class="text-sm text-gray-500 mt-1">
                    @if(auth()->id() === $contract->client_id)
                        {{ __('project.developer') }}: {{ $contract->developer->name }}
                    @else
                        {{ __('project.client') }}: {{ $contract->client->name }}
                    @endif
                </div>
                <div class="text-xs text-gray-400 mt-1">
                    {{ $contract->milestones->count() }} {{ __('project.milestones') }} |
                    {{ __('project.started') }}: {{ $contract->started_at?->format('Y-m-d') ?? '-' }}
                </div>
            </div>
            <div class="text-right">
                <div class="font-bold text-gray-900">{{ $contract->currency }} {{ number_format($contract->total_amount) }}</div>
                <span class="inline-block mt-1 px-2 py-0.5 text-xs rounded-full font-medium
                    @if($contract->status === 'active') bg-yellow-50 text-yellow-600
                    @elseif($contract->status === 'completed') bg-green-50 text-green-600
                    @elseif($contract->status === 'disputed') bg-red-50 text-red-600
                    @else bg-gray-100 text-gray-500
                    @endif">{{ __('project.contract_' . $contract->status) }}</span>
            </div>
        </div>
    </a>
    @empty
    <div class="text-center py-16 bg-white rounded-lg shadow-sm">
        <p class="text-gray-400 text-lg">{{ __('project.no_contracts') }}</p>
    </div>
    @endforelse

    <div class="mt-6">{{ $contracts->links() }}</div>
</div>
@endsection
