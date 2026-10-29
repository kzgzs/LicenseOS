<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('technical_experts') && !Schema::hasColumn('technical_experts', 'qr_image')) {
            Schema::table('technical_experts', function (Blueprint $table) {
                $table->string('qr_image')->nullable()->after('contact_value');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('technical_experts') && Schema::hasColumn('technical_experts', 'qr_image')) {
            Schema::table('technical_experts', function (Blueprint $table) {
                $table->dropColumn('qr_image');
            });
        }
    }
};
