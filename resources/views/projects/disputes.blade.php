@extends('layouts.app')
@section('title', __('dispute.my_disputes'))
@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('dispute.my_disputes') }}</h1>

    @if($disputes->isEmpty())
        <div class="bg-white rounded-lg shadow-sm p-12 text-center">
            <p class="text-gray-500">{{ __('dispute.empty') }}</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($disputes as $dispute)
                <div class="bg-white rounded-lg shadow-sm p-5 border-l-4 {{ $dispute->isResolved() ? 'border-green-500' : ($dispute->isClosed() ? 'border-gray-400' : 'border-orange-500') }}">
                    <div class="flex justify-between items-start">
                        <div>
                            <a href="{{ route('projects.disputes.show', $dispute) }}" class="text-lg font-semibold text-indigo-600 hover:text-indigo-800">
                                {{ $dispute->contract->project->title ?? __('dispute.unknown_project') }}
                            </a>
                            <p class="text-sm text-gray-500 mt-1">
                                {{ __('dispute.reason') }}: {{ __($reasons[$dispute->reason] ?? $dispute->reason) }}
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ __('dispute.raised_by') }}: {{ $dispute->raisedByUser->name ?? '-' }}
                                &middot; {{ $dispute->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <span class="px-3 py-1 text-xs rounded-full {{ $dispute->isResolved() ? 'bg-green-100 text-green-700' : ($dispute->isClosed() ? 'bg-gray-100 text-gray-600' : 'bg-orange-100 text-orange-700') }}">
                            {{ __($statuses[$dispute->status] ?? $dispute->status) }}
                        </span>
                    </div>
                    @if($dispute->isResolved())
                        <div class="mt-3 text-sm text-gray-600 bg-gray-50 rounded p-3">
                            {{ __('dispute.client_refund') }}: ¥{{ number_format($dispute->client_refund, 2) }}
                            &middot;
                            {{ __('dispute.developer_payout') }}: ¥{{ number_format($dispute->developer_payout, 2) }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6">{{ $disputes->links() }}</div>
    @endif
</div>
@endsection
