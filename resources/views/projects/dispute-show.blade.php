@extends('layouts.app')
@section('title', __('dispute.detail'))
@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('projects.disputes') }}" class="text-sm text-indigo-600 hover:underline">&larr; {{ __('dispute.back_to_list') }}</a>

    <div class="bg-white rounded-lg shadow-sm p-6 mt-4">
        <div class="flex justify-between items-start mb-4">
            <h1 class="text-xl font-bold text-gray-900">
                {{ $dispute->contract->project->title ?? __('dispute.unknown_project') }}
            </h1>
            <span class="px-3 py-1 text-sm rounded-full {{ $dispute->isResolved() ? 'bg-green-100 text-green-700' : ($dispute->isClosed() ? 'bg-gray-100 text-gray-600' : 'bg-orange-100 text-orange-700') }}">
                {{ __('dispute.status_' . $dispute->status) }}
            </span>
        </div>

        @if($dispute->ticket)
            <div class="mb-4 px-3 py-2 bg-indigo-50 border border-indigo-200 rounded-lg flex items-center gap-2 text-sm">
                <span class="text-indigo-700 font-medium">{{ __('dispute.linked_ticket') }}:</span>
                <a href="{{ route('developer.tickets.show', $dispute->ticket) }}" class="text-indigo-600 hover:underline">
                    #{{ $dispute->ticket->id }} — {{ $dispute->ticket->subject }}
                </a>
            </div>
        @endif

        <div class="space-y-4 text-sm">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="text-gray-500">{{ __('dispute.reason') }}</span>
                    <p class="font-medium">{{ __($reasons[$dispute->reason] ?? $dispute->reason) }}</p>
                </div>
                <div>
                    <span class="text-gray-500">{{ __('dispute.raised_by') }}</span>
                    <p class="font-medium">{{ $dispute->raisedByUser->name ?? '-' }}</p>
                </div>
            </div>

            <div>
                <span class="text-gray-500">{{ __('dispute.description') }}</span>
                <div class="mt-1 p-3 bg-gray-50 rounded-lg text-gray-700 whitespace-pre-wrap">{{ $dispute->description }}</div>
            </div>

            @if($dispute->milestone)
                <div>
                    <span class="text-gray-500">{{ __('dispute.related_milestone') }}</span>
                    <p class="font-medium">{{ $dispute->milestone->title }} (¥{{ number_format($dispute->milestone->amount, 2) }})</p>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="text-gray-500">{{ __('dispute.contract_amount') }}</span>
                    <p class="font-medium">¥{{ number_format($dispute->contract->total_amount, 2) }}</p>
                </div>
                <div>
                    <span class="text-gray-500">{{ __('dispute.filed_at') }}</span>
                    <p class="font-medium">{{ $dispute->created_at->format('Y-m-d H:i') }}</p>
                </div>
            </div>
        </div>

        @if($dispute->isResolved())
            <div class="mt-6 p-4 bg-green-50 rounded-lg border border-green-200">
                <h3 class="font-semibold text-green-800 mb-2">{{ __('dispute.resolution') }}</h3>
                <div class="text-sm text-green-700 space-y-1">
                    <p>{{ __('dispute.client_refund') }}: <strong>¥{{ number_format($dispute->client_refund, 2) }}</strong></p>
                    <p>{{ __('dispute.developer_payout') }}: <strong>¥{{ number_format($dispute->developer_payout, 2) }}</strong></p>
                    @if($dispute->admin_note)
                        <p>{{ __('dispute.admin_note') }}: {{ $dispute->admin_note }}</p>
                    @endif
                    <p>{{ __('dispute.resolved_at') }}: {{ $dispute->resolved_at?->format('Y-m-d H:i') }}</p>
                </div>
            </div>
        @endif

        @if($dispute->isOpen() && $dispute->contract)
            @php
                $contract = $dispute->contract;
            @endphp
            @if($contract->milestones->where('status', 'funded')->count() > 0 || $contract->milestones->where('status', 'submitted')->count() > 0)
                <div class="mt-6 pt-4 border-t">
                    <h3 class="font-semibold text-gray-800 mb-3">{{ __('dispute.escrow_info') }}</h3>
                    @foreach($contract->milestones as $ms)
                        <div class="flex justify-between text-sm py-1">
                            <span>{{ $ms->title }}</span>
                            <span class="{{ $ms->status === 'funded' ? 'text-orange-600' : 'text-gray-500' }}">
                                ¥{{ number_format($ms->amount, 2) }} ({{ __('milestone.status_' . $ms->status) }})
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
