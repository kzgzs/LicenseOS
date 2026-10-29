@extends('layouts.app')
@section('title', __('licenses.title'))
@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">{{ __('licenses.title') }}</h1>

    @if($orders->isEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center">
            <svg class="mx-auto h-16 w-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
            <p class="text-gray-500 text-lg mb-2">{{ __('licenses.no_licenses') }}</p>
            <p class="text-gray-400 text-sm">{{ __('licenses.no_licenses_hint') }}</p>
            <a href="{{ route('marketplace') }}" class="inline-block mt-4 px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">{{ __('licenses.browse_marketplace') }}</a>
        </div>
    @else
        @foreach($orders as $order)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 overflow-hidden">
                <div class="px-6 py-4 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 flex items-center justify-between">
                    <div>
                        <span class="text-sm text-gray-500">{{ __('orders.order') }} #{{ $order->id }}</span>
                        <span class="text-sm text-gray-400 ml-2">{{ $order->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                    <span class="text-sm font-medium text-green-600 dark:text-green-400">
                        {{ __('licenses.paid') }} · {{ $currencySymbol }}{{ number_format($order->amount, 2) }}
                        @if($order->is_trial)
                            <span class="ml-1 text-xs bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300 px-1.5 py-0.5 rounded">{{ __('licenses.trial') }}</span>
                        @endif
                    </span>
                </div>

                <div class="px-6 py-4">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $order->product->name ?? __('licenses.unknown_product') }}</h3>
                            <div class="flex items-center gap-2 mt-0.5">
                                @if($order->product)
                                    <span class="text-xs text-gray-400">v{{ $order->product->version }}</span>
                                @endif
                                @if($order->tier_label)
                                    <span class="text-xs bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300 px-1.5 py-0.5 rounded">{{ $order->tier_label }}</span>
                                @endif
                                @if($order->domain)
                                    <span class="text-xs text-gray-500">🌐 {{ $order->domain }}</span>
                                @endif
                            </div>
                        </div>
                        @if($order->product && $order->product->download_url)
                            <a href="{{ $order->product->download_url }}"
                               class="text-sm px-4 py-1.5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300 rounded hover:bg-indigo-100 dark:hover:bg-indigo-900/50 font-medium">
                                {{ __('licenses.download') }}
                            </a>
                        @endif
                    </div>

                    @if($order->licenses->isNotEmpty())
                        <div class="space-y-2">
                            @foreach($order->licenses as $license)
                                <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3">
                                    <svg class="h-5 w-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <code class="text-sm font-mono text-indigo-700 dark:text-indigo-300 bg-white dark:bg-gray-900 border dark:border-gray-600 rounded px-2 py-1 select-all">{{ $license->license_key }}</code>
                                            <button type="button"
                                                    onclick="navigator.clipboard.writeText('{{ $license->license_key }}');this.textContent='{{ __('licenses.copied') }}';setTimeout(()=>this.textContent='{{ __('licenses.copy') }}',1500)"
                                                    class="text-xs px-2 py-0.5 bg-gray-200 dark:bg-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600">{{ __('licenses.copy') }}</button>
                                        </div>
                                        <div class="flex items-center gap-4 text-xs text-gray-500">
                                            <span>{{ __('licenses.status') }}:
                                                @if($license->status === 'active')
                                                    <span class="text-green-600 dark:text-green-400 font-medium">{{ __('licenses.active') }}</span>
                                                @elseif($license->status === 'trial')
                                                    <span class="text-orange-600 dark:text-orange-400 font-medium">{{ __('licenses.trial') }}
                                                        @if($license->expire_at)
                                                            ({{ now()->diffInDays($license->expire_at, false) > 0 ? __('licenses.d_left', ['n' => now()->diffInDays($license->expire_at, false)]) : __('licenses.expired') }})
                                                        @endif
                                                    </span>
                                                @elseif($license->status === 'expired')
                                                    <span class="text-red-500 dark:text-red-400 font-medium">{{ __('licenses.expired') }}</span>
                                                @else
                                                    <span class="text-gray-400">{{ ucfirst($license->status) }}</span>
                                                @endif
                                            </span>
                                            <span>{{ __('licenses.domains') }}: {{ $license->activations->count() }} / {{ $license->max_domains }}</span>
                                            <span>{{ __('licenses.expires') }}: {{ $license->expire_at ? $license->expire_at->format('Y-m-d') : __('licenses.lifetime') }}</span>
                                        </div>
                                        @if($license->activations->isNotEmpty())
                                            <div class="mt-1 text-xs text-gray-400">
                                                {{ __('licenses.activated') }}: {{ $license->activations->pluck('domain')->implode(', ') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 italic">{{ __('licenses.pending_generation') }}</p>
                    @endif
                </div>
            </div>
        @endforeach

        @if($orders->hasPages())
            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    @endif
</div>
@endsection
