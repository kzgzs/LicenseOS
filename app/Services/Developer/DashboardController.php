<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\CommissionService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected CommissionService $commissionService,
    ) {}

    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;

        if (!$tenant) {
            if ($request->user()->isSuperAdmin()) {
                return view('developer.dashboard', [
                    'tenant' => null,
                    'totalProducts' => \App\Models\Product::count(),
                    'totalLicenses' => \App\Models\License::count(),
                    'totalOrders' => \App\Models\Order::count(),
                    'totalIncome' => \App\Models\Commission::sum('developer_income'),
                    'availableBalance' => 0,
                    'recentOrders' => \App\Models\Order::with('product')->latest()->take(10)->get(),
                ]);
            }
            // Regular developer without tenant: show empty dashboard
            return view('developer.dashboard', [
                'tenant' => null,
                'totalProducts' => 0,
                'totalLicenses' => 0,
                'totalOrders' => 0,
                'totalIncome' => 0,
                'availableBalance' => 0,
                'recentOrders' => collect(),
                'noTenant' => true,
            ]);
        }

        return view('developer.dashboard', [
            'tenant' => $tenant,
            'totalProducts' => $tenant->products()->count(),
            'totalLicenses' => $tenant->licenses()->count(),
            'totalOrders' => $tenant->orders()->count(),
            'totalIncome' => $tenant->commissions()->sum('developer_income'),
            'availableBalance' => $this->commissionService->getPendingBalance($tenant->id),
            'recentOrders' => $tenant->orders()->with('product')->latest()->take(10)->get(),
        ]);
    }
}
