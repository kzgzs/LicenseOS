<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Services\AdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdController extends Controller
{
    public function __construct(
        protected AdService $adService
    ) {}

    /**
     * GET /api/v3/ads/banners - Get active banner ads.
     */
    public function banners(): JsonResponse
    {
        $ads = $this->adService->getBannerAds();
        return response()->json(['data' => $ads]);
    }

    /**
     * GET /api/v3/ads/featured - Get featured product ads.
     */
    public function featured(): JsonResponse
    {
        $ads = $this->adService->getFeaturedAds();
        return response()->json(['data' => $ads]);
    }

    /**
     * POST /api/v3/ads/{ad}/click - Record ad click.
     */
    public function recordClick(Ad $ad): JsonResponse
    {
        $this->adService->recordClick($ad);
        return response()->json(['success' => true]);
    }

    /**
     * POST /api/v3/ads/campaigns - Create ad campaign (developer).
     */
    public function createCampaign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:banner,featured,search_boost',
            'bid_price' => 'required|numeric|min:0.01',
            'daily_budget' => 'nullable|numeric|min:1',
            'start_at' => 'required|date|after_or_equal:today',
            'end_at' => 'required|date|after:start_at',
            'product_id' => 'required|exists:products,id',
            'target_keywords' => 'nullable|string',
            'banner_image' => 'nullable|string',
            'landing_url' => 'nullable|url',
        ]);

        $user = $request->user();
        $validated['tenant_id'] = $user->tenant_id;

        $ad = $this->adService->createCampaign($validated);

        return response()->json(['data' => $ad], 201);
    }

    /**
     * GET /api/v3/ads/my-campaigns - List developer's campaigns.
     */
    public function myCampaigns(Request $request): JsonResponse
    {
        $ads = Ad::where('tenant_id', $request->user()->tenant_id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($ads);
    }

    /**
     * POST /api/v3/ads/{ad}/pause - Pause a campaign.
     */
    public function pause(Ad $ad, Request $request): JsonResponse
    {
        if ($ad->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $this->adService->pauseCampaign($ad);
        return response()->json(['success' => true, 'status' => 'paused']);
    }

    /**
     * POST /api/v3/ads/{ad}/activate - Activate a campaign.
     */
    public function activate(Ad $ad, Request $request): JsonResponse
    {
        if ($ad->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $this->adService->activateCampaign($ad);
        return response()->json(['success' => true, 'status' => 'active']);
    }

    /**
     * GET /api/v3/ads/stats - Get developer ad performance stats.
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->adService->getTenantAdStats($request->user()->tenant_id);
        return response()->json(['data' => $stats]);
    }
}
