@extends('layouts.app')
@section('title', __('home.hero_title'))
@section('content')
<div class="text-center py-12">
    @php
        $heroName = \App\Models\SiteSetting::get('site_name', config('app.name', 'Platform'));
        $heroName = (__($heroName) !== $heroName) ? __($heroName) : $heroName;
    @endphp
    <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-3">{{ $heroName }}</h1>
    <p class="text-lg text-gray-600 dark:text-gray-300 mb-6 max-w-2xl mx-auto">{{ __('home.hero_desc') }}</p>
    <div class="flex justify-center space-x-4">
        <a href="{{ route('marketplace') }}" class="px-8 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-base font-medium shadow-sm shadow-indigo-200">{{ __('home.browse_marketplace') }}</a>
        @guest
            <a href="{{ route('register') }}" class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50">{{ __('home.become_developer') }}</a>
        @endguest
    </div>

    {{-- 分享开关开启时：hero 区提供一张可见、非懒加载、绝对 URL 的首图（微信爬虫抓取分享封面） --}}
    @php
        $wxShareOn = \App\Models\SiteSetting::getBoolean('share_enabled', true);
        $wxHeroImg = trim((string) \App\Models\SiteSetting::get('share_default_image', ''));
        $wxHeroImgUrl = $wxHeroImg !== ''
            ? (str_starts_with($wxHeroImg, 'http://') || str_starts_with($wxHeroImg, 'https://') ? $wxHeroImg : asset('storage/' . ltrim($wxHeroImg, '/')))
            : '';
    @endphp
    @if($wxShareOn && $wxHeroImgUrl !== '')
    <div class="max-w-3xl mx-auto mt-8 rounded-xl overflow-hidden border dark:border-gray-700 shadow-md dark:shadow-gray-900">
        <img src="{{ $wxHeroImgUrl }}"
             alt="{{ $heroName }}"
             loading="eager" decoding="async" fetchpriority="high"
             class="w-full h-48 md:h-64 object-cover"
             onerror="this.parentElement.classList.add('hidden')">
    </div>
    @endif

    {{-- 社交分享按钮组（受后台分享开关控制） --}}
    <div class="max-w-md mx-auto text-center">
        @include('partials.share-buttons')
    </div>
</div>

{{-- Banner Ad Carousel --}}
@include('partials.ad-banner')

{{-- Featured Products --}}
@php
    $featuredProducts = \App\Models\Product::where('status', 'published')
        ->withCount('reviews')
        ->latest()
        ->limit(6)
        ->get();
@endphp
@if($featuredProducts->isNotEmpty())
<div class="mt-8">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6 text-center">{{ __('home.popular_plugins') }}</h2>
    <div class="grid md:grid-cols-3 gap-6">
        @foreach($featuredProducts as $product)
        <a href="{{ route('product.show', $product->slug) }}" class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow overflow-hidden block">
            @if($product->cover_image)
            <div class="h-44 overflow-hidden">
                <img src="{{ asset('storage/' . $product->cover_image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
            </div>
            @else
            <div class="h-44 bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center">
                <span class="text-5xl font-bold text-indigo-300">{{ strtoupper(mb_substr($product->name, 0, 1)) }}</span>
            </div>
            @endif
            <div class="p-5">
                <h3 class="text-lg font-semibold mb-1 text-gray-900 dark:text-white">{{ $product->name }}</h3>
                <p class="text-gray-500 dark:text-gray-400 text-sm mb-3 line-clamp-2">{{ Str::limit($product->short_description ?? $product->description, 100) }}</p>
                <div class="flex justify-between items-center">
                    <span class="text-indigo-600 font-bold">{{ $currencySymbol }}{{ number_format($product->price, 2) }}</span>
                    <div class="flex items-center gap-2 text-xs text-gray-400">
                        @if($product->rating)
                        <span class="text-yellow-500">{{ number_format($product->rating, 1) }} ★</span>
                        <span>({{ $product->reviews_count }})</span>
                        @endif
                        <span class="text-gray-300">v{{ $product->current_version ?? $product->version ?? '1.0' }}</span>
                    </div>
                </div>
            </div>
        </a>
        @endforeach
    </div>
    <div class="text-center mt-8">
        <a href="{{ route('marketplace') }}" class="text-indigo-600 hover:text-indigo-800 font-medium">{{ __('home.view_all_plugins') }} →</a>
    </div>
