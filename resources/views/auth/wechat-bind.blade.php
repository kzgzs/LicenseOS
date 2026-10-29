@extends('layouts.app')
@section('title', __('wechat_bind_account'))
@section('content')
<div class="max-w-lg mx-auto mt-12">
    <div class="bg-white rounded-lg shadow p-8">
        <h2 class="text-2xl font-bold text-center mb-2">{{ __('wechat_bind_account') }}</h2>
        <p class="text-center text-gray-500 text-sm mb-6">{{ __('wechat_bind_hint', ['name' => $nickname ?? '-']) }}</p>

        <div x-data="{ tab: 'login' }">
            <div class="flex mb-6 border-b">
                <button @click="tab='login'" :class="tab==='login' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500'" class="flex-1 pb-2 text-sm font-medium border-b-2">{{ __('auth.login_title') }}</button>
                <button @click="tab='register'" :class="tab==='register' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500'" class="flex-1 pb-2 text-sm font-medium border-b-2">{{ __('register') }}</button>
            </div>

            {{-- Login Tab --}}
            <form x-show="tab==='login'" method="POST" action="{{ route('wechat.bind.login') }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('contact.email') }}</label>
                    <input type="email" name="email" class="w-full border rounded-lg px-3 py-2" required>
                    @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.password') }}</label>
                    <input type="password" name="password" class="w-full border rounded-lg px-3 py-2" required>
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700">{{ __('wechat_bind_and_login') }}</button>
            </form>

            {{-- Register Tab --}}
            <form x-show="tab==='register'" method="POST" action="{{ route('wechat.bind.register') }}" x-cloak>
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('profile.name') }}</label>
                    <input type="text" name="name" value="{{ $nickname }}" class="w-full border rounded-lg px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('contact.email') }}</label>
                    <input type="email" name="email" class="w-full border rounded-lg px-3 py-2" required>
                    @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.password') }}</label>
                    <input type="password" name="password" class="w-full border rounded-lg px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.confirm_password') }}</label>
                    <input type="password" name="password_confirmation" class="w-full border rounded-lg px-3 py-2" required>
                </div>
                <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">{{ __('wechat_register_and_bind') }}</button>
            </form>
        </div>

        <div class="text-center mt-4">
            <a href="{{ route('login') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('back_to_login') }}</a>
        </div>
    </div>
</div>
@endsection
