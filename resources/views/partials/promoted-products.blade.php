{{-- Promoted / Sponsored Product Cards - injected from AdComposer --}}
@if(isset($featuredAds) && $featuredAds->count() > 0)
<div class="mb-8">
    <h2 class="text-lg font-semibold text-gray-700 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"/></svg>
        {{ __('ad.promoted_products') }}
    </h2>
    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($featuredAds as $ad)
        @if($ad->product)
        <a href="{{ route('product.show', $ad->product->slug) }}"
           onclick="trackAdClick({{ $ad->id }})"
           class="block bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-amber-100 overflow-hidden relative group">
            {{-- Sponsored badge --}}
            <span class="absolute top-2 right-2 z-10 text-xs bg-amber-400 text-white px-2 py-0.5 rounded-full font-medium shadow-sm">
                {{ __('ad.sponsored') }}
            </span>
            @if($ad->product->cover_image)
            <div class="h-32 overflow-hidden">
                <img src="{{ asset('storage/' . $ad->product->cover_image) }}" alt="{{ $ad->product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
            </div>
            @else
            <div class="h-32 bg-gradient-to-br from-amber-50 to-orange-100 flex items-center justify-center">
                <span class="text-4xl font-bold text-amber-200">{{ strtoupper(mb_substr($ad->product->name, 0, 1)) }}</span>
            </div>
            @endif
            <div class="p-4">
                <h3 class="font-semibold text-gray-900 text-sm mb-1">{{ $ad->product->name }}</h3>
                <p class="text-xs text-gray-500 mb-2 line-clamp-2">{{ $ad->product->short_description }}</p>
                <div class="flex justify-between items-center">
                    <span class="text-indigo-600 font-bold text-sm">{{ $currencySymbol }}{{ number_format($ad->product->price, 2) }}</span>
                    @if($ad->product->rating)
                    <span class="text-xs text-yellow-500">{{ number_format($ad->product->rating, 1) }} ★</span>
                    @endif
                </div>
            </div>
        </a>
        @endif
        @endforeach
    </div>
</div>

{{-- Ensure trackAdClick is defined even when ad-banner is not included --}}
<script>
if (typeof trackAdClick !== 'function') {
    window.trackAdClick = function(adId) {
        fetch('/api/v3/ads/' + adId + '/click', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
        }).catch(function() {});
    };
}
</script>
@endif
