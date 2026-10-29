<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\License;
use App\Models\Activation;
use App\Models\Commission;
use App\Models\ApiKey;
use App\Models\DeveloperApplication;
use App\Models\UpdatePackage;
use App\Models\Payout;
use App\Models\Category;
use App\Models\Review;
use App\Models\Subscription;
use App\Models\Refund;
use App\Models\Ad;
use App\Models\ExchangeRate;
use App\Models\LedgerEntry;
use App\Models\PlatformTier;
use App\Models\ProductTranslation;
use App\Models\Region;
use App\Models\RevenueRecord;
use App\Models\TaxRule;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create developer tenant
        $tenant = Tenant::create([
            'name' => 'Demo Studio',
            'email' => 'dev@demostudio.com',
            'commission_rate' => 10,
            'status' => 'active',
            'balance' => 0,
        ]);

        // 2. Create users
        User::create([
            'name' => 'Admin',
            'email' => 'admin@licenseos.com',
            'password' => bcrypt('123456'),
            'role' => 'super_admin',
        ]);

        $developer = User::create([
            'name' => 'Developer',
            'email' => 'developer@demo.com',
            'password' => bcrypt('password'),
            'role' => 'developer',
            'tenant_id' => $tenant->id,
        ]);

        // 3. Generate API Key for the developer
        $apiKey = ApiKey::generateFor($tenant);

        // 4. Create product
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'SEO Booster Pro',
            'slug' => 'seo-booster-pro',
            'type' => 'wp_plugin',
            'price' => 49.99,
            'version' => '1.1.0',
            'status' => 'published',
            'description' => 'Professional SEO plugin for WordPress. Boost your search rankings with AI-powered optimization.',
        ]);

        // 5. Create update packages (versions)
        UpdatePackage::create([
            'product_id' => $product->id,
            'version' => '1.0.0',
            'file_url' => 'https://cdn.licenseos.com/packages/seo-booster-pro-1.0.0.zip',
            'changelog' => 'Initial release.',
        ]);

        UpdatePackage::create([
            'product_id' => $product->id,
            'version' => '1.1.0',
            'file_url' => 'https://cdn.licenseos.com/packages/seo-booster-pro-1.1.0.zip',
            'changelog' => 'Added AI keyword analysis, improved performance, fixed compatibility issues.',
        ]);

        // 6. Create order (pending)
        $order = Order::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'amount' => 49.99,
            'status' => 'pending',
            'payment_method' => 'stripe',
        ]);

        // 7. Create a paid order with license and commission (demo)
        $paidOrder = Order::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'amount' => 49.99,
            'status' => 'paid',
            'payment_method' => 'stripe',
        ]);

        $license = License::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'order_id' => $paidOrder->id,
            'license_key' => 'DEMO-A1B2-C3D4-E5F6',
            'status' => 'active',
            'max_domains' => 3,
            'expire_at' => now()->addYear(),
        ]);

        Activation::create([
            'license_id' => $license->id,
            'domain' => 'demo-site.com',
            'ip' => '127.0.0.1',
            'last_check' => now(),
        ]);

        Commission::create([
            'order_id' => $paidOrder->id,
            'tenant_id' => $tenant->id,
            'platform_fee' => 5.00,
            'developer_income' => 44.99,
            'rate' => 10,
        ]);

        $tenant->update(['balance' => 44.99]);

        // 8. Create a pending payout
        Payout::create([
            'tenant_id' => $tenant->id,
            'amount' => 44.99,
            'method' => 'stripe',
            'status' => 'pending',
        ]);

        // 9. Create a pending developer application (demo)
        DeveloperApplication::create([
            'name' => 'John Plugin Dev',
            'email' => 'john@example.com',
            'reason' => 'I want to sell my WordPress plugins on the marketplace.',
            'status' => 'pending',
        ]);

        // 10. V3: Create categories
        $catPlugins = Category::create([
            'name' => 'WordPress Plugins',
            'slug' => 'wordpress-plugins',
            'description' => 'Premium WordPress plugins for developers and businesses.',
            'sort_order' => 1,
        ]);

        $catThemes = Category::create([
            'name' => 'WordPress Themes',
            'slug' => 'wordpress-themes',
            'description' => 'Professional WordPress themes and templates.',
            'sort_order' => 2,
        ]);

        $catSaaS = Category::create([
            'name' => 'SaaS Tools',
            'slug' => 'saas-tools',
            'description' => 'Software-as-a-Service tools and APIs.',
            'sort_order' => 3,
        ]);

        // Subcategories
        Category::create([
            'name' => 'SEO Plugins',
            'slug' => 'seo-plugins',
            'parent_id' => $catPlugins->id,
            'sort_order' => 1,
        ]);

        Category::create([
            'name' => 'Security Plugins',
            'slug' => 'security-plugins',
            'parent_id' => $catPlugins->id,
            'sort_order' => 2,
        ]);

        // 11. V3: Update product with marketplace fields
        $product->update([
            'category_id' => $catPlugins->id,
            'short_description' => 'AI-powered SEO optimization for WordPress sites.',
            'tags' => ['seo', 'wordpress', 'optimization', 'ai'],
            'downloads' => 1523,
            'rating' => 4.7,
            'featured' => true,
            'screenshots' => [
                ['url' => 'https://cdn.licenseos.com/screenshots/seo-booster-1.jpg', 'caption' => 'Dashboard Overview'],
                ['url' => 'https://cdn.licenseos.com/screenshots/seo-booster-2.jpg', 'caption' => 'Keyword Analysis'],
            ],
        ]);

        // 12. V3: Create sample reviews
        $customer = User::create([
            'name' => 'Jane Customer',
            'email' => 'customer@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'avatar' => 'https://i.pravatar.cc/150?u=jane',
            'bio' => 'WordPress enthusiast and plugin reviewer.',
        ]);

        Review::create([
            'product_id' => $product->id,
            'user_id' => $customer->id,
            'rating' => 5,
            'comment' => 'Excellent plugin! My site traffic increased by 40% in just two weeks. The AI keyword suggestions are spot on.',
            'approved' => true,
        ]);

        $anotherCustomer = User::create([
            'name' => 'Bob Reviewer',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'avatar' => 'https://i.pravatar.cc/150?u=bob',
        ]);

        Review::create([
            'product_id' => $product->id,
            'user_id' => $anotherCustomer->id,
            'rating' => 4,
            'comment' => 'Great features and easy to use. Would love to see more integration options in future updates.',
            'approved' => true,
        ]);

        // Pending review (needs moderation)
        Review::create([
            'product_id' => $product->id,
            'user_id' => $developer->id,
            'rating' => 3,
            'comment' => 'Good plugin but needs better documentation for advanced features.',
            'approved' => false,
        ]);

        // 13. V3: Create subscription example
        Subscription::create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'status' => 'active',
            'renew_at' => now()->addMonth(),
        ]);

        // 14. V3.1: Create a refunded order example
        $refundedOrder = Order::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'user_id' => $anotherCustomer->id,
            'amount' => 49.99,
            'currency' => 'USD',
            'status' => 'refunded',
            'payment_method' => 'stripe',
            'payment_intent_id' => 'pi_demo_refunded_' . time(),
            'refunded_at' => now(),
        ]);

        Refund::create([
            'order_id' => $refundedOrder->id,
            'amount' => 49.99,
            'reason' => 'Customer requested refund - product not compatible.',
            'stripe_refund_id' => 're_demo_' . time(),
            'status' => 'completed',
        ]);

        // ================================================
        // V3.4: Commercial Expansion Demo Data
        // ================================================

        // 15. Platform Tiers (Developer levels)
        PlatformTier::create([
            'name' => 'New Developer', 'type' => 'developer', 'level' => 0,
            'commission_discount' => 0, 'api_quota_bonus' => 0,
            'priority_ranking' => false, 'featured_eligible' => false,
            'benefits' => ['Basic listing', 'Standard support'],
            'requirements' => ['Approved application'],
        ]);
        PlatformTier::create([
            'name' => 'Growth Developer', 'type' => 'developer', 'level' => 1,
            'commission_discount' => 2, 'api_quota_bonus' => 5000,
            'priority_ranking' => false, 'featured_eligible' => true,
            'benefits' => ['Featured eligibility', '2% lower commission', '+5000 API calls'],
            'requirements' => ['20+ sales', '$1000+ revenue'],
        ]);
        PlatformTier::create([
            'name' => 'Top Developer', 'type' => 'developer', 'level' => 2,
            'commission_discount' => 5, 'api_quota_bonus' => 50000,
            'priority_ranking' => true, 'featured_eligible' => true, 'promotion_access' => true,
            'benefits' => ['Priority ranking', '5% lower commission', '+50K API calls', 'Promotion access'],
            'requirements' => ['200+ sales', '$20K+ revenue'],
        ]);
        PlatformTier::create([
            'name' => 'Official Partner', 'type' => 'developer', 'level' => 3,
            'commission_discount' => 8, 'api_quota_bonus' => 500000,
            'priority_ranking' => true, 'featured_eligible' => true, 'promotion_access' => true,
            'benefits' => ['Lowest commission', 'Unlimited API', 'Dedicated account manager', 'Co-marketing'],
            'requirements' => ['1000+ sales', '$100K+ revenue', 'Platform invitation'],
        ]);

        // User tiers
        PlatformTier::create([
            'name' => 'Basic User', 'type' => 'user', 'level' => 0,
            'benefits' => ['Standard access', 'Community support'],
        ]);
        PlatformTier::create([
            'name' => 'Member', 'type' => 'user', 'level' => 1,
            'benefits' => ['Priority support', 'Member discounts', 'Early access'],
        ]);
        PlatformTier::create([
            'name' => 'Premium', 'type' => 'user', 'level' => 2,
            'benefits' => ['VIP support', 'Exclusive plugins', 'Beta access', 'Premium discounts'],
        ]);

        // 16. Demo Ad Campaign
        $ad = Ad::create([
            'title' => 'SEO Booster Pro - Premium Listing',
            'type' => 'search_boost',
            'bid_price' => 2.50,
            'daily_budget' => 50.00,
            'total_spent' => 12.50,
            'start_at' => now()->subDays(5),
            'end_at' => now()->addDays(25),
            'product_id' => $product->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'impressions' => 520,
            'clicks' => 5,
            'target_keywords' => 'seo,wordpress seo,seo plugin',
        ]);

        // 17. Demo Revenue Records
        RevenueRecord::record('commission', 5.00, $paidOrder, 'Sales commission: Order #' . $paidOrder->id);
        RevenueRecord::record('ad', 12.50, $ad, 'Ad spend: SEO Booster Pro campaign');

        // ================================================
        // V4: Global Enterprise Demo Data
        // ================================================

        // 19. V4 Regions
        $regionUS = Region::create([
            'code' => 'US', 'name' => 'United States', 'display_name' => 'North America',
            'timezone' => 'America/New_York', 'primary_currency' => 'USD',
            'supported_currencies' => ['USD', 'CAD'],
            'is_primary' => true, 'status' => 'active',
            'config' => ['stripe_enabled' => true, 'aws_region' => 'us-east-1'],
        ]);
        $regionEU = Region::create([
            'code' => 'EU', 'name' => 'Europe', 'display_name' => 'European Union',
            'timezone' => 'Europe/Berlin', 'primary_currency' => 'EUR',
            'supported_currencies' => ['EUR', 'GBP', 'SEK', 'PLN'],
            'is_primary' => false, 'status' => 'active',
            'config' => ['adyen_enabled' => true, 'aws_region' => 'eu-west-1', 'gdpr' => true],
        ]);
        $regionSG = Region::create([
            'code' => 'SG', 'name' => 'Southeast Asia', 'display_name' => 'Asia Pacific',
            'timezone' => 'Asia/Singapore', 'primary_currency' => 'SGD',
            'supported_currencies' => ['SGD', 'MYR', 'THB', 'IDR'],
            'is_primary' => false, 'status' => 'active',
            'config' => ['stripe_enabled' => true, 'aws_region' => 'ap-southeast-1'],
        ]);
        $regionME = Region::create([
            'code' => 'ME', 'name' => 'Middle East', 'display_name' => 'Middle East & Africa',
            'timezone' => 'Asia/Dubai', 'primary_currency' => 'AED',
            'supported_currencies' => ['AED', 'SAR', 'QAR'],
            'is_primary' => false, 'status' => 'active',
            'config' => ['stripe_enabled' => true, 'aws_region' => 'me-south-1'],
        ]);

        // 20. V4 Tax Rules (seed defaults)
        (new \App\Services\TaxService())->seedDefaultRules();

        // 21. V4 Exchange Rates
        $rates = [
            ['EUR', 'USD', 1.08], ['GBP', 'USD', 1.27], ['CNY', 'USD', 0.14],
            ['JPY', 'USD', 0.0067], ['SGD', 'USD', 0.74], ['INR', 'USD', 0.012],
            ['AED', 'USD', 0.27], ['CAD', 'USD', 0.73],
        ];
        foreach ($rates as [$from, $to, $rate]) {
            ExchangeRate::create([
                'from_currency' => $from, 'to_currency' => $to,
                'rate' => $rate, 'fetched_at' => now(),
            ]);
        }

        // 22. V4 Ledger Entries (multi-currency)
        LedgerEntry::record(['tenant_id' => $tenant->id, 'currency' => 'USD', 'amount' => 44.99, 'exchange_rate' => 1.0, 'type' => 'income', 'description' => 'Order #1 income (US)', 'reference_type' => 'order', 'reference_id' => $paidOrder->id, 'region_id' => $regionUS->id]);
        LedgerEntry::record(['tenant_id' => $tenant->id, 'currency' => 'EUR', 'amount' => 39.99, 'exchange_rate' => 1.08, 'type' => 'income', 'description' => 'EU customer purchase', 'region_id' => $regionEU->id]);
        LedgerEntry::record(['tenant_id' => $tenant->id, 'currency' => 'GBP', 'amount' => 29.99, 'exchange_rate' => 1.27, 'type' => 'income', 'description' => 'UK customer purchase', 'region_id' => $regionEU->id]);
        LedgerEntry::record(['tenant_id' => $tenant->id, 'currency' => 'SGD', 'amount' => 59.99, 'exchange_rate' => 0.74, 'type' => 'income', 'description' => 'SG customer purchase', 'region_id' => $regionSG->id]);
        LedgerEntry::record(['tenant_id' => $tenant->id, 'currency' => 'USD', 'amount' => -5.00, 'exchange_rate' => 1.0, 'type' => 'fee', 'description' => 'Platform commission: Order #1']);
        LedgerEntry::record(['tenant_id' => $tenant->id, 'currency' => 'USD', 'amount' => 4.86, 'exchange_rate' => 1.08, 'type' => 'tax', 'description' => 'EU VAT collected (20%)']);

        // 23. V4 Product Translations (multi-language SEO)
        ProductTranslation::create([
            'product_id' => $product->id, 'locale' => 'zh_CN',
            'name' => 'SEO优化大师专业版', 'slug' => 'seo-booster-pro-zh',
            'short_description' => '专业WordPress SEO插件，AI驱动搜索优化。',
            'description' => '强大的WordPress SEO插件，利用AI技术分析关键词，提升搜索引擎排名。',
            'meta_title' => 'SEO优化大师 - 专业WordPress SEO插件',
            'meta_description' => '使用AI驱动的SEO优化大师，提升您的网站搜索排名和流量。',
            'keywords' => ['seo', 'wordpress', '搜索引擎优化', '关键词分析'],
        ]);
        ProductTranslation::create([
            'product_id' => $product->id, 'locale' => 'es',
            'name' => 'SEO Booster Pro', 'slug' => 'seo-booster-pro-es',
            'short_description' => 'Plugin SEO profesional para WordPress.',
            'meta_title' => 'SEO Booster Pro - Plugin SEO para WordPress',
            'meta_description' => 'Mejore el ranking de su sitio web con nuestro plugin SEO impulsado por IA.',
            'keywords' => ['seo', 'wordpress', 'plugin seo', 'optimizacion'],
        ]);
        ProductTranslation::create([
            'product_id' => $product->id, 'locale' => 'de',
            'name' => 'SEO Booster Pro', 'slug' => 'seo-booster-pro-de',
            'short_description' => 'Professionelles SEO-Plugin für WordPress.',
            'meta_title' => 'SEO Booster Pro - Professionelles WordPress SEO Plugin',
            'meta_description' => 'Verbessern Sie Ihr Suchranking mit unserem KI-gestützten SEO-Plugin.',
            'keywords' => ['seo', 'wordpress', 'seo-plugin', 'suchmaschinenoptimierung'],
        ]);
        ProductTranslation::create([
            'product_id' => $product->id, 'locale' => 'ja',
            'name' => 'SEOブースタープロ', 'slug' => 'seo-booster-pro-ja',
            'short_description' => 'WordPress向けプロフェッショナルSEOプラグイン。',
            'meta_title' => 'SEOブースタープロ - WordPress SEOプラグイン',
            'meta_description' => 'AI搭載のSEOプラグインで検索ランキングを向上させましょう。',
            'keywords' => ['seo', 'wordpress', 'seoプラグイン', '検索最適化'],
        ]);

        // Update product with V4 fields
        $product->update([
            'supported_locales' => ['en', 'zh_CN', 'es', 'de', 'ja'],
            'download_url' => 'https://cdn-us.licenseos.com/downloads/seo-booster-pro-latest.zip',
            'cdn_node' => 'us-east',
            'file_size_bytes' => 2457600,
        ]);

        // Update tenant with V4 fields
        $tenant->update([
            'region_id' => $regionUS->id,
            'country_code' => 'US',
            'primary_currency' => 'USD',
        ]);

        // Update customer with V4 fields
        $customer->update([
            'locale' => 'en',
            'country_code' => 'US',
            'timezone' => 'America/New_York',
        ]);

        $this->command->info('');
        $this->command->info('Demo data seeded successfully!');
        $this->command->info('');
    }
}
