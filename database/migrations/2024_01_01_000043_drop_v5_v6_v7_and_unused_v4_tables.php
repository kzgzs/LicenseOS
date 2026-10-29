<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Disable foreign key checks to avoid dependency order issues
        Schema::disableForeignKeyConstraints();

        // V5 AI Platform tables
        Schema::dropIfExists('ai_pricing_logs');
        Schema::dropIfExists('ai_ab_tests');
        Schema::dropIfExists('ai_automation_rules');
        Schema::dropIfExists('ai_market_insights');
        Schema::dropIfExists('ai_recommendations');
        Schema::dropIfExists('ai_support_messages');
        Schema::dropIfExists('ai_support_sessions');
        Schema::dropIfExists('ai_contents');
        Schema::dropIfExists('ai_products');
        Schema::dropIfExists('ai_tasks');
        Schema::dropIfExists('ai_agents');

        // V6 Autonomous Ecosystem tables
        Schema::dropIfExists('agent_actions');
        Schema::dropIfExists('value_distributions');
        Schema::dropIfExists('ecosystem_metrics');
        Schema::dropIfExists('ecosystem_events');
        Schema::dropIfExists('ecosystem_clones');
        Schema::dropIfExists('evolution_cycles');
        Schema::dropIfExists('governance_rules');

        // V7 Life Form tables
        Schema::dropIfExists('signal_streams');
        Schema::dropIfExists('organism_states');
        Schema::dropIfExists('life_cycle_events');
        Schema::dropIfExists('mutations');
        Schema::dropIfExists('dna_profiles');
        Schema::dropIfExists('decisions');

        // V4 Enterprise (removed modules)
        Schema::dropIfExists('cdn_nodes');
        Schema::dropIfExists('privacy_consents');
        Schema::dropIfExists('data_requests');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('white_labels');
        Schema::dropIfExists('kyc_verifications');
        Schema::dropIfExists('enterprise_seats');
        Schema::dropIfExists('enterprise_accounts');

        // Remove deleted columns from tenants table
        if (Schema::hasColumn('tenants', 'kyc_status')) {
            Schema::table('tenants', function ($table) {
                $table->dropColumn('kyc_status');
            });
        }
        if (Schema::hasColumn('tenants', 'ai_enabled')) {
            Schema::table('tenants', function ($table) {
                $table->dropColumn([
                    'ai_enabled', 'ai_credits', 'ai_plan', 'ai_products_generated',
                    'ai_content_generated', 'agent_count', 'ecosystem_revenue', 'ecosystem_tier',
                ]);
            });
        }

        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Tables are permanently removed - no rollback
    }
};
