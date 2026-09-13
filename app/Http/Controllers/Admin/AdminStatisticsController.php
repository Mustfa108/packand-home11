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

class AdminStatisticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', 'in:civil_society,volunteer_team,startup,other'],
            'size' => ['nullable', 'in:small,medium,large'],
        ]);

        $from = $validated['from'] ?? null;
        $to = isset($validated['to']) ? \Illuminate\Support\Carbon::parse($validated['to'])->endOfDay() : null;
        $type = $validated['type'] ?? null;
        $size = $validated['size'] ?? null;

        // Assessments filtered by period and org profile snapshots.
        $assessmentsQuery = Assessment::where('status', 'completed');
        $this->applyFilters($assessmentsQuery, $from, $to, $type, $size);

        $completedCount = (clone $assessmentsQuery)->count();

        $averageReadiness = round((clone $assessmentsQuery)->avg('overall_score') ?? 0, 2);

        // Readiness distribution.
        $readinessRows = (clone $assessmentsQuery)
            ->select('readiness_level', DB::raw('COUNT(*) as count'))
            ->groupBy('readiness_level')
            ->pluck('count', 'readiness_level');

        $readinessDistribution = [];
        foreach (['low', 'medium', 'good'] as $level) {
            $count = (int) ($readinessRows[$level] ?? 0);
            $readinessDistribution[$level] = [
                'count'      => $count,
                'label_ar'   => match ($level) {
                    'low'    => 'منخفض',
                    'medium' => 'متوسط',
                    'good'   => 'جيد',
                },
                'percentage' => $completedCount > 0 ? round($count / $completedCount * 100, 1) : 0,
            ];
        }

        // Average score per axis.
        $axisAveragesQuery = AssessmentPillarResult::query()
            ->join('assessments', 'assessments.id', '=', 'assessment_pillar_results.assessment_id')
            ->where('assessments.status', 'completed');
        $this->applyFilters($axisAveragesQuery, $from, $to, $type, $size, 'assessments.');

        $axisAverages = Pillar::orderBy('display_order')->get()->map(function ($pillar) use ($axisAveragesQuery) {
            $avg = (clone $axisAveragesQuery)->where('assessment_pillar_results.pillar_id', $pillar->id)->avg('assessment_pillar_results.percentage');

            return [
                'pillar_id'          => $pillar->id,
                'pillar_name_ar'     => $pillar->name_ar,
                'average_percentage' => round($avg ?? 0, 1),
            ];
        });

        $sortedAxes = $axisAverages->sortByDesc('average_percentage')->values();

        // Organization distribution by type and size (snapshot at assessment time).
        $orgTypeDistribution = (clone $assessmentsQuery)
            ->select('org_type', DB::raw('COUNT(*) as count'))
            ->whereNotNull('org_type')
            ->groupBy('org_type')
            ->pluck('count', 'org_type');

        $orgSizeDistribution = (clone $assessmentsQuery)
            ->select('org_size', DB::raw('COUNT(*) as count'))
            ->whereNotNull('org_size')
            ->groupBy('org_size')
            ->pluck('count', 'org_size');

        // Assessments over time, grouped by month within the period.
        // substr(...,1,7) works on both MySQL and SQLite date strings.
        $timeQuery = (clone $assessmentsQuery)
            ->select(
                DB::raw("substr(COALESCE(completed_at, created_at), 1, 7) as period"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('period')
            ->orderBy('period');

        $timeSeries = $timeQuery->get()->map(fn ($row) => [
            'period' => $row->period,
            'count'  => (int) $row->count,
        ])->values();

        $unfilteredCompleted = Assessment::where('status', 'completed')->count();

        return ApiResponse::success([
            'filters' => [
                'from' => $from,
                'to'   => $validated['to'] ?? null,
                'type' => $type,
                'size' => $size,
            ],
            'total_users'              => User::count(),
            'total_organizations'      => User::whereNotNull('organization_name')->where('organization_name', '!=', '')->count(),
            'completed_assessments'    => $completedCount,
            'total_completed_all_time' => $unfilteredCompleted,
            'assessments_in_period'    => $completedCount,
            'average_readiness'        => $averageReadiness,
            'readiness_distribution'   => $readinessDistribution,
            'axis_averages'            => $axisAverages->values(),
            'strongest_axis'           => $sortedAxes->first(),
            'weakest_axis'             => $sortedAxes->last(),
            'org_type_distribution'    => $orgTypeDistribution,
            'org_size_distribution'    => $orgSizeDistribution,
            'assessments_over_time'    => $timeSeries,
        ], 'تم تحميل الإحصائيات بنجاح.');
    }

    private function applyFilters($query, ?string $from, $to, ?string $type, ?string $size, string $prefix = ''): void
    {
        $dateColumn = $prefix.'COALESCE(completed_at, created_at)';

        if ($from) {
            $query->whereRaw("{$dateColumn} >= ?", [$from]);
        }

        if ($to) {
            $query->whereRaw("{$dateColumn} <= ?", [$to]);
        }

        if ($type) {
            $query->where($prefix.'org_type', $type);
        }

        if ($size) {
            $query->where($prefix.'org_size', $size);
        }
    }
}
