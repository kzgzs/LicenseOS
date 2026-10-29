<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // === products: pricing tiers + trial ===
        if (!Schema::hasColumn('products', 'pricing_tiers')) {
            Schema::table('products', function (Blueprint $table) {
                $table->json('pricing_tiers')->nullable()->after('price');
                // 6 slots: trial(7/15d), 1m, 3m, 6m, 1y, lifetime
                // Each: {enabled, days, price, label, description, auto_renew}
            });
        }

        // === orders: tier info + domain binding ===
        if (!Schema::hasColumn('orders', 'tier_months')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->integer('tier_months')->default(12)->after('amount');  // 0=lifetime
                $table->integer('tier_days')->nullable()->after('tier_months'); // for trial (7/15 days)
                $table->string('tier_label')->nullable()->after('tier_days');
                $table->string('domain')->nullable()->after('tier_label');
                $table->boolean('is_trial')->default(false)->after('domain');
            });
        }

        // === licenses: trial status support ===
        if (!Schema::hasColumn('licenses', 'is_trial')) {
            Schema::table('licenses', function (Blueprint $table) {
                $table->boolean('is_trial')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'pricing_tiers')) {
                $table->dropColumn('pricing_tiers');
            }
        });
        Schema::table('orders', function (Blueprint $table) {
            foreach (['tier_months', 'tier_days', 'tier_label', 'domain', 'is_trial'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('licenses', function (Blueprint $table) {
            if (Schema::hasColumn('licenses', 'is_trial')) {
                $table->dropColumn('is_trial');
            }
        });
    }
};
