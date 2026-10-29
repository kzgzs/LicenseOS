@extends('layouts.app')
@section('title', __('ticket.new_ticket'))
@section('content')
<div class="max-w-2xl mx-auto" x-data="{ showExpertModal: {{ $experts->count() > 0 ? 'true' : 'false' }}, qrValue: '', qrImg: '', showQr: false }">

    {{-- Expert Recommendation Popup --}}
    @if($experts->count() > 0)
    <div x-show="showExpertModal" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="showExpertModal = false">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full max-h-[80vh] overflow-hidden" @click.stop>
            {{-- Header --}}
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 text-white">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                        {{ __('ticket.expert_popup_title') }}
                    </h2>
                    <button @click="showExpertModal = false" class="text-white/80 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="text-sm text-white/80 mt-1">{{ __('ticket.expert_popup_desc') }}</p>
            </div>

            {{-- Expert List --}}
            <div class="overflow-y-auto max-h-[50vh] p-4 space-y-3">
                @foreach($experts as $expert)
                <div class="border rounded-lg p-4 hover:border-indigo-300 transition-colors">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-sm">
                            {{ strtoupper(mb_substr($expert->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-gray-900">{{ $expert->name }}</span>
                                @if($expert->specialty)
                                <span class="inline-flex px-2 py-0.5 text-xs bg-indigo-50 text-indigo-600 rounded-full font-medium">{{ $expert->specialty }}</span>
                                @endif
                            </div>
                            @if($expert->description)
                            <p class="text-sm text-gray-500 mt-1">{{ $expert->description }}</p>
                            @endif
                            @if($expert->contact_method && $expert->contact_value)
                            <div class="flex items-center gap-1.5 mt-2 text-sm">
                                <span class="text-gray-400">{{ __('ticket.contact') }}:</span>
                                <span class="font-medium text-gray-700">
                                    @php
                                        $methodLabels = ['wechat' => 'WeChat', 'qq' => 'QQ', 'telegram' => 'Telegram', 'email' => 'Email', 'whatsapp' => 'WhatsApp'];
                                    @endphp
                                    {{ $methodLabels[$expert->contact_method] ?? ucfirst($expert->contact_method) }}:
                                    @if($expert->contact_method === 'wechat' && $expert->qr_image)
                                        <button type="button" class="text-indigo-600 font-mono cursor-pointer underline decoration-dashed hover:text-indigo-800"
                                                @click="qrValue = '{{ $expert->contact_value }}'; qrImg = '{{ Storage::url($expert->qr_image) }}'; showQr = true">
                                            {{ $expert->contact_value }}
                                        </button>
                                        <span class="text-xs text-gray-400 ml-1">({{ __('ticket.click_show_qr') }})</span>
                                    @else
                                        <span class="text-indigo-600 font-mono">{{ $expert->contact_value }}</span>
                                    @endif
                                </span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Footer --}}
            <div class="border-t bg-gray-50 px-6 py-3">
                <p class="text-xs text-gray-400 mb-3">{{ __('ticket.expert_disclaimer') }}</p>
                <div class="flex items-center gap-3">
                    <button @click="showExpertModal = false"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        {{ __('ticket.continue_submit') }}
                    </button>
                    <a href="{{ route('tickets.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                        {{ __('cancel') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="mb-6">
        <a href="{{ route('tickets.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; {{ __('ticket.back_to_list') }}</a>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('ticket.new_ticket') }}</h1>

    @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('tickets.store') }}" class="bg-white rounded-lg shadow-sm p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('ticket.subject') }} *</label>
            <input type="text" name="subject" value="{{ old('subject') }}" required maxlength="255"
                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="{{ __('ticket.subject_placeholder') }}">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('ticket.category') }} *</label>
                <select name="category" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="general" {{ old('category') === 'general' ? 'selected' : '' }}>{{ __('ticket.general') }}</option>
                    <option value="billing" {{ old('category') === 'billing' ? 'selected' : '' }}>{{ __('ticket.billing') }}</option>
                    <option value="technical" {{ old('category') === 'technical' ? 'selected' : '' }}>{{ __('ticket.technical') }}</option>
                    <option value="account" {{ old('category') === 'account' ? 'selected' : '' }}>{{ __('ticket.account') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('ticket.priority') }} *</label>
                <select name="priority" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>{{ __('ticket.priority_low') }}</option>
                    <option value="medium" {{ old('priority') !== 'low' && old('priority') !== 'high' && old('priority') !== 'urgent' ? 'selected' : '' }}>{{ __('ticket.priority_medium') }}</option>
                    <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>{{ __('ticket.priority_high') }}</option>
                    <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>{{ __('ticket.priority_urgent') }}</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('ticket.message') }} *</label>
            <textarea name="message" rows="6" required maxlength="10000"
                      class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                      placeholder="{{ __('ticket.message_placeholder') }}">{{ old('message') }}</textarea>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                {{ __('ticket.submit') }}
            </button>
            <a href="{{ route('tickets.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('cancel') }}</a>
        </div>
    </form>
    {{-- WeChat QR Code Modal --}}
    <div x-show="showQr" x-transition.opacity
         class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/50" @click.self="showQr = false">
        <div class="bg-white rounded-xl shadow-2xl p-6 max-w-xs w-full text-center" @click.stop>
            <h3 class="text-lg font-bold text-gray-900 mb-1">{{ __('ticket.wechat_qr_title') }}</h3>
            <p class="text-sm text-gray-500 mb-4" x-text="qrValue"></p>
            <div class="flex justify-center mb-4">
                <img :src="qrImg" alt="QR Code" class="w-48 h-48 rounded-lg border object-cover">
            </div>
            <p class="text-xs text-gray-400 mb-4">{{ __('ticket.wechat_qr_hint') }}</p>
            <button @click="showQr = false" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition">
                {{ __('cancel') }}
            </button>
        </div>
    </div>
</div>
@endsection
