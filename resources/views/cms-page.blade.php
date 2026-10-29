@extends('layouts.app')
@section('title', $page->title)
@if($page->meta_description)
@section('head')
    <meta name="description" content="{{ $page->meta_description }}">
@endsection
@endif
@section('content')
<style>
.cms-content a { color: #4f46e5; text-decoration: underline; }
.cms-content a:hover { color: #3730a3; }
.dark .cms-content a { color: #818cf8; }
.dark .cms-content a:hover { color: #a5b4fc; }
.dark .cms-content { color: #d1d5db; }
.dark .cms-content h2, .dark .cms-content h3 { color: #f3f4f6; }
.dark .cms-content li { color: #d1d5db; }
.dark .cms-content strong { color: #f9fafb; }
.dark .cms-content ul > li::marker { color: #6b7280; }
.dark .cms-content ol > li::marker { color: #6b7280; }
</style>
<div class="max-w-4xl mx-auto py-8">
    <article class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-8 prose prose-indigo dark:prose-invert max-w-none cms-content">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">{{ $page->title }}</h1>
        @php
    $content = $page->content;
    $sn = \App\Models\SiteSetting::get('site_name', config('app.name', 'Platform'));
    $sn = (__($sn) !== $sn) ? __($sn) : $sn;
    $content = str_replace(['{site_name}', '{year}'], [$sn, date('Y')], $content);
@endphp
<div class="text-gray-700 dark:text-gray-300 leading-relaxed">
    {!! $content !!}
</div>
    </article>
    <p class="text-xs text-gray-400 dark:text-gray-500 mt-4 text-right">{{ __('cms.last_updated') }}: {{ $page->updated_at->format('Y-m-d') }}</p>
</div>
<script src="{{ asset('js/cms-renderer.js') }}?v=5" defer></script>
@endsection
