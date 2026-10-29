@extends('layouts.app')
@section('title', __('dev.dashboard_title'))
@section('content')
<div class="flex items-center justify-between mb-8">
    <h1 class="text-3xl font-bold dark:text-white">{{ $tenant->name ?? __('dev.dashboard_title') }}</h1>
    <div class="flex gap-2">
        <a href="{{ route('affiliate.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-sm text-gray-700 dark:text-gray-200">{{ __('nav.affiliate') }}</a>
        <a href="{{ route('developer.payouts.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-sm text-gray-700 dark:text-gray-200">{{ __('nav.payouts') }}</a>
        <a href="{{ route('developer.api-keys.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-sm text-gray-700 dark:text-gray-200">{{ __('dev.api_keys_title') }}</a>
        <a href="{{ route('developer.products.index') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">{{ __('nav.products') }}</a>
    </div>
</div>

@if(!empty($noTenant))
<div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded-lg">
    <p class="text-yellow-800 dark:text-yellow-300 font-medium">{{ __('dev.pending_setup') }}</p>
    <p class="text-yellow-600 dark:text-yellow-400 text-sm mt-1">{{ __('dev.pending_setup_hint') }}</p>
</div>
@endif

<div class="grid md:grid-cols-5 gap-6 mb-8">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dev.total_income') }}</p>
        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $currencySymbol }}{{ number_format($totalIncome, 2) }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dev.available_balance') }}</p>
        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $currencySymbol }}{{ number_format($availableBalance, 2) }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dev.total_products') }}</p>
        <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $totalProducts }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dev.total_licenses') }}</p>
        <p class="text-2xl font-bold dark:text-gray-200">{{ $totalLicenses }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dev.total_orders') }}</p>
        <p class="text-2xl font-bold dark:text-gray-200">{{ $totalOrders }}</p>
    </div>
</div>

<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <div class="p-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold dark:text-white">{{ __('dev.recent_orders') }}</h2>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('orders.order') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('orders.product') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('orders.amount') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev.method') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('orders.status') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('orders.date') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recentOrders as $order)
            <tr class="border-t dark:border-gray-700">
                <td class="px-4 py-3 text-sm dark:text-gray-200">#{{ $order->id }}</td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ $order->product->name ?? '-' }}</td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ $currencySymbol }}{{ $order->amount }}</td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ $order->payment_method ?? '-' }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs rounded-full
                        {{ $order->status === 'paid' ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : '' }}
                        {{ $order->status === 'pending' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300' : '' }}
                        {{ $order->status === 'failed' ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' : '' }}
                    ">{{ $order->status }}</span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $order->created_at->diffForHumans() }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">{{ __('dev.no_orders') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
