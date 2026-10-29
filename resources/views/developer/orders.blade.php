@extends('layouts.app')
@section('title', __('dev_orders.title'))
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold dark:text-white">{{ __('dev_orders.title') }}</h1>
</div>

@if(!empty($noTenant))
<div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded-lg">
    <p class="text-yellow-800 dark:text-yellow-300 font-medium">{{ __('dev_orders.pending_setup') }}</p>
    <p class="text-yellow-600 dark:text-yellow-400 text-sm mt-1">{{ __('dev_orders.pending_setup_desc') }}</p>
</div>
@endif

<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_orders.order_num') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_orders.product') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_orders.amount') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_orders.payment') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_orders.status') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_orders.date') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            <tr class="border-t dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="px-4 py-3 text-sm font-medium dark:text-gray-200">#{{ $order->id }}</td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ $order->product->name ?? '-' }}</td>
                <td class="px-4 py-3 text-sm font-medium dark:text-gray-200">{{ $currencySymbol }}{{ number_format((float)$order->amount, 2) }}</td>
                <td class="px-4 py-3 text-sm">
                    <span class="text-gray-600 dark:text-gray-400">{{ ucfirst($order->payment_method ?? '-') }}</span>
                </td>
                <td class="px-4 py-3">
                    @php
                        $badgeClass = match($order->status) {
                            'paid' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                            'pending' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                            'failed' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                            'refunded' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                            default => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                        };
                    @endphp
                    <span class="px-2 py-1 text-xs rounded-full {{ $badgeClass }}">{{ __('order_status.' . $order->status) }}</span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $order->created_at->format('Y-m-d H:i') }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400 dark:text-gray-500">{{ __('dev_orders.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($orders instanceof \Illuminate\Pagination\LengthAwarePaginator && $orders->hasPages())
    <div class="border-t dark:border-gray-700 px-4 py-3">
        {{ $orders->links() }}
    </div>
    @endif
</div>
@endsection
