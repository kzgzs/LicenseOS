<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 先删外键约束，再改 nullable，再重建外键
        DB::statement('ALTER TABLE domain_integrity_logs DROP FOREIGN KEY domain_integrity_logs_product_id_foreign');
        DB::statement('ALTER TABLE domain_integrity_logs MODIFY product_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE domain_integrity_logs ADD CONSTRAINT domain_integrity_logs_product_id_foreign FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE domain_integrity_logs DROP FOREIGN KEY domain_integrity_logs_product_id_foreign');
        DB::statement('ALTER TABLE domain_integrity_logs MODIFY product_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE domain_integrity_logs ADD CONSTRAINT domain_integrity_logs_product_id_foreign FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE');
    }
};
