<?php

namespace App\Services;

use App\Models\DeveloperSkill;
use App\Models\DeveloperScore;
use App\Models\Project;

class ProjectMatchingService
{
    /**
     * Recommend matching projects for a developer based on skills and history.
     */
    public function recommend(int $userId, int $limit = 10): array
    {
        // Get developer skills
        $skills = DeveloperSkill::where('user_id', $userId)->pluck('skill')->toArray();

        // Get developer's past project categories
        $pastCategories = \App\Models\ProjectContract::where('developer_id', $userId)
            ->with('project')
            ->get()
            ->pluck('project.category')
            ->unique()
            ->filter()
            ->toArray();

        // Find open projects matching skills or categories
        $query = Project::open()
            ->where('client_id', '!=', $userId) // exclude own projects
            ->whereDoesntHave('contract'); // not already contracted

        $projects = $query->get()->map(function ($project) use ($skills, $pastCategories) {
            $score = 0;

            // Skill match (up to 50 points)
            $projectSkills = $project->skills ?? [];
            $matchedSkills = array_intersect($skills, $projectSkills);
            $skillScore = count($projectSkills) > 0
                ? (count($matchedSkills) / count($projectSkills)) * 50
                : 0;
            $score += $skillScore;

            // Category history match (up to 30 points)
            if (in_array($project->category, $pastCategories)) {
                $score += 30;
            }

            // Budget fit (up to 20 points)
            $creditScore = DeveloperScore::where('user_id', auth()->id())->first();
            if ($creditScore && $creditScore->total_earned > 0) {
                $avgBudget = $project->budget_max ?? $project->budget_min ?? 0;
                $score += 20; // baseline
            }

            return [
                'project' => $project,
                'match_score' => round($score, 0),
                'matched_skills' => $matchedSkills,
            ];
        });

        // Sort by match score descending
        return $projects->sortByDesc('match_score')->take($limit)->values()->all();
    }
}
