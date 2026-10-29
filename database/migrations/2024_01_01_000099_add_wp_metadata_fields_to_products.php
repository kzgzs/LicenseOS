<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('license')->nullable()->after('version');
            $table->string('license_uri')->nullable()->after('license');
            $table->string('text_domain')->nullable()->after('license_uri');
            $table->string('wp_tags')->nullable()->after('text_domain');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['license', 'license_uri', 'text_domain', 'wp_tags']);
        });
    }
};
