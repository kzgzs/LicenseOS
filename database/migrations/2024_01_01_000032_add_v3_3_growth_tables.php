<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // === SEO fields on products ===
        Schema::table('products', function (Blueprint $table) {
            $table->string('meta_title', 255)->nullable()->after('featured');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
            $table->json('keywords')->nullable()->after('meta_description');
            $table->unsignedBigInteger('views')->default(0)->after('keywords');
        });

        // === Coupons table ===
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 10, 2);
            $table->decimal('min_purchase', 10, 2)->default(0);
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('product_id')
                ->references('id')->on('products')
                ->nullOnDelete();
        });

        // === Product views (conversion tracking) ===
        Schema::create('product_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id', 64)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('referrer')->nullable();
            $table->string('source', 50)->default('direct'); // direct, google, social, referral
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['session_id', 'created_at']);
        });

        // === Notification log (sent emails/webhooks tracking) ===
        Schema::create('notification_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('channel', 20); // email, webhook, in_app
            $table->string('type', 100); // license_expiring, new_version, promo
            $table->string('subject');
            $table->text('body')->nullable();
            $table->boolean('sent')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_log');
        Schema::dropIfExists('product_views');
        Schema::dropIfExists('coupons');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description', 'keywords', 'views']);
        });
    }
};
