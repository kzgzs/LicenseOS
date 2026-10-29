@extends('layouts.app')
@section('title', __('verification.title'))
@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">{{ __('verification.title') }}</h1>

    {{-- Current Status --}}
    @if($user->verification_status === 'pending')
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <p class="text-sm text-yellow-700 font-medium">{{ __('verification.status_pending') }}</p>
        </div>
    @elseif($user->verification_status === 'approved')
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <p class="text-sm text-green-700 font-medium">{{ __('verification.status_approved') }}</p>
            <p class="text-xs text-green-600 mt-1">{{ __('verification.verified_at') }}: {{ $user->verified_at?->format('Y-m-d') ?? '-' }}</p>
        </div>
    @elseif($user->verification_status === 'rejected')
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <p class="text-sm text-red-700 font-medium">{{ __('verification.status_rejected') }}</p>
            @if($user->verification_note)
                <p class="text-xs text-red-600 mt-1">{{ $user->verification_note }}</p>
            @endif
        </div>
    @endif

    {{-- Verification Form --}}
    <div class="bg-white rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('projects.verification.submit') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            {{-- Account Type --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('verification.account_type') }}</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                        <input type="radio" name="account_type" value="personal" {{ $user->account_type !== 'company' ? 'checked' : '' }} onchange="document.getElementById('company-fields').classList.add('hidden')">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ __('verification.personal') }}</p>
                            <p class="text-xs text-gray-500">{{ __('verification.personal_desc') }}</p>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                        <input type="radio" name="account_type" value="company" {{ $user->account_type === 'company' ? 'checked' : '' }} onchange="document.getElementById('company-fields').classList.remove('hidden')">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ __('verification.company') }}</p>
                            <p class="text-xs text-gray-500">{{ __('verification.company_desc') }}</p>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Company Fields --}}
            <div id="company-fields" class="{{ $user->account_type !== 'company' ? 'hidden' : '' }} space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('verification.company_name') }} *</label>
                    <input type="text" name="company_name" value="{{ old('company_name', $user->company_name) }}" maxlength="150"
                           class="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('verification.business_license') }} *</label>
                    <input type="file" name="business_license" accept=".pdf,.jpg,.png"
                           class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-xs text-gray-400 mt-1">{{ __('verification.license_hint') }}</p>
                    @if($user->business_license)
                        <a href="{{ asset('storage/' . $user->business_license) }}" target="_blank" class="text-xs text-indigo-600 hover:underline mt-1 inline-block">
                            {{ __('verification.view_current') }}
                        </a>
                    @endif
                </div>
            </div>

            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                {{ __('verification.submit') }}
            </button>
        </form>
    </div>
</div>
@endsection
