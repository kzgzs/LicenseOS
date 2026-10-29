<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Client Enterprise Verification ───
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'account_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('account_type', 20)->default('personal')->after('bio');
                // personal = individual, company = enterprise
                $table->string('company_name', 150)->nullable()->after('account_type');
                $table->string('business_license')->nullable()->after('company_name');
                // file path to uploaded license
                $table->string('verification_status', 20)->default('none')->after('business_license');
                // none, pending, approved, rejected
                $table->text('verification_note')->nullable()->after('verification_status');
                $table->timestamp('verified_at')->nullable()->after('verification_note');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'account_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn([
                    'account_type', 'company_name', 'business_license',
                    'verification_status', 'verification_note', 'verified_at',
                ]);
            });
        }
    }
};
