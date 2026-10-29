@extends('layouts.app')
@section('title', __('profile.title'))
@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">{{ __('profile.title') }}</h1>

    {{-- Avatar --}}
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">{{ __('profile.avatar') }}</h2>
        <div class="flex items-center gap-6">
            <div class="shrink-0">
                @if($user->avatar)
                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="Avatar" class="w-20 h-20 rounded-full object-cover">
                @else
                    <span class="w-20 h-20 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-2xl font-bold">
                        {{ strtoupper(mb_substr($user->name ?? 'U', 0, 1)) }}
                    </span>
                @endif
            </div>
            <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="flex-1">
                @csrf
                <div class="flex items-center gap-3">
                    <input type="file" name="avatar" accept="image/*" required class="text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm shrink-0">{{ __('profile.upload') }}</button>
                </div>
                <p class="text-xs text-gray-400 mt-2">{{ __('profile.avatar_hint') }}</p>
            </form>
        </div>
    </div>

    {{-- Info --}}
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">{{ __('profile.info') }}</h2>
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('profile.name') }}</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('profile.email') }}</label>
                <input type="email" value="{{ $user->email }}" disabled class="w-full border rounded-lg px-3 py-2 bg-gray-50 text-gray-500">
                <p class="text-xs text-gray-400 mt-1">{{ __('profile.email_hint') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('profile.phone') }}</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('profile.bio') }}</label>
                <textarea name="bio" rows="3" class="w-full border rounded-lg px-3 py-2">{{ old('bio', $user->bio) }}</textarea>
            </div>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">{{ __('save') }}</button>
        </form>
    </div>

    {{-- Role & Account --}}
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold mb-4">{{ __('profile.account') }}</h2>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-500">{{ __('profile.role') }}</p>
                <p class="font-medium">{{ ucfirst(str_replace('_', ' ', $user->role)) }}</p>
            </div>
            <div>
                <p class="text-gray-500">{{ __('profile.member_since') }}</p>
                <p class="font-medium">{{ $user->created_at->format('Y-m-d') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
