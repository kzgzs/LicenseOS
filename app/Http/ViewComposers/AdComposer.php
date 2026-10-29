<?php

namespace App\Http\ViewComposers;

use App\Services\AdService;
use Illuminate\View\View;

class AdComposer
{
    public function __construct(
        protected AdService $adService
    ) {}

    /**
     * Inject active banner and featured ads into storefront views.
     */
    public function compose(View $view): void
    {
        try {
            $bannerAds = $this->adService->getBannerAds(3);
            $featuredAds = $this->adService->getFeaturedAds(4);

            $view->with('bannerAds', $bannerAds);
            $view->with('featuredAds', $featuredAds);
        } catch (\Throwable $e) {
            // Ads are non-critical; fail silently
            $view->with('bannerAds', collect());
            $view->with('featuredAds', collect());
        }
    }
}
