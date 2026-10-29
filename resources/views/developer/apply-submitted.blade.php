@extends('layouts.app')
@section('title', 'Application Submitted')
@section('content')
<div class="max-w-md mx-auto mt-16 text-center">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8">
        <div class="text-5xl mb-4">&#9989;</div>
        <h2 class="text-2xl font-bold mb-2 dark:text-white">Application Submitted</h2>
        <p class="text-gray-600 dark:text-gray-300 mb-6">
            Your developer application has been received and is under review.
            You will receive an email once it is approved.
        </p>
        <p class="text-sm text-gray-400 dark:text-gray-500 mb-6">
            Typical review time: 1-2 business days
        </p>
        <a href="{{ route('home') }}" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Back to Home</a>
    </div>
</div>
@endsection
