<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_replies', function (Blueprint $table) {
            $table->foreignId('review_parent_id')->nullable()->after('review_id')->constrained('reviews')->nullOnDelete();
            $table->enum('reply_type', ['developer', 'platform'])->default('platform')->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('review_replies', function (Blueprint $table) {
            $table->dropForeign(['review_parent_id']);
            $table->dropColumn(['review_parent_id', 'reply_type']);
        });
    }
};
