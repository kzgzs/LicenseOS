<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectEscrow extends Model
{
    protected $fillable = [
        'contract_id', 'milestone_id', 'amount', 'status',
        'payment_intent_id', 'gateway', 'funded_at', 'released_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'funded_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function contract() { return $this->belongsTo(ProjectContract::class, 'contract_id'); }
    public function milestone() { return $this->belongsTo(ProjectMilestone::class, 'milestone_id'); }

    public function isFunded(): bool { return $this->status === 'funded'; }
    public function isReleased(): bool { return $this->status === 'released'; }
    public function isRefunded(): bool { return $this->status === 'refunded'; }

    /**
     * Release funds to developer: mark as released, credit developer balance.
     */
    public function releaseToDeveloper(): void
    {
        if (!$this->isFunded()) return;

        // If Stripe escrow, capture the PaymentIntent
        if ($this->gateway === 'stripe' && $this->payment_intent_id) {
            $stripe = app(\App\Services\StripeService::class);
            $intent = $stripe->capturePaymentIntent($this->payment_intent_id);
            if (!$intent) {
                \Log::error("Stripe capture failed for escrow #{$this->id}, PI: {$this->payment_intent_id}");
            }
        }

        $this->update([
            'status' => 'released',
            'released_at' => now(),
        ]);

        // Update milestone status
        if ($this->milestone) {
            $this->milestone->update([
                'status' => 'released',
                'released_at' => now(),
            ]);
        }

        // Credit developer (tenant) balance via Commission record
        $contract = $this->contract;
        $developer = $contract->developer;
        $tenant = $developer->tenant;

        if ($tenant) {
            // Create a commission record for this milestone release
            Commission::create([
                'order_id' => null,
                'tenant_id' => $tenant->id,
                'platform_fee' => 0, // already calculated at contract level
                'developer_income' => $this->amount,
                'rate' => 0,
            ]);
        }
    }

    /**
     * Refund escrow back to client (cancel PaymentIntent).
     */
    public function refundToClient(): void
    {
        if (!$this->isFunded()) return;

        if ($this->gateway === 'stripe' && $this->payment_intent_id) {
            $stripe = app(\App\Services\StripeService::class);
            $stripe->cancelPaymentIntent($this->payment_intent_id);
        }

        $this->update([
            'status' => 'refunded',
            'released_at' => now(),
        ]);
    }
}
