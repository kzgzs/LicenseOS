<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'wechat_openid')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('wechat_openid', 128)->unique()->nullable()->after('verified_at');
                $table->string('wechat_unionid', 128)->nullable()->index()->after('wechat_openid');
                $table->string('wechat_nickname', 100)->nullable()->after('wechat_unionid');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'wechat_openid')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['wechat_openid', 'wechat_unionid', 'wechat_nickname']);
            });
        }
    }
};
