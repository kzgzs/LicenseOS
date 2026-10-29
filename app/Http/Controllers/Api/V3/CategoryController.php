<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    /**
     * List all top-level categories with children and product counts.
     * GET /api/v3/categories
     */
    public function index()
    {
        $categories = Category::topLevel()
            ->ordered()
            ->with(['children' => function ($q) {
                $q->ordered()->withCount('products');
            }])
            ->withCount('products')
            ->get();

        return response()->json(['data' => $categories]);
    }
}
