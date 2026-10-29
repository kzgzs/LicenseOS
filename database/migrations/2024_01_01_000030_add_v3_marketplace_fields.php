<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // === New table: categories ===
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')
                ->references('id')->on('categories')
                ->nullOnDelete();
        });

        // === New table: reviews ===
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('rating');
            $table->text('comment');
            $table->boolean('approved')->default(false);
            $table->timestamps();
        });

        // === New table: subscriptions ===
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['active', 'canceled', 'expired'])->default('active');
            $table->timestamp('renew_at')->nullable();
            $table->timestamps();
        });

        // === Alter: products (add marketplace fields) ===
        Schema::table('products', function (Blueprint $table) {
            $table->integer('downloads')->default(0)->after('description');
            $table->decimal('rating', 3, 2)->default(0)->after('downloads');
            $table->json('tags')->nullable()->after('rating');
            $table->unsignedBigInteger('category_id')->nullable()->after('tags');
            $table->json('screenshots')->nullable()->after('category_id');
            $table->string('short_description', 255)->nullable()->after('screenshots');
            $table->boolean('featured')->default(false)->after('short_description');

            $table->foreign('category_id')
                ->references('id')->on('categories')
                ->nullOnDelete();
        });

        // === Alter: payouts (add fee/reference/note) ===
        Schema::table('payouts', function (Blueprint $table) {
            $table->decimal('fee', 10, 2)->default(0)->after('amount');
            $table->string('reference_id')->nullable()->after('status');
            $table->text('note')->nullable()->after('reference_id');
        });

        // === Alter: users (add avatar/bio) ===
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('role');
            $table->text('bio')->nullable()->after('avatar');
        });

        // === Alter: users.role enum (add moderator) ===
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','moderator','developer','customer') DEFAULT 'developer'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','developer','customer') DEFAULT 'developer'");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'bio']);
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn(['fee', 'reference_id', 'note']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['downloads', 'rating', 'tags', 'category_id', 'screenshots', 'short_description', 'featured']);
        });

        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('categories');
    }
};
