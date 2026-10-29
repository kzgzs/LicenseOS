<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add affiliate_code to users table
        if (!Schema::hasColumn('users', 'affiliate_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('affiliate_code', 20)->unique()->nullable()->after('role')
                    ->comment('Unique referral code for affiliate tracking');
                $table->decimal('affiliate_balance', 10, 2)->default(0)->after('affiliate_code')
                    ->comment('Current withdrawable affiliate balance');
                $table->decimal('affiliate_total_earned', 10, 2)->default(0)->after('affiliate_balance')
                    ->comment('Total lifetime affiliate earnings');
                $table->integer('affiliate_total_referrals')->default(0)->after('affiliate_total_earned')
                    ->comment('Total number of referred users who purchased');
            });
        }

        // 2. Add referred_by to orders table
        if (!Schema::hasColumn('orders', 'referred_by')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('referred_by')->nullable()->after('user_id')
                    ->comment('User ID of the affiliate who referred this order');
            });
        }

        // 3. Add affiliate fields to commissions table
        if (!Schema::hasColumn('commissions', 'affiliate_user_id')) {
            Schema::table('commissions', function (Blueprint $table) {
                $table->unsignedBigInteger('affiliate_user_id')->nullable()->after('tenant_id')
                    ->comment('User ID of the affiliate who referred this order');
                $table->decimal('affiliate_fee', 10, 2)->default(0)->after('developer_income')
                    ->comment('Commission paid to the affiliate');
                $table->decimal('affiliate_rate', 5, 2)->default(0)->after('affiliate_fee')
                    ->comment('Affiliate commission rate percentage');
                $table->string('developer_tier', 20)->nullable()->after('rate')
                    ->comment('Developer tier at time of sale: standard/silver/gold/platinum');
            });
        }

        // 4. Create affiliate settings table
        if (!Schema::hasTable('affiliate_settings')) {
            Schema::create('affiliate_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'affiliate_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['affiliate_code', 'affiliate_balance', 'affiliate_total_earned', 'affiliate_total_referrals']);
            });
        }
        if (Schema::hasColumn('orders', 'referred_by')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('referred_by');
            });
        }
        if (Schema::hasColumn('commissions', 'affiliate_user_id')) {
            Schema::table('commissions', function (Blueprint $table) {
                $table->dropColumn(['affiliate_user_id', 'affiliate_fee', 'affiliate_rate', 'developer_tier']);
            });
        }
        if (Schema::hasTable('affiliate_settings')) {
            Schema::dropIfExists('affiliate_settings');
        }
    }
};
