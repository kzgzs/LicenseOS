<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class MyOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $orders = Order::where('user_id', $user->id)
            ->with(['product'])
            ->latest()
            ->paginate(20);

        $currencySymbol = \App\Models\SiteSetting::get('currency_symbol', '¥');

        return view('my-orders', compact('orders', 'currencySymbol'));
    }
}
