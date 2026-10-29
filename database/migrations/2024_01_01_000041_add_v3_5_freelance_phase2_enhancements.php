<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Auto-approval countdown on milestones ───
        if (Schema::hasTable('project_milestones') && !Schema::hasColumn('project_milestones', 'auto_approve_at')) {
            Schema::table('project_milestones', function (Blueprint $table) {
                $table->timestamp('auto_approve_at')->nullable()->after('submitted_at');
            });
        }

        // ─── Deliverable file upload on milestones ───
        if (Schema::hasTable('project_milestones') && !Schema::hasColumn('project_milestones', 'deliverable_files')) {
            Schema::table('project_milestones', function (Blueprint $table) {
                $table->json('deliverable_files')->nullable()->after('deliverable_note');
            });
        }

        // ─── Project Messages (private chat) ───
        if (!Schema::hasTable('project_messages')) {
            Schema::create('project_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('contract_id')->nullable()->constrained('project_contracts')->nullOnDelete();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->string('attachment')->nullable(); // single file path
                $table->boolean('is_read')->default(false);
                $table->timestamps();
                $table->index(['project_id', 'created_at']);
                $table->index(['contract_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_messages');

        if (Schema::hasTable('project_milestones')) {
            if (Schema::hasColumn('project_milestones', 'deliverable_files')) {
                Schema::table('project_milestones', function (Blueprint $table) {
                    $table->dropColumn('deliverable_files');
                });
            }
            if (Schema::hasColumn('project_milestones', 'auto_approve_at')) {
                Schema::table('project_milestones', function (Blueprint $table) {
                    $table->dropColumn('auto_approve_at');
                });
            }
        }
    }
};
