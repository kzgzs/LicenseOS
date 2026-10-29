<?php

namespace App\Services;

use App\Models\DeveloperScore;
use App\Models\ProjectContract;
use App\Models\ProjectDispute;
use App\Models\ProjectMessage;
use App\Models\ProjectReview;

class CreditScoreService
{
    /**
     * Calculate credit score for a developer.
     */
    public function calculate(int $userId): DeveloperScore
    {
        // ─── Project Statistics ───
        $totalContracts = ProjectContract::where('developer_id', $userId)->count();
        $completedContracts = ProjectContract::where('developer_id', $userId)
            ->where('status', 'completed')
            ->count();
        $cancelledContracts = ProjectContract::where('developer_id', $userId)
            ->where('status', 'cancelled')
            ->count();
        $disputeCount = ProjectDispute::whereHas('contract', function ($q) use ($userId) {
            $q->where('developer_id', $userId);
        })->count();

        $completionRate = $totalContracts > 0
            ? round(($completedContracts / $totalContracts) * 100, 2)
            : 0;

        $totalEarned = ProjectContract::where('developer_id', $userId)
            ->where('status', 'completed')
            ->sum('developer_income');

        // ─── Average Rating from Project Reviews ───
        $avgRating = ProjectReview::where('reviewee_id', $userId)->avg('rating') ?? 0;

        // ─── Response Speed (average response time in messages) ───
        $responseSpeed = $this->calculateResponseSpeed($userId);

        // ─── Composite Score Calculation ───
        // Weights: completion 30%, rating 25%, response 15%, disputes -10%, earned bonus
        $composite = 0;

        // Completion rate (0-30 points)
        $composite += ($completionRate / 100) * 30;

        // Rating (0-25 points) — rating is 0-5, normalize to 0-25
        $composite += ($avgRating / 5) * 25;

        // Response speed (0-15 points)
        $composite += ($responseSpeed / 100) * 15;

        // Dispute penalty (0 to -15 points)
        if ($totalContracts > 0) {
            $disputeRate = $disputeCount / $totalContracts;
            $composite -= min(15, $disputeRate * 30);
        }

        // Earned bonus (0-15 points) — logarithmic scale
        if ($totalEarned > 0) {
            $earnedScore = min(15, log10(max(1, $totalEarned)) * 5);
            $composite += $earnedScore;
        }

        $composite = max(0, min(100, round($composite, 2)));

        $tier = DeveloperScore::tierFromScore($composite);

        return DeveloperScore::updateOrCreate(
            ['user_id' => $userId],
            [
                'completion_rate' => $completionRate,
                'response_speed' => $responseSpeed,
                'avg_rating' => round($avgRating, 2),
                'project_count' => $completedContracts,
                'dispute_count' => $disputeCount,
                'total_earned' => $totalEarned,
                'composite_score' => $composite,
                'tier' => $tier,
                'last_calculated_at' => now(),
            ]
        );
    }

    /**
     * Calculate response speed score (0-100).
     * Based on average time to first reply in project conversations.
     */
    private function calculateResponseSpeed(int $userId): int
    {
        // Get all projects where this user participated
        $projectIds = ProjectContract::where('developer_id', $userId)->pluck('project_id');

        if ($projectIds->isEmpty()) {
            return 50; // default score for new developers
        }

        // Get average response time
        $avgMinutes = ProjectMessage::whereIn('project_id', $projectIds)
            ->where('sender_id', $userId)
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, LAG(created_at) OVER (PARTITION BY project_id ORDER BY created_at), created_at)) as avg_response')
            ->value('avg_response');

        if (!$avgMinutes || $avgMinutes < 0) {
            return 50; // default for no data
        }

        // Convert to score: <1h = 100, <4h = 80, <24h = 60, <72h = 40, >72h = 20
        if ($avgMinutes < 60) return 100;
        if ($avgMinutes < 240) return 80;
        if ($avgMinutes < 1440) return 60;
        if ($avgMinutes < 4320) return 40;
        return 20;
    }

    /**
     * Batch calculate scores for all developers with contracts.
     */
    public function calculateAll(): int
    {
        $developerIds = ProjectContract::distinct('developer_id')->pluck('developer_id');
        $count = 0;
        foreach ($developerIds as $devId) {
            $this->calculate($devId);
            $count++;
        }
        return $count;
    }
}
