<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductView;
use App\Services\PaymentService;
use App\Services\RecommendationService;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StorefrontController extends Controller
{
    public function __construct(
        protected SeoService $seoService,
        protected RecommendationService $recommendations,
        protected PaymentService $paymentService,
    ) {}

    /**
     * Marketplace homepage - featured products + categories.
     * GET /marketplace
     */
    public function index(Request $request)
    {
        $query = Product::published()->with(['tenant', 'category']);

        // Category filter
        if ($category = $request->input('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $category));
        }

        // Search
        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%");
            });
        }

        // Sort
        $sort = $request->input('sort', 'popular');
        $query = match ($sort) {
            'newest' => $query->latest(),
            'price_low' => $query->orderBy('price', 'asc'),
            'price_high' => $query->orderBy('price', 'desc'),
            'rating' => $query->orderByDesc('rating'),
            default => $query->orderByDesc('views'),
        };

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::topLevel()->ordered()->withCount('products')->get();
        $featured = Product::published()->featured()->limit(4)->get();
        $trending = $this->recommendations->getTrending(4);
        $newArrivals = $this->recommendations->getNewArrivals(4);

        return view('storefront.index', compact('products', 'categories', 'featured', 'trending', 'newArrivals'));
    }

    /**
     * Product detail page with SEO metadata and view tracking.
     * GET /products/{slug}
     */
    public function show(Request $request, string $slug)
    {
        $product = Product::published()
            ->where('slug', $slug)
            ->with(['tenant', 'category', 'reviews' => function ($q) {
                $q->approved()->latest()->with(['user', 'replies' => function ($q2) {
                    $q2->oldest()->with('user');
                }]);
            }, 'versions'])
            ->firstOrFail();

        // Track view
        $this->trackView($product, $request);

        // Increment product view counter
        $product->incrementViews();

        // Generate SEO metadata
        $seo = $this->seoService->generateProductMeta($product);

        // Recommendations
        $related = $this->recommendations->getRelated($product, 4);
        $boughtTogether = $this->recommendations->getFrequentlyBoughtTogether($product, 3);

        // Payment methods
        $availableMethods = $this->paymentService->availableMethods();

        // Localized description (lazy machine translation with fallback to original)
        $localizedDescription = $product->getLocalizedDescription();
        $localizedShortDescription = $product->getLocalizedShortDescription();

        // Readme sections (installation / faq): original English text + localized display text
        $rawSections = $product->readme_sections;
        if (is_string($rawSections)) {
            $rawSections = json_decode($rawSections, true);
        }
        $rawSections = is_array($rawSections) ? $rawSections : [];

        $installationSource = trim((string) ($rawSections['installation'] ?? ''));
        $faqSource = trim((string) ($rawSections['faq'] ?? ''));

        // Fetch localized sections once (lazy machine translation, same as description)
        $localizedSections = $product->getLocalizedReadmeSectionsFor(app()->getLocale());

        // Display text = localized translation when available, otherwise fall back to original
        $installationText = $installationSource === ''
            ? ''
            : (trim((string) ($localizedSections['installation'] ?? '')) !== ''
                ? trim((string) $localizedSections['installation'])
                : $installationSource);
        $faqText = $faqSource === ''
            ? ''
            : (trim((string) ($localizedSections['faq'] ?? '')) !== ''
                ? trim((string) $localizedSections['faq'])
                : $faqSource);

        return view('storefront.show', compact('product', 'seo', 'related', 'boughtTogether', 'availableMethods', 'localizedDescription', 'localizedShortDescription', 'installationSource', 'faqSource', 'installationText', 'faqText'));
    }

    /**
     * Category browsing page.
     * GET /marketplace/category/{slug}
     */
    public function category(Request $request, string $slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $products = Product::published()
            ->where('category_id', $category->id)
            ->with(['tenant'])
            ->orderByDesc('views')
            ->paginate(12);

        return view('storefront.category', compact('category', 'products'));
    }

    /**
     * XML Sitemap for search engines.
     * GET /sitemap.xml
     */
    public function sitemap()
    {
        $entries = $this->seoService->generateSitemapEntries();

        return response()
            ->view('storefront.sitemap', compact('entries'))
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Track a product view for analytics.
     */
    protected function trackView(Product $product, Request $request): void
    {
        $sessionId = $request->session()->getId();
        $referrer = $request->header('referer');

        // Prevent duplicate views within 5 minutes for same session
        $recentView = ProductView::where('product_id', $product->id)
            ->where('session_id', $sessionId)
            ->where('created_at', '>', now()->subMinutes(5))
            ->exists();

        if ($recentView) {
            return;
        }

        ProductView::create([
            'product_id' => $product->id,
            'user_id' => $request->user()?->id,
            'session_id' => $sessionId,
            'ip' => $request->ip(),
            'referrer' => Str::limit($referrer, 500),
            'source' => ProductView::detectSource($referrer),
        ]);
    }
}
