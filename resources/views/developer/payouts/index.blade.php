@extends('layouts.app')
@section('title', __('payout.title'))
@section('content')
@php $cs = \App\Models\SiteSetting::get('currency_symbol', '¥'); @endphp
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold dark:text-white">{{ __('payout.title') }}</h1>
</div>

{{-- Payout Accounts --}}
@if($tenant)
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-8">
    <h2 class="text-lg font-semibold mb-4 dark:text-white">{{ __('payout.accounts') }}</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('payout.accounts_desc') }}</p>
    <form method="POST" action="{{ route('developer.payouts.accounts') }}" class="grid md:grid-cols-2 gap-4">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('payout.alipay_account') }}</label>
            <input type="text" name="alipay_account" value="{{ old('alipay_account', $tenant->alipay_account) }}" placeholder="{{ __('payout.alipay_placeholder') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('payout.wechat_account') }}</label>
            <input type="text" name="wechat_account" value="{{ old('wechat_account', $tenant->wechat_account) }}" placeholder="{{ __('payout.wechat_placeholder') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('payout.stripe_account') }}</label>
            <input type="text" name="stripe_account_id" value="{{ old('stripe_account_id', $tenant->stripe_account_id) }}" placeholder="{{ __('payout.stripe_placeholder') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2">
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('payout.stripe_hint') }}</p>
        </div>
        <div class="md:col-span-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">{{ __('payout.save_accounts') }}</button>
        </div>
    </form>
</div>
@endif

{{-- Balance Cards --}}
<div class="grid md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('payout.available_balance') }}</p>
        <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ $cs }}{{ number_format($availableBalance, 2) }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('payout.total_earned') }}</p>
        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $cs }}{{ number_format($totalIncome, 2) }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('payout.total_paid_out') }}</p>
        <p class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $cs }}{{ number_format($totalPaidOut, 2) }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('payout.methods') }}</p>
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Stripe / Alipay / WeChat Pay</p>
    </div>
</div>

{{-- Request Payout --}}
@if($availableBalance >= 10)
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-8">
    <h2 class="text-lg font-semibold mb-4 dark:text-white">{{ __('payout.request') }}</h2>
    <form method="POST" action="{{ route('developer.payouts.store') }}" class="flex items-end gap-4">
        @csrf
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('payout.amount') }}</label>
            <input type="number" name="amount" step="0.01" min="10" max="{{ $availableBalance }}" value="{{ $availableBalance }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2" required>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('payout.minimum') }}: {{ $cs }}10.00</p>
        </div>
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('payout.method') }}</label>
            <select name="method" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2">
                <option value="stripe">{{ __('payout.stripe') }}</option>
                <option value="alipay">{{ __('payout.alipay') }}</option>
                <option value="wechat">{{ __('payout.wechat_pay') }}</option>
            </select>
        </div>
        <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">{{ __('payout.submit') }}</button>
    </form>
</div>
@endif

{{-- Payout History --}}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <div class="p-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold dark:text-white">{{ __('payout.history') }}</h2>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">ID</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('payout.amount') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('payout.method') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('payout.status') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('payout.date') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payouts as $payout)
            <tr class="border-t dark:border-gray-700">
                <td class="px-4 py-3 text-sm dark:text-gray-200">#{{ $payout->id }}</td>
                <td class="px-4 py-3 text-sm font-medium dark:text-gray-200">{{ $cs }}{{ number_format($payout->amount, 2) }}</td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ strtoupper($payout->method) }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs rounded-full
                        {{ $payout->status === 'paid' ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : '' }}
                        {{ $payout->status === 'pending' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300' : '' }}
                        {{ $payout->status === 'processing' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : '' }}
                        {{ $payout->status === 'failed' ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' : '' }}
                    ">{{ __('payout.status_' . $payout->status) }}</span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $payout->created_at->format('Y-m-d H:i') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">{{ __('payout.no_history') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-4 border-t dark:border-gray-700">{{ $payouts->links() }}</div>
</div>
@endsection
