<?php

namespace App\Services;

use App\Models\Assessment;

class AssessmentCompareService
{
    /**
     * Compare two completed assessments belonging to the same organization/user.
     *
     * @return array{valid:bool, message?:string, first?:Assessment, second?:Assessment}
     */
    public function resolve(Assessment $first, Assessment $second, int $userId): array
    {
        foreach ([$first, $second] as $assessment) {
            if ($assessment->user_id !== $userId || $assessment->status !== 'completed') {
                return ['valid' => false, 'message' => 'غير مصرح لك بمقارنة هذه التقييمات.'];
            }
        }

        return ['valid' => true, 'first' => $first, 'second' => $second];
    }

    /**
     * Build the full comparison payload: overall scores, delta and percentage,
     * per-axis results for both assessments, and improved/declined/unchanged axes.
     */
    public function compare(Assessment $first, Assessment $second): array
    {
        $firstResults = $this->resultsByPillar($first);
        $secondResults = $this->resultsByPillar($second);

        $axes = [];
        $improved = [];
        $declined = [];
        $unchanged = [];

        foreach ($secondResults as $pillarId => $secondRow) {
            $firstPercentage = $firstResults[$pillarId]['percentage'] ?? null;
            $secondPercentage = $secondRow['percentage'];
            $nameAr = $secondRow['name_ar'];
            $nameEn = $secondRow['name_en'];

            if ($firstPercentage === null) {
                $difference = null;
                $change = 'new';
                $changePercent = null;
            } else {
                $difference = round($secondPercentage - $firstPercentage, 2);
                $changePercent = $firstPercentage > 0
                    ? round(($difference / $firstPercentage) * 100, 2)
                    : null;
                $change = match (true) {
                    $difference > 0.005  => 'improved',
                    $difference < -0.005 => 'declined',
                    default              => 'unchanged',
                };
            }

            $row = [
                'pillar_id'         => $pillarId,
                'pillar_name_ar'    => $nameAr,
                'pillar_name_en'    => $nameEn,
                'first_percentage'  => $firstPercentage,
                'second_percentage' => $secondPercentage,
                'difference'        => $difference,
                'difference_percent' => $changePercent,
                'change'            => $change,
            ];

            $axes[] = $row;

            if ($change === 'improved') {
                $improved[] = $row;
            } elseif ($change === 'declined') {
                $declined[] = $row;
            } elseif ($change === 'unchanged') {
                $unchanged[] = $row;
            }
        }

        usort($improved, fn ($a, $b) => $b['difference'] <=> $a['difference']);
        usort($declined, fn ($a, $b) => $a['difference'] <=> $b['difference']);

        $overallDelta = round(($second->overall_score ?? 0) - ($first->overall_score ?? 0), 2);
        $overallDeltaPercent = ($first->overall_score ?? 0) > 0
            ? round($overallDelta / $first->overall_score * 100, 2)
            : null;

        return [
            'first'           => $this->summary($first),
            'second'          => $this->summary($second),
            'overall'         => [
                'first_score'        => $first->overall_score,
                'second_score'       => $second->overall_score,
                'difference'         => $overallDelta,
                'difference_percent' => $overallDeltaPercent,
                'direction'          => match (true) {
                    $overallDelta > 0.005  => 'improved',
                    $overallDelta < -0.005 => 'declined',
                    default                => 'unchanged',
                },
            ],
            'axes'            => $axes,
            'improved_axes'   => $improved,
            'declined_axes'   => $declined,
            'unchanged_axes'  => $unchanged,
            'most_improved'   => $improved[0] ?? null,
            'most_declined'   => $declined[0] ?? null,
        ];
    }

    private function resultsByPillar(Assessment $assessment): array
    {
        return $assessment->pillarResults->mapWithKeys(fn ($r) => [
            $r->pillar_id => [
                'name_ar'     => $r->pillar_name_ar ?? $r->pillar?->name_ar,
                'name_en'     => $r->pillar_name_en ?? $r->pillar?->name_en,
                'percentage'  => (float) $r->percentage,
            ],
        ])->all();
    }

    private function summary(Assessment $assessment): array
    {
        return [
            'id'                 => $assessment->id,
            'overall_score'      => $assessment->overall_score,
            'readiness_level'    => $assessment->readiness_level,
            'readiness_level_ar' => $assessment->readiness_level_ar,
            'completed_at'       => $assessment->completed_at ?? $assessment->created_at,
            'version_number'     => $assessment->version?->version_number,
        ];
    }
}
