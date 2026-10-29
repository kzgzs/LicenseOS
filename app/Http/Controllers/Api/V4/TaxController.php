<?php

namespace App\Http\Controllers\Api\V4;

use App\Http\Controllers\Controller;
use App\Services\TaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function __construct(protected TaxService $taxService) {}

    /**
     * POST /api/v4/tax/calculate - Calculate tax for a price.
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
            'country_code' => 'required|string|size:2',
            'state_code' => 'nullable|string|max:10',
        ]);

        $result = $this->taxService->calculate(
            $validated['price'],
            $validated['country_code'],
            $validated['state_code'] ?? null
        );

        $totalWithTax = $this->taxService->getPriceWithTax(
            $validated['price'],
            $validated['country_code'],
            $validated['state_code'] ?? null
        );

        return response()->json([
            'data' => array_merge($result, [
                'price' => $validated['price'],
                'total_with_tax' => $totalWithTax,
            ]),
        ]);
    }

    /**
     * GET /api/v4/tax/rules - List active tax rules.
     */
    public function rules(): JsonResponse
    {
        $rules = \App\Models\TaxRule::active()->orderBy('country_code')->paginate(50);
        return response()->json($rules);
    }
}
