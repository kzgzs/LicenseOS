<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UpdateService;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    public function __construct(
        protected UpdateService $updateService,
    ) {}

    /**
     * Check for updates for a product.
     * GET /api/v2/update/check?product=slug&version=1.0.0&license_key=xxx
     */
    public function check(Request $request)
    {
        $request->validate([
            'product' => 'required|string',
            'version' => 'nullable|string',
            'license_key' => 'nullable|string',
        ]);

        $result = $this->updateService->checkForUpdate(
            $request->input('product'),
            $request->input('version'),
            $request->input('license_key'),
        );

        if (!$result) {
            return response()->json([
                'error' => 'Product not found.',
            ], 404);
        }

        return response()->json($result);
    }

    /**
     * Secure download endpoint — validates license key before serving ZIP.
     * GET /api/v2/download?product=slug&version=1.0.0&license_key=xxx
     */
    public function download(Request $request)
    {
        $request->validate([
            'product' => 'required|string',
            'version' => 'required|string',
            'license_key' => 'nullable|string', // nullable — free products don't require a license
        ]);

        $filePath = $this->updateService->authorizeDownload(
            $request->input('product'),
            $request->input('version'),
            $request->input('license_key'),
        );

        if (!$filePath) {
            return response()->json([
                'error' => 'Unauthorized. Invalid license key or product not found.',
            ], 403);
        }

        $fileName = basename($filePath);

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
