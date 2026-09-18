<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentPillarResult;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $totalUsers       = User::count();
        $totalAssessments = Assessment::count();
        $completedCount   = Assessment::where('status', 'completed')->count();
        $inProgressCount  = Assessment::where('status', 'in_progress')->count();

        // Readiness distribution
        $distribution = Assessment::where('status', 'completed')
            ->select('readiness_level', DB::raw('COUNT(*) as count'))
            ->groupBy('readiness_level')
            ->pluck('count', 'readiness_level')
            ->toArray();

        $readinessDistribution = [];
        foreach (['low', 'medium', 'good'] as $level) {
            $count      = $distribution[$level] ?? 0;
            $percentage = $completedCount > 0 ? round(($count / $completedCount) * 100, 1) : 0;
            $labelAr    = match ($level) {
                'low'    => 'منخفض',
                'medium' => 'متوسط',
                'good'   => 'جيد',
            };

            $readinessDistribution[$level] = [
                'count'      => $count,
                'label_ar'   => $labelAr,
                'percentage' => $percentage,
            ];
        }

        $averageScore = Assessment::where('status', 'completed')->avg('overall_score');

        $assessmentsThisMonth = Assessment::where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $newUsersThisMonth = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Pillar averages — group by snapshot name to avoid version duplicates.
        $pillarAverages = AssessmentPillarResult::query()
            ->select(
                'pillar_name_ar as pillar_ar',
                DB::raw('AVG(percentage) as average_percentage')
            )
            ->whereNotNull('pillar_name_ar')
            ->where('pillar_name_ar', '!=', '')
            ->groupBy('pillar_name_ar')
            ->orderBy('pillar_name_ar')
            ->get()
            ->map(fn ($row) => [
                'pillar_ar'          => $row->pillar_ar,
                'average_percentage' => round((float) $row->average_percentage, 1),
            ]);

        // Most common weak pillar (by snapshot name).
        $mostCommonWeak = AssessmentPillarResult::where('is_weak', true)
            ->select('pillar_name_ar', DB::raw('COUNT(*) as count'))
            ->whereNotNull('pillar_name_ar')
            ->groupBy('pillar_name_ar')
            ->orderByDesc('count')
            ->first();

        $mostCommonWeakAr = $mostCommonWeak?->pillar_name_ar ?? 'غير محدد';

        return ApiResponse::success([
            'total_users'              => $totalUsers,
            'total_assessments'        => $totalAssessments,
            'completed_assessments'    => $completedCount,
            'in_progress_assessments'  => $inProgressCount,
            'readiness_distribution'   => $readinessDistribution,
            'average_overall_score'    => round($averageScore ?? 0, 1),
            'assessments_this_month'   => $assessmentsThisMonth,
            'new_users_this_month'     => $newUsersThisMonth,
            'pillar_averages'          => $pillarAverages,
            'most_common_weak_pillar_ar' => $mostCommonWeakAr,
        ], 'تم تحميل إحصائيات لوحة التحكم.');
    }

    public function users(Request $request): JsonResponse
    {
        $users = User::withCount('assessments')
            ->addSelect([
                'last_assessment_at' => Assessment::select('created_at')
                    ->whereColumn('user_id', 'users.id')
                    ->latest()
                    ->limit(1),
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::paginated($users, 'تم تحميل قائمة المستخدمين.');
    }

    public function pillarAnalytics(): JsonResponse
    {
        $analytics = AssessmentPillarResult::query()
            ->leftJoin('pillars', 'pillars.id', '=', 'assessment_pillar_results.pillar_id')
            ->select(
                DB::raw('MAX(pillars.key) as pillar_key'),
                'assessment_pillar_results.pillar_name_ar as pillar_ar',
                DB::raw('AVG(assessment_pillar_results.percentage) as average_percentage')
            )
            ->whereNotNull('assessment_pillar_results.pillar_name_ar')
            ->where('assessment_pillar_results.pillar_name_ar', '!=', '')
            ->groupBy('assessment_pillar_results.pillar_name_ar')
            ->orderBy('assessment_pillar_results.pillar_name_ar')
            ->get()
            ->map(fn ($row) => [
                'pillar_key'         => $row->pillar_key ?: $row->pillar_ar,
                'pillar_ar'          => $row->pillar_ar,
                'average_percentage' => round((float) $row->average_percentage, 1),
            ]);

        $sorted   = $analytics->sortByDesc('average_percentage');
        $strongest = $sorted->first();
        $weakest   = $sorted->last();

        return ApiResponse::success([
            'pillars'             => $analytics->values(),
            'strongest_pillar_ar' => $strongest['pillar_ar'] ?? 'غير محدد',
            'weakest_pillar_ar'   => $weakest['pillar_ar'] ?? 'غير محدد',
        ], 'تم تحميل تحليلات المحاور.');
    }
}
