<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activations', function (Blueprint $table) {
            $table->boolean('is_tampered')->default(false)->after('last_check');
            $table->timestamp('tampered_at')->nullable()->after('is_tampered');
        });
    }

    public function down(): void
    {
        Schema::table('activations', function (Blueprint $table) {
            $table->dropColumn(['is_tampered', 'tampered_at']);
        });
    }
};
