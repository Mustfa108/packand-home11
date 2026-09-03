<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentPillarResult;
use App\Models\Pillar;
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

        // Pillar averages
        $pillarAverages = Pillar::orderBy('display_order')
            ->get()
            ->map(function ($pillar) {
                $avg = AssessmentPillarResult::where('pillar_id', $pillar->id)->avg('percentage');
                return [
                    'pillar_ar'          => $pillar->name_ar,
                    'average_percentage' => round($avg ?? 0, 1),
                ];
            });

        // Most common weak pillar
        $mostCommonWeak = AssessmentPillarResult::where('is_weak', true)
            ->select('pillar_id', DB::raw('COUNT(*) as count'))
            ->groupBy('pillar_id')
            ->orderByDesc('count')
            ->first();

        $mostCommonWeakAr = $mostCommonWeak
            ? Pillar::find($mostCommonWeak->pillar_id)?->name_ar ?? 'غير محدد'
            : 'غير محدد';

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
        $pillars = Pillar::orderBy('display_order')->get();

        $analytics = $pillars->map(function ($pillar) {
            $avg = AssessmentPillarResult::where('pillar_id', $pillar->id)->avg('percentage');
            return [
                'pillar_key'         => $pillar->key,
                'pillar_ar'          => $pillar->name_ar,
                'average_percentage' => round($avg ?? 0, 1),
            ];
        });

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
