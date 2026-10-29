<?php

namespace App\Http\Controllers\Api\V4;

use App\Http\Controllers\Controller;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function __construct(protected LedgerService $ledgerService) {}

    /**
     * GET /api/v4/ledger - Get tenant ledger entries.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $entries = $this->ledgerService->getEntries($tenant, $request->only(['type', 'currency', 'from', 'to']));

        return response()->json([
            'data' => $entries->items(),
            'meta' => [
                'total' => $entries->total(),
                'balance_usd' => $this->ledgerService->getBalance($tenant),
            ],
            'links' => [
                'next' => $entries->nextPageUrl(),
                'prev' => $entries->previousPageUrl(),
            ],
        ]);
    }

    /**
     * GET /api/v4/ledger/balance - Get tenant balance.
     */
    public function balance(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $currency = $request->query('currency');

        return response()->json([
            'data' => [
                'balance' => $this->ledgerService->getBalance($tenant, $currency),
                'currency' => $currency ?? 'USD',
                'tenant_currency' => $tenant->primary_currency,
            ],
        ]);
    }
}
