@extends('layouts.app')
@section('title', __('auth.forgot_password'))
@section('content')
<div class="max-w-md mx-auto mt-12">
    <div class="bg-white rounded-lg shadow p-8">
        <h2 class="text-2xl font-bold text-center mb-6">{{ __('auth.forgot_password') }}</h2>
        <p class="text-sm text-gray-600 text-center mb-6">{{ __('auth.forgot_subtitle') }}</p>

        @if(session('status'))
            <div class="mb-4 p-4 bg-green-50 text-green-700 rounded-lg text-sm">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('contact.email') }} <span class="text-red-500">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" class="w-full border rounded-lg px-3 py-2" required autofocus>
                @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
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
                @error('captcha') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700">{{ __('auth.send_reset_link') }}</button>
        </form>
        <p class="text-center text-sm text-gray-500 mt-4">
            <a href="{{ route('login') }}" class="text-indigo-600">{{ __('auth.back_to_login') }}</a>
        </p>
    </div>
</div>
@endsection
