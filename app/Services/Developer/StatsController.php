<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Ecosystem\DeveloperStatsService;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function __construct(
        protected DeveloperStatsService $statsService,
    ) {}

    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return view('developer.stats', [
                'tenant' => null,
                'report' => ['total_sales' => 0, 'total_revenue' => 0, 'platform_fees' => 0, 'net_income' => 0, 'total_orders' => 0, 'avg_order_value' => 0, 'products' => []],
                'revenueChart' => [],
                'from' => now()->subDays(30)->format('Y-m-d'),
                'to' => now()->format('Y-m-d'),
            ]);
        }

        $from = $request->input('from') ? now()->parse($request->input('from')) : now()->subDays(30);
        $to = $request->input('to') ? now()->parse($request->input('to')) : now();

        $raw = $this->statsService->getSalesReport($tenant, $from, $to);
        $revenueChart = $this->statsService->getRevenueChart($tenant);

        // Map service keys to view-expected keys
        $report = [
            'total_sales' => $raw['total_orders'],
            'total_revenue' => $raw['total_revenue'],
            'platform_fees' => $raw['total_platform_fee'],
            'net_income' => $raw['total_net_income'],
            'total_orders' => $raw['total_orders'],
            'avg_order_value' => $raw['total_orders'] > 0 ? round($raw['total_revenue'] / $raw['total_orders'], 2) : 0,
            'products' => collect($raw['top_products'])->map(function ($p) {
                $product = $p['product'] ?? [];
                return [
                    'name' => $product['name'] ?? 'Unknown',
                    'version' => '1.0',
                    'type' => 'plugin',
                    'price' => $p['revenue'] > 0 && $p['sales'] > 0 ? round($p['revenue'] / $p['sales'], 2) : 0,
                    'downloads' => 0,
                    'rating' => 0,
                    'orders_count' => $p['sales'],
                    'revenue' => (float) $p['revenue'],
                ];
            })->toArray(),
        ];

        // Map chart keys
        $chart = collect($revenueChart)->map(function ($day) {
            return ['date' => $day['date'], 'amount' => (float) ($day['revenue'] ?? 0)];
        })->toArray();

        return view('developer.stats', [
            'tenant' => $tenant,
            'report' => $report,
            'revenueChart' => $chart,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ]);
    }

    public function product(Product $product)
    {
        if ($product->tenant_id !== auth()->user()->tenant_id && !auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $stats = $this->statsService->getProductStats($product);

        return view('developer.product-stats', [
            'product' => $product,
            'stats' => $stats,
        ]);
    }
}
