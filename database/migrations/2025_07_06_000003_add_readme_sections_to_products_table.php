<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('readme_sections')->nullable()->after('readme_raw')
                ->comment('readme.txt 按段落拆分的结构化数据（description/installation/faq/changelog/screenshots）');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('readme_sections');
        });
    }
};