</div>
@endif

{{-- Category Browse Section --}}
@php
    $topCategories = \App\Models\Category::withCount(['products' => fn($q) => $q->where('status', 'published')])
        ->orderByDesc('products_count')
        ->limit(6)
        ->get();
@endphp
@if($topCategories->isNotEmpty())
<div class="mt-16">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6 text-center">{{ __('home.browse_categories') }}</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        @foreach($topCategories as $cat)
        <a href="{{ route('marketplace.category', $cat->slug) }}" class="bg-white rounded-lg border hover:border-indigo-300 hover:shadow-sm transition-all p-4 text-center">
            <div class="text-2xl font-bold text-indigo-600 mb-1">{{ $cat->products_count }}</div>
            <div class="text-sm text-gray-700 dark:text-gray-200 font-medium">{{ $cat->name }}</div>
        </a>
        @endforeach
    </div>
</div>
@endif

{{-- Platform Features (SVG icons instead of emoji) --}}
<div class="grid md:grid-cols-3 gap-8 mt-16">
    <div class="bg-white p-6 rounded-lg shadow-sm text-center">
        <div class="w-14 h-14 mx-auto mb-4 bg-blue-50 rounded-xl flex items-center justify-center">
            <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
        </div>
        <h3 class="text-lg font-semibold mb-2">{{ __('home.feature_licensing_title') }}</h3>
        <p class="text-gray-600 dark:text-gray-300 text-sm">{{ __('home.feature_licensing_desc') }}</p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow-sm text-center">
        <div class="w-14 h-14 mx-auto mb-4 bg-green-50 rounded-xl flex items-center justify-center">
            <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182"/></svg>
        </div>
        <h3 class="text-lg font-semibold mb-2">{{ __('home.feature_updates_title') }}</h3>
        <p class="text-gray-600 dark:text-gray-300 text-sm">{{ __('home.feature_updates_desc') }}</p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow-sm text-center">
        <div class="w-14 h-14 mx-auto mb-4 bg-purple-50 rounded-xl flex items-center justify-center">
            <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z"/></svg>
        </div>
        <h3 class="text-lg font-semibold mb-2">{{ __('home.feature_payments_title') }}</h3>
        <p class="text-gray-600 dark:text-gray-300 text-sm">{{ __('home.feature_payments_desc') }}</p>
    </div>
</div>

{{-- Affiliate Promotion Section --}}
<div class="mt-20 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-gray-800 dark:to-gray-800 rounded-2xl p-8 md:p-12">
    <div class="md:flex items-center justify-between gap-8">
        <div class="md:w-1/2 mb-6 md:mb-0">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">{{ __('home.affiliate_title') }}</h2>
            <p class="text-gray-600 dark:text-gray-300 mb-4">{{ __('home.affiliate_desc') }}</p>
            <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <li class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('home.affiliate_step1') }}
                </li>
                <li class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('home.affiliate_step2') }}
                </li>
                <li class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('home.affiliate_step3') }}
                </li>
            </ul>
            <div class="mt-6">
                @auth
                    <a href="{{ route('affiliate.index') }}" class="inline-block px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">{{ __('home.go_to_affiliate') }}</a>
                @else
                    <a href="{{ route('register') }}" class="inline-block px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">{{ __('home.register_to_promote') }}</a>
                @endauth
            </div>
        </div>
        <div class="md:w-1/2 text-center">
            <div class="bg-white rounded-xl p-8 shadow-sm inline-block">
                <div class="text-5xl mb-3">
                    <svg class="w-14 h-14 mx-auto text-yellow-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/></svg>
                </div>
                <div class="text-3xl font-bold text-indigo-600">{{ \App\Models\SiteSetting::get('affiliate_rate', '10') }}%</div>
                <div class="text-gray-500 dark:text-gray-400 text-sm mt-1">{{ __('home.commission_rate') }}</div>
            </div>
        </div>
    </div>
