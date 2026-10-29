<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // === 1. Ads system (banner / featured / search_boost) ===
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type', 30); // banner, featured, search_boost
            $table->decimal('bid_price', 10, 2)->default(0);
            $table->decimal('daily_budget', 10, 2)->nullable();
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->date('start_at');
            $table->date('end_at');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending, active, paused, expired
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->string('target_keywords')->nullable();
            $table->string('banner_image')->nullable();
            $table->string('landing_url')->nullable();
            $table->timestamps();

            $table->index(['status', 'type', 'start_at', 'end_at']);
            $table->index(['product_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });

        // === 2. Enterprise accounts (B2B) ===
        Schema::create('enterprise_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('company_name');
            $table->string('industry', 100)->nullable();
            $table->integer('seat_limit')->default(5);
            $table->decimal('monthly_fee', 10, 2)->default(0);
            $table->string('plan', 30)->default('starter'); // starter, business, enterprise
            $table->string('status', 20)->default('active'); // active, suspended, cancelled
            $table->boolean('sla_enabled')->default(false);
            $table->boolean('custom_plugins')->default(false);
            $table->boolean('dedicated_support')->default(false);
            $table->json('features')->nullable(); // enabled features list
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'plan']);
        });

        // === 3. Enterprise seats (team management) ===
        Schema::create('enterprise_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enterprise_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30)->default('member'); // owner, admin, member
            $table->timestamps();

            $table->unique(['enterprise_account_id', 'user_id']);
        });

        // === 4. API usage log (quota tracking) ===
        Schema::create('api_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint');
            $table->string('method', 10);
            $table->integer('response_code')->default(200);
            $table->integer('response_time_ms')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['api_key_id', 'created_at']);
            $table->index(['endpoint', 'created_at']);
        });

        // === 5. Platform tiers (developer/user levels) ===
        Schema::create('platform_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "New Developer", "Growth Developer", "Top Developer"
            $table->string('type', 20); // developer, user
            $table->integer('level')->default(0);
            $table->decimal('commission_discount', 5, 2)->default(0); // % reduction
            $table->integer('api_quota_bonus')->default(0);
            $table->boolean('priority_ranking')->default(false);
            $table->boolean('featured_eligible')->default(false);
            $table->boolean('promotion_access')->default(false);
            $table->json('benefits')->nullable();
            $table->json('requirements')->nullable(); // criteria to reach this tier
            $table->timestamps();

            $table->unique(['type', 'level']);
        });

        // === 6. Revenue records (multi-source income tracking) ===
        Schema::create('revenue_records', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30); // commission, subscription, ad, api, enterprise, custom
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->morphs('recordable'); // polymorphic: ad_id, enterprise_id, api_key_id, etc.
            $table->date('period_date'); // for daily aggregation
            $table->timestamps();

            $table->index(['source', 'period_date']);
        });

        // === 7. Upgrade api_keys table with quota & billing ===
        Schema::table('api_keys', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->integer('quota')->default(1000)->after('active');
            $table->integer('used_quota')->default(0)->after('quota');
            $table->decimal('overage_rate', 8, 4)->default(0.01)->after('used_quota'); // cost per extra call
            $table->string('tier', 20)->default('free')->after('overage_rate'); // free, basic, pro, enterprise
            $table->timestamp('quota_reset_at')->nullable()->after('tier');
        });

        // === 8. Upgrade users table with tier & membership ===
        Schema::table('users', function (Blueprint $table) {
            $table->string('tier', 30)->default('basic')->after('bio'); // basic, member, premium
            $table->string('developer_level', 30)->nullable()->after('tier'); // new, growth, top, partner
            $table->unsignedBigInteger('platform_tier_id')->nullable()->after('developer_level');
            $table->boolean('is_member')->default(false)->after('platform_tier_id');
            $table->timestamp('membership_expires_at')->nullable()->after('is_member');
            $table->unsignedBigInteger('enterprise_account_id')->nullable()->after('membership_expires_at');
        });

        // === 9. Upgrade tenants table with tier fields ===
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('developer_level', 30)->default('new')->after('balance'); // new, growth, top, partner
            $table->unsignedBigInteger('total_revenue')->default(0)->after('developer_level');
            $table->unsignedBigInteger('total_sales')->default(0)->after('total_revenue');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['developer_level', 'total_revenue', 'total_sales']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'tier', 'developer_level', 'platform_tier_id',
                'is_member', 'membership_expires_at', 'enterprise_account_id',
            ]);
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'quota', 'used_quota', 'overage_rate', 'tier', 'quota_reset_at']);
        });

        Schema::dropIfExists('revenue_records');
        Schema::dropIfExists('platform_tiers');
        Schema::dropIfExists('api_usage_logs');
        Schema::dropIfExists('enterprise_seats');
        Schema::dropIfExists('enterprise_accounts');
        Schema::dropIfExists('ads');
    }
};
