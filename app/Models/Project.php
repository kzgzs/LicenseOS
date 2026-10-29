<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    /**
     * Project categories — broader than Product::TYPES.
     * Covers all types of freelance project work.
     */
    public const CATEGORIES = [
        'website'          => 'Website Development',
        'wp_plugin'        => 'WordPress Plugin',
        'wp_theme'         => 'WordPress Theme',
        'mobile_app'       => 'Mobile App (iOS/Android)',
        'mini_program'     => 'Mini Program',
        'web_app'          => 'Web App / SaaS',
        'desktop_app'      => 'Desktop App',
        'game'             => 'Game Development',
        'chrome_extension' => 'Browser Extension',
        'shopify_app'      => 'Shopify App',
        'laravel_package'  => 'Laravel Package',
        'api_service'      => 'API Service',
        'figma_plugin'     => 'Figma Plugin',
        'ai_ml'            => 'AI / Machine Learning',
        'blockchain'       => 'Blockchain / Web3',
        'data_science'     => 'Data Science',
        'ui_ux_design'     => 'UI/UX Design',
        'graphic_design'   => 'Graphic Design',
        'video_animation'  => 'Video & Animation',
        'seo_marketing'    => 'SEO & Marketing',
        'content_writing'  => 'Content Writing',
        'ecommerce'        => 'E-commerce Setup',
        'devops'           => 'DevOps & Server',
        'source_code'      => 'Source Code',
        'script'           => 'Script / Automation',
        'template'         => 'Template',
        'other'            => 'Other',
    ];

    protected $fillable = [
        'client_id', 'title', 'description', 'category', 'skills',
        'budget_min', 'budget_max', 'currency', 'duration', 'status',
        'bid_count', 'deadline', 'mode', 'team_members', 'max_team_size',
    ];

    protected $casts = [
        'skills' => 'array',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'bid_count' => 'integer',
        'deadline' => 'datetime',
        'team_members' => 'array',
    ];

    // ─── Relationships ───
    public function client() { return $this->belongsTo(User::class, 'client_id'); }
    public function bids() { return $this->hasMany(ProjectBid::class); }
    public function contract() { return $this->hasOne(ProjectContract::class); }
    public function acceptedBid() { return $this->hasOne(ProjectBid::class)->where('status', 'accepted'); }
    public function messages() { return $this->hasMany(ProjectMessage::class); }

    // ─── Status Helpers ───
    public function isOpen(): bool { return $this->status === 'open'; }
    public function isInProgress(): bool { return $this->status === 'in_progress'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    // ─── Mode Helpers ───
    public const MODES = [
        'bidding'    => 'Bidding',
        'bounty'     => 'Bounty / Contest',
        'assignment' => 'Platform Assignment',
    ];

    public function isBidding(): bool { return $this->mode === 'bidding'; }
    public function isBounty(): bool { return $this->mode === 'bounty'; }
    public function isAssignment(): bool { return $this->mode === 'assignment'; }

    public static function modeOptions(): array
    {
        $result = [];
        foreach (self::MODES as $key => $label) {
            $result[$key] = 'project.mode_' . $key;
        }
        return $result;
    }

    // ─── Scopes ───
    public function scopeOpen($query) { return $query->where('status', 'open'); }
    public function scopeInProgress($query) { return $query->where('status', 'in_progress'); }
    public function scopeCompleted($query) { return $query->where('status', 'completed'); }

    // ─── Category Labels (broader than product types) ───
    public static function categories(): array
    {
        $cats = [];
        foreach (self::CATEGORIES as $key => $label) {
            $cats[$key] = 'project_category.' . $key;
        }
        return $cats;
    }

    public static function durations(): array
    {
        return [
            '1-3days' => 'project.duration_1_3days',
            '1-2weeks' => 'project.duration_1_2weeks',
            '2-4weeks' => 'project.duration_2_4weeks',
            '1-3months' => 'project.duration_1_3months',
            '3-6months' => 'project.duration_3_6months',
            '6months+' => 'project.duration_6months_plus',
        ];
    }
}
