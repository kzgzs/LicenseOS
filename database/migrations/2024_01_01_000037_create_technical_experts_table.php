<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('technical_experts')) {
            Schema::create('technical_experts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('specialty')->nullable(); // e.g. WordPress, PHP, Server
                $table->text('description')->nullable();
                $table->string('contact_method')->nullable(); // WeChat/QQ/Email/Telegram
                $table->string('contact_value')->nullable();
                $table->string('avatar')->nullable();
                $table->boolean('active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_experts');
    }
};
