<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ \App\Models\SiteSetting::get('site_name', config('app.name', 'Platform')) }}">
    @if(file_exists(public_path('icons/icon-192.png')))
    <link rel="manifest" href="/manifest.json">
    @endif
    @php
        $seoTitle = \App\Models\SiteSetting::get('seo_title', config('app.name', 'Platform'));
        $seoTitle = trim($seoTitle, '() ');
        $seoTitle = (__($seoTitle) !== $seoTitle) ? __($seoTitle) : $seoTitle;
        $seoDesc = \App\Models\SiteSetting::get('seo_description', '');
        $seoDesc = trim($seoDesc, '() ');
        $seoDesc = (__($seoDesc) !== $seoDesc) ? __($seoDesc) : $seoDesc;
        $seoKeywords = \App\Models\SiteSetting::get('seo_keywords', '');
        $seoKeywords = trim($seoKeywords, '() ');
        $seoKeywords = (__($seoKeywords) !== $seoKeywords) ? __($seoKeywords) : $seoKeywords;
        // Unified <title> format: {page title} - {site_name}
        $titleSiteName = trim((string) \App\Models\SiteSetting::get('site_name', config('app.name', 'Platform')));
        if ($titleSiteName !== '') {
            $titleSiteName = (__($titleSiteName) !== $titleSiteName) ? __($titleSiteName) : $titleSiteName;
            $titleSiteName = trim((string) $titleSiteName);
        }
        $pageTitle = trim((string) $__env->yieldContent('title'));
        if ($pageTitle === '') {
            $fullTitle = ($titleSiteName !== '') ? $titleSiteName : $seoTitle;
        } elseif ($titleSiteName === '' || mb_strpos($pageTitle, $titleSiteName) !== false) {
            $fullTitle = $pageTitle;
        } else {
            $fullTitle = $pageTitle . ' - ' . $titleSiteName;
        }
    @endphp
    <title>{{ $fullTitle }}</title>
    {{-- meta description 始终注入：产品页=产品简介，其它页=默认分享描述（由 ShareMetaComposer 提供） --}}
    <meta name="description" content="{{ $shareDescription ?? $seoDesc }}">
    <meta name="keywords" content="{{ $seoKeywords }}">
    {{-- Open Graph：仅在后台分享开关开启时注入 --}}
    @if(!empty($shareEnabled ?? false))
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $shareDescription ?? $seoDesc }}">
    @if(!empty($shareImage))
    <meta property="og:image" content="{{ $shareImage }}">
    @endif
    <meta property="og:url" content="{{ $shareUrl ?? request()->fullUrl() }}">
    <meta property="og:type" content="{{ $shareType ?? 'website' }}">
    @endif
    {{-- JSON-LD 始终注入：产品页 SoftwareApplication，其它页 WebSite --}}
    @if(!empty($shareJsonLd ?? null))
    <script type="application/ld+json">{!! json_encode($shareJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
    @if($favicon = \App\Models\SiteSetting::get('site_favicon'))
        <link rel="icon" href="{{ str_starts_with($favicon, 'http') ? $favicon : asset('storage/' . $favicon) }}">
    @endif
    @yield('head')
    <script>
        // Apply dark class before render to prevent flash
        (function() {
            if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
    /* Global dark mode overrides for content areas */
    .dark main .bg-white { background-color: #1f2937 !important; }
    .dark main .bg-gray-50 { background-color: #374151 !important; }
    .dark main .bg-indigo-50 { background-color: rgba(99,102,241,0.15) !important; }
    .dark main .bg-green-50 { background-color: rgba(34,197,94,0.1) !important; }
    .dark main .bg-red-50 { background-color: rgba(239,68,68,0.1) !important; }
    .dark main .bg-red-100 { background-color: rgba(239,68,68,0.2) !important; }
    .dark main .bg-green-100 { background-color: rgba(34,197,94,0.2) !important; }
    .dark main .bg-yellow-50, .dark main .bg-amber-50 { background-color: rgba(245,158,11,0.1) !important; }
    .dark main .bg-blue-50 { background-color: rgba(59,130,246,0.1) !important; }
    .dark main .border-gray-100, .dark main .border-gray-200 { border-color: #374151 !important; }
    .dark main .text-red-600 { color: #fca5a5 !important; }
    .dark main .text-green-600 { color: #86efac !important; }
    .dark main h1, .dark main h2, .dark main h3, .dark main h4, .dark main h5, .dark main h6 { color: #f3f4f6; }
    .dark main p, .dark main li, .dark main span, .dark main dt, .dark main dd { color: #d1d5db; }
    .dark main label { color: #d1d5db; }
    .dark main .text-gray-900 { color: #f3f4f6 !important; }
    .dark main .text-gray-800 { color: #e5e7eb !important; }
    .dark main .text-gray-700 { color: #d1d5db !important; }
    .dark main .text-gray-600 { color: #9ca3af !important; }
    .dark main .text-gray-500 { color: #9ca3af !important; }
    .dark main .text-gray-400 { color: #6b7280 !important; }
    .dark main .text-black { color: #f3f4f6 !important; }
    .dark main table thead { background-color: #374151 !important; }
    .dark main table th { color: #d1d5db !important; border-color: #4b5563 !important; }
    .dark main table td { border-color: #4b5563 !important; }
    .dark main table tr { border-color: #4b5563 !important; }
    .dark main .border-t, .dark main .border-b { border-color: #4b5563 !important; }
    .dark main input, .dark main textarea, .dark main select {
        background-color: #374151; color: #f3f4f6; border-color: #4b5563;
    }
    .dark main code:not(.code-block code) { background-color: #374151; color: #a5b4fc; padding: 2px 4px; border-radius: 4px; }
    .dark main .shadow-sm { box-shadow: 0 1px 2px rgba(0,0,0,0.3) !important; }
    .dark main .shadow { box-shadow: 0 1px 3px rgba(0,0,0,0.4) !important; }
    .dark main a:not(.text-white):not(.text-indigo-600):not(.text-green-600):not(.text-red-500):not([class*="dark:text"]) { color: #d1d5db; }
    /* Dark mode scrollbar for user dropdown menu */
    .dark .user-dropdown-scroll::-webkit-scrollbar { width: 6px; }
    .dark .user-dropdown-scroll::-webkit-scrollbar-track { background: transparent; }
    .dark .user-dropdown-scroll::-webkit-scrollbar-thumb { background-color: #6b7280; border-radius: 3px; }
    .user-dropdown-scroll::-webkit-scrollbar { width: 6px; }
    .user-dropdown-scroll::-webkit-scrollbar-track { background: transparent; }
    .user-dropdown-scroll::-webkit-scrollbar-thumb { background-color: #d1d5db; border-radius: 3px; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen flex flex-col transition-colors">
    @php
        $preloaderType = \App\Models\SiteSetting::get('preloader_type', 'none');
        $preloaderImage = \App\Models\SiteSetting::get('preloader_image', '');
    @endphp
    @if($preloaderType !== 'none')
    <div id="preloader" style="position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:#fff;transition:opacity 0.5s ease;">
        <div id="preloader-anim">
            @if($preloaderType === 'custom' && $preloaderImage)
                <img src="{{ str_starts_with($preloaderImage, 'http') ? $preloaderImage : asset('storage/' . $preloaderImage) }}" alt="Loading" style="max-width:120px;max-height:120px;">
            @elseif($preloaderType === 'cube_tilt')
                <div class="pl-cube"><div></div><div></div><div></div><div></div><div></div><div></div></div>
            @elseif($preloaderType === 'circle_1')
                <div class="pl-circle-pulse"></div>
            @elseif($preloaderType === 'circle_2')
                <div class="pl-circle-ripple"><div></div><div></div></div>
            @elseif($preloaderType === 'spinner_1')
                <div class="pl-spinner-1"></div>
            @elseif($preloaderType === 'spinner_2')
                <div class="pl-spinner-2"><div></div><div></div><div></div></div>
            @else
                <div class="pl-spinner-1"></div>
            @endif
        </div>
    </div>
    <style>
    /* Dark mode preloader background */
    .dark #preloader { background: #111827; }

    /* Spinner 1: simple rotating border */
    .pl-spinner-1 {
        width: 48px; height: 48px;
        border: 4px solid #e5e7eb; border-top-color: #6366f1;
        border-radius: 50%;
        animation: pl-spin 0.8s linear infinite;
    }
    @keyframes pl-spin { to { transform: rotate(360deg); } }

    /* Spinner 2: three bouncing dots */
    .pl-spinner-2 { display: flex; gap: 6px; }
    .pl-spinner-2 > div {
        width: 14px; height: 14px; border-radius: 50%; background: #6366f1;
        animation: pl-bounce 1.2s ease-in-out infinite;
    }
    .pl-spinner-2 > div:nth-child(2) { animation-delay: 0.15s; }
    .pl-spinner-2 > div:nth-child(3) { animation-delay: 0.3s; }
    @keyframes pl-bounce {
        0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
        40% { transform: scale(1); opacity: 1; }
    }

    /* Circle 1: pulsing circle */
    .pl-circle-pulse {
        width: 48px; height: 48px; border-radius: 50%; background: #6366f1;
        animation: pl-pulse 1.2s ease-in-out infinite;
    }
    @keyframes pl-pulse {
        0% { transform: scale(0.8); opacity: 1; }
        50% { transform: scale(1.2); opacity: 0.4; }
        100% { transform: scale(0.8); opacity: 1; }
    }

    /* Circle 2: expanding ripple */
    .pl-circle-ripple { position: relative; width: 48px; height: 48px; }
    .pl-circle-ripple > div {
        position: absolute; inset: 0; border: 3px solid #6366f1; border-radius: 50%;
        animation: pl-ripple 1.5s ease-out infinite;
    }
    .pl-circle-ripple > div:nth-child(2) { animation-delay: 0.5s; }
    @keyframes pl-ripple {
        0% { transform: scale(0.5); opacity: 1; }
        100% { transform: scale(1.5); opacity: 0; }
    }

    /* Cube Tilt: 3D rotating cube */
    .pl-cube {
        width: 40px; height: 40px;
        transform-style: preserve-3d;
        animation: pl-cube-rotate 2s infinite ease-in-out;
    }
    .pl-cube > div {
        position: absolute; width: 40px; height: 40px;
        background: rgba(99, 102, 241, 0.7);
        border: 1px solid rgba(99, 102, 241, 0.9);
    }
    .pl-cube > div:nth-child(1) { transform: translateZ(20px); }
    .pl-cube > div:nth-child(2) { transform: rotateY(90deg) translateZ(20px); }
    .pl-cube > div:nth-child(3) { transform: rotateY(180deg) translateZ(20px); }
    .pl-cube > div:nth-child(4) { transform: rotateY(-90deg) translateZ(20px); }
    .pl-cube > div:nth-child(5) { transform: rotateX(90deg) translateZ(20px); }
    .pl-cube > div:nth-child(6) { transform: rotateX(-90deg) translateZ(20px); }
    @keyframes pl-cube-rotate {
        0% { transform: rotateX(0) rotateY(0); }
        50% { transform: rotateX(180deg) rotateY(180deg); }
        100% { transform: rotateX(360deg) rotateY(360deg); }
    }
    </style>
    <script>
    (function() {
        var timeout = setTimeout(function() {
            var p = document.getElementById('preloader');
            if (p) { p.style.opacity = '0'; setTimeout(function() { p.style.display = 'none'; }, 500); }
        }, 8000);
        window.addEventListener('load', function() {
            clearTimeout(timeout);
            var p = document.getElementById('preloader');
            if (p) { p.style.opacity = '0'; setTimeout(function() { p.style.display = 'none'; }, 500); }
        });
    })();
    </script>
    @endif
    <nav class="sticky top-0 z-50 bg-white dark:bg-gray-800 shadow-sm border-b dark:border-gray-700 transition-colors" x-data="{ mobileMenu: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                {{-- Left: Logo + Desktop Menu --}}
                <div class="flex items-center space-x-6">
                    @php
                        $siteName = \App\Models\SiteSetting::get('site_name', config('app.name', 'Platform'));
                        $siteName = (__($siteName) !== $siteName) ? __($siteName) : $siteName;
                    @endphp
                    <a href="{{ route('home') }}" data-nav-sound class="flex items-center space-x-2 shrink-0">
                        @if($logo = \App\Models\SiteSetting::get('site_logo'))
                            <img src="{{ str_starts_with($logo, 'http') ? $logo : asset('storage/' . $logo) }}" alt="{{ $siteName }}" class="h-8 w-auto">
                        @endif
                        <span class="text-xl font-bold text-indigo-600 dark:text-indigo-400">{{ $siteName }}</span>
                    </a>

                    {{-- Desktop nav items (hidden on mobile) --}}
                    <div class="hidden md:flex items-center space-x-4">
                        @php $navItems = \App\Models\NavigationItem::getMenu('header'); @endphp
                        @foreach($navItems as $item)
                            @php $label = (__($item['label']) !== $item['label']) ? __($item['label']) : $item['label']; @endphp
                            @if(empty($item['children']))
                                @php $isActive = request()->is(ltrim($item['url'], '/') ?: '/') || request()->url() === $item['url']; @endphp
                                <a href="{{ $item['url'] }}" target="{{ $item['target'] ?? '_self' }}" data-nav-sound
                                   class="{{ $isActive ? 'text-indigo-600 font-semibold border-b-2 border-indigo-600' : 'text-gray-600 dark:text-gray-300' }} hover:text-gray-900 dark:hover:text-white text-base pb-1">
                                    {{ $label }}
                                </a>
                                {{-- Inject Talent Market & Bidding Market after Marketplace --}}
                                @if(str_contains($item['url'], '/marketplace') && \App\Models\SiteSetting::get('freelance_enabled', '0') === '1')
                                    <a href="{{ route('projects.talent') }}" data-nav-sound class="{{ request()->routeIs('projects.talent') ? 'text-indigo-600 font-semibold border-b-2 border-indigo-600' : 'text-gray-600 dark:text-gray-300' }} hover:text-gray-900 dark:hover:text-white text-base pb-1">
                                        {{ __('nav.talent_market') }}
                                    </a>
                                    <a href="{{ route('projects.index') }}" data-nav-sound class="{{ request()->routeIs('projects.index') || request()->routeIs('projects.show') || request()->routeIs('projects.create') ? 'text-indigo-600 font-semibold border-b-2 border-indigo-600' : 'text-gray-600 dark:text-gray-300' }} hover:text-gray-900 dark:hover:text-white text-base pb-1">
                                        {{ __('nav.bidding_market') }}
                                    </a>
                                @endif
                            @else
                                @php $groupActive = collect($item['children'])->contains(fn($c) => request()->is(ltrim($c['url'], '/')) || request()->url() === $c['url']); @endphp
                                <div class="relative group">
                                    <button data-nav-sound class="{{ $groupActive ? 'text-indigo-600 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' }} text-base flex items-center">
                                        {{ $label }}
                                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <div class="absolute left-0 mt-2 w-48 bg-white dark:bg-gray-700 rounded-md shadow-lg py-1 z-50 hidden group-hover:block">
                                        @foreach($item['children'] as $child)
                                            @php $childActive = request()->is(ltrim($child['url'], '/')) || request()->url() === $child['url']; @endphp
                                            @php $childLabel = (__($child['label']) !== $child['label']) ? __($child['label']) : $child['label']; @endphp
                                            <a href="{{ $child['url'] }}" target="{{ $child['target'] ?? '_self' }}" data-nav-sound
                                               class="block px-4 py-2 text-sm {{ $childActive ? 'text-indigo-600 font-semibold bg-indigo-50 dark:bg-gray-600' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600' }}">
                                                {{ $childLabel }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                        <a href="{{ route('docs') }}" data-nav-sound class="{{ request()->routeIs('docs') ? 'text-indigo-600 font-semibold border-b-2 border-indigo-600' : 'text-gray-600 dark:text-gray-300' }} hover:text-gray-900 dark:hover:text-white text-base pb-1">
                            {{ __('nav.documentation') }}
                        </a>
                    </div>
                </div>

                {{-- Right: Lang Switch + User Menu (desktop) --}}
                <div class="hidden md:flex items-center space-x-3">
                    {{-- Dark Mode Toggle (desktop) --}}
                    <button data-nav-sound onclick="toggleTheme()" class="p-2 rounded-full text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="{{ __('theme_toggle') }}">
                        <svg class="w-5 h-5 hidden dark:block" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"/></svg>
                        <svg class="w-5 h-5 block dark:hidden" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                    </button>
                    {{-- Language Switcher (horizontal inline) --}}
                    @php $currentLang = app()->getLocale(); @endphp
                    <div class="flex items-center text-xs border border-gray-200 rounded-full overflow-hidden">
                        <a href="?lang=zh_CN" data-nav-sound class="px-2 py-1 {{ $currentLang === 'zh_CN' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-100' }}">中文</a>
                        <a href="?lang=en" data-nav-sound class="px-2 py-1 {{ $currentLang === 'en' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-100' }}">EN</a>
                    </div>

                    @auth
                        {{-- Notification Bell --}}
                        <div class="relative" x-data="{ open: false }">
                            <button data-nav-sound @click="open = !open" class="relative p-2 text-gray-500 hover:text-indigo-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                                <span id="notif-badge" class="hidden absolute -top-0.5 -right-0.5 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center leading-none"></span>
                            </button>
                            <div x-show="open" @click.away="open = false" x-transition
                                 class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-700 rounded-lg shadow-lg border dark:border-gray-600 z-50 overflow-hidden">
                                <div class="flex items-center justify-between px-4 py-3 border-b dark:border-gray-600 bg-gray-50 dark:bg-gray-800">
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ __('notification.title') }}</span>
                                    <a href="{{ route('notifications.index') }}" data-nav-sound class="text-xs text-indigo-600 hover:text-indigo-800">{{ __('notification.view_all') }}</a>
                                </div>
                                <div id="notif-list" class="max-h-72 overflow-y-auto">
                                    <div class="px-4 py-6 text-center text-sm text-gray-400">{{ __('notification.loading') }}</div>
                                </div>
                            </div>
                        </div>

                        @php $user = auth()->user(); @endphp
                        <div class="relative group">
                            <button data-nav-sound class="flex items-center space-x-2 text-sm text-gray-700 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400 py-1">
                                @if($user->avatar)
                                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="" class="w-8 h-8 rounded-full object-cover">
                                @else
                                    <span class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-semibold text-xs">
                                        {{ strtoupper(mb_substr($user->name ?? 'U', 0, 1)) }}
                                    </span>
                                @endif
                                <span class="hidden sm:inline max-w-[100px] truncate">{{ $user->name }}</span>
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div class="absolute right-0 mt-0 w-56 z-50 hidden group-hover:block before:absolute before:-top-2 before:left-0 before:right-0 before:h-3">
                                <div class="user-dropdown-scroll bg-white dark:bg-gray-700 rounded-md shadow-lg py-1 border dark:border-gray-600 max-h-[calc(100vh-4rem)] overflow-y-auto" style="scrollbar-width: thin; scrollbar-color: #6b7280 transparent;">
                                <div class="px-4 py-2 border-b">
                                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('nav.user_center') }}</p>
                                </div>
                                <a href="{{ route('my-licenses') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('my-licenses') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('nav.my_licenses') }}
                                </a>
                                <a href="{{ route('my-orders') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('my-orders') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('nav.my_orders') }}
                                </a>
                                <a href="{{ route('profile') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('profile') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('nav.profile') }}
                                </a>
                                <a href="{{ route('affiliate.index') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('affiliate.*') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('nav.affiliate') }}
                                </a>
                                @if(\App\Models\SiteSetting::get('ticket_enabled', '0') === '1')
                                <a href="{{ route('tickets.index') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('tickets.*') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('nav.tickets') }}
                                </a>
                                @endif
                                @if(\App\Models\SiteSetting::get('freelance_enabled', '0') === '1')
                                <div class="px-4 py-2 border-t dark:border-gray-600 mt-1">
                                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('project.marketplace') }}</p>
                                </div>
                                <a href="{{ route('projects.index') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('projects.index') || request()->routeIs('projects.show') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('project.marketplace') }}
                                </a>
                                <a href="{{ route('projects.my-projects') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('projects.my-projects') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('project.my_posted_projects') }}
                                </a>
                                <a href="{{ route('projects.my-bids') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('projects.my-bids') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('project.my_bids') }}
                                </a>
                                <a href="{{ route('projects.contracts') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('projects.contracts') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('project.my_contracts') }}
                                </a>
                                <a href="{{ route('projects.disputes') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('projects.disputes') || request()->routeIs('projects.disputes.show') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('dispute.my_disputes') }}
                                </a>
                                <a href="{{ route('projects.badges') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('projects.badges') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('badge.my_badges') }}
                                </a>
                                <a href="{{ route('projects.skills') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('projects.skills') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('skill.my_skills') }}
                                </a>
                                <a href="{{ route('projects.score') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('projects.score') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('score.title') }}
                                </a>
                                <a href="{{ route('projects.recommendations') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('projects.recommendations') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('recommend.title') }}
                                </a>
                                <a href="{{ route('projects.templates') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('projects.templates') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('template.title') }}
                                </a>
                                <a href="{{ route('projects.verification') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('projects.verification') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                    {{ __('verification.title') }}
                                    @if($user->verification_status === 'approved')
                                        <span class="ml-1 text-green-500">&#10003;</span>
                                    @endif
                                </a>
                                @endif

                                @if($user->isDeveloper() || $user->isSuperAdmin())
                                    <div class="px-4 py-2 border-t border-b dark:border-gray-600 mt-1">
                                        <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('nav.developer') }}</p>
                                    </div>
                                    <a href="{{ route('developer.dashboard') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('developer.dashboard') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                        {{ __('nav.dashboard') }}
                                    </a>
                                    <a href="{{ route('developer.products.index') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('developer.products.*') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                        {{ __('nav.products') }}
                                    </a>
                                    <a href="{{ route('developer.orders.index') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('developer.orders*') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                        {{ __('nav.orders') }}
                                    </a>
                                    <a href="{{ route('developer.stats.index') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('developer.stats*') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                        {{ __('nav.analytics') }}
                                    </a>
                                    <a href="{{ route('developer.payouts.index') }}" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400 {{ request()->routeIs('developer.payouts*') ? 'bg-indigo-50 text-indigo-600 font-semibold' : '' }}">
                                        {{ __('nav.payouts') }}
                                    </a>
                                @endif

                                <div class="border-t dark:border-gray-600 mt-1 pt-1">
                                    @if($user->isSuperAdmin())
                                        <a href="/admin" data-nav-sound class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-gray-600 hover:text-indigo-600 dark:hover:text-indigo-400">
                                            {{ __('nav.admin_panel') }}
                                        </a>
                                    @endif
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button data-nav-sound type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-700 dark:hover:text-red-300">
                                            {{ __('logout') }}
                                        </button>
                                    </form>
                                </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" data-nav-sound class="text-sm text-indigo-600 hover:text-indigo-800">{{ __('login') }}</a>
                        <a href="{{ route('register') }}" data-nav-sound class="text-sm px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">{{ __('register') }}</a>
                    @endauth
                </div>
                {{-- Mobile: Hamburger button --}}
                <div class="md:hidden flex items-center space-x-3">
                    {{-- Mobile notification bell --}}
                    @auth
                    <a href="{{ route('notifications.index') }}" class="relative p-2 text-gray-500 dark:text-gray-300 hover:text-indigo-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                        <span id="notif-badge-mobile" class="hidden absolute -top-0.5 -right-0.5 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center leading-none"></span>
                    </a>
                    @endauth
                    @php $currentLang = app()->getLocale(); @endphp
                    <div class="flex items-center text-xs border border-gray-200 dark:border-gray-600 rounded-full overflow-hidden">
                        <a href="?lang=zh_CN" class="px-2 py-1 {{ $currentLang === 'zh_CN' ? 'bg-indigo-600 text-white' : 'text-gray-500 dark:text-gray-300' }}">中文</a>
                        <a href="?lang=en" class="px-2 py-1 {{ $currentLang === 'en' ? 'bg-indigo-600 text-white' : 'text-gray-500 dark:text-gray-300' }}">EN</a>
                    </div>
                    <button @click="mobileMenu = !mobileMenu" class="p-2 text-gray-600 dark:text-gray-300 hover:text-indigo-600">
                        <svg x-show="!mobileMenu" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        <svg x-show="mobileMenu" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Mobile menu drawer --}}
        <div x-show="mobileMenu" x-transition:enter="transition ease-out duration-200" x-transition:leave="transition ease-in duration-150" class="md:hidden border-t dark:border-gray-700 bg-white dark:bg-gray-800">
            <div class="px-4 py-3 space-y-2">
                {{-- Dark Mode Toggle (mobile) --}}
                <button onclick="toggleTheme()" class="flex items-center gap-2 w-full px-3 py-2 rounded-md text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <svg class="w-5 h-5 hidden dark:block" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"/></svg>
                    <svg class="w-5 h-5 block dark:hidden" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                    <span class="dark:hidden">{{ __('theme_dark') }}</span>
                    <span class="hidden dark:inline">{{ __('theme_light') }}</span>
                </button>
                @php $navItems = \App\Models\NavigationItem::getMenu('header'); @endphp
                @foreach($navItems as $item)
                    @php $label = (__($item['label']) !== $item['label']) ? __($item['label']) : $item['label']; @endphp
                    @if(empty($item['children']))
                        @php $isActive = request()->is(ltrim($item['url'], '/') ?: '/') || request()->url() === $item['url']; @endphp
                        <a href="{{ $item['url'] }}" target="{{ $item['target'] ?? '_self' }}"
                           class="block px-3 py-2 rounded-md text-sm {{ $isActive ? 'bg-indigo-50 text-indigo-600 font-semibold' : 'text-gray-700 dark:text-gray-200' }} hover:bg-gray-100 dark:hover:bg-gray-700">
                            {{ $label }}
                        </a>
                        {{-- Inject Talent Market & Bidding Market after Marketplace --}}
                        @if(str_contains($item['url'], '/marketplace') && \App\Models\SiteSetting::get('freelance_enabled', '0') === '1')
                            <a href="{{ route('projects.talent') }}" class="block px-3 py-2 rounded-md text-sm {{ request()->routeIs('projects.talent') ? 'bg-indigo-50 text-indigo-600 font-semibold' : 'text-gray-700 dark:text-gray-200' }} hover:bg-gray-100 dark:hover:bg-gray-700">
                                {{ __('nav.talent_market') }}
                            </a>
                            <a href="{{ route('projects.index') }}" class="block px-3 py-2 rounded-md text-sm {{ request()->routeIs('projects.index') || request()->routeIs('projects.show') || request()->routeIs('projects.create') ? 'bg-indigo-50 text-indigo-600 font-semibold' : 'text-gray-700 dark:text-gray-200' }} hover:bg-gray-100 dark:hover:bg-gray-700">
                                {{ __('nav.bidding_market') }}
                            </a>
                        @endif
                    @else
                        <div class="rounded-md" x-data="{ open: false }">
                            <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">
                                {{ $label }}
                                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" class="pl-6 space-y-1">
                                @foreach($item['children'] as $child)
                                    @php $childActive = request()->is(ltrim($child['url'], '/')) || request()->url() === $child['url']; @endphp
                                    @php $childLabel = (__($child['label']) !== $child['label']) ? __($child['label']) : $child['label']; @endphp
                                    <a href="{{ $child['url'] }}" target="{{ $child['target'] ?? '_self' }}"
                                       class="block px-3 py-1.5 text-sm {{ $childActive ? 'text-indigo-600 font-semibold' : 'text-gray-600 dark:text-gray-300' }} hover:bg-gray-50 dark:hover:bg-gray-700 rounded">
                                        {{ $childLabel }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
                <a href="{{ route('docs') }}" class="block px-3 py-2 rounded-md text-sm {{ request()->routeIs('docs') ? 'bg-indigo-50 text-indigo-600 font-semibold' : 'text-gray-700 dark:text-gray-200' }} hover:bg-gray-100 dark:hover:bg-gray-700">
                    {{ __('nav.documentation') }}
                </a>

                {{-- Mobile user menu --}}
                @auth
                    @php $user = auth()->user(); @endphp
                    <div class="border-t dark:border-gray-700 mt-3 pt-3">
                        <p class="px-3 py-1 text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ $user->name }}</p>
                        <a href="{{ route('my-licenses') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">{{ __('nav.my_licenses') }}</a>
                        <a href="{{ route('my-orders') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">{{ __('nav.my_orders') }}</a>
                        <a href="{{ route('affiliate.index') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">{{ __('nav.affiliate') }}</a>
                        @if(\App\Models\SiteSetting::get('freelance_enabled', '0') === '1')
                            <a href="{{ route('projects.contracts') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">{{ __('project.my_contracts') }}</a>
                            <a href="{{ route('projects.disputes') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">{{ __('dispute.my_disputes') }}</a>
                            <a href="{{ route('projects.badges') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">{{ __('badge.my_badges') }}</a>
                            <a href="{{ route('projects.skills') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">{{ __('skill.my_skills') }}</a>
                            <a href="{{ route('projects.score') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">{{ __('score.title') }}</a>
                            <a href="{{ route('projects.recommendations') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">{{ __('recommend.title') }}</a>
                            <a href="{{ route('projects.templates') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">{{ __('template.title') }}</a>
                            <a href="{{ route('projects.verification') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">
                                {{ __('verification.title') }}
                                @if($user->verification_status === 'approved')<span class="text-green-500">&#10003;</span>@endif
                            </a>
                        @endif
                        @if($user->isDeveloper() || $user->isSuperAdmin())
                            <a href="{{ route('developer.dashboard') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">{{ __('nav.dashboard') }}</a>
                            <a href="{{ route('developer.products.index') }}" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">{{ __('nav.products') }}</a>
                        @endif
                        @if($user->isSuperAdmin())
                            <a href="/admin" class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">{{ __('nav.admin_panel') }}</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-3 py-2 text-sm text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md">{{ __('logout') }}</button>
                        </form>
                    </div>
                @else
                    <div class="border-t dark:border-gray-700 mt-3 pt-3 flex gap-2">
                        <a href="{{ route('login') }}" class="flex-1 text-center py-2 text-sm text-indigo-600 dark:text-indigo-400 border border-indigo-600 dark:border-indigo-400 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/20">{{ __('login') }}</a>
                        <a href="{{ route('register') }}" class="flex-1 text-center py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">{{ __('register') }}</a>
                    </div>
                @endauth
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 text-green-700 rounded-lg">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-4 bg-red-50 text-red-700 rounded-lg">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>

    <footer class="border-t dark:border-gray-700 bg-white dark:bg-gray-800 mt-12 transition-colors">
        <div class="max-w-7xl mx-auto px-4 py-6">
            @php
                $footerItems = \App\Models\NavigationItem::getMenu('footer');
            @endphp
            @if(count($footerItems) > 0)
                <div class="flex justify-center space-x-6 mb-4">
                    @foreach($footerItems as $item)
                        @php $fLabel = (__($item['label']) !== $item['label']) ? __($item['label']) : $item['label']; @endphp
                        <a href="{{ $item['url'] }}"
                           target="{{ $item['target'] ?? '_self' }}"
                           class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                            {{ $fLabel }}
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="text-center text-sm text-gray-400">
                @php
                    $footerText = \App\Models\SiteSetting::get('footer_copyright', '© {year} {site_name}. All rights reserved.');
                    $footerText = (__($footerText) !== $footerText) ? __($footerText) : $footerText;
                    $sn = \App\Models\SiteSetting::get('site_name', config('app.name', 'Platform'));
                                        $sn = (__($sn) !== $sn) ? __($sn) : $sn;
                                        $footerText = str_replace(['{year}', '{site_name}'], [date('Y'), '<a href="/' . '" class="hover:underline">' . e($sn) . '</a>'], $footerText);
                @endphp
                {!! $footerText !!}
            </div>
        </div>
    </footer>

@auth
<script>
(function() {
    var unreadUrl = '{{ route("notifications.unread") }}';
    var markReadUrl = '{{ route("notifications.read", "__ID__") }}';
    var noNotifsText = '{{ __("notification.empty") }}';

    function loadNotifs() {
        fetch(unreadUrl, { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                var badge = document.getElementById('notif-badge');
                var badgeMobile = document.getElementById('notif-badge-mobile');
                var count = res.count || 0;

                // Update badge
                [badge, badgeMobile].forEach(function(b) {
                    if (!b) return;
                    if (count > 0) {
                        b.textContent = count > 9 ? '9+' : count;
                        b.classList.remove('hidden');
                    } else {
                        b.classList.add('hidden');
                    }
                });

                // Update dropdown list
                var list = document.getElementById('notif-list');
                if (!list) return;
                if (!res.data || res.data.length === 0) {
                    list.innerHTML = '<div class="px-4 py-6 text-center text-sm text-gray-400">' + noNotifsText + '</div>';
                    return;
                }
                list.innerHTML = res.data.map(function(n) {
                    return '<a href="' + n.url + '" data-nav-sound class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 border-b last:border-0 text-sm" onclick="markNotifRead(\'' + n.id + '\')">' +
                        '<span class="text-lg mt-0.5">' + notifIcon(n.icon) + '</span>' +
                        '<div class="flex-1 min-w-0">' +
                            '<p class="font-medium text-gray-900 truncate">' + escapeHtml(n.title) + '</p>' +
                            '<p class="text-gray-500 text-xs mt-0.5 line-clamp-2">' + escapeHtml(n.message) + '</p>' +
                            '<p class="text-gray-400 text-xs mt-1">' + n.created_at + '</p>' +
                        '</div></a>';
                }).join('');
            })
            .catch(function() {});
    }

    function notifIcon(icon) {
        var icons = {
            'shopping-cart': '<svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>',
            'banknotes': '<svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/></svg>',
            'star': '<svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/></svg>',
            'exclamation-triangle': '<svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>',
            'megaphone': '<svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46"/></svg>',
            'bell': '<svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>',
        };
        return icons[icon] || '<svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>';
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    window.markNotifRead = function(id) {
        fetch(markReadUrl.replace('__ID__', id), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
        }).catch(function() {});
    };

    loadNotifs();
})();
</script>
@endauth

{{-- PWA Service Worker --}}
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function() {});
}
function toggleTheme() {
    var html = document.documentElement;
    if (html.classList.contains('dark')) {
        html.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    } else {
        html.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    }
}
</script>

{{-- Desktop nav hover & click sound --}}
<script>
(function() {
    if (window.innerWidth <= 768) return;

    var dingSound = new Audio('/sounds/ding.mp3');
    dingSound.volume = 0.25;
    var clickSound = new Audio('/sounds/dianji.mp3');
    clickSound.volume = 0.25;

    function playDing() {
        dingSound.currentTime = 0;
        dingSound.play().catch(function(){});
    }
    function playClick() {
        clickSound.currentTime = 0;
        clickSound.play().catch(function(){});
    }

    // 直接绑定：给所有带 data-nav-sound 的元素绑定事件
    // 不使用事件委托，不依赖 DOM 父子关系，不依赖坐标判断
    function bindSound(el) {
        el.addEventListener('mouseenter', function() {
            if (window.innerWidth > 768) playDing();
        });
        el.addEventListener('mousedown', function() {
            if (window.innerWidth > 768) playClick();
        });
    }
    document.querySelectorAll('[data-nav-sound]').forEach(bindSound);

    // MutationObserver 监听动态新增的元素（如 AJAX 加载的通知链接）
    new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            m.addedNodes.forEach(function(node) {
                if (node.nodeType !== 1) return;
                if (node.hasAttribute && node.hasAttribute('data-nav-sound')) bindSound(node);
                if (node.querySelectorAll) {
                    node.querySelectorAll('[data-nav-sound]').forEach(bindSound);
                }
            });
        });
    }).observe(document.body, { childList: true, subtree: true });
})();
</script>
</body>
</html>
