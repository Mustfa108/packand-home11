<?php

namespace App\Http\Controllers\User;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $latestAssessment = Assessment::with('pillarResults.pillar')
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->latest()
            ->first();

        if (! $latestAssessment) {
            return ApiResponse::success([
                'has_assessment' => false,
                'message' => 'لم تقم بأي تقييم بعد. ابدأ تقييمك الأول الآن!',
                'expansion_areas_count' => $user->expansionAreas()->count(),
                'preferences' => [
                    'locale' => $user->locale ?? 'ar',
                    'theme' => $user->theme ?? 'system',
                ],
            ], 'تم تحميل لوحة المعلومات.');
        }

        $pillarResults = $latestAssessment->pillarResults->sortBy('pillar.display_order');

        $radarChartData = $pillarResults->map(fn ($r) => [
            'pillar_key' => $r->pillar->key,
            'pillar_ar' => $r->pillar->name_ar,
            'pillar_en' => $r->pillar->name_en,
            'percentage' => $r->percentage,
        ])->values();

        $sortedByScore = $pillarResults->sortByDesc('percentage')->values();
        $strongest = $sortedByScore->first();
        $weakest = $sortedByScore->last();

        $previousAssessment = Assessment::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('id', '!=', $latestAssessment->id)
            ->where(function ($query) use ($latestAssessment) {
                $query->where('completed_at', '<', $latestAssessment->completed_at ?? $latestAssessment->created_at)
                    ->orWhereNull('completed_at')
                    ->where('created_at', '<', $latestAssessment->created_at);
            })
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->first();

        $improvement = null;
        if ($previousAssessment && $previousAssessment->overall_score !== null && $latestAssessment->overall_score !== null) {
            $improvement = [
                'previous_score' => $previousAssessment->overall_score,
                'difference'     => round($latestAssessment->overall_score - $previousAssessment->overall_score, 2),
                'direction'      => $latestAssessment->overall_score > $previousAssessment->overall_score
                    ? 'improved'
                    : ($latestAssessment->overall_score < $previousAssessment->overall_score ? 'declined' : 'unchanged'),
                'previous_assessment_id' => $previousAssessment->id,
            ];
        }

        // Score over time (for the trend chart).
        $trendPoints = Assessment::where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('completed_at')
            ->orderBy('created_at')
            ->get(['id', 'overall_score', 'completed_at', 'created_at'])
            ->map(fn ($a) => [
                'assessment_id' => $a->id,
                'overall_score' => $a->overall_score,
                'date'          => $a->completed_at ?? $a->created_at,
            ])->values();

        $totalAssessments = Assessment::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        $expansionAreasCount = $user->expansionAreas()->count();

        return ApiResponse::success([
            'has_assessment' => true,
            'latest_assessment' => [
                'id' => $latestAssessment->id,
                'overall_score' => $latestAssessment->overall_score,
                'readiness_level' => $latestAssessment->readiness_level,
                'readiness_level_ar' => $latestAssessment->readiness_level_ar,
                'readiness_level_en' => $latestAssessment->readiness_level_en,
                'ai_ready' => $latestAssessment->ai_ready,
                'pdf_ready' => $latestAssessment->pdf_ready,
                'created_at' => $latestAssessment->created_at,
            ],
            'improvement' => $improvement,
            'strongest_pillar' => $strongest ? [
                'pillar_ar' => $strongest->pillar_name_ar ?? $strongest->pillar->name_ar,
                'pillar_en' => $strongest->pillar_name_en ?? $strongest->pillar->name_en,
                'percentage' => $strongest->percentage,
            ] : null,
            'weakest_pillar' => $weakest ? [
                'pillar_ar' => $weakest->pillar_name_ar ?? $weakest->pillar->name_ar,
                'pillar_en' => $weakest->pillar_name_en ?? $weakest->pillar->name_en,
                'percentage' => $weakest->percentage,
            ] : null,
            'score_trend' => $trendPoints,
            'radar_chart_data' => $radarChartData,
            'strengths' => $pillarResults->where('is_weak', false)
                ->sortByDesc('percentage')
                ->take(2)
                ->map(fn ($r) => [
                    'pillar_ar' => $r->pillar->name_ar,
                    'pillar_en' => $r->pillar->name_en,
                    'percentage' => $r->percentage,
                ])->values(),
            'weaknesses' => $pillarResults->where('is_weak', true)
                ->sortBy('percentage')
                ->map(fn ($r) => [
                    'pillar_ar' => $r->pillar->name_ar,
                    'pillar_en' => $r->pillar->name_en,
                    'percentage' => $r->percentage,
                ])->values(),
            'ai_summary_ar' => $latestAssessment->ai_summary_ar,
            'total_assessments' => $totalAssessments,
            'expansion_areas_count' => $expansionAreasCount,
            'preferences' => [
                'locale' => $user->locale ?? 'ar',
                'theme' => $user->theme ?? 'system',
            ],
        ], 'تم تحميل لوحة المعلومات بنجاح.');
    }
}
