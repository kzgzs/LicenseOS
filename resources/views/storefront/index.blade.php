@extends('layouts.app')
@section('title', __('shop.title'))
@section('content')
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">{{ __('shop.title') }}</h1>
    <p class="text-gray-600 dark:text-gray-400">{{ __('shop.subtitle') }}</p>
</div>

<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
    <form method="GET" action="{{ route('marketplace') }}" class="flex flex-col sm:flex-row gap-3">
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="{{ __('shop.search_placeholder') }}"
               class="flex-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-4 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
        <select name="sort" class="border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm">
            <option value="popular" {{ request('sort') === 'popular' ? 'selected' : '' }}>{{ __('shop.sort_popular') }}</option>
            <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>{{ __('shop.sort_newest') }}</option>
            <option value="rating" {{ request('sort') === 'rating' ? 'selected' : '' }}>{{ __('shop.sort_rating') }}</option>
            <option value="price_low" {{ request('sort') === 'price_low' ? 'selected' : '' }}>{{ __('shop.sort_price_low') }}</option>
            <option value="price_high" {{ request('sort') === 'price_high' ? 'selected' : '' }}>{{ __('shop.sort_price_high') }}</option>
        </select>
        <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">{{ __('search') }}</button>
    </form>
</div>

{{-- Banner Ad Carousel --}}
@include('partials.ad-banner')

@if($categories->isNotEmpty())
<div class="flex flex-wrap gap-2 mb-6">
    <a href="{{ route('marketplace') }}"
       class="px-3 py-1 rounded-full text-sm {{ !request('category') ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}">
        {{ __('shop.all') }}
    </a>
    @foreach($categories as $cat)
    <a href="{{ route('marketplace', ['category' => $cat->slug]) }}"
       class="px-3 py-1 rounded-full text-sm {{ request('category') === $cat->slug ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}">
        {{ $cat->name }} ({{ $cat->products_count }})
    </a>
    @endforeach
</div>
@endif

@if($featured->isNotEmpty() && !request('q') && !request('category'))
<div class="mb-8">
    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ __('shop.featured') }}</h2>
    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($featured as $product)
        <a href="{{ route('product.show', $product->slug) }}" class="block">
            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                @if($product->cover_image)
                <div class="h-40 overflow-hidden">
                    <img src="{{ asset('storage/' . $product->cover_image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover opacity-80">
                </div>
                @endif
                <div class="p-5 text-white">
                    <h3 class="font-semibold text-lg mb-1">{{ $product->name }}</h3>
                    <p class="text-indigo-100 text-sm mb-3">{{ $product->short_description }}</p>
                    <div class="flex justify-between items-center">
                        <span class="font-bold">{{ $currencySymbol }}{{ number_format($product->price, 2) }}</span>
                        @if($product->rating)
                        <span class="text-sm">{{ str_repeat('★', (int)$product->rating) }}{{ str_repeat('☆', 5 - (int)$product->rating) }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </a>
        @endforeach
    </div>
</div>
@endif

@if($trending->isNotEmpty())
<div class="mb-8">
    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ __('shop.trending') }}</h2>
    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($trending as $product)
        <a href="{{ route('product.show', $product->slug) }}" class="block bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-md transition-shadow overflow-hidden">
            @if($product->cover_image)
            <div class="h-32 overflow-hidden">
                <img src="{{ asset('storage/' . $product->cover_image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
            </div>
            @endif
            <div class="p-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-xs bg-red-100 text-red-600 dark:bg-red-900/50 dark:text-red-300 px-2 py-0.5 rounded-full font-medium">{{ __('shop.hot') }}</span>
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $product->name }}</h3>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ Str::limit($product->short_description, 60) }}</p>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-indigo-600 dark:text-indigo-400 font-bold">{{ $currencySymbol }}{{ number_format($product->price, 2) }}</span>
                    <span class="text-gray-400 dark:text-gray-500">{{ number_format($product->views) }} {{ __('shop.views') }}</span>
                </div>
            </div>
        </a>
        @endforeach
    </div>
</div>
@endif

@if($newArrivals->isNotEmpty())
<div class="mb-8">
    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ __('shop.new_arrivals') }}</h2>
    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($newArrivals as $product)
        <a href="{{ route('product.show', $product->slug) }}" class="block bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-md transition-shadow overflow-hidden">
            @if($product->cover_image)
            <div class="h-32 overflow-hidden">
                <img src="{{ asset('storage/' . $product->cover_image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
            </div>
            @endif
            <div class="p-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-xs bg-green-100 text-green-600 dark:bg-green-900/50 dark:text-green-300 px-2 py-0.5 rounded-full font-medium">{{ __('shop.new_badge') }}</span>
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $product->name }}</h3>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ Str::limit($product->short_description, 60) }}</p>
                <span class="text-indigo-600 dark:text-indigo-400 font-bold text-sm">{{ $currencySymbol }}{{ number_format($product->price, 2) }}</span>
            </div>
        </a>
        @endforeach
    </div>
</div>
@endif

<div class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    @forelse($products as $product)

{{-- Promoted products (shown before main list on first page) --}}
@if($loop->first && !request('q'))
@include('partials.promoted-products')
@endif
    <a href="{{ route('product.show', $product->slug) }}" class="block bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-md transition-shadow overflow-hidden">
        @if($product->cover_image)
        <div class="h-36 overflow-hidden">
            <img src="{{ asset('storage/' . $product->cover_image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
        </div>
        @else
        <div class="h-36 bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-900/30 dark:to-purple-900/30 flex items-center justify-center">
            <span class="text-4xl font-bold text-indigo-300 dark:text-indigo-400">{{ strtoupper(mb_substr($product->name, 0, 1)) }}</span>
        </div>
        @endif
        <div class="p-5">
            <div class="flex items-start justify-between mb-2">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $product->name }}</h3>
                <span class="text-indigo-600 dark:text-indigo-400 font-bold">{{ $currencySymbol }}{{ number_format($product->price, 2) }}</span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ Str::limit($product->short_description, 80) }}</p>
            <div class="flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
                <span>{{ $product->tenant->name ?? __('product.developer') }}</span>
                <span>
                    @if($product->rating)
                        {{ number_format($product->rating, 1) }} ★
                    @endif
                    · {{ number_format($product->views) }} {{ __('shop.views') }}
                </span>
            </div>
            @if(is_array($product->tags) && count($product->tags) > 0)
            <div class="flex flex-wrap gap-1 mt-2">
                @foreach(array_slice($product->tags, 0, 3) as $tag)
                <span class="text-xs bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300 px-2 py-0.5 rounded">{{ $tag }}</span>
                @endforeach
            </div>
            @endif
        </div>
    </a>
    @empty
    <div class="col-span-full text-center py-12 text-gray-500 dark:text-gray-400">
        {{ __('shop.no_results') }}
    </div>
    @endforelse
</div>

@if($products->hasPages())
<div class="mt-8">
    {{ $products->links() }}
</div>
@endif
@endsection
