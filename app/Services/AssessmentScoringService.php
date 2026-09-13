<?php

namespace App\Services;

use App\Enums\ReadinessLevel;
use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentPillarResult;
use App\Models\AssessmentVersion;
use App\Models\Pillar;
use App\Models\Question;

class AssessmentScoringService
{
    /**
     * Calculate weighted pillar scores, overall readiness, mark weakest pillars,
     * then generate the action plan. All scores are computed server-side from
     * stored answers — values coming from the frontend are never trusted.
     */
    public function calculate(Assessment $assessment): void
    {
        $answers = AssessmentAnswer::with(['question.pillar'])
            ->where('assessment_id', $assessment->id)
            ->get();

        $questions = $this->versionQuestions($assessment)->get()->keyBy('id');

        // Weighted results per pillar: raw = Σ(score × q_weight), max = Σ(5 × q_weight)
        $perPillar = [];
        foreach ($answers as $answer) {
            $question = $questions->get($answer->question_id);
            if (! $question) {
                continue;
            }

            $pillarId = $question->pillar_id;
            $weight = max($question->weight, 0.01);

            $perPillar[$pillarId]['raw'] = ($perPillar[$pillarId]['raw'] ?? 0) + ($answer->score * $weight);
            $perPillar[$pillarId]['max'] = ($perPillar[$pillarId]['max'] ?? 0) + (5 * $weight);
        }

        // Axis metadata (weights, names) comes from the assessment's version when present.
        $pillarIds = array_keys($perPillar);
        $pillars = Pillar::whereIn('id', $pillarIds)->get()->keyBy('id');

        $pillarResults = [];
        AssessmentPillarResult::where('assessment_id', $assessment->id)->delete();

        foreach ($perPillar as $pillarId => $totals) {
            $pillar = $pillars->get($pillarId);
            $percentage = $totals['max'] > 0
                ? round(($totals['raw'] / $totals['max']) * 100, 2)
                : 0.0;

            AssessmentPillarResult::create([
                'assessment_id'  => $assessment->id,
                'pillar_id'      => $pillarId,
                'pillar_name_ar' => $pillar?->name_ar,
                'pillar_name_en' => $pillar?->name_en,
                'raw_score'      => round($totals['raw'], 2),
                'max_score'      => round($totals['max'], 2),
                'percentage'     => $percentage,
                'is_weak'        => false,
            ]);

            $pillarResults[$pillarId] = $percentage;
        }

        // Overall score = weighted average of pillar percentages using axis weights.
        $axisWeights = [];
        foreach (array_keys($pillarResults) as $pillarId) {
            $axisWeights[$pillarId] = max($pillars->get($pillarId)?->weight ?? 1.0, 0.01);
        }

        $weightSum = array_sum($axisWeights);
        $weightedSum = 0.0;
        foreach ($pillarResults as $pillarId => $percentage) {
            $weightedSum += $percentage * $axisWeights[$pillarId];
        }
        $overallScore = $weightSum > 0 ? round($weightedSum / $weightSum, 2) : 0.0;
        $readinessLevel = ReadinessLevel::fromScore($overallScore);

        // Weakest pillars = lowest 3 by percentage (rule-based, not AI-decided).
        asort($pillarResults);
        $weakPillarIds = array_slice(array_keys($pillarResults), 0, 3);

        AssessmentPillarResult::where('assessment_id', $assessment->id)
            ->whereIn('pillar_id', $weakPillarIds)
            ->update(['is_weak' => true]);

        $user = $assessment->user;
        $assessment->update([
            'overall_score'     => $overallScore,
            'readiness_level'   => $readinessLevel->value,
            'status'            => 'completed',
            'completed_at'      => now(),
            'org_type'          => $user->org_type,
            'org_size'          => $user->org_size,
            'team_member_count' => $user->team_member_count,
        ]);

        Question::whereIn('id', $questions->keys())
            ->update(['used_in_assessments' => true]);

        app(ActionPlanService::class)->generate($assessment->fresh());
    }

    /**
     * Questions belonging to the assessment's questionnaire version.
     * Legacy assessments (created before versioning) fall back to global questions.
     */
    public function versionQuestions(Assessment $assessment)
    {
        if ($assessment->assessment_version_id) {
            return Question::where('assessment_version_id', $assessment->assessment_version_id);
        }

        return Question::whereNull('assessment_version_id');
    }

    /**
     * The currently published version, or null when the platform still runs
     * on the legacy global questionnaire.
     */
    public static function publishedVersion(): ?AssessmentVersion
    {
        return AssessmentVersion::where('status', 'published')->orderByDesc('version_number')->first();
    }
}
