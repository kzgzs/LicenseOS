<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add other_contact field
        if (!Schema::hasColumn('contact_submissions', 'other_contact')) {
            Schema::table('contact_submissions', function (Blueprint $table) {
                $table->string('other_contact')->nullable()->after('phone');
            });
        }

        // Add replied_at field
        if (!Schema::hasColumn('contact_submissions', 'replied_at')) {
            Schema::table('contact_submissions', function (Blueprint $table) {
                $table->timestamp('replied_at')->nullable()->after('admin_reply');
            });
        }

        // Clean up wechat/qq if they were added previously
        if (Schema::hasColumn('contact_submissions', 'wechat')) {
            Schema::table('contact_submissions', function (Blueprint $table) {
                $table->dropColumn(['wechat', 'qq']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contact_submissions', 'other_contact')) {
            Schema::table('contact_submissions', function (Blueprint $table) {
                $table->dropColumn('other_contact');
            });
        }
        if (Schema::hasColumn('contact_submissions', 'replied_at')) {
            Schema::table('contact_submissions', function (Blueprint $table) {
                $table->dropColumn('replied_at');
            });
        }
    }
};
