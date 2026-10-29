@extends('layouts.app')
@section('title', __('auth.reset_password'))
@section('content')
<div class="max-w-md mx-auto mt-12">
    <div class="bg-white rounded-lg shadow p-8">
        <h2 class="text-2xl font-bold text-center mb-6">{{ __('auth.reset_password') }}</h2>
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('contact.email') }}</label>
                <input type="email" name="email" value="{{ old('email', $email ?? '') }}" class="w-full border rounded-lg px-3 py-2 bg-gray-50" readonly>
                @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.password') }} <span class="text-red-500">*</span></label>
                <input type="password" name="password" class="w-full border rounded-lg px-3 py-2" required minlength="6">
                @error('password') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.confirm_password') }} <span class="text-red-500">*</span></label>
                <input type="password" name="password_confirmation" class="w-full border rounded-lg px-3 py-2" required>
            </div>
            <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700">{{ __('auth.reset_password') }}</button>
        </form>
    </div>
</div>
@endsection
