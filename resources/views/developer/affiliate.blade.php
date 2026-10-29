@extends('layouts.app')
@section('title', __('affiliate.title'))
@section('content')
<div class="flex items-center justify-between mb-8">
    <h1 class="text-3xl font-bold dark:text-white">{{ __('affiliate.title') }}</h1>
    <a href="{{ route('home') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-sm text-gray-700 dark:text-gray-200">{{ __('back') }}</a>
</div>

{{-- Stats Cards --}}
<div class="grid md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('affiliate.total_earnings') }}</p>
        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $currencySymbol }}{{ number_format($totalEarnings, 2) }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('affiliate.available_balance') }}</p>
        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $currencySymbol }}{{ number_format($availableBalance, 2) }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('affiliate.total_referrals') }}</p>
        <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $totalReferrals }}</p>
    </div>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('affiliate.commission_rate') }}</p>
        <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $affiliateRate }}%</p>
    </div>
</div>

{{-- Affiliate Link --}}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-8">
    <h2 class="text-lg font-semibold mb-4 dark:text-white">{{ __('affiliate.your_link') }}</h2>
    <div class="flex gap-2">
        <input type="text" id="affiliate-link" value="{{ $affiliateLink }}" readonly
               class="flex-1 border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 text-sm bg-gray-50 dark:bg-gray-700 dark:text-gray-200 font-mono">
        <button onclick="copyLink()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">
            {{ __('affiliate.copy') }}
        </button>
    </div>
    <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">{{ __('affiliate.link_hint') }}</p>
</div>

{{-- Withdraw Section --}}
@if($availableBalance > 0)
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-8">
    <h2 class="text-lg font-semibold mb-4 dark:text-white">{{ __('affiliate.withdraw_title') }}</h2>
    <form method="POST" action="{{ route('affiliate.withdraw') }}" class="flex gap-2 items-end">
        @csrf
        <div class="flex-1">
            <label class="text-sm text-gray-600 dark:text-gray-400">{{ __('affiliate.withdraw_amount') }}</label>
            <input type="number" name="amount" min="1" max="{{ $availableBalance }}" step="0.01"
                   value="{{ $availableBalance }}"
                   class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-4 py-2 text-sm mt-1">
        </div>
        <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
            {{ __('affiliate.withdraw_btn') }}
        </button>
    </form>
    <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">{{ __('affiliate.withdraw_hint') }}</p>
</div>
@endif

{{-- Developer Tier Info (only for developers) --}}
@if($isDeveloper)
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-8">
    <h2 class="text-lg font-semibold mb-4 dark:text-white">{{ __('affiliate.tier_title') }}</h2>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
        {{ __('affiliate.tier_current') }}:
        <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ __('affiliate.tier_' . $tierKey) }}</span>
        ({{ $currentTier['rate'] }}%)
    </p>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
        {{ __('affiliate.monthly_sales') }}: {{ $currencySymbol }}{{ number_format($monthlySales, 2) }}
    </p>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-2 text-left dark:text-gray-300">{{ __('affiliate.tier_name') }}</th>
                    <th class="px-4 py-2 text-left dark:text-gray-300">{{ __('affiliate.tier_min_sales') }}</th>
                    <th class="px-4 py-2 text-left dark:text-gray-300">{{ __('affiliate.tier_rate') }}</th>
                    <th class="px-4 py-2 text-left dark:text-gray-300">{{ __('status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tiers as $key => $tier)
                <tr class="border-t dark:border-gray-700 {{ $key === $tierKey ? 'bg-indigo-50 dark:bg-indigo-900/30' : '' }}">
                    <td class="px-4 py-2 font-medium dark:text-gray-200">{{ __('affiliate.tier_' . $key) }}</td>
                    <td class="px-4 py-2 dark:text-gray-200">{{ $currencySymbol }}{{ number_format($tier['min'], 0) }}+</td>
                    <td class="px-4 py-2 dark:text-gray-200">{{ $tier['rate'] }}%</td>
                    <td class="px-4 py-2">
                        @if($key === $tierKey)
                            <span class="px-2 py-1 text-xs rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">{{ __('affiliate.current') }}</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Recent Commissions --}}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <div class="p-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold dark:text-white">{{ __('affiliate.recent_commissions') }}</h2>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('orders.order') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('orders.product') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('orders.amount') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('affiliate.my_commission') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('orders.date') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recentCommissions as $comm)
            <tr class="border-t dark:border-gray-700">
                <td class="px-4 py-3 text-sm dark:text-gray-200">#{{ $comm->order_id }}</td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ $comm->order->product->name ?? '-' }}</td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ $currencySymbol }}{{ number_format($comm->order->amount, 2) }}</td>
                <td class="px-4 py-3 text-sm font-medium text-green-600 dark:text-green-400">{{ $currencySymbol }}{{ number_format($comm->affiliate_fee, 2) }}</td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $comm->created_at->format('Y-m-d') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">{{ __('affiliate.no_commissions') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($recentCommissions->hasPages())
    <div class="p-4 border-t dark:border-gray-700">{{ $recentCommissions->links() }}</div>
    @endif
</div>

<script>
function copyLink() {
    const input = document.getElementById('affiliate-link');
    input.select();
    document.execCommand('copy');
    alert('{{ __('affiliate.copied') }}');
}
</script>
@endsection
