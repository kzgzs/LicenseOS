@extends('layouts.app')
@section('title', __('contact.title'))
@section('content')
<div class="max-w-4xl mx-auto py-8">
    <div class="grid md:grid-cols-3 gap-8">

        {{-- Contact Form --}}
        <div class="md:col-span-2">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ __('contact.title') }}</h1>
            <p class="text-gray-600 mb-8">
                {{ __('contact.subtitle') }}
            </p>

            <form method="POST" action="{{ route('contact.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm text-gray-500 mb-1">{{ __('contact.name') }} <span class="text-red-400">*</span></label>
                    <input type="text" name="name" value="{{ old('name', auth()->user()?->name) }}" required
                           class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white"
                           placeholder="{{ __('contact.name') }}">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-500 mb-1">{{ __('contact.email') }} <span class="text-red-400">*</span></label>
                    <input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required
                           class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white"
                           placeholder="you@example.com">
                    @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-500 mb-1">{{ __('contact.other_contact') }}</label>
                    <input type="text" name="other_contact" value="{{ old('other_contact') }}"
                           class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white"
                           placeholder="{{ __('contact.other_contact_placeholder') }}">
                </div>

                <div>
                    <label class="block text-sm text-gray-500 mb-1">{{ __('contact.message') }} <span class="text-red-400">*</span></label>
                    <textarea name="message" rows="6" required
                              class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white resize-none"
                              placeholder="{{ __('contact.message_placeholder') }}">{{ old('message') }}</textarea>
                    @error('message') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        class="w-full py-3 rounded-lg text-white font-bold uppercase tracking-wider bg-indigo-600 hover:bg-indigo-700 transition-all">
                    {{ __('contact.send') }}
                </button>
            </form>
        </div>

        {{-- Contact Info Sidebar --}}
        <div>
            <div class="bg-white rounded-lg shadow-sm p-6 sticky top-4">
                <h2 class="font-semibold mb-4">{{ __('contact.info_title') }}</h2>

                @php
                    $info = [
                        ['key' => 'contact_email',   'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'label' => __('contact.email'), 'link' => fn($v) => "mailto:$v"],
                        ['key' => 'contact_phone',   'icon' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z', 'label' => __('contact.phone'), 'link' => fn($v) => "tel:$v"],
                        ['key' => 'contact_wechat',  'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', 'label' => __('contact.wechat'), 'link' => null],
                        ['key' => 'contact_qq',      'icon' => 'M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z', 'label' => __('contact.qq'), 'link' => null],
                        ['key' => 'contact_telegram','icon' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z', 'label' => __('contact.telegram'), 'link' => fn($v) => "https://t.me/$v"],
                        ['key' => 'contact_whatsapp','icon' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z', 'label' => __('contact.whatsapp'), 'link' => fn($v) => "https://wa.me/" . preg_replace('/[^0-9]/', '', $v)],
                    ];
                @endphp

                <div class="space-y-4">
                    @foreach($info as $item)
                        @php $value = \App\Models\SiteSetting::get($item['key']); @endphp
                        @if($value)
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 bg-indigo-50 rounded-lg flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400">{{ $item['label'] }}</p>
                                    @if($item['link'])
                                        <a href="{{ $item['link']($value) }}" class="text-sm text-gray-700 hover:text-indigo-600 break-all">{{ $value }}</a>
                                    @else
                                        <p class="text-sm text-gray-700 break-all">{{ $value }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                @php
                    $hasAny = \App\Models\SiteSetting::get('contact_email')
                        || \App\Models\SiteSetting::get('contact_phone')
                        || \App\Models\SiteSetting::get('contact_wechat')
                        || \App\Models\SiteSetting::get('contact_qq')
                        || \App\Models\SiteSetting::get('contact_telegram')
                        || \App\Models\SiteSetting::get('contact_whatsapp');
                @endphp
                @if(!$hasAny)
                    <p class="text-sm text-gray-400">{{ __('contact.no_info') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
