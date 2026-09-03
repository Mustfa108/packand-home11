<?php

namespace App\Services;

use App\Enums\ReadinessLevel;
use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentPillarResult;

class AssessmentScoringService
{
    /**
     * Calculate pillar scores, overall readiness, mark weakest pillars, then generate action plan.
     */
    public function calculate(Assessment $assessment): void
    {
        $answers = AssessmentAnswer::with('question.pillar')
            ->where('assessment_id', $assessment->id)
            ->get();

        $grouped = $answers->groupBy('question.pillar_id');

        $pillarResults = [];
        foreach ($grouped as $pillarId => $pillarAnswers) {
            $rawScore = $pillarAnswers->sum('score');
            $maxScore = 15; // 3 questions × max 5
            $percentage = round(($rawScore / $maxScore) * 100, 2);

            AssessmentPillarResult::updateOrCreate(
                ['assessment_id' => $assessment->id, 'pillar_id' => $pillarId],
                [
                    'raw_score' => $rawScore,
                    'max_score' => $maxScore,
                    'percentage' => $percentage,
                    'is_weak' => false,
                ]
            );

            $pillarResults[$pillarId] = $percentage;
        }

        $overallScore = round(array_sum($pillarResults) / max(count($pillarResults), 1), 2);
        $readinessLevel = ReadinessLevel::fromScore($overallScore);

        asort($pillarResults);
        $weakPillarIds = array_slice(array_keys($pillarResults), 0, 3);

        AssessmentPillarResult::where('assessment_id', $assessment->id)
            ->whereIn('pillar_id', $weakPillarIds)
            ->update(['is_weak' => true]);

        $assessment->update([
            'overall_score' => $overallScore,
            'readiness_level' => $readinessLevel->value,
            'status' => 'completed',
        ]);

        app(ActionPlanService::class)->generate($assessment);
    }
}
