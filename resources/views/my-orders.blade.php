@extends('layouts.app')
@section('title', __('orders.title'))
@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">{{ __('orders.title') }}</h1>

    @if($orders->isEmpty())
        <div class="bg-white rounded-lg shadow p-12 text-center">
            <svg class="mx-auto h-16 w-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
            </svg>
            <p class="text-gray-500 text-lg mb-2">{{ __('orders.no_orders') }}</p>
            <p class="text-gray-400 text-sm">{{ __('orders.no_orders_hint') }}</p>
            <a href="{{ route('marketplace') }}" class="inline-block mt-4 px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">{{ __('licenses.browse_marketplace') }}</a>
        </div>
    @else
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-6 py-3 text-gray-500 font-medium">{{ __('orders.order') }}</th>
                        <th class="text-left px-6 py-3 text-gray-500 font-medium">{{ __('orders.product') }}</th>
                        <th class="text-left px-6 py-3 text-gray-500 font-medium">{{ __('orders.amount') }}</th>
                        <th class="text-left px-6 py-3 text-gray-500 font-medium">{{ __('orders.status') }}</th>
                        <th class="text-left px-6 py-3 text-gray-500 font-medium">{{ __('orders.date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($orders as $order)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-gray-600">#{{ $order->id }}</td>
                            <td class="px-6 py-4">
                                <span class="text-gray-900 font-medium">{{ $order->product->name ?? 'Unknown' }}</span>
                                @if($order->tier_label)
                                    <span class="ml-1 text-xs bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded">{{ $order->tier_label }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-900 font-medium">{{ $currencySymbol }}{{ number_format($order->amount, 2) }}</td>
                            <td class="px-6 py-4">
                                @if($order->status === 'paid')
                                    <span class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded-full">{{ __('orders.paid') }}</span>
                                @elseif($order->status === 'pending')
                                    <span class="text-xs px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full">{{ __('orders.pending') }}</span>
                                @elseif($order->status === 'refunded')
                                    <span class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded-full">{{ __('orders.refunded') }}</span>
                                @else
                                    <span class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded-full">{{ ucfirst($order->status) }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-xs">{{ $order->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    @endif
</div>
@endsection
