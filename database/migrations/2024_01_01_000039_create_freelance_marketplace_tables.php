<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Projects (需求发布) ───
        if (!Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
                $table->string('title', 200);
                $table->text('description');
                $table->string('category', 50)->default('development'); // development, design, marketing, other
                $table->json('skills')->nullable(); // ["WordPress","PHP","Server"]
                $table->decimal('budget_min', 10, 2)->nullable();
                $table->decimal('budget_max', 10, 2)->nullable();
                $table->string('currency', 10)->default('USD');
                $table->string('duration', 30)->nullable(); // 1-3days, 1-2weeks, 1-3months, etc.
                $table->string('status', 20)->default('open'); // open, in_progress, completed, cancelled, closed
                $table->unsignedInteger('bid_count')->default(0);
                $table->timestamp('deadline')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
                $table->index('client_id');
            });
        }

        // ─── Project Bids (竞标) ───
        if (!Schema::hasTable('project_bids')) {
            Schema::create('project_bids', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('developer_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('amount', 10, 2);
                $table->string('currency', 10)->default('USD');
                $table->text('proposal'); // 投标方案说明
                $table->unsignedInteger('delivery_days')->nullable();
                $table->string('status', 20)->default('pending'); // pending, accepted, rejected, withdrawn
                $table->timestamps();
                $table->unique(['project_id', 'developer_id']); // 每人每个项目只能投一次
                $table->index('developer_id');
            });
        }

        // ─── Project Contracts (合同/签约) ───
        if (!Schema::hasTable('project_contracts')) {
            Schema::create('project_contracts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('developer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('bid_id')->nullable()->constrained('project_bids')->nullOnDelete();
                $table->decimal('total_amount', 10, 2);
                $table->string('currency', 10)->default('USD');
                $table->decimal('platform_fee', 10, 2)->default(0); // 平台佣金
                $table->decimal('developer_income', 10, 2)->default(0); // 开发者收入
                $table->string('status', 20)->default('active'); // active, completed, cancelled, disputed
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['client_id', 'status']);
                $table->index(['developer_id', 'status']);
            });
        }

        // ─── Project Milestones (里程碑) ───
        if (!Schema::hasTable('project_milestones')) {
            Schema::create('project_milestones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('project_contracts')->cascadeOnDelete();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->decimal('amount', 10, 2);
                $table->string('status', 20)->default('pending'); // pending, funded, submitted, approved, disputed, released
                $table->timestamp('funded_at')->nullable(); // 客户充值到托管的时间
                $table->timestamp('submitted_at')->nullable(); // 开发者提交交付的时间
                $table->timestamp('approved_at')->nullable(); // 客户验收通过的时间
                $table->timestamp('released_at')->nullable(); // 资金释放时间
                $table->text('deliverable_note')->nullable(); // 开发者交付说明
                $table->text('revision_note')->nullable(); // 客户修改要求
                $table->timestamps();
                $table->index('contract_id');
            });
        }

        // ─── Project Escrows (资金托管记录) ───
        if (!Schema::hasTable('project_escrows')) {
            Schema::create('project_escrows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('project_contracts')->cascadeOnDelete();
                $table->foreignId('milestone_id')->nullable()->constrained('project_milestones')->nullOnDelete();
                $table->decimal('amount', 10, 2);
                $table->string('status', 20)->default('funded'); // funded, released, refunded
                $table->string('payment_intent_id')->nullable(); // Stripe payment intent
                $table->string('gateway', 20)->default('stripe'); // stripe, wechat, alipay
                $table->timestamp('funded_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();
                $table->index('contract_id');
                $table->index('milestone_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_escrows');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('project_contracts');
        Schema::dropIfExists('project_bids');
        Schema::dropIfExists('projects');
    }
};
