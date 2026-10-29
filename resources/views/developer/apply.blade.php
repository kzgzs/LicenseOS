@extends('layouts.app')
@section('title', 'Apply as Developer')
@section('content')
<div class="max-w-md mx-auto mt-12">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8">
        <h2 class="text-2xl font-bold text-center mb-2 dark:text-white">Become a Developer</h2>
        <p class="text-gray-500 dark:text-gray-400 text-center mb-6">Submit your application to join the marketplace</p>
        <form method="POST" action="{{ route('apply.store') }}">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Studio / Company Name</label>
                <input type="text" name="tenant_name" value="{{ old('tenant_name') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Your Name</label>
                <input type="text" name="name" value="{{ old('name') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2" required>
                @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                <input type="password" name="password" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm Password</label>
                <input type="password" name="password_confirmation" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Why do you want to join? (optional)</label>
                <textarea name="reason" rows="3" class="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2">{{ old('reason') }}</textarea>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Verification Code</label>
                <div class="flex items-center gap-3">
                    <input type="text" name="captcha" class="flex-1 border dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2" required
                           placeholder="Enter code" autocomplete="off" maxlength="5">
                    <img src="{{ route('captcha.generate') }}" alt="Captcha"
                         class="h-10 rounded cursor-pointer border" title="Click to refresh"
                         onclick="this.src='{{ route('captcha.generate') }}?t='+Date.now()">
                </div>
                @error('captcha') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700">Submit Application</button>
        </form>
        <p class="text-center text-sm text-gray-500 dark:text-gray-400 mt-4">
            Already approved? <a href="{{ route('login') }}" class="text-indigo-600 dark:text-indigo-400">Login</a>
        </p>
    </div>
</div>
@endsection
