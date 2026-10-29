<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id');
            $table->string('version');
            $table->string('file_url');
            $table->text('changelog')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_packages');
    }
};
