@extends('layouts.app')
@section('title', __('dev_stats.title'))
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold dark:text-white">{{ __('dev_stats.title') }}</h1>
    <div class="flex gap-2">
        <form method="GET" action="{{ route('developer.stats.index') }}" class="flex gap-2">
            <input type="date" name="from" value="{{ $from }}" class="border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm">
            <span class="py-2 text-gray-500 dark:text-gray-400">{{ __('dev_stats.to') }}</span>
            <input type="date" name="to" value="{{ $to }}" class="border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">{{ __('dev_stats.apply') }}</button>
        </form>
    </div>
</div>

{{-- Stats Cards --}}
<div class="grid md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dev_stats.total_revenue') }}</p>
        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $currencySymbol }}{{ number_format($report['total_revenue'], 2) }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dev_stats.platform_fees') }}</p>
        <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $currencySymbol }}{{ number_format($report['platform_fees'], 2) }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dev_stats.net_income') }}</p>
        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $currencySymbol }}{{ number_format($report['net_income'], 2) }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dev_stats.total_orders') }}</p>
        <p class="text-2xl font-bold dark:text-gray-200">{{ $report['total_orders'] }}</p>
    </div>
</div>

{{-- Revenue Chart (simple bar chart using HTML/CSS) --}}
@if(count($revenueChart) > 0)
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-8">
    <h2 class="text-lg font-semibold mb-4 dark:text-white">{{ __('dev_stats.revenue_trend') }}</h2>
    <div class="space-y-2">
        @foreach($revenueChart as $day)
        <div class="flex items-center gap-4">
            <div class="w-24 text-sm text-gray-500 dark:text-gray-400">{{ $day['date'] }}</div>
            <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded-full h-6 overflow-hidden">
                @if($day['amount'] > 0)
                <div class="bg-green-500 h-full rounded-full flex items-center justify-end pr-2" style="width: {{ min(100, ($day['amount'] / max(array_column($revenueChart, 'amount')) * 100)) }}%">
                    <span class="text-xs text-white font-medium">{{ $currencySymbol }}{{ number_format($day['amount'], 0) }}</span>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Product Performance Table --}}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <div class="p-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold dark:text-white">{{ __('dev_stats.product_performance') }}</h2>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_stats.product') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_stats.type') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_stats.price') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_stats.downloads') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_stats.rating') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_stats.orders') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_stats.revenue') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['products'] as $product)
            <tr class="border-t dark:border-gray-700">
                <td class="px-4 py-3">
                    <p class="font-medium dark:text-gray-200">{{ $product['name'] }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">v{{ $product['version'] }}</p>
                </td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ $product['type'] }}</td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ $currencySymbol }}{{ number_format($product['price'], 2) }}</td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ $product['downloads'] }}</td>
                <td class="px-4 py-3 text-sm">
                    @if($product['rating'] > 0)
                        <span class="text-yellow-500">&#9733;</span> {{ number_format($product['rating'], 1) }}
                    @else
                        <span class="text-gray-400 dark:text-gray-500">-</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ $product['orders_count'] }}</td>
                <td class="px-4 py-3 text-sm font-medium text-green-600 dark:text-green-400">{{ $currencySymbol }}{{ number_format($product['revenue'], 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">{{ __('dev_stats.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
