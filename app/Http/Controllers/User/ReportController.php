<?php

namespace App\Http\Controllers\User;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Services\PdfReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ReportController extends Controller
{
    /**
     * Authenticated PDF download. Frontend must not use public storage URLs.
     */
    public function download(Request $request, int $assessment_id): JsonResponse|BinaryFileResponse
    {
        $user = $request->user();
        $assessment = Assessment::findOrFail($assessment_id);

        if ($user->cannot('view', $assessment)) {
            return ApiResponse::error('غير مصرح لك بتحميل هذا التقرير.', 403);
        }

        if (! $assessment->pdf_path) {
            return ApiResponse::error('التقرير غير جاهز بعد. يرجى الانتظار.', 422);
        }

        $fullPath = Storage::disk('public')->path($assessment->pdf_path);

        if (! file_exists($fullPath)) {
            return ApiResponse::error('ملف التقرير غير موجود.', 404);
        }

        return response()->download($fullPath, "humascale_report_{$assessment->id}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function status(Request $request, int $assessment_id): JsonResponse
    {
        $user = $request->user();
        $assessment = Assessment::findOrFail($assessment_id);

        if ($user->cannot('view', $assessment)) {
            return ApiResponse::error('غير مصرح لك بعرض حالة هذا التقرير.', 403);
        }

        return ApiResponse::success([
            'assessment_id' => $assessment->id,
            'pdf_ready' => $assessment->pdf_ready,
            'ai_ready' => $assessment->ai_ready,
            'download_via' => 'GET /api/report/{assessment_id}/download',
        ], 'تم تحميل حالة التقرير.');
    }

    /**
     * Regenerate the PDF synchronously for the assessment owner.
     */
    public function regenerate(Request $request, int $assessment_id, PdfReportService $pdfService): JsonResponse
    {
        $user = $request->user();
        $assessment = Assessment::findOrFail($assessment_id);

        if ($user->cannot('view', $assessment)) {
            return ApiResponse::error('غير مصرح لك بإعادة توليد هذا التقرير.', 403);
        }

        if ($assessment->status !== 'completed') {
            return ApiResponse::error('يجب اكتمال التقييم قبل توليد التقرير.', 422);
        }

        try {
            $path = $pdfService->generate($assessment);
            $assessment->update(['pdf_path' => $path]);

            return ApiResponse::success([
                'assessment_id' => $assessment->id,
                'pdf_ready' => true,
                'pdf_path' => $path,
            ], 'تم تجهيز تقرير PDF بنجاح.');
        } catch (Throwable $e) {
            Log::error('PDF regenerate failed', [
                'assessment_id' => $assessment->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('تعذّر توليد تقرير PDF. حاول مرة أخرى.', 500);
        }
    }
}
