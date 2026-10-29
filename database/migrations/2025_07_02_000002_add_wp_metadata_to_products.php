<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('author')->nullable()->after('icon_url_2x');
            $table->string('author_profile')->nullable()->after('author');
            $table->string('plugin_homepage')->nullable()->after('author_profile');
            $table->string('requires_wp')->nullable()->after('plugin_homepage');
            $table->string('tested_up_to')->nullable()->after('requires_wp');
            $table->string('requires_php')->nullable()->after('tested_up_to');
            $table->boolean('license_protection_enabled')->default(false)->after('requires_php');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'author', 'author_profile', 'plugin_homepage',
                'requires_wp', 'tested_up_to', 'requires_php',
                'license_protection_enabled',
            ]);
        });
    }
};
