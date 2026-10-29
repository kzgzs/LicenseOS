@extends('layouts.app')
@section('title', __('api_keys.title'))
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold dark:text-white">{{ __('api_keys.title') }}</h1>
    <form method="POST" action="{{ route('developer.api-keys.store') }}">
        @csrf
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">+ {{ __('api_keys.generate') }}</button>
    </form>
</div>

<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('api_keys.key') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('api_keys.status') }}</th>
                <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-300">{{ __('api_keys.created') }}</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($apiKeys as $key)
            <tr class="border-t dark:border-gray-700">
                <td class="px-4 py-3 font-mono text-sm text-gray-800 dark:text-green-400">{{ $key->key }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs rounded-full {{ $key->active ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' }}">
                        {{ $key->active ? __('api_keys.active') : __('api_keys.revoked') }}
                    </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $key->created_at->format('Y-m-d H:i') }}</td>
                <td class="px-4 py-3 text-right">
                    @if($key->active)
                    <form method="POST" action="{{ route('developer.api-keys.destroy', $key) }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 text-sm" onclick="return confirm('{{ __('api_keys.revoke_confirm') }}')">{{ __('api_keys.revoke') }}</button>
                    </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">{{ __('api_keys.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6 bg-gray-900 text-gray-100 rounded-lg p-6">
    <h2 class="text-lg font-semibold mb-4 text-white">{{ __('api_keys.usage') }}</h2>
    <pre class="text-sm text-green-400 overflow-x-auto"><code># Include API key in header
curl -X POST {{ config('app.url') }}/api/v2/license/activate \
  -H "X-API-KEY: your-api-key-here" \
  -d "license_key=XXXX-XXXX-XXXX-XXXX&domain=example.com"

# Check for updates
curl "{{ config('app.url') }}/api/v2/update/check?product=your-plugin&version=1.0.0" \
  -H "X-API-KEY: your-api-key-here"</code></pre>
</div>
@endsection
