<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'alipay_account')) {
                $table->string('alipay_account')->nullable()->after('stripe_account_id');
            }
            if (!Schema::hasColumn('tenants', 'wechat_account')) {
                $table->string('wechat_account')->nullable()->after('alipay_account');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['alipay_account', 'wechat_account']);
        });
    }
};
