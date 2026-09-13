<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AiAnalysis;
use App\Models\Assessment;
use App\Services\AdminAuditLogger;
use App\Services\GeminiAssessmentAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAiAnalysisController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $analyses = AiAnalysis::with(['assessment.user:id,name,organization_name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('id')
            ->paginate(15);

        $data = collect($analyses->items())->map(fn ($analysis) => $this->payload($analysis))->all();

        return response()->json([
            'success' => true,
            'message' => 'تم تحميل تحليلات الذكاء الاصطناعي.',
            'data'    => $data,
            'meta'    => [
                'current_page' => $analyses->currentPage(),
                'last_page'    => $analyses->lastPage(),
                'per_page'     => $analyses->perPage(),
                'total'        => $analyses->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $analysis = AiAnalysis::with(['assessment.user:id,name,organization_name'])->find($id);

        if (! $analysis) {
            return ApiResponse::error('التحليل المطلوب غير موجود.', 404);
        }

        return ApiResponse::success($this->payload($analysis), 'تم تحميل التحليل.');
    }

    public function regenerate(Request $request, int $id, GeminiAssessmentAnalysisService $analysisService): JsonResponse
    {
        $analysis = AiAnalysis::find($id);

        if (! $analysis) {
            return ApiResponse::error('التحليل المطلوب غير موجود.', 404);
        }

        $assessment = Assessment::find($analysis->assessment_id);

        if (! $assessment || $assessment->status !== 'completed') {
            return ApiResponse::error('لا يمكن إعادة توليد التحليل لتقييم غير مكتمل.', 422);
        }

        $newAnalysis = $analysisService->generate($assessment);

        // Mark the previous generation as superseded.
        $analysis->update(['status' => $analysis->status === 'approved' ? 'rejected' : 'failed']);

        AdminAuditLogger::record(
            $request->user()?->id,
            'ai_analysis.regenerated',
            'ai_analysis',
            $newAnalysis->id,
            ['assessment_id' => $assessment->id, 'previous_id' => $analysis->id],
            $request
        );

        return ApiResponse::success($this->payload($newAnalysis->load('assessment.user:id,name,organization_name')), 'تم إعادة توليد التحليل.');
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $analysis = AiAnalysis::find($id);

        if (! $analysis) {
            return ApiResponse::error('التحليل المطلوب غير موجود.', 404);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
        ], [
            'decision.required' => 'يجب تحديد قرار المراجعة.',
            'decision.in'       => 'قرار المراجعة يجب أن يكون اعتماداً أو رفضاً.',
        ]);

        $analysis->update([
            'status'      => $validated['decision'],
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        AdminAuditLogger::record(
            $request->user()?->id,
            'ai_analysis.'.$validated['decision'],
            'ai_analysis',
            $analysis->id,
            null,
            $request
        );

        return ApiResponse::success(
            ['id' => $analysis->id, 'status' => $analysis->status],
            $validated['decision'] === 'approved' ? 'تم اعتماد التحليل.' : 'تم رفض التحليل.'
        );
    }

    private function payload(AiAnalysis $analysis): array
    {
        return [
            'id'             => $analysis->id,
            'assessment_id'  => $analysis->assessment_id,
            'user_name'      => $analysis->assessment?->user?->name,
            'org_name'       => $analysis->assessment?->user?->organization_name,
            'model'          => $analysis->model,
            'prompt_version' => $analysis->prompt_version,
            'status'         => $analysis->status,
            'is_fallback'    => $analysis->is_fallback,
            'error_message'  => $analysis->error_message,
            'duration_ms'    => $analysis->duration_ms,
            'response_json'  => $analysis->response_json,
            'reviewed_by'    => $analysis->reviewed_by,
            'reviewed_at'    => $analysis->reviewed_at,
            'created_at'     => $analysis->created_at,
        ];
    }
}
