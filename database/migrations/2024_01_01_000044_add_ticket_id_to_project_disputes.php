<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_disputes') && !Schema::hasColumn('project_disputes', 'ticket_id')) {
            Schema::table('project_disputes', function (Blueprint $table) {
                $table->foreignId('ticket_id')->nullable()->after('contract_id')->constrained('tickets')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('project_disputes') && Schema::hasColumn('project_disputes', 'ticket_id')) {
            Schema::table('project_disputes', function (Blueprint $table) {
                $table->dropForeign(['ticket_id']);
                $table->dropColumn('ticket_id');
            });
        }
    }
};
