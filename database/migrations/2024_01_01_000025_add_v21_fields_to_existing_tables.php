<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->after('status');
            $table->decimal('balance', 12, 2)->default(0)->after('stripe_account_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('product_id');
            $table->string('payment_intent_id')->nullable()->after('payment_method');
            $table->string('checkout_session_id')->nullable()->after('payment_intent_id');
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('order_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'payment_intent_id', 'checkout_session_id']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['stripe_account_id', 'balance']);
        });
    }
};
