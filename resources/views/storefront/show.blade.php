@extends('layouts.app')
@section('title', $product->name)

@section('head')
{{-- meta description / Open Graph / JSON-LD 由主布局 ShareMetaComposer 统一注入 --}}
@if(!empty($seo['keywords']))
<meta name="keywords" content="{{ implode(', ', $seo['keywords']) }}">
@endif
<link rel="canonical" href="{{ $seo['canonical'] }}">
@endsection

@section('content')
<div class="grid lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2">
        <nav class="text-sm text-gray-500 mb-4">
            <a href="{{ route('marketplace') }}" class="hover:text-indigo-600">{{ __('shop.title') }}</a>
            @if($product->category)
            &rsaquo; <a href="{{ route('marketplace.category', $product->category->slug) }}" class="hover:text-indigo-600">{{ $product->category->name }}</a>
            @endif
            &rsaquo; <span class="text-gray-900">{{ $product->name }}</span>
        </nav>

        <h1 class="text-3xl font-bold text-gray-900 mb-2 dark:text-gray-100">{{ $product->name }}</h1>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ $localizedShortDescription ?? $product->short_description }}</p>
        
        {{-- 社交分享按钮组（受后台分享开关控制） --}}
        @include('partials.share-buttons')

        {{-- 产品封面图片展示（仅产品自身封面；无封面则不展示，预设分享图只用于 og:image） --}}
        {{-- 分享开关开启时：封面为正文第一张可见、非懒加载、绝对 URL 的 img（微信爬虫抓取分享封面） --}}
        @php
            $wxShareOn = \App\Models\SiteSetting::getBoolean('share_enabled', true);
            $coverPath = trim((string) $product->cover_image);
            $coverAbsoluteUrl = $coverPath !== ''
                ? (str_starts_with($coverPath, 'http://') || str_starts_with($coverPath, 'https://') ? $coverPath : asset('storage/' . ltrim($coverPath, '/')))
                : '';
        @endphp
        @if($product->cover_image)
        <div class="mb-6 rounded-xl overflow-hidden border dark:border-gray-700 shadow-md dark:shadow-gray-900">
            <img src="{{ $wxShareOn && $coverAbsoluteUrl !== '' ? $coverAbsoluteUrl : Storage::disk('public')->url($product->cover_image) }}"
                 alt="{{ $product->name }}"
                 @if($wxShareOn)loading="eager" decoding="async" fetchpriority="high"@endif
                 class="w-full h-64 md:h-80 object-cover"
                 onerror="this.parentElement.classList.add('hidden')">
        </div>
        @endif

        {{-- Screenshots 截图展示 --}}
        @if($product->screenshots && is_array($product->screenshots) && count($product->screenshots) > 0)
        <div class="mt-8 mb-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">{{ __('产品截图') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($product->screenshots as $screenshot)
                <div class="rounded-lg overflow-hidden shadow-md dark:shadow-gray-900 dark:bg-gray-800">
                    @php
                        $url = is_array($screenshot) ? ($screenshot['url'] ?? '') : $screenshot;
                        $caption = is_array($screenshot) ? ($screenshot['caption'] ?? '') : '';
                        // 无产品封面时，第一张截图充当微信抓取首图：eager + 绝对 URL（不改变画廊渲染位置与样式）
                        $isWxFirstImage = $wxShareOn && !$product->cover_image && $loop->first && $url !== '';
                        $screenshotSrc = $isWxFirstImage
                            ? (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') ? $url : url($url))
                            : $url;
                    @endphp
                    @if($url)
                    <a href="{{ $url }}" target="_blank">
                        <img src="{{ $screenshotSrc }}" alt="{{ $caption }}" class="w-full h-auto hover:opacity-90 transition-opacity"@if($isWxFirstImage) loading="eager" decoding="async" fetchpriority="high"@endif>
                    </a>
                    @if($caption)
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 px-2 pb-2">{{ $caption }}</p>
                    @endif
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @php
            $reviewsVisible = \App\Models\SiteSetting::getBoolean('reviews_enabled', config('app.reviews_enabled', true)) && $product->reviews_enabled;
        @endphp

        {{-- 页面内锚点导航：产品介绍 / 安装说明 / 常见问题 / 评价 --}}
        <div class="flex flex-wrap gap-2 mb-6 text-sm">
            <a href="#about" class="px-3 py-1.5 rounded-full bg-white dark:bg-gray-800 border dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 hover:border-indigo-300 shadow-sm">{{ __('product.about') }}</a>
            @if($installationSource !== '')
            <a href="#installation" class="px-3 py-1.5 rounded-full bg-white dark:bg-gray-800 border dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 hover:border-indigo-300 shadow-sm">{{ __('product.installation') }}</a>
            @endif
            @if($faqSource !== '')
            <a href="#faq" class="px-3 py-1.5 rounded-full bg-white dark:bg-gray-800 border dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 hover:border-indigo-300 shadow-sm">{{ __('product.faq') }}</a>
            @endif
            @if($reviewsVisible)
            <a href="#reviews" class="px-3 py-1.5 rounded-full bg-white dark:bg-gray-800 border dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 hover:border-indigo-300 shadow-sm">{{ __('product.reviews') }}</a>
            @endif
        </div>

        <div id="about" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6 scroll-mt-24">
            <h2 class="text-xl font-semibold mb-4 dark:text-gray-100">{{ __('product.about') }}</h2>
            <div class="prose prose-sm max-w-none text-gray-700 dark:text-gray-300">
                @php
                    $desc = isset($localizedDescription) && trim($localizedDescription) !== ''
                        ? $localizedDescription
                        : $product->description;
                @endphp
                @if($desc)
                    @php
                        $hasHtml = strip_tags($desc) !== $desc;
                    @endphp
                    @if($hasHtml)
                        {!! $desc !!}
                    @else
                        {!! nl2br(e($desc)) !!}
                    @endif
                @endif
            </div>
        </div>

        {{-- 安装说明（仅当 readme 解析出 installation 原文时展示） --}}
        @if($installationSource !== '')
        <div id="installation" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6 scroll-mt-24">
            <h2 class="text-xl font-semibold mb-4 dark:text-gray-100">{{ __('product.installation') }}</h2>
            <div class="prose prose-sm max-w-none text-gray-700 dark:text-gray-300">
                @php
                    $hasHtml = strip_tags($installationText) !== $installationText;
                @endphp
                @if($hasHtml)
                    {!! $installationText !!}
                @else
                    {!! nl2br(e($installationText)) !!}
                @endif
            </div>
        </div>
        @endif

        {{-- 常见问题（仅当 readme 解析出 faq 原文时展示） --}}
        @if($faqSource !== '')
        <div id="faq" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6 scroll-mt-24">
            <h2 class="text-xl font-semibold mb-4 dark:text-gray-100">{{ __('product.faq') }}</h2>
            <div class="prose prose-sm max-w-none text-gray-700 dark:text-gray-300">
                @php
                    $hasHtml = strip_tags($faqText) !== $faqText;
                @endphp
                @if($hasHtml)
                    {!! $faqText !!}
                @else
                    {!! nl2br(e($faqText)) !!}
                @endif
            </div>
        </div>
        @endif

        {{-- 永久评价锚点：确保 SDK #reviews 链接始终可跳转 --}}
        <div id="reviews"></div>

        @if($reviewsVisible)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mt-6">
            <h2 class="text-xl font-semibold mb-4 dark:text-gray-100">{{ __('产品评价') }} ({{ $product->reviews()->approved()->count() }})</h2>

            @foreach($product->reviews as $review)
            <div class="border rounded p-4 mb-3 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <span class="text-yellow-500">
                        @for($i=1; $i<=5; $i++)
                            {{ $i <= $review->rating ? '★' : '☆' }}
                        @endfor
                    </span>
                    <strong class="dark:text-gray-200">{{ $review->user?->name ?? 'Anonymous' }}</strong>
                    <span class="text-sm text-gray-500">{{ $review->created_at->format('Y-m-d') }}</span>
                </div>
                <p class="mt-2 text-gray-700 dark:text-gray-300">{{ $review->comment }}</p>

                {{-- 回复列表 --}}
                @foreach($review->replies as $reply)
                <div class="ml-8 mt-2 p-3 bg-gray-50 dark:bg-gray-700/50 rounded text-sm">
                    <strong class="dark:text-gray-200">{{ $reply->user?->name ?? 'Anonymous' }}</strong>
                    <span class="text-gray-500 text-xs ml-2">{{ $reply->created_at->diffForHumans() }}</span>
                    <p class="mt-1 text-gray-700 dark:text-gray-300">{{ $reply->content }}</p>
                </div>
                @endforeach

                {{-- 回复按钮 + 输入框（所有登录用户可见） --}}
                @auth
                <div class="mt-2">
                    <button onclick="this.nextElementSibling.classList.toggle('hidden')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('回复') }}</button>
                    <form method="POST" action="{{ route('reviews.reply', $review->id) }}" class="hidden mt-2">
                        @csrf
                        <textarea name="content" rows="2" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-3 py-2 text-sm" placeholder="{{ __('输入回复内容...') }}" required></textarea>
                        <button type="submit" class="mt-1 px-3 py-1 text-sm bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded hover:bg-indigo-100">{{ __('提交回复') }}</button>
                    </form>
                </div>
                @endauth
            </div>
            @endforeach

            @auth
            @if($product->reviews()->where('user_id', auth()->id())->doesntExist())
            <form method="POST" action="{{ route('reviews.store', $product->slug) }}" class="mt-6 border rounded p-4 dark:border-gray-700">
                @csrf
                <h4 class="font-semibold dark:text-gray-100">{{ __('撰写评价') }}</h4>
                <div class="mt-3">
                    <label class="block text-sm font-medium dark:text-gray-300">{{ __('评分') }}</label>
                    <div class="flex gap-1 mt-1" id="star-rating">
                        @for($i=1; $i<=5; $i++)
                        <label class="cursor-pointer text-2xl text-gray-300 dark:text-gray-600" data-star="{{ $i }}">
                            <input type="radio" name="rating" value="{{ $i }}" class="hidden" required>
                            <span>★</span>
                        </label>
                        @endfor
                    </div>
                    <script>
                    (function(){
                        var container=document.getElementById('star-rating');
                        if(!container)return;
                        var labels=container.querySelectorAll('label[data-star]');
                        var currentRating=0;
                        function paint(n){
                            labels.forEach(function(l){
                                var s=parseInt(l.getAttribute('data-star'));
                                l.querySelector('span').className=s<=n?'text-yellow-500 dark:text-white':'text-gray-300 dark:text-gray-600';
                            });
                        }
                        labels.forEach(function(l){
                            l.addEventListener('click',function(){
                                currentRating=parseInt(l.getAttribute('data-star'));
                                paint(currentRating);
                            });
                            l.addEventListener('mouseenter',function(){
                                paint(parseInt(l.getAttribute('data-star')));
                            });
                        });
                        container.addEventListener('mouseleave',function(){
                            paint(currentRating);
                        });
                    })();
                    </script>
                </div>
                <div class="mt-3">
                    <label class="block text-sm font-medium dark:text-gray-300">{{ __('你的评价') }}</label>
                    <textarea name="comment" rows="4" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"></textarea>
                </div>
                <button type="submit" class="mt-3 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">{{ __('提交评价') }}</button>
            </form>
            @endif
            @else
            <p class="mt-4 text-gray-500"><a href="{{ route('login') }}" class="text-indigo-600">{{ __('登录') }}</a> {{ __('后即可撰写评价。') }}</p>
            @endauth
        </div>
        @endif
    </div>

    @php
        $tiers = $product->getEnabledTiers();
        $userHasAnyTrial = false;
        if (auth()->check() && $product->hasTrial()) {
            $userHasAnyTrial = \App\Models\License::where('product_id', $product->id)
                ->where('is_trial', true)
                ->exists();
        }
        $defaultIdx = 0;
        if ($userHasAnyTrial && count($tiers) > 1) {
            foreach ($tiers as $idx => $t) {
                if ($t['type'] !== 'trial') { $defaultIdx = $idx; break; }
            }
        }
        $defaultTier = $tiers[$defaultIdx] ?? $tiers[0] ?? null;
        // 判断是否为纯 trial 产品（无付费层级，仅 trial）
        $isTrialOnly = count($tiers) === 1 && ($defaultTier['type'] ?? '') === 'trial';
        $isFreeProduct = $isTrialOnly || (float)($defaultTier['price'] ?? $product->price) <= 0;
        $hasFreeTier = collect($tiers)->contains(fn($t) => ($t['type'] ?? '') === 'free');
    @endphp
    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border dark:border-gray-700 p-6 sticky top-8">
            {{-- 产品封面图片（侧栏） --}}
            @if($product->cover_image)
            <div class="mb-4 -mx-6 -mt-6">
                <img src="{{ Storage::disk('public')->url($product->cover_image) }}"
                     alt="{{ $product->name }}"
                     class="w-full h-48 object-cover rounded-t-lg"
                     onerror="this.parentElement.classList.add('hidden')">
            </div>
            @endif

            <div class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-1" id="price-display">
                @if($isFreeProduct)
                    {{ __('product.free') }}
                @else
                    {{ $currencySymbol }}{{ number_format($defaultTier['price'] ?? $product->price, 2) }}
                @endif
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1" id="tier-label-display">{{ __($defaultTier['label'] ?? '') }}</div>
            @if($product->hasTrial() && ($defaultTier['type'] ?? '') === 'trial')
            <div class="text-sm text-orange-600 dark:text-orange-400 font-medium mb-4" id="trial-info-display">
                {{ __('product.free_trial_days', ['days' => $defaultTier['days'] ?? 7]) }}
            </div>
            @else
            <div class="text-sm text-orange-600 dark:text-orange-400 font-medium mb-4 hidden" id="trial-info-display"></div>
            @endif

            @if($hasFreeTier)
            {{-- 免费产品下载区域 --}}
            <div class="mb-3">
                @auth
                <button type="button" id="free-download-btn" class="w-full py-3 bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-800 text-white rounded-lg font-medium flex items-center justify-center gap-2 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    {{ __('product.free_download') }}
                </button>
                <p class="text-xs text-gray-500 dark:text-gray-400 text-center mt-2">{{ __('product.free_description') }}</p>
                @else
                <a href="{{ route('login') }}" class="w-full py-3 bg-gray-500 hover:bg-gray-600 dark:bg-gray-600 dark:hover:bg-gray-700 text-white rounded-lg font-medium flex items-center justify-center gap-2 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    {{ __('product.requires_login') }}
                </a>
                <p class="text-xs text-gray-500 dark:text-gray-400 text-center mt-2">{{ __('product.free_description') }}</p>
                @endauth
            </div>
            <div id="free-download-error" class="hidden text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 p-3 rounded-lg mb-3"></div>

            @else
            @auth
            <form id="checkout-form" method="POST" action="{{ route('checkout.direct') }}" class="mb-3">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="tier_type" id="tier-type-input" value="{{ $defaultTier['type'] ?? '' }}">

                @if(count($tiers) > 1)
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('product.duration') }}</label>
                    <div class="grid {{ count($tiers) > 2 ? 'grid-cols-3' : 'grid-cols-2' }} gap-2" id="tier-selector">
                        @foreach($tiers as $i => $tier)
                        <label class="tier-option relative flex flex-col items-center justify-center p-2 border rounded-lg cursor-pointer text-center transition-all {{ $i === $defaultIdx ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30' : 'border-gray-200 dark:border-gray-600 hover:border-indigo-300 dark:hover:border-indigo-500' }} {{ $tier['type'] === 'trial' && $userHasAnyTrial ? 'opacity-60' : '' }}" data-type="{{ $tier['type'] }}" data-price="{{ $tier['price'] }}" data-label="{{ $tier['label'] }}" data-days="{{ $tier['days'] ?? 0 }}">
                            <input type="radio" name="tier_radio" value="{{ $tier['type'] }}" class="sr-only" {{ $i === $defaultIdx ? 'checked' : '' }}>
                            <span class="text-xs font-medium {{ $tier['type'] === 'trial' ? 'text-orange-600 dark:text-orange-400' : 'text-gray-900 dark:text-gray-100' }}">{{ __($tier['label']) }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                @if($tier['type'] === 'trial')
                                    {{ __('product.free') }}
                                @else
                                    {{ $currencySymbol }}{{ number_format($tier['price'], 2) }}
                                @endif
                            </span>
                            @if(!empty($tier['description']))
                            <span class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ __($tier['description']) }}</span>
                            @endif
                            @if($tier['type'] === 'trial' && $userHasAnyTrial)
                            <span class="text-xs text-amber-600 dark:text-amber-400 font-medium mt-0.5">{{ __('product.already_trialed') }}</span>
                            @endif
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('product.domain') }}</label>
                    <input type="text" name="domain" id="domain-input" placeholder="{{ __('product.domain_placeholder') }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                           pattern="[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}">
                    <p class="text-xs text-gray-400 mt-1">{{ __('product.domain_hint') }}</p>
                </div>

                {{-- 支付方式选择 --}}
                {{-- Tailwind JIT safelist: dark:bg-blue-900/20 dark:text-blue-300 dark:border-gray-700 dark:text-gray-300 --}}
                <span class="hidden dark:bg-blue-900/20 dark:text-blue-300 dark:border-gray-700 dark:text-gray-300"></span>
                {{-- 微信环境引导提示 --}}
                <div id="wechat-payment-guide" class="hidden mb-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-3 text-sm text-yellow-800 dark:text-yellow-200">
                    <span class="mr-1">&#x1F4F1;</span>
                    {{ __('payment.wechat_browser_guide') }}
                    <button type="button" onclick="(function(){navigator.clipboard.writeText(window.location.href).then(function(){alert('链接已复制，请在外部浏览器中粘贴打开');});})();return false;" class="underline font-medium hover:text-yellow-900 dark:hover:text-yellow-100">复制链接到外部浏览器</button>
                    打开。
                </div>
                {{-- 支付宝环境引导提示 --}}
                <div id="alipay-payment-guide" class="hidden mb-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-3 text-sm text-blue-800 dark:text-blue-200">
                    <span class="mr-1">&#x1F4B3;</span>
                    {{ __('payment.alipay_browser_guide') }}
                </div>
                @php $methodCount = count($availableMethods ?? []); @endphp
                @if($methodCount > 1 && !$isFreeProduct)
                <div class="mb-4" id="payment-method-section">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('payment.method') }}</label>
                    <div class="flex gap-2" id="payment-method-selector">
                        @if(in_array('wechat', $availableMethods))
                        <button type="button" data-method="wechat" class="payment-method-btn flex-1 py-2 px-3 rounded-lg border-2 flex items-center justify-center gap-1.5 text-sm font-medium transition-all">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178 1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18zm3.307 3.932c-3.906 0-7.091 2.735-7.091 6.097 0 3.363 3.185 6.098 7.091 6.098.756 0 1.489-.098 2.189-.312a.673.673 0 0 1 .556.076l1.467.858a.254.254 0 0 0 .13.041.227.227 0 0 0 .224-.228c0-.056-.022-.11-.037-.165l-.301-1.141a.457.457 0 0 1 .165-.515C21.095 19.842 22 18.27 22 16.02c0-3.362-3.185-6.097-7.095-6.097zm-2.543 3.504c.496 0 .898.409.898.912s-.402.911-.898.911a.905.905 0 0 1-.898-.911c0-.503.402-.912.898-.912zm5.086 0c.496 0 .898.409.898.912s-.402.911-.898.911a.905.905 0 0 1-.898-.911c0-.503.402-.912.898-.912z"/></svg>
                            {{ __('payment.wechat') }}
                        </button>
                        @endif
                        @if(in_array('alipay', $availableMethods))
                        <button type="button" data-method="alipay" class="payment-method-btn flex-1 py-2 px-3 rounded-lg border-2 flex items-center justify-center gap-1.5 text-sm font-medium transition-all">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M21.422 15.358c-1.126-.404-5.02-1.907-6.725-2.684a16.184 16.184 0 0 0 1.542-4.174h-3.896V7.077h4.8V5.95h-4.8V3.163h-2.186s-.048.375-.14.887H5.742v1.127h4.078a15.093 15.093 0 0 1-1.39 3.326c-.853-.393-1.785-.733-2.786-.983C3.496 6.93 2 7.16 2 8.97c0 1.697 1.498 2.269 3.26 1.752a12.16 12.16 0 0 0 3.476-1.876 14.282 14.282 0 0 0 2.263 2.615c-1.852 1.152-4.2 2.192-4.2 2.192v.002c-2.422 1.158-4.11 2.765-4.11 4.505 0 2.292 2.58 3.21 4.928 1.802 1.696-1.017 3.086-2.895 4.138-5.073.998.498 1.828.94 2.39 1.248C16.585 17.6 22 19.584 22 16.52c0-.83-.284-1.018-.578-1.162zM5.026 9.726c-.974 0-1.764-.282-1.764-1.104 0-.766.638-1.042 1.528-.82.738.184 1.476.49 2.15.876a7.697 7.697 0 0 1-1.914 1.048zm7.862 5.088c-.83-1.728-1.285-3.594-1.285-5.563 0-.33.023-.655.048-.977h2.076a13.42 13.42 0 0 1-1.162 3.506 17.456 17.456 0 0 1-1.677 3.034z"/></svg>
                            {{ __('payment.alipay') }}
                        </button>
                        @endif
                        @if(in_array('stripe', $availableMethods))
                        <button type="button" data-method="stripe" class="payment-method-btn flex-1 py-2 px-3 rounded-lg border-2 flex items-center justify-center gap-1.5 text-sm font-medium transition-all">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            {{ __('payment.stripe') }}
                        </button>
                        @endif
                    </div>
                </div>
                @elseif($methodCount === 0 && !$isFreeProduct)
                <div class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg text-sm text-yellow-700 dark:text-yellow-300 text-center">
                    {{ __('payment.none_available') }}
                </div>
                @endif
                <input type="hidden" name="method" id="payment-method-input" value="{{ $availableMethods[0] ?? 'wechat' }}">

                <button type="submit" id="purchase-btn" class="w-full py-3 {{ $isFreeProduct ? 'bg-green-600 hover:bg-green-700' : 'bg-indigo-600 hover:bg-indigo-700' }} text-white rounded-lg font-medium">
                    {{ $isTrialOnly ? __('product.start_trial') : ($isFreeProduct ? __('product.get_free') : __('product.purchase_now')) }}
                </button>
            </form>
            <div id="checkout-error" class="hidden text-red-600 text-sm bg-red-50 p-3 rounded-lg mb-3"></div>

            <div id="wechat-qr-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
                <div class="bg-white rounded-2xl p-8 max-w-sm w-full mx-4 text-center">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">{{ __('product.wechat_pay') }}</h3>
                    <p class="text-gray-500 text-sm mb-4">{{ __('product.wechat_scan') }}</p>
                    <div id="qr-container" class="flex justify-center mb-4"></div>
                    <p class="text-gray-400 text-xs mb-4">{{ __('product.wechat_waiting') }}</p>
                    <button onclick="closeQrModal()" class="text-gray-500 hover:text-gray-700 text-sm underline">{{ __('cancel') }}</button>
                </div>
            </div>

            <script src="https://cdn.bootcdn.net/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
            <script>
            if (typeof QRCode === 'undefined') {
                var s = document.createElement('script');
                s.src = 'https://unpkg.com/qrcode@1.5.3/build/qrcode.min.js';
                document.head.appendChild(s);
            }
            </script>
            <script>
            var pollingTimer = null;
            var currencySymbol = '{{ $currencySymbol }}';
            var purchaseText = '{{ __('product.purchase_now') }}';
            var trialText = '{{ __('product.start_trial') }}';
            var freeText = '{{ __('product.get_free') }}';
            var freePriceText = '{{ __('product.free') }}';
            var freeTrialDaysText = '{{ __('product.free_trial_days', ['days' => '__DAYS__']) }}';
            var processingText = '{{ __('product.processing') }}';
            var enterDomainText = '{{ __('product.enter_domain') }}';
            var errorText = '{{ __('product.error_occurred') }}';
            var networkErrorText = '{{ __('product.network_error') }}';
            var isTrialOnly = {{ $isTrialOnly ? 'true' : 'false' }};
            var isFreeProduct = {{ $isFreeProduct ? 'true' : 'false' }};
            var hasFreeTier = {{ $hasFreeTier ? 'true' : 'false' }};
            var selectedPaymentMethod = '{{ $availableMethods[0] ?? 'wechat' }}';

            // 初始化支付方式按钮样式
            (function(){
                var btns = document.querySelectorAll('.payment-method-btn');
                btns.forEach(function(btn){
                    if(btn.dataset.method === selectedPaymentMethod){
                        btn.classList.add('border-blue-500','bg-blue-50','dark:bg-blue-900/20','text-blue-700','dark:text-blue-300');
                    } else {
                        btn.classList.add('border-gray-200','dark:border-gray-700','text-gray-700','dark:text-gray-300');
                    }
                    btn.addEventListener('click', function(){
                        selectedPaymentMethod = this.dataset.method;
                        document.getElementById('payment-method-input').value = selectedPaymentMethod;
                        btns.forEach(function(b){
                            b.classList.remove('border-blue-500','bg-blue-50','dark:bg-blue-900/20','text-blue-700','dark:text-blue-300');
                            b.classList.add('border-gray-200','dark:border-gray-700','text-gray-700','dark:text-gray-300');
                        });
                        this.classList.remove('border-gray-200','dark:border-gray-700','text-gray-700','dark:text-gray-300');
                        this.classList.add('border-blue-500','bg-blue-50','dark:bg-blue-900/20','text-blue-700','dark:text-blue-300');
                    });
                });

                // 支付通道环境感知：自动检测微信/支付宝内置浏览器，隐藏不兼容支付方式
                var ua = navigator.userAgent.toLowerCase();
                var isWeChat = ua.indexOf('micromessenger') !== -1;
                var isAlipay = ua.indexOf('alipayclient') !== -1;
                var wechatBtn = document.querySelector('[data-method="wechat"]');
                var alipayBtn = document.querySelector('[data-method="alipay"]');
                var wechatGuide = document.getElementById('wechat-payment-guide');
                var alipayGuide = document.getElementById('alipay-payment-guide');
                var methodInput = document.getElementById('payment-method-input');

                function selectMethod(method) {
                    selectedPaymentMethod = method;
                    if (methodInput) methodInput.value = method;
                    var allBtns = document.querySelectorAll('.payment-method-btn');
                    allBtns.forEach(function(b){
                        b.classList.remove('border-blue-500','bg-blue-50','dark:bg-blue-900/20','text-blue-700','dark:text-blue-300');
                        b.classList.add('border-gray-200','dark:border-gray-700','text-gray-700','dark:text-gray-300');
                    });
                    var target = document.querySelector('[data-method="' + method + '"]');
                    if (target) {
                        target.classList.remove('border-gray-200','dark:border-gray-700','text-gray-700','dark:text-gray-300');
                        target.classList.add('border-blue-500','bg-blue-50','dark:bg-blue-900/20','text-blue-700','dark:text-blue-300');
                    }
                }

                if (isWeChat) {
                    // 微信环境：如果有微信支付，隐藏支付宝；否则保留支付宝但显示引导提示
                    if (wechatGuide) wechatGuide.classList.remove('hidden');
                    if (wechatBtn && alipayBtn) {
                        alipayBtn.style.display = 'none';
                        selectMethod('wechat');
                    } else if (wechatBtn) {
                        selectMethod('wechat');
                    }
                } else if (isAlipay) {
                    // 支付宝环境：如果有支付宝，隐藏微信支付；否则保留微信支付但显示引导提示
                    if (alipayGuide) alipayGuide.classList.remove('hidden');
                    if (alipayBtn && wechatBtn) {
                        wechatBtn.style.display = 'none';
                        selectMethod('alipay');
                    } else if (alipayBtn) {
                        selectMethod('alipay');
                    }
                }
            })();

            document.querySelectorAll('.tier-option').forEach(function(el) {
                el.addEventListener('click', function() {
                    document.querySelectorAll('.tier-option').forEach(function(o) {
                        o.classList.remove('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-900/30');
                        o.classList.add('border-gray-200');
                    });
                    this.classList.remove('border-gray-200');
                    this.classList.add('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-900/30');
                    this.querySelector('input').checked = true;
                    document.getElementById('tier-type-input').value = this.dataset.type;
                    var trialInfo = document.getElementById('trial-info-display');
                    if (this.dataset.type === 'trial') {
                        document.getElementById('price-display').textContent = freePriceText;
                        trialInfo.textContent = freeTrialDaysText.replace('__DAYS__', this.dataset.days || 7);
                        trialInfo.classList.remove('hidden');
                    } else {
                        document.getElementById('price-display').textContent = currencySymbol + parseFloat(this.dataset.price).toFixed(2);
                        trialInfo.classList.add('hidden');
                    }
                    document.getElementById('tier-label-display').textContent = this.dataset.label;
                    var btn = document.getElementById('purchase-btn');
                    btn.textContent = this.dataset.type === 'trial' ? trialText : purchaseText;
                });
            });

            function closeQrModal() {
                document.getElementById('wechat-qr-modal').classList.add('hidden');
                if (pollingTimer) { clearInterval(pollingTimer); pollingTimer = null; }
                var btn = document.getElementById('purchase-btn');
                btn.disabled = false;
                btn.textContent = document.querySelector('.tier-option.border-indigo-500 input')?.value === 'trial' ? trialText : purchaseText;
            }

            document.getElementById('checkout-form').addEventListener('submit', function(e) {
                e.preventDefault();
                var btn = document.getElementById('purchase-btn');
                var errDiv = document.getElementById('checkout-error');
                var domain = document.getElementById('domain-input').value.trim();
                if (!domain) { errDiv.textContent = enterDomainText; errDiv.classList.remove('hidden'); return; }
                btn.disabled = true;
                btn.textContent = processingText;
                errDiv.classList.add('hidden');

                fetch(this.action, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('input[name=_token]').value, 'Accept': 'application/json'},
                    body: JSON.stringify({
                        product_id: {{ $product->id }},
                        tier_type: document.getElementById('tier-type-input').value,
                        domain: domain,
                        method: selectedPaymentMethod
                    })
                })
                .then(function(r) { return r.json().then(function(d) { return {ok: r.ok, data: d}; }); })
                .then(function(res) {
                    if (res.ok && res.data.checkout_url) {
                        var pm = res.data.payment_method;
                        if (pm === 'wechat' && res.data.checkout_url.indexOf('weixin://') === 0) {
                            showWechatQr(res.data.checkout_url, res.data.order_id);
                        } else if (pm === 'wechat') {
                            showWechatQr(res.data.checkout_url, res.data.order_id);
                        } else {
                            // stripe / alipay / free → redirect
                            window.location.href = res.data.checkout_url;
                        }
                    } else {
                        var msg = res.data.error || res.data.message || '';
                        if (!msg && res.data.errors) {
                            msg = Object.values(res.data.errors).flat().join(', ');
                        }
                        errDiv.textContent = msg || errorText;
                        errDiv.classList.remove('hidden');
                        btn.disabled = false;
                        btn.textContent = document.getElementById('tier-type-input').value === 'trial' ? trialText : purchaseText;
                    }
                })
                .catch(function() {
                    errDiv.textContent = networkErrorText;
                    errDiv.classList.remove('hidden');
                    btn.disabled = false;
                    btn.textContent = document.getElementById('tier-type-input').value === 'trial' ? trialText : purchaseText;
                });
            });

            function showWechatQr(codeUrl, orderId) {
                var container = document.getElementById('qr-container');
                container.innerHTML = '';
                document.getElementById('wechat-qr-modal').classList.remove('hidden');

                if (typeof QRCode === 'function' && QRCode.prototype && QRCode.prototype.makeCode) {
                    new QRCode(container, {
                        text: codeUrl,
                        width: 200,
                        height: 200,
                        colorDark: '#000000',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.M
                    });
                }
                else if (typeof QRCode !== 'undefined' && typeof QRCode.toCanvas === 'function') {
                    QRCode.toCanvas(codeUrl, {width: 200, margin: 2}, function(err, canvas) {
                        if (!err) { container.appendChild(canvas); }
                        else { showQrFallback(container, codeUrl); }
                    });
                }
                else {
                    showQrFallback(container, codeUrl);
                }

                pollingTimer = setInterval(function() {
                    fetch('/checkout/status/' + orderId, {headers: {'Accept': 'application/json'}})
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.paid) {
                            clearInterval(pollingTimer);
                            pollingTimer = null;
                            window.location.href = '/checkout/success?session_id=' + orderId;
                        }
                    });
                }, 3000);
            }

            function showQrFallback(container, codeUrl) {
                var img = document.createElement('img');
                img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(codeUrl);
                img.alt = '{{ __('product.wechat_pay') }}';
                img.width = 200;
                img.height = 200;
                img.onerror = function() {
                    container.innerHTML = '<p class="text-gray-500 text-sm break-all">' + codeUrl + '</p><p class="text-xs text-gray-400 mt-2">{{ __('product.copy_url') }}</p>';
                };
                container.appendChild(img);
            }

            </script>
            @else
            <a href="{{ route('login') }}" class="block w-full py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-center font-medium mb-3">
                {{ __('product.login_to_purchase') }}
            </a>
            @endauth
            @endif

            {{-- 免费下载按钮 JS（所有用户可见，包括未登录） --}}
            @if($hasFreeTier)
            <script>
            (function(){
                var freeBtn = document.getElementById('free-download-btn');
                if (!freeBtn) return;
                var freeBtnOriginalHTML = freeBtn.innerHTML;
                var freeDownloadText = '{{ __('product.free_download') }}';
                var freeProcessingText = '{{ __('product.processing') }}';
                freeBtn.addEventListener('click', function() {
                    this.disabled = true;
                    this.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> ' + freeProcessingText;
                    var errDiv = document.getElementById('free-download-error');
                    if (errDiv) errDiv.classList.add('hidden');

                    fetch('/api/v2/download?' + new URLSearchParams({
                        product: '{{ $product->slug }}',
                        version: '{{ $product->version ?? "latest" }}'
                    }))
                    .then(function(r) {
                        if (!r.ok) throw new Error('Download failed');
                        return r.blob();
                    })
                    .then(function(blob) {
                        var url = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = '{{ $product->slug }}-{{ $product->version ?? "latest" }}.zip';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        URL.revokeObjectURL(url);
                        freeBtn.disabled = false;
                        freeBtn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> ' + freeDownloadText;
                        setTimeout(function() { freeBtn.innerHTML = freeBtnOriginalHTML; }, 2000);
                    })
                    .catch(function(err) {
                        freeBtn.disabled = false;
                        freeBtn.innerHTML = freeBtnOriginalHTML;
                        if (errDiv) {
                            errDiv.textContent = '{{ __('product.network_error') }}';
                            errDiv.classList.remove('hidden');
                        }
                    });
                });
            })();
            </script>
            @endif

            <div class="space-y-3 text-sm text-gray-600 border-t pt-4 mt-4">
                <div class="flex justify-between">
                    <span>{{ __('product.developer') }}</span>
                    <span class="text-gray-900">{{ $product->tenant->name ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span>{{ __('product.version') }}</span>
                    <span class="text-gray-900">{{ $product->version ?? '1.0' }}</span>
                </div>
                @if($product->rating && \App\Models\SiteSetting::getBoolean('reviews_enabled', config('app.reviews_enabled', true)) && $product->reviews_enabled)
                <div class="flex justify-between">
                    <span>{{ __('product.rating') }}</span>
                    <span class="text-yellow-500">
                        {{ number_format($product->rating, 1) }} ★ ({{ $product->reviews()->approved()->count() }})
                    </span>
                </div>
                @endif
                <div class="flex justify-between">
                    <span>{{ __('product.views') }}</span>
                    <span class="text-gray-900">{{ number_format($product->views) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>{{ __('product.downloads') }}</span>
                    <span class="text-gray-900">{{ number_format($product->downloads ?? 0) }}</span>
                </div>
                @if($product->category)
                <div class="flex justify-between">
                    <span>{{ __('product.category') }}</span>
                    <a href="{{ route('marketplace.category', $product->category->slug) }}" class="text-indigo-600 hover:text-indigo-800">
                        {{ $product->category->name }}
                    </a>
                </div>
                @endif
            </div>

            @if($product->tags && is_array($product->tags))
            <div class="flex flex-wrap gap-1 mt-4 pt-4 border-t">
                @foreach($product->tags as $tag)
                <span class="text-xs bg-gray-100 text-gray-500 px-2 py-1 rounded">{{ $tag }}</span>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>

@if($related->isNotEmpty())
<div class="mt-12">
    {{-- Sidebar Banner Ad (above related products) --}}
    @include('partials.ad-banner')

    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ __('product.related') }}</h2>
    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($related as $rel)
        <a href="{{ route('product.show', $rel->slug) }}" class="block bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-md transition-shadow p-4 border dark:border-gray-700">
            @if($rel->cover_image)
            <div class="mb-3 -mx-4 -mt-4">
                <img src="{{ Storage::disk('public')->url($rel->cover_image) }}" alt="{{ $rel->name }}" class="w-full h-32 object-cover rounded-t-lg" onerror="this.parentElement.classList.add('hidden')">
            </div>
            @endif
            <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">{{ $rel->name }}</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ Str::limit($rel->short_description, 60) }}</p>
            <span class="text-indigo-600 dark:text-indigo-400 font-bold">{{ $currencySymbol }}{{ number_format($rel->price, 2) }}</span>
        </a>
        @endforeach
    </div>
</div>
@endif
@endsection
