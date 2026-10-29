<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // 1. Regions (Multi-region deployment routing)
        // ============================================================
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // us, eu, sg, me
            $table->string('name');
            $table->string('display_name');
            $table->string('timezone');
            $table->string('primary_currency', 3); // ISO 4217
            $table->json('supported_currencies')->nullable();
            $table->string('stripe_account_id')->nullable();
            $table->string('adyen_account_id')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 20)->default('active');
            $table->json('config')->nullable(); // region-specific config
            $table->timestamps();
        });

        // ============================================================
        // 2. Tax Rules (VAT / GST / Sales Tax)
        // ============================================================
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 2); // ISO 3166-1 alpha-2
            $table->string('state_code', 10)->nullable();
            $table->string('tax_type', 20); // vat, gst, sales_tax
            $table->decimal('rate', 5, 2); // percentage
            $table->string('name');
            $table->boolean('applies_to_digital')->default(true);
            $table->boolean('applies_to_business')->default(false);
            $table->decimal('threshold', 12, 2)->nullable(); // revenue threshold
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['country_code', 'state_code']);
        });

        // ============================================================
        // 3. Ledger (Multi-currency double-entry bookkeeping)
        // ============================================================
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 3); // ISO 4217
            $table->decimal('amount', 14, 4);
            $table->decimal('amount_base', 14, 4); // converted to base currency (USD)
            $table->decimal('exchange_rate', 10, 6)->default(1.0);
            $table->string('type', 30); // income, payout, fee, tax, refund, ad, api, enterprise
            $table->string('description')->nullable();
            $table->morphs('reference'); // polymorphic: order_id, ad_id, etc.
            $table->unsignedBigInteger('region_id')->nullable();
            $table->string('status', 20)->default('completed'); // pending, completed, reversed
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'currency', 'created_at']);
            $table->index(['type', 'status', 'created_at']);
            $table->index(['region_id', 'created_at']);
        });

        // ============================================================
        // 4. Exchange Rates (multi-currency conversion)
        // ============================================================
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('rate', 10, 6);
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['from_currency', 'to_currency']);
        });

        // ============================================================
        // 5. KYC Verification (Know Your Customer)
        // ============================================================
        Schema::create('kyc_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // individual, company
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->json('documents')->nullable(); // document URLs
            $table->string('legal_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('address')->nullable();
            $table->string('risk_level', 10)->default('low'); // low, medium, high
            $table->text('rejection_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // ============================================================
        // 6. GDPR / Data Compliance
        // ============================================================
        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // export, deletion, correction
            $table->string('status', 20)->default('pending'); // pending, processing, completed, rejected
            $table->text('details')->nullable();
            $table->text('response')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('privacy_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_id', 64)->nullable();
            $table->string('ip', 45);
            $table->string('country_code', 2)->nullable();
            $table->boolean('analytics_consent')->default(false);
            $table->boolean('marketing_consent')->default(false);
            $table->boolean('cookies_consent')->default(false);
            $table->string('privacy_policy_version')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // ============================================================
        // 7. White Label (White-label SaaS system)
        // ============================================================
        Schema::create('white_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('custom_domain')->unique()->nullable();
            $table->string('brand_name');
            $table->string('primary_color', 7)->default('#3B82F6');
            $table->string('logo_url')->nullable();
            $table->string('favicon_url')->nullable();
            $table->json('custom_css')->nullable();
            $table->json('custom_config')->nullable();
            $table->boolean('custom_api_enabled')->default(false);
            $table->boolean('custom_payment_enabled')->default(false);
            $table->string('stripe_connect_id')->nullable();
            $table->string('status', 20)->default('pending'); // pending, active, suspended
            $table->decimal('monthly_fee', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // ============================================================
        // 8. CDN Distribution Nodes
        // ============================================================
        Schema::create('cdn_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('region'); // us-east, eu-west, ap-southeast, etc.
            $table->string('endpoint'); // CDN endpoint URL
            $table->boolean('is_primary')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('total_downloads')->default(0);
            $table->decimal('bandwidth_gb', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['region', 'active']);
        });

        // ============================================================
        // 9. Product Translations (multi-language SEO)
        // ============================================================
        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5); // en, zh, es, fr, de, ja
            $table->string('name');
            $table->string('slug');
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->json('keywords')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'locale']);
            $table->index(['locale', 'slug']);
        });

        // ============================================================
        // 10. Invoices (auto-generated tax invoices)
        // ============================================================
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('number', 50)->unique();
            $table->string('type', 20)->default('standard'); // standard, credit_note
            $table->string('currency', 3);
            $table->decimal('subtotal', 12, 4);
            $table->decimal('tax_amount', 12, 4)->default(0);
            $table->decimal('total', 12, 4);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->string('tax_type')->nullable();
            $table->string('tax_id')->nullable(); // customer tax ID
            $table->string('billing_name');
            $table->string('billing_email');
            $table->string('billing_address')->nullable();
            $table->string('billing_country', 2)->nullable();
            $table->string('pdf_url')->nullable();
            $table->string('status', 20)->default('issued'); // issued, paid, cancelled
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['created_at']);
        });

        // ============================================================
        // 11. API Layer Configuration
        // ============================================================
        Schema::create('api_access_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // public, partner, enterprise, internal
            $table->string('code', 20)->unique();
            $table->integer('rate_limit_per_minute')->default(60);
            $table->integer('monthly_quota')->default(1000);
            $table->boolean('requires_auth')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->json('allowed_endpoints')->nullable();
            $table->decimal('cost_per_request', 8, 4)->default(0);
            $table->timestamps();
        });

        // ============================================================
        // 12. Upgrade existing tables with V4 fields
        // ============================================================

        // tenants: region + KYC + tax
        if (!Schema::hasColumn('tenants', 'region_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->unsignedBigInteger('region_id')->nullable()->after('total_sales');
                $table->string('kyc_status', 20)->default('pending')->after('region_id');
                $table->string('tax_id')->nullable()->after('kyc_status');
                $table->string('legal_entity_name')->nullable()->after('tax_id');
                $table->string('country_code', 2)->nullable()->after('legal_entity_name');
                $table->string('primary_currency', 3)->default('USD')->after('country_code');
            });
        }

        // users: locale + country + VAT
        if (!Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('locale', 5)->default('en')->after('enterprise_account_id');
                $table->string('country_code', 2)->nullable()->after('locale');
                $table->string('vat_id')->nullable()->after('country_code');
                $table->string('timezone', 50)->nullable()->after('vat_id');
            });
        }

        // orders: multi-currency + tax + invoice
        if (!Schema::hasColumn('orders', 'tax_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'currency')) {
                    $table->string('currency', 3)->default('USD')->after('amount');
                }
                $table->decimal('tax_amount', 12, 4)->default(0)->after('currency');
                $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_amount');
                $table->string('tax_type')->nullable()->after('tax_rate');
                $table->string('invoice_number')->nullable()->after('tax_type');
                $table->unsignedBigInteger('region_id')->nullable()->after('invoice_number');
                $table->string('gateway', 30)->nullable()->after('region_id');
            });
        }

        // products: multi-language flag
        if (!Schema::hasColumn('products', 'supported_locales')) {
            Schema::table('products', function (Blueprint $table) {
                $table->json('supported_locales')->nullable()->after('views');
                $table->string('download_url')->nullable()->after('supported_locales');
                $table->string('cdn_node')->nullable()->after('download_url');
                $table->unsignedBigInteger('file_size_bytes')->default(0)->after('cdn_node');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['supported_locales', 'download_url', 'cdn_node', 'file_size_bytes']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['currency', 'tax_amount', 'tax_rate', 'tax_type', 'invoice_number', 'region_id', 'gateway']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['locale', 'country_code', 'vat_id', 'timezone']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['region_id', 'kyc_status', 'tax_id', 'legal_entity_name', 'country_code', 'primary_currency']);
        });

        Schema::dropIfExists('api_access_tiers');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('product_translations');
        Schema::dropIfExists('cdn_nodes');
        Schema::dropIfExists('white_labels');
        Schema::dropIfExists('privacy_consents');
        Schema::dropIfExists('data_requests');
        Schema::dropIfExists('kyc_verifications');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('regions');
    }
};
