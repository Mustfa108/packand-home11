<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAssessmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Assessment::with('user')
            ->where('status', 'completed');

        // Filters
        if ($request->filled('readiness_level')) {
            $query->where('readiness_level', $request->readiness_level);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        if (in_array($sortBy, ['created_at', 'overall_score'])) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $assessments = $query->paginate(20);

        $items = collect($assessments->items())->map(fn ($a) => [
            'id' => $a->id,
            'user_name' => $a->user->name,
            'organization_name' => $a->user->organization_name,
            'overall_score' => $a->overall_score,
            'readiness_level' => $a->readiness_level,
            'readiness_level_ar' => $a->readiness_level_ar,
            'status' => $a->status,
            'created_at' => $a->created_at,
        ]);

        // Replace the items in the paginator with the mapped items
        $assessments->setCollection($items);

        return ApiResponse::paginated($assessments, 'تم تحميل قائمة التقييمات.');
    }

    public function show(int $id): JsonResponse
    {
        $assessment = Assessment::with([
            'user',
            'pillarResults.pillar',
            'actionPlan.items.pillar',
        ])->findOrFail($id);

        $pillarResults = $assessment->pillarResults->sortBy('pillar.display_order')->map(fn ($r) => [
            'pillar_id' => $r->pillar_id,
            'pillar_key' => $r->pillar->key,
            'pillar_name_ar' => $r->pillar->name_ar,
            'raw_score' => $r->raw_score,
            'max_score' => $r->max_score,
            'percentage' => $r->percentage,
            'is_weak' => $r->is_weak,
        ])->values();

        $actionPlanData = null;
        if ($assessment->actionPlan) {
            $groupedItems = $assessment->actionPlan->items->groupBy('phase');
            $phases = [];

            foreach (['immediate', 'medium', 'long'] as $phase) {
                $labelAr = match ($phase) {
                    'immediate' => '0-30 يوم',
                    'medium' => '1-3 أشهر',
                    'long' => '3-6 أشهر',
                };

                $items = ($groupedItems[$phase] ?? collect())->map(fn ($item) => [
                    'id' => $item->id,
                    'pillar_name_ar' => $item->pillar->name_ar,
                    'pillar_name_en' => $item->pillar->name_en,
                    'action_ar' => $item->action_ar,
                    'action_en' => $item->action_en,
                    'ai_rephrased_ar' => $item->ai_rephrased_ar,
                    'kpi_ar' => $item->kpi_ar,
                    'kpi_en' => $item->kpi_en,
                    'status' => $item->status?->value ?? $item->status,
                ])->values();

                $phases[$phase] = [
                    'label_ar' => $labelAr,
                    'items' => $items,
                ];
            }

            $actionPlanData = [
                'id' => $assessment->actionPlan->id,
                'ai_intro_ar' => $assessment->actionPlan->ai_intro_ar,
                'phases' => $phases,
            ];
        }

        return ApiResponse::success([
            'assessment' => [
                'id' => $assessment->id,
                'user_name' => $assessment->user->name,
                'organization_name' => $assessment->user->organization_name,
                'status' => $assessment->status,
                'overall_score' => $assessment->overall_score,
                'readiness_level' => $assessment->readiness_level,
                'readiness_level_ar' => $assessment->readiness_level_ar,
                'readiness_level_en' => $assessment->readiness_level_en,
                'readiness_color' => $assessment->readiness_color,
                'ai_summary_ar' => $assessment->ai_summary_ar,
                'ai_ready' => $assessment->ai_ready,
                'pdf_ready' => $assessment->pdf_ready,
                'created_at' => $assessment->created_at,
            ],
            'pillar_results' => $pillarResults,
            'action_plan' => $actionPlanData,
        ], 'تم تحميل تفاصيل التقييم.');
    }
}
