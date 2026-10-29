@extends('layouts.app')
@section('title', __('docs.title'))
@section('head')
<style>
    .doc-nav a { display: block; padding: 6px 12px; font-size: 13px; color: #6b7280; border-left: 2px solid transparent; }
    .doc-nav a:hover, .doc-nav a.active { color: #4f46e5; border-left-color: #4f46e5; background: #eef2ff; }
    .doc-section { scroll-margin-top: 80px; }
    .code-block { background: #1e293b; color: #e2e8f0; border-radius: 8px; padding: 16px; overflow-x: auto; font-size: 13px; line-height: 1.5; }
    .code-block .c { color: #94a3b8; }
    .code-block .s { color: #a5f3fc; }
    .code-block .k { color: #c4b5fd; }
    .code-block .f { color: #fbbf24; }
    .endpoint { font-family: monospace; font-size: 13px; }
    .method { font-weight: 600; font-size: 11px; padding: 2px 8px; border-radius: 4px; }
    .method-post { background: #dcfce7; color: #166534; }
    .method-get { background: #dbeafe; color: #1e40af; }
    table.params th { font-size: 12px; color: #6b7280; font-weight: 500; }
    table.params td { font-size: 13px; }
    @media (max-width: 768px) { .doc-sidebar { display: none; } }
    /* Dark mode */
    .dark .doc-sidebar .bg-white { background: #1f2937 !important; }
    .dark .doc-nav a { color: #9ca3af; }
    .dark .doc-nav a:hover, .dark .doc-nav a.active { color: #818cf8; background: rgba(99,102,241,0.15); }
    .dark .method-post { background: rgba(34,197,94,0.15); color: #86efac; }
    .dark .method-get { background: rgba(59,130,246,0.15); color: #93c5fd; }
    .dark .endpoint { color: #d1d5db; }
    .dark table.params th { color: #9ca3af; }
    .dark table.params td { color: #d1d5db; }
</style>
@endsection

@section('content')
<div class="max-w-7xl mx-auto flex gap-8">

    {{-- Sidebar --}}
    <aside class="doc-sidebar w-56 shrink-0">
        <div class="sticky top-4 bg-white rounded-lg shadow-sm p-4">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">{{ __('docs.title') }}</p>
            <nav class="doc-nav space-y-0.5">
                <a href="#introduction">{{ __('docs.nav_intro') }}</a>
                <a href="#authentication">{{ __('docs.nav_auth') }}</a>
                <a href="#license-activate">{{ __('docs.nav_activate') }}</a>
                <a href="#license-check">{{ __('docs.nav_check') }}</a>
                <a href="#update-check">{{ __('docs.nav_update_check') }}</a>
                <a href="#update-download">{{ __('docs.nav_update_download') }}</a>
                <a href="#sdk-wordpress">{{ __('docs.nav_sdk_wp') }}</a>
                <a href="#errors">{{ __('docs.nav_errors') }}</a>
            </nav>
        </div>
    </aside>

    {{-- Content --}}
    <main class="flex-1 min-w-0">
        @include('partials.docs-' . app()->getLocale())
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var sections = document.querySelectorAll('.doc-section');
    var navLinks = document.querySelectorAll('.doc-nav a');
    window.addEventListener('scroll', function() {
        var top = window.scrollY + 100;
        sections.forEach(function(s) {
            if (s.offsetTop <= top && s.offsetTop + s.offsetHeight > top) {
                navLinks.forEach(function(a) { a.classList.remove('active'); });
                var link = document.querySelector('.doc-nav a[href="#' + s.id + '"]');
                if (link) link.classList.add('active');
            }
        });
    });
});
</script>
@endsection
