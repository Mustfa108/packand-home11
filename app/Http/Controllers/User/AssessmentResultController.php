<?php

namespace App\Http\Controllers\User;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Services\AssessmentCompareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * User-facing assessment endpoints: list, details, compare, progress.
 * Ownership is enforced — users can only access their own assessments.
 */
class AssessmentResultController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $assessments = Assessment::with('version')
            ->where('user_id', $request->user()->id)
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->paginate(10);

        $data = collect($assessments->items())->map(fn ($assessment) => $this->listItem($assessment))->all();

        return response()->json([
            'success' => true,
            'message' => 'تم تحميل سجل التقييمات بنجاح.',
            'data'    => $data,
            'meta'    => [
                'current_page' => $assessments->currentPage(),
                'last_page'    => $assessments->lastPage(),
                'per_page'     => $assessments->perPage(),
                'total'        => $assessments->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $assessment = Assessment::with(['pillarResults.pillar', 'version'])
            ->find($id);

        if (! $assessment) {
            return ApiResponse::error('التقييم المطلوب غير موجود.', 404);
        }

        if ($assessment->user_id !== $request->user()->id) {
            return ApiResponse::error('غير مصرح لك بالوصول إلى هذا التقييم.', 403);
        }

        if ($assessment->status !== 'completed') {
            return ApiResponse::error('لم يكتمل هذا التقييم بعد.', 422);
        }

        return ApiResponse::success([
            'assessment' => $this->summary($assessment),
            'axes'       => $assessment->pillarResults->map(fn ($r) => [
                'pillar_id'      => $r->pillar_id,
                'pillar_name_ar' => $r->pillar_name_ar ?? $r->pillar?->name_ar,
                'pillar_name_en' => $r->pillar_name_en ?? $r->pillar?->name_en,
                'raw_score'      => $r->raw_score,
                'max_score'      => $r->max_score,
                'percentage'     => $r->percentage,
                'is_weak'        => $r->is_weak,
            ])->values(),
        ], 'تم تحميل التقييم بنجاح.');
    }

    public function compare(Request $request, AssessmentCompareService $compareService): JsonResponse
    {
        $request->validate([
            'first_id'  => ['required', 'integer', 'different:second_id'],
            'second_id' => ['required', 'integer'],
        ], [
            'first_id.required'   => 'يجب تحديد التقييم الأول.',
            'second_id.required'  => 'يجب تحديد التقييم الثاني.',
            'first_id.different'  => 'لا يمكن مقارنة التقييم بنفسه.',
        ]);

        $first = Assessment::with(['pillarResults.pillar', 'version'])->find($request->first_id);
        $second = Assessment::with(['pillarResults.pillar', 'version'])->find($request->second_id);

        if (! $first || ! $second) {
            return ApiResponse::error('أحد التقييمين المطلوبين غير موجود.', 404);
        }

        $resolved = $compareService->resolve($first, $second, $request->user()->id);

        if (! $resolved['valid']) {
            return ApiResponse::error($resolved['message'], 403);
        }

        return ApiResponse::success(
            $compareService->compare($resolved['first'], $resolved['second']),
            'تمت مقارنة التقييمين بنجاح.'
        );
    }

    public function progress(Request $request): JsonResponse
    {
        $assessments = Assessment::with('pillarResults')
            ->where('user_id', $request->user()->id)
            ->where('status', 'completed')
            ->orderBy('completed_at')
            ->orderBy('created_at')
            ->get();

        $points = $assessments->map(function ($assessment, $index) use ($assessments) {
            $previous = $index > 0 ? $assessments[$index - 1] : null;
            $delta = $previous && $assessment->overall_score !== null
                ? round($assessment->overall_score - $previous->overall_score, 2)
                : null;

            return [
                'assessment_id'      => $assessment->id,
                'overall_score'      => $assessment->overall_score,
                'readiness_level'    => $assessment->readiness_level,
                'readiness_level_ar' => $assessment->readiness_level_ar,
                'difference'         => $delta,
                'completed_at'       => $assessment->completed_at ?? $assessment->created_at,
                'version_number'     => $assessment->version?->version_number,
                'axes'               => $assessment->pillarResults->map(fn ($r) => [
                    'pillar_name_ar' => $r->pillar_name_ar ?? $r->pillar?->name_ar,
                    'percentage'     => $r->percentage,
                ])->values(),
            ];
        })->values();

        return ApiResponse::success([
            'points'            => $points,
            'total_assessments' => $points->count(),
            'overall_trend'     => $points->count() >= 2
                ? ($points->last()['overall_score'] - $points->first()['overall_score'])
                : null,
        ], 'تم تحميل تقدم النتائج بنجاح.');
    }

    private function listItem(Assessment $assessment): array
    {
        return [
            'id'                 => $assessment->id,
            'overall_score'      => $assessment->overall_score,
            'readiness_level'    => $assessment->readiness_level,
            'readiness_level_ar' => $assessment->readiness_level_ar,
            'completed_at'       => $assessment->completed_at ?? $assessment->created_at,
            'version_number'     => $assessment->version?->version_number,
            'org_type'           => $assessment->org_type,
            'org_size'           => $assessment->org_size,
        ];
    }

    private function summary(Assessment $assessment): array
    {
        return [
            'id'                 => $assessment->id,
            'overall_score'      => $assessment->overall_score,
            'readiness_level'    => $assessment->readiness_level,
            'readiness_level_ar' => $assessment->readiness_level_ar,
            'readiness_color'    => $assessment->readiness_color,
            'completed_at'       => $assessment->completed_at ?? $assessment->created_at,
            'version_number'     => $assessment->version?->version_number,
            'org_type'           => $assessment->org_type,
            'org_size'           => $assessment->org_size,
            'team_member_count'  => $assessment->team_member_count,
        ];
    }
}
