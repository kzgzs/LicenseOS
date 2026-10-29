@extends('layouts.app')
@section('title', __('talent.title'))
@section('content')
<div class="max-w-6xl mx-auto">

    {{-- Page Title --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold">{{ __('talent.title') }}</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">{{ __('talent.subtitle') }}</p>
    </div>

    {{-- Search & Filter Bar --}}
    <form method="GET" action="{{ route('projects.talent') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('talent.search_developer') }}</label>
                <input type="text" name="q" value="{{ request('q') }}"
                    class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                    placeholder="{{ __('talent.search_placeholder') }}">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('talent.filter_by_type') }}</label>
                <select name="type" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-3 py-2 text-sm">
                    <option value="">{{ __('talent.all_types') }}</option>
                    @foreach($categories as $key => $label)
                        <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 text-sm">
                    {{ __('talent.search_btn') }}
                </button>
            </div>
        </div>
    </form>

    {{-- Developer Grid --}}
    @if($developers->count())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($developers as $tenant)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-5">

                    {{-- Avatar + Name --}}
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-bold text-lg">
                            {{ strtoupper(substr($tenant->name, 0, 1)) }}
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $tenant->name }}</h3>
                            @if($tenant->developer_level)
                                <span class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300">
                                    {{ __('developer_level.' . $tenant->developer_level) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Stats --}}
                    <div class="flex gap-4 mb-3 text-sm text-gray-600 dark:text-gray-400">
                        <span>
                            <strong class="text-gray-800 dark:text-gray-200">{{ $tenant->total_sales ?? 0 }}</strong>
                            {{ __('talent.sales') }}
                        </span>
                        <span>
                            <strong class="text-gray-800 dark:text-gray-200">{{ $tenant->products->count() }}</strong>
                            {{ __('talent.products') }}
                        </span>
                    </div>

                    {{-- Skill Tags (product types) --}}
                    @if($tenant->products->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5 mb-3">
                            @foreach($tenant->products->pluck('type')->unique() as $type)
                                <span class="text-xs bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 px-2 py-1 rounded">
                                    {{ __('product_type.' . $type) }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    {{-- Action: Invite to Project --}}
                    @auth
                        <a href="{{ route('projects.create') }}"
                            class="block text-center text-sm bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 py-2 rounded hover:bg-blue-100 dark:hover:bg-blue-900/50 font-medium">
                            {{ __('talent.invite_developer') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                            class="block text-center text-sm bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-400 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-600 font-medium">
                            {{ __('talent.login_to_invite') }}
                        </a>
                    @endauth

                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $developers->links() }}
        </div>
    @else
        <div class="text-center py-16 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
            <div class="text-gray-400 text-4xl mb-4">👨‍💻</div>
            <h3 class="text-gray-700 dark:text-gray-300 font-medium">{{ __('talent.no_developers') }}</h3>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">{{ __('talent.try_different_filters') }}</p>
        </div>
    @endif

    {{-- Freelance CTA --}}
    @if(\App\Models\SiteSetting::get('freelance_enabled'))
        <div class="mt-8 bg-blue-50 dark:bg-blue-900/30 rounded-lg p-6 text-center">
            <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-300 mb-2">{{ __('talent.have_project') }}</h3>
            <p class="text-blue-600 dark:text-blue-400 mb-4">{{ __('talent.have_project_desc') }}</p>
            <a href="{{ route('projects.index') }}" class="inline-block bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                {{ __('talent.browse_projects') }}
            </a>
        </div>
    @endif

</div>
@endsection
