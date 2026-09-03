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

        $strengths = $pillarResults->where('is_weak', false)
            ->sortByDesc('percentage')
            ->take(2)
            ->map(fn ($r) => [
                'pillar_ar' => $r->pillar->name_ar,
                'pillar_en' => $r->pillar->name_en,
                'percentage' => $r->percentage,
            ])->values();

        $weaknesses = $pillarResults->where('is_weak', true)
            ->sortBy('percentage')
            ->map(fn ($r) => [
                'pillar_ar' => $r->pillar->name_ar,
                'pillar_en' => $r->pillar->name_en,
                'percentage' => $r->percentage,
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
            'radar_chart_data' => $radarChartData,
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
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
