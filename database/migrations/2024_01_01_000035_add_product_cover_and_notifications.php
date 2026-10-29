<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add cover_image to products table
        if (!Schema::hasColumn('products', 'cover_image')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('cover_image')->nullable()->after('short_description');
            });
        }

        // 2. Laravel built-in notifications table
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'cover_image')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('cover_image');
            });
        }

        Schema::dropIfExists('notifications');
    }
};
