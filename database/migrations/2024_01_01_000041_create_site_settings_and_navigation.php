<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('group')->default('general')->index();
                $table->string('label')->nullable();
                $table->string('type')->default('text');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('navigation_items')) {
            Schema::create('navigation_items', function (Blueprint $table) {
                $table->id();
                $table->string('label');
                $table->string('url');
                $table->string('target')->default('_self');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->foreignId('parent_id')->nullable()->constrained('navigation_items')->nullOnDelete();
                $table->string('icon')->nullable();
                $table->string('group')->default('header')->index();
                $table->timestamps();
            });
        }

        // Seed default navigation items
        if (Schema::hasTable('navigation_items') && \App\Models\NavigationItem::count() === 0) {
            $items = [
                ['label' => 'nav.home', 'url' => '/', 'sort_order' => 1, 'group' => 'header'],
                ['label' => 'nav.marketplace', 'url' => '/marketplace', 'sort_order' => 2, 'group' => 'header'],
                ['label' => 'nav.faq', 'url' => '/faq', 'sort_order' => 3, 'group' => 'header'],
                ['label' => 'nav.contact', 'url' => '/contact', 'sort_order' => 4, 'group' => 'header'],
            ];
            foreach ($items as $item) {
                \App\Models\NavigationItem::create($item);
            }
        }

        // Seed default site settings
        if (Schema::hasTable('site_settings') && \App\Models\SiteSetting::count() === 0) {
            $defaults = [
                ['key' => 'site_name', 'value' => 'Digital Platform', 'group' => 'general', 'label' => 'Site Name', 'type' => 'text'],
                ['key' => 'site_logo', 'value' => '', 'group' => 'general', 'label' => 'Site Logo URL', 'type' => 'text'],
                ['key' => 'site_favicon', 'value' => '', 'group' => 'general', 'label' => 'Favicon URL', 'type' => 'text'],
                ['key' => 'seo_title', 'value' => 'Digital Platform - Developer Monetization Platform', 'group' => 'seo', 'label' => 'SEO Title', 'type' => 'text'],
                ['key' => 'seo_description', 'value' => 'Sell plugins, manage licenses, accept payments, auto-update & onboard developers', 'group' => 'seo', 'label' => 'SEO Description', 'type' => 'text'],
                ['key' => 'seo_keywords', 'value' => 'license management, plugin marketplace, developer monetization', 'group' => 'seo', 'label' => 'SEO Keywords', 'type' => 'text'],
                ['key' => 'footer_copyright', 'value' => '© ' . date('Y') . ' {site_name}. All rights reserved.', 'group' => 'footer', 'label' => 'Copyright Text', 'type' => 'text'],
                ['key' => 'footer_links', 'value' => '', 'group' => 'footer', 'label' => 'Footer Links (JSON)', 'type' => 'text'],
                ['key' => 'contact_email', 'value' => '', 'group' => 'contact', 'label' => 'Contact Email', 'type' => 'text'],
                ['key' => 'contact_phone', 'value' => '', 'group' => 'contact', 'label' => 'Contact Phone', 'type' => 'text'],
                ['key' => 'contact_address', 'value' => '', 'group' => 'contact', 'label' => 'Contact Address', 'type' => 'text'],
            ];
            foreach ($defaults as $item) {
                \App\Models\SiteSetting::create($item);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
        Schema::dropIfExists('site_settings');
    }
};
