<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('update_packages', function (Blueprint $table) {
            $table->string('original_file_url')->nullable()->after('file_url');
            $table->boolean('has_original')->default(false)->after('original_file_url');
            $table->json('injection_modules')->nullable()->after('has_original');
        });
    }

    public function down(): void
    {
        Schema::table('update_packages', function (Blueprint $table) {
            $table->dropColumn(['original_file_url', 'has_original', 'injection_modules']);
        });
    }
};
