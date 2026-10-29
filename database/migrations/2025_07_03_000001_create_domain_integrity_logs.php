<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_integrity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('license_id')->nullable()->constrained()->onDelete('set null');
            $table->string('domain');
            $table->string('code_hash')->nullable();
            $table->string('expected_hash');
            $table->string('api_key_hash')->nullable();
            $table->boolean('is_valid')->default(true);
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->boolean('is_tampered')->default(false)->after('status');
            $table->timestamp('tampered_at')->nullable()->after('is_tampered');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn(['is_tampered', 'tampered_at']);
        });
        Schema::dropIfExists('domain_integrity_logs');
    }
};
