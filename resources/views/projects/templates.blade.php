@extends('layouts.app')
@section('title', __('template.title'))
@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('template.title') }}</h1>
        <p class="text-sm text-gray-500">{{ __('template.description') }}</p>
    </div>

    {{-- Category Filter --}}
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('projects.templates') }}"
           class="px-3 py-1.5 text-sm rounded-full {{ !$category ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
            {{ __('template.all') }}
        </a>
        @foreach($categories as $key => $label)
            <a href="{{ route('projects.templates', ['category' => $key]) }}"
               class="px-3 py-1.5 text-sm rounded-full {{ $category === $key ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                {{ __($label) }}
            </a>
        @endforeach
    </div>

    {{-- Template Grid --}}
    @if($templates->count() > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($templates as $tpl)
        <div class="bg-white rounded-xl shadow-sm border hover:shadow-md transition-shadow p-5 flex flex-col">
            <div class="flex items-start justify-between mb-2">
                <span class="px-2 py-0.5 text-xs bg-indigo-50 text-indigo-600 rounded-full font-medium">
                    {{ __($categories[$tpl->category] ?? $tpl->category) }}
                </span>
                <span class="text-xs text-gray-400">{{ __('template.used') }} {{ $tpl->use_count }}x</span>
            </div>
            <h3 class="font-semibold text-gray-900 mb-2">{{ $tpl->title }}</h3>
            <p class="text-sm text-gray-500 flex-1 mb-3">{{ Str::limit($tpl->description, 120) }}</p>

            @if($tpl->skills && is_array($tpl->skills))
            <div class="flex flex-wrap gap-1 mb-3">
                @foreach(array_slice($tpl->skills, 0, 5) as $skill)
                    <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">{{ $skill }}</span>
                @endforeach
            </div>
            @endif

            <div class="flex items-center justify-between pt-3 border-t">
                @if($tpl->budget_min && $tpl->budget_max)
                    <span class="text-sm font-bold text-gray-900">{{ $tpl->currency }} {{ number_format($tpl->budget_min) }} - {{ number_format($tpl->budget_max) }}</span>
                @endif
                <a href="{{ route('projects.templates.use', $tpl) }}"
                   class="px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                    {{ __('template.use_this') }}
                </a>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-4">{{ $templates->links() }}</div>
    @else
    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
        <p class="text-gray-500">{{ __('template.empty') }}</p>
    </div>
    @endif
</div>
@endsection
