@extends('layouts.app')
@section('title', __('faq.title'))
@section('head')
<style>
    .faq-section { border-bottom: 1px solid #e5e7eb; }
    .faq-toggle { cursor: pointer; user-select: none; }
    .faq-toggle:hover { background: #f9fafb; }
    .faq-answer { display: none; }
    .faq-answer.open { display: block; }
    .faq-toggle .arrow { transition: transform 0.2s; }
    .faq-toggle.open .arrow { transform: rotate(90deg); }
    pre code { font-size: 13px; line-height: 1.5; }
    .code-block { background: #1e293b; color: #e2e8f0; border-radius: 8px; padding: 16px; overflow-x: auto; }
    .code-block .comment { color: #94a3b8; }
    .code-block .string { color: #a5f3fc; }
    .code-block .keyword { color: #c4b5fd; }
    .code-block .func { color: #fbbf24; }
    /* Dark mode */
    .dark .faq-section { border-bottom-color: #4b5563; }
    .dark .faq-toggle:hover { background: #374151; }
    .dark .faq-toggle .font-medium { color: #f3f4f6; }
    .dark .faq-answer p, .dark .faq-answer li, .dark .faq-answer span { color: #d1d5db; }
</style>
@endsection

@section('content')
<div class="max-w-4xl mx-auto">
    @include('partials.faq-' . app()->getLocale())
</div>

<script>
function toggleFaq(el) {
    el.classList.toggle('open');
    var answer = el.nextElementSibling;
    answer.classList.toggle('open');
}
</script>
@endsection
