<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Phase 2: Project mode + bounty/assignment ───
        if (Schema::hasTable('projects') && !Schema::hasColumn('projects', 'mode')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('mode', 20)->default('bidding')->after('status');
                // bidding = open bidding, bounty = submission contest, assignment = platform assigned
            });
        }

        // ─── Phase 2: Hourly rate support on bids ───
        if (Schema::hasTable('project_bids') && !Schema::hasColumn('project_bids', 'billing_type')) {
            Schema::table('project_bids', function (Blueprint $table) {
                $table->string('billing_type', 20)->default('fixed')->after('delivery_days');
                // fixed = fixed price, hourly = hourly rate
                $table->decimal('hourly_rate', 10, 2)->nullable()->after('billing_type');
            });
        }

        // ─── Phase 2: Disputes ───
        if (!Schema::hasTable('project_disputes')) {
            Schema::create('project_disputes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('project_contracts')->cascadeOnDelete();
                $table->foreignId('milestone_id')->nullable()->constrained('project_milestones')->nullOnDelete();
                $table->foreignId('raised_by')->constrained('users')->cascadeOnDelete();
                $table->string('reason', 50); // not_delivered, quality_issue, scope_change, no_response, other
                $table->text('description');
                $table->text('evidence')->nullable(); // JSON: file URLs or notes
                $table->string('status', 20)->default('open'); // open, under_review, resolved_client, resolved_developer, resolved_split, closed
                $table->text('admin_note')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('client_refund', 10, 2)->default(0);
                $table->decimal('developer_payout', 10, 2)->default(0);
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index('contract_id');
                $table->index('status');
            });
        }

        // ─── Phase 2: Time Logs ───
        if (!Schema::hasTable('project_time_logs')) {
            Schema::create('project_time_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('project_contracts')->cascadeOnDelete();
                $table->foreignId('milestone_id')->nullable()->constrained('project_milestones')->nullOnDelete();
                $table->foreignId('developer_id')->constrained('users')->cascadeOnDelete();
                $table->text('description')->nullable();
                $table->unsignedInteger('minutes')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->decimal('amount', 10, 2)->default(0); // calculated from hourly_rate * hours
                $table->string('status', 20)->default('pending'); // pending, approved, rejected, billed
                $table->timestamps();
                $table->index(['contract_id', 'developer_id']);
            });
        }

        // ─── Phase 2: Developer Badges ───
        if (!Schema::hasTable('developer_badges')) {
            Schema::create('developer_badges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('badge_type', 30); // verified, top_rated, fast_delivery, specialist, partner
                $table->string('label', 100);
                $table->text('description')->nullable();
                $table->string('icon', 50)->nullable(); // icon class name
                $table->timestamp('awarded_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['user_id', 'badge_type']);
                $table->unique(['user_id', 'badge_type']);
            });
        }

        // ─── Phase 2: Developer Skills (structured) ───
        if (!Schema::hasTable('developer_skills')) {
            Schema::create('developer_skills', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('skill', 50);
                $table->unsignedTinyInteger('level')->default(3); // 1=beginner, 2=intermediate, 3=advanced, 4=expert, 5=master
                $table->boolean('verified')->default(false);
                $table->timestamps();
                $table->unique(['user_id', 'skill']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('developer_skills');
        Schema::dropIfExists('developer_badges');
        Schema::dropIfExists('project_time_logs');
        Schema::dropIfExists('project_disputes');

        if (Schema::hasTable('project_bids') && Schema::hasColumn('project_bids', 'billing_type')) {
            Schema::table('project_bids', function (Blueprint $table) {
                $table->dropColumn(['billing_type', 'hourly_rate']);
            });
        }

        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'mode')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('mode');
            });
        }
    }
};
