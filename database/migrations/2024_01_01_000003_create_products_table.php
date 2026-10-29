<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('name');
            $table->string('slug');
            $table->enum('type', ['wp_plugin', 'theme', 'saas', 'app'])->default('wp_plugin');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('version')->default('1.0.0');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