</div>

{{-- For Developers Section --}}
<div class="mt-20">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 text-center">{{ __('home.for_developers_title') }}</h2>
    <p class="text-gray-500 dark:text-gray-400 text-center mb-8">{{ __('home.for_developers_desc') }}</p>
    <div class="grid md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg border hover:shadow-md transition-shadow">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 mb-4">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.58-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/></svg>
            </div>
            <h3 class="font-semibold mb-2">{{ __('home.dev_publish_title') }}</h3>
            <p class="text-gray-500 dark:text-gray-400 text-sm">{{ __('home.dev_publish_desc') }}</p>
        </div>
        <div class="bg-white p-6 rounded-lg border hover:shadow-md transition-shadow">
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center text-green-600 mb-4">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"/></svg>
            </div>
            <h3 class="font-semibold mb-2">{{ __('home.dev_tier_title') }}</h3>
            <p class="text-gray-500 dark:text-gray-400 text-sm">{{ __('home.dev_tier_desc') }}</p>
        </div>
        <div class="bg-white p-6 rounded-lg border hover:shadow-md transition-shadow">
            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center text-purple-600 mb-4">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </div>
            <h3 class="font-semibold mb-2">{{ __('home.dev_payout_title') }}</h3>
            <p class="text-gray-500 dark:text-gray-400 text-sm">{{ __('home.dev_payout_desc') }}</p>
        </div>
    </div>
</div>

{{-- Platform Stats (with count-up animation) --}}
@php
    $totalProducts = \App\Models\Product::where('status', 'published')->count();
    $totalDevelopers = \App\Models\User::where('role', 'developer')->count();
    $totalOrders = \App\Models\Order::where('status', 'paid')->count();
@endphp
<div class="mt-20 mb-8">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
        <div class="bg-white p-6 rounded-lg border">
            <div class="text-3xl font-bold text-indigo-600 counter" data-target="{{ $totalProducts }}">0</div>
            <div class="text-gray-500 dark:text-gray-400 text-sm mt-1">{{ __('home.stat_products') }}</div>
        </div>
        <div class="bg-white p-6 rounded-lg border">
            <div class="text-3xl font-bold text-indigo-600 counter" data-target="{{ $totalDevelopers }}">0</div>
            <div class="text-gray-500 dark:text-gray-400 text-sm mt-1">{{ __('home.stat_developers') }}</div>
        </div>
        <div class="bg-white p-6 rounded-lg border">
            <div class="text-3xl font-bold text-indigo-600 counter" data-target="{{ $totalOrders }}">0</div>
            <div class="text-gray-500 dark:text-gray-400 text-sm mt-1">{{ __('home.stat_orders') }}</div>
        </div>
        <div class="bg-white p-6 rounded-lg border">
            <div class="text-3xl font-bold text-green-600">{{ \App\Models\SiteSetting::get('affiliate_rate', '10') }}%</div>
            <div class="text-gray-500 dark:text-gray-400 text-sm mt-1">{{ __('home.stat_commission') }}</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var counters = document.querySelectorAll('.counter');
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                var el = entry.target;
                var target = parseInt(el.getAttribute('data-target'));
                var duration = 1200;
                var start = 0;
                var startTime = null;
                function animate(timestamp) {
                    if (!startTime) startTime = timestamp;
                    var progress = Math.min((timestamp - startTime) / duration, 1);
                    var eased = 1 - Math.pow(1 - progress, 3);
                    el.textContent = Math.floor(eased * target);
                    if (progress < 1) {
                        requestAnimationFrame(animate);
                    } else {
                        el.textContent = target;
                    }
                }
                requestAnimationFrame(animate);
                observer.unobserve(el);
            }
        });
    }, { threshold: 0.3 });
    counters.forEach(function(c) { observer.observe(c); });
});
</script>
@endsection
