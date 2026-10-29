<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Phase 3: Developer Credit Scores ───
        if (!Schema::hasTable('developer_scores')) {
            Schema::create('developer_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('completion_rate', 5, 2)->default(0);  // 0-100%
                $table->unsignedTinyInteger('response_speed')->default(50); // 0-100 score
                $table->decimal('avg_rating', 3, 2)->default(0); // 0-5 stars
                $table->unsignedInteger('project_count')->default(0);
                $table->unsignedInteger('dispute_count')->default(0);
                $table->decimal('total_earned', 12, 2)->default(0);
                $table->decimal('composite_score', 5, 2)->default(0); // 0-100 composite
                $table->string('tier', 20)->default('bronze'); // bronze, silver, gold, platinum, diamond
                $table->timestamp('last_calculated_at')->nullable();
                $table->timestamps();
                $table->unique('user_id');
                $table->index('composite_score');
            });
        }

        // ─── Phase 3: Project Templates ───
        if (!Schema::hasTable('project_templates')) {
            Schema::create('project_templates', function (Blueprint $table) {
                $table->id();
                $table->string('title', 150);
                $table->text('description');
                $table->string('category', 50);
                $table->json('skills')->nullable();
                $table->decimal('budget_min', 10, 2)->nullable();
                $table->decimal('budget_max', 10, 2)->nullable();
                $table->string('currency', 3)->default('USD');
                $table->string('duration', 20)->nullable();
                $table->json('milestones')->nullable(); // [{title, description, amount_pct}]
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('use_count')->default(0);
                $table->timestamps();
                $table->index('category');
            });
        }

        // ─── Phase 3: Team Collaboration ───
        if (Schema::hasTable('projects') && !Schema::hasColumn('projects', 'team_members')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->json('team_members')->nullable()->after('mode');
                // [{user_id, role, joined_at}]
            });
        }

        if (Schema::hasTable('projects') && !Schema::hasColumn('projects', 'max_team_size')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->unsignedTinyInteger('max_team_size')->default(1)->after('team_members');
                // 1 = solo only, 2-10 = team allowed
            });
        }

        // ─── Phase 3: Project Reviews (separate from product reviews) ───
        if (!Schema::hasTable('project_reviews')) {
            Schema::create('project_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('project_contracts')->cascadeOnDelete();
                $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('reviewee_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedTinyInteger('rating'); // 1-5
                $table->text('comment')->nullable();
                $table->unsignedTinyInteger('communication')->default(5); // 1-5
                $table->unsignedTinyInteger('quality')->default(5); // 1-5
                $table->unsignedTinyInteger('timeliness')->default(5); // 1-5
                $table->timestamps();
                $table->unique(['contract_id', 'reviewer_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_reviews');

        if (Schema::hasTable('projects')) {
            if (Schema::hasColumn('projects', 'max_team_size')) {
                Schema::table('projects', function (Blueprint $table) {
                    $table->dropColumn('max_team_size');
                });
            }
            if (Schema::hasColumn('projects', 'team_members')) {
                Schema::table('projects', function (Blueprint $table) {
                    $table->dropColumn('team_members');
                });
            }
        }

        Schema::dropIfExists('project_templates');
        Schema::dropIfExists('developer_scores');
    }
};
