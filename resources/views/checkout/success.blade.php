@extends('layouts.app')
@section('title', __('checkout.success_title'))
@section('content')
<div class="max-w-lg mx-auto mt-12 text-center">
    <div class="bg-white rounded-lg shadow p-8">
        <div class="text-5xl mb-4">&#127881;</div>
        <h2 class="text-2xl font-bold mb-2">{{ __('checkout.success_title') }}</h2>
        <p class="text-gray-600 mb-6">{{ __('checkout.success_msg', ['id' => $order->id]) }}</p>

        @if($order->licenses->isNotEmpty())
        <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left">
            <h3 class="font-semibold mb-2">{{ __('checkout.your_license_key') }}</h3>
            @foreach($order->licenses as $license)
            <div class="flex items-center gap-2 mb-2">
                <code class="flex-1 bg-white border rounded px-3 py-2 font-mono text-sm text-indigo-700">{{ $license->license_key }}</code>
            </div>
            <p class="text-xs text-gray-500">{{ __('licenses.status') }}: {{ $license->status }} | {{ __('licenses.expires') }}: {{ $license->expire_at?->format('Y-m-d') ?? __('checkout.never') }}</p>
            @endforeach
        </div>
        @endif

        <div class="flex justify-center gap-4">
            <a href="{{ route('home') }}" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">{{ __('checkout.back_home') }}</a>
        </div>
    </div>
</div>
@endsection
