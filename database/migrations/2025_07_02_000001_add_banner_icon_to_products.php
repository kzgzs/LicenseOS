<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('banner_url')->nullable()->after('cover_image');
            $table->string('banner_url_2x')->nullable()->after('banner_url');
            $table->string('icon_url')->nullable()->after('banner_url_2x');
            $table->string('icon_url_2x')->nullable()->after('icon_url');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['banner_url', 'banner_url_2x', 'icon_url', 'icon_url_2x']);
        });
    }
};
