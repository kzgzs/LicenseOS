@extends('layouts.app')
@section('title', $category->name . ' - ' . __('shop.title'))
@section('content')
<nav class="text-sm text-gray-500 mb-4">
    <a href="{{ route('marketplace') }}" class="hover:text-indigo-600">{{ __('shop.title') }}</a>
    &rsaquo; <span class="text-gray-900">{{ $category->name }}</span>
</nav>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $category->name }}</h1>
    @if($category->description)
    <p class="text-gray-600">{{ $category->description }}</p>
    @endif
</div>

<div class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    @forelse($products as $product)
    <a href="{{ route('product.show', $product->slug) }}" class="block bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-md transition-shadow overflow-hidden">
        @if($product->cover_image)
        <div class="h-36 overflow-hidden">
            <img src="{{ asset('storage/' . $product->cover_image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
        </div>
        @else
        <div class="h-36 bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-gray-700 dark:to-gray-600 flex items-center justify-center">
            <span class="text-4xl font-bold text-indigo-300 dark:text-gray-500">{{ strtoupper(mb_substr($product->name, 0, 1)) }}</span>
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
                    @if($product->rating) {{ number_format($product->rating, 1) }} ★ @endif
                    · {{ number_format($product->views) }} {{ __('shop.views') }}
                </span>
            </div>
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
