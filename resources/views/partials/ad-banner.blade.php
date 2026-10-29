{{-- Ad Banner Carousel - include in storefront pages --}}
@if(isset($bannerAds) && $bannerAds->count() > 0)
<div class="mb-8" x-data="adCarousel()" x-init="init()">
    <div class="relative rounded-xl overflow-hidden bg-gradient-to-r from-indigo-600 to-purple-600">
        {{-- Slides --}}
        <div class="relative h-48 sm:h-56 md:h-64 overflow-hidden">
            @foreach($bannerAds as $i => $ad)
            <div x-show="current === {{ $i }}"
                 x-transition:enter="transition ease-out duration-500"
                 x-transition:enter-start="opacity-0 translate-x-4"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="absolute inset-0"
                 style="display: {{ $i === 0 ? 'block' : 'none' }}">
                {{-- Background --}}
                @if($ad->banner_image)
                <img src="{{ $ad->banner_image }}" alt="{{ $ad->title }}" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-black/30"></div>
                @else
                <div class="w-full h-full bg-gradient-to-r from-indigo-600 to-purple-600"></div>
                @endif

                {{-- Content --}}
                <div class="absolute inset-0 flex items-center px-8 sm:px-12">
                    <div class="max-w-lg text-white">
                        <span class="inline-block text-xs bg-white/20 text-white px-2 py-0.5 rounded-full mb-2 backdrop-blur-sm">{{ __('ad.sponsored') }}</span>
                        <h3 class="text-xl sm:text-2xl font-bold mb-2">{{ $ad->title }}</h3>
                        @if($ad->product)
                        <p class="text-sm text-white/80 mb-4 line-clamp-2">{{ $ad->product->short_description }}</p>
                        @endif
                        <a href="{{ $ad->landing_url ?? ($ad->product ? route('product.show', $ad->product->slug) : '#') }}"
                           onclick="trackAdClick({{ $ad->id }})"
                           class="inline-flex items-center gap-1 px-5 py-2 bg-white text-indigo-700 rounded-lg font-semibold text-sm hover:bg-indigo-50 transition-colors">
                            {{ __('ad.learn_more') }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Navigation arrows --}}
        @if($bannerAds->count() > 1)
        <button @click="prev()" class="absolute left-3 top-1/2 -translate-y-1/2 p-1.5 bg-white/20 hover:bg-white/40 rounded-full backdrop-blur-sm transition-colors">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
        </button>
        <button @click="next()" class="absolute right-3 top-1/2 -translate-y-1/2 p-1.5 bg-white/20 hover:bg-white/40 rounded-full backdrop-blur-sm transition-colors">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
        </button>

        {{-- Dot indicators --}}
        <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
            @foreach($bannerAds as $i => $ad)
            <button @click="goTo({{ $i }})" :class="current === {{ $i }} ? 'bg-white w-6' : 'bg-white/50 w-2'" class="h-2 rounded-full transition-all"></button>
            @endforeach
        </div>
        @endif
    </div>
</div>

<script>
function adCarousel() {
    return {
        current: 0,
        total: {{ $bannerAds->count() }},
        timer: null,
        init() {
            if (this.total > 1) {
                this.timer = setInterval(() => this.next(), 5000);
            }
        },
        next() { this.goTo((this.current + 1) % this.total); },
        prev() { this.goTo((this.current - 1 + this.total) % this.total); },
        goTo(idx) {
            this.current = idx;
            clearInterval(this.timer);
            if (this.total > 1) {
                this.timer = setInterval(() => this.next(), 5000);
            }
        }
    };
}

function trackAdClick(adId) {
    fetch('/api/v3/ads/' + adId + '/click', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
    }).catch(function() {});
}
</script>
@endif
