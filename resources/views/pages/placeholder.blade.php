@extends('layouts.app')
@section('title', $title ?? config('app.name', 'Platform'))
@section('content')
<div class="text-center py-20">
    <h1 class="text-4xl font-bold text-gray-900 mb-4">{{ $title ?? 'Coming Soon' }}</h1>
    <p class="text-lg text-gray-500 mb-8">{{ $message ?? 'This page is under construction.' }}</p>
    <a href="/" class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Back to Home</a>
</div>
@endsection
