@extends('layouts.app')
@section('title', __('auth.login_title'))
@section('content')
<div class="max-w-md mx-auto mt-12">
    <div class="bg-white rounded-lg shadow p-8">
        <h2 class="text-2xl font-bold text-center mb-6">{{ __('auth.login_title') }}</h2>
        <form method="POST" action="{{ route('login.post') }}">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('contact.email') }} <span class="text-red-500">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" class="w-full border rounded-lg px-3 py-2" required autofocus>
                @error('email') <p class="text-red-600 dark:text-red-400 text-sm mt-1 font-medium">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.password') }} <span class="text-red-500">*</span></label>
                <input type="password" name="password" class="w-full border rounded-lg px-3 py-2" required>
                @error('password') <p class="text-red-600 dark:text-red-400 text-sm mt-1 font-medium">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.captcha') }} <span class="text-red-500">*</span></label>
                <div class="flex items-center gap-3">
                    <input type="text" name="captcha" class="flex-1 min-w-0 border rounded-lg px-3 py-2" required
                           placeholder="{{ __('auth.captcha_placeholder') }}" autocomplete="off" maxlength="5">
                    <img src="{{ route('captcha.generate') }}" alt="Captcha" id="captcha-img"
                         class="h-10 flex-shrink-0 rounded cursor-pointer border" title="Click to refresh"
                         onclick="this.src='{{ route('captcha.generate') }}?t='+Date.now()">
                </div>
                @error('captcha') <p class="text-red-600 dark:text-red-400 text-sm mt-1 font-medium">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4 flex items-center justify-between">
                <label class="flex items-center text-sm text-gray-600">
                    <input type="checkbox" name="remember" class="mr-2"> {{ __('auth.remember_me') }}
                </label>
                <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:text-indigo-800">{{ __('auth.forgot_password') }}</a>
            </div>
            <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700">{{ __('auth.login_title') }}</button>
        </form>

        @if(\App\Models\SiteSetting::get('wechat_login_enabled', '0') === '1')
        <div class="mt-4">
            <div class="flex items-center gap-3 my-3">
                <div class="flex-1 h-px bg-gray-200"></div>
                <span class="text-xs text-gray-400">{{ __('or_login_with') }}</span>
                <div class="flex-1 h-px bg-gray-200"></div>
            </div>
            <a href="{{ route('wechat.redirect') }}" class="flex items-center justify-center gap-2 w-full bg-green-500 text-white py-2 rounded-lg hover:bg-green-600 transition">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178 1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 0 1 .598.082l1.584.926a.272.272 0 0 0 .14.047c.134 0 .24-.111.24-.247 0-.06-.023-.12-.038-.177l-.327-1.233a.582.582 0 0 1-.023-.156.49.49 0 0 1 .201-.398C23.024 18.48 24 16.82 24 14.98c0-3.21-2.931-5.883-7.062-6.122zm-2.89 2.065c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982zm4.844 0c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982z"/></svg>
                {{ __('wechat_login') }}
            </a>
        </div>
        @endif

        <p class="text-center text-sm text-gray-500 mt-4">
            {{ __('auth.no_account') }} <a href="{{ route('register') }}" class="text-indigo-600">{{ __('register') }}</a>
        </p>
    </div>
</div>
@endsection
