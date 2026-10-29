@extends('layouts.app')
@section('title', __('dev_products.title'))
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold dark:text-white">{{ __('dev_products.title') }}</h1>
    <a href="{{ route('developer.products.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">+ {{ __('dev_products.new') }}</a>
</div>

<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_products.product') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_products.type') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_products.price') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_products.licenses') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_products.orders') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('dev_products.status') }}</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
            <tr class="border-t dark:border-gray-700">
                <td class="px-4 py-3">
                    <p class="font-medium dark:text-gray-200">{{ $product->name }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">v{{ $product->version }}</p>
                </td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ __('product_type.' . $product->type) }}</td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ $currencySymbol }}{{ $product->price }}</td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ $product->licenses_count }}</td>
                <td class="px-4 py-3 text-sm dark:text-gray-200">{{ $product->orders_count }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs rounded-full {{ $product->status === 'published' ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">{{ __('dev_products.status_' . $product->status) }}</span>
                </td>
                <td class="px-4 py-3 text-right">
                    <div class="flex items-center justify-end space-x-2">
                        <a href="{{ route('developer.products.show', $product) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline text-sm">{{ __('dev_products.manage') }}</a>
                        <form method="POST" action="{{ route('developer.products.destroy', $product) }}" onsubmit="return confirm('{{ __('dev_products.delete_confirm') }}')" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 dark:text-red-400 hover:underline text-sm">{{ __('dev_products.delete') }}</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">{{ __('dev_products.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
