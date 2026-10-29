<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Map old ENUM values to new VARCHAR values
        DB::statement("UPDATE products SET type = 'wp_theme' WHERE type = 'theme'");
        DB::statement("UPDATE products SET type = 'web_app' WHERE type = 'saas'");
        DB::statement("UPDATE products SET type = 'desktop_app' WHERE type = 'app'");

        // Alter column from ENUM to VARCHAR(50) to support new types
        DB::statement("ALTER TABLE products MODIFY COLUMN type VARCHAR(50) NOT NULL DEFAULT 'wp_plugin'");
    }

    public function down(): void
    {
        // Map new values back to old ENUM values
        DB::statement("UPDATE products SET type = 'theme' WHERE type = 'wp_theme'");
        DB::statement("UPDATE products SET type = 'saas' WHERE type = 'web_app'");
        DB::statement("UPDATE products SET type = 'app' WHERE type = 'desktop_app'");

        // Restore ENUM (only original 4 values)
        DB::statement("ALTER TABLE products MODIFY COLUMN type ENUM('wp_plugin','theme','saas','app') NOT NULL DEFAULT 'wp_plugin'");
    }
};
