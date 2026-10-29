<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;

        if (!$tenant) {
            return view('developer.orders', [
                'orders' => collect(),
                'noTenant' => true,
            ]);
        }

        $orders = $tenant->orders()
            ->with('product')
            ->latest()
            ->paginate(20);

        return view('developer.orders', [
            'orders' => $orders,
            'tenant' => $tenant,
        ]);
    }
}
