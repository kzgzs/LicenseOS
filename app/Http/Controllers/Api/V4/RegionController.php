<?php

namespace App\Http\Controllers\Api\V4;

use App\Http\Controllers\Controller;
use App\Services\RegionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    public function __construct(protected RegionService $regionService) {}

    /**
     * GET /api/v4/regions - List active regions.
     */
    public function index(): JsonResponse
    {
        $regions = $this->regionService->getActiveRegions();
        return response()->json(['data' => $regions]);
    }

    /**
     * GET /api/v4/regions/resolve - Resolve region by country code.
     */
    public function resolve(Request $request): JsonResponse
    {
        $countryCode = $request->query('country', $request->user()?->country_code);
        $region = $this->regionService->resolveByCountry($countryCode);

        return response()->json([
            'data' => $region,
            'meta' => ['country_code' => $countryCode],
        ]);
    }

    /**
     * GET /api/v4/regions/analytics - Region analytics (admin).
     */
    public function analytics(): JsonResponse
    {
        return response()->json(['data' => $this->regionService->getAnalytics()]);
    }
}
