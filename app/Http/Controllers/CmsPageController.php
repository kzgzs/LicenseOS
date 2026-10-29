<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;

class CmsPageController extends Controller
{
    public function show(string $slug)
    {
        $locale = app()->getLocale();
        $shortLang = explode('_', $locale)[0]; // zh_CN -> zh

        // Try locale-specific slug (e.g., terms-zh, terms-en), then fall back to base slug
        $page = CmsPage::where('slug', $slug . '-' . $shortLang)
            ->where('status', 'published')
            ->first()
            ?? CmsPage::where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return view('cms-page', compact('page'));
    }
}
