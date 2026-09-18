<?php

namespace App\Http\Controllers\User;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AiAnalysis;
use App\Models\AiChatMessage;
use App\Models\Assessment;
use App\Services\GeminiAssessmentAnalysisService;
use App\Services\GeminiAssessmentChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AiAnalysisController extends Controller
{
    public function store(Request $request, int $id, GeminiAssessmentAnalysisService $analysisService): JsonResponse
    {
        $assessment = $this->ownedCompletedAssessment($request, $id);
        if ($assessment instanceof JsonResponse) {
            return $assessment;
        }

        $forceRetry = $request->boolean('force');

        // Never regenerate when a good (non-fallback) analysis already exists.
        $existing = $this->latestUsable($assessment);
        if ($existing && ! ($forceRetry && $existing->is_fallback)) {
            return ApiResponse::success($this->payload($existing), 'التحليل الذكي جاهز بالفعل.');
        }

        // Allow one more Gemini attempt when the stored analysis is rule-based only.
        if ($existing && $forceRetry && $existing->is_fallback) {
            $existing->update(['status' => 'failed', 'error_message' => 'أُعيدت المحاولة للحصول على تحليل ذكي موسّع.']);
        }

        if ($assessment->user->org_type === null || $assessment->user->org_size === null) {
            return ApiResponse::error(
                'يجب إكمال بيانات المنظمة (النوع والحجم) في ملفك الشخصي قبل إنشاء التوصيات المخصصة.',
                422
            );
        }

        $limiterKey = 'ai-analysis:user:'.$request->user()->id;
        $maxAttempts = (int) config('gemini.analysis_attempts_per_day', 10);

        if (! RateLimiter::attempt($limiterKey, $maxAttempts, fn () => true, 86400)) {
            return ApiResponse::error('تم تجاوز الحد المسموح لطلبات التحليل الذكي لهذا اليوم. حاول غداً.', 429);
        }

        @set_time_limit(120);

        try {
            $analysis = $analysisService->generate($assessment);
        } catch (\Throwable $e) {
            Log::channel('ai')->error('AI analysis generation failed', [
                'assessment_id' => $assessment->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            try {
                $analysis = $analysisService->persistFallback(
                    $assessment,
                    'تعذر إنشاء التحليل الذكي بسبب خطأ في الخادم.'
                );
            } catch (\Throwable $fallbackError) {
                Log::channel('ai')->error('AI analysis fallback persist failed', [
                    'assessment_id' => $assessment->id,
                    'error' => $fallbackError->getMessage(),
                ]);

                return ApiResponse::error(
                    'تعذر إنشاء التحليل الذكي بسبب خطأ في الخادم. يمكنك المحاولة لاحقاً.',
                    503,
                    null,
                    'ai_generation_failed'
                );
            }
        }

        return ApiResponse::success($this->payload($analysis), 'تم إنشاء التحليل الذكي بنجاح.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $assessment = $this->ownedCompletedAssessment($request, $id);
        if ($assessment instanceof JsonResponse) {
            return $assessment;
        }

        $analysis = $this->latestUsable($assessment);

        if (! $analysis) {
            return ApiResponse::success([
                'status'       => 'none',
                'can_generate' => $assessment->user->org_type !== null && $assessment->user->org_size !== null,
            ], 'لم يتم إنشاء تحليل ذكي لهذا التقييم بعد.');
        }

        return ApiResponse::success($this->payload($analysis), 'تم تحميل التحليل الذكي بنجاح.');
    }

    public function chat(Request $request, int $id, GeminiAssessmentChatService $chatService): JsonResponse
    {
        $assessment = $this->ownedCompletedAssessment($request, $id);
        if ($assessment instanceof JsonResponse) {
            return $assessment;
        }

        $maxLength = (int) config('gemini.chat_max_question_length', 500);

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:'.$maxLength],
        ], [
            'message.required' => 'اكتب سؤالك أولاً.',
            'message.min'      => 'السؤال قصير جداً.',
            'message.max'      => "الحد الأقصى لطول السؤال {$maxLength} حرف.",
        ]);

        $limiterKey = 'ai-chat:user:'.$request->user()->id;
        $maxMessages = (int) config('gemini.chat_messages_per_day', 30);

        if (! RateLimiter::attempt($limiterKey, $maxMessages, fn () => true, 86400)) {
            return ApiResponse::error('تم تجاوز الحد المسموح لعدد الرسائل اليوم. حاول غداً.', 429);
        }

        @set_time_limit(120);

        $history = AiChatMessage::where('assessment_id', $assessment->id)
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->all();

        AiChatMessage::create([
            'assessment_id' => $assessment->id,
            'user_id'       => $request->user()->id,
            'role'          => 'user',
            'content'       => $validated['message'],
        ]);

        try {
            $result = $chatService->ask($assessment, $validated['message'], $history);
        } catch (\Throwable $e) {
            Log::channel('ai')->error('AI chat failed', [
                'assessment_id' => $assessment->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'تعذر الرد على سؤالك حالياً بسبب خطأ في الخادم. حاول لاحقاً.',
                503,
                null,
                'ai_chat_failed'
            );
        }

        AiChatMessage::create([
            'assessment_id' => $assessment->id,
            'user_id'       => $request->user()->id,
            'role'          => 'assistant',
            'content'       => $result['answer'],
        ]);

        $messages = AiChatMessage::where('assessment_id', $assessment->id)
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'created_at']);

        return ApiResponse::success([
            'answer'          => $result['answer'],
            'is_fallback'     => $result['fallback'],
            'messages'        => $messages,
            'remaining_today' => RateLimiter::remaining($limiterKey, $maxMessages),
        ], 'تم الرد على سؤالك.');
    }

    public function chatHistory(Request $request, int $id): JsonResponse
    {
        $assessment = $this->ownedCompletedAssessment($request, $id);
        if ($assessment instanceof JsonResponse) {
            return $assessment;
        }

        $messages = AiChatMessage::where('assessment_id', $assessment->id)
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'created_at']);

        return ApiResponse::success(['messages' => $messages], 'تم تحميل المحادثة.');
    }

    private function ownedCompletedAssessment(Request $request, int $id): Assessment|JsonResponse
    {
        $assessment = Assessment::find($id);

        if (! $assessment) {
            return ApiResponse::error('التقييم المطلوب غير موجود.', 404);
        }

        if ($assessment->user_id !== $request->user()->id) {
            return ApiResponse::error('غير مصرح لك بالوصول إلى هذا التقييم.', 403);
        }

        if ($assessment->status !== 'completed') {
            return ApiResponse::error('يجب إكمال التقييم أولاً.', 422);
        }

        return $assessment;
    }

    private function latestUsable(Assessment $assessment): ?AiAnalysis
    {
        return AiAnalysis::where('assessment_id', $assessment->id)
            ->whereIn('status', ['completed', 'approved'])
            ->latest('id')
            ->first();
    }

    private function payload(AiAnalysis $analysis): array
    {
        return [
            'id'               => $analysis->id,
            'status'           => $analysis->status,
            'is_fallback'      => $analysis->is_fallback,
            'model'            => $analysis->model,
            'prompt_version'   => $analysis->prompt_version,
            'analysis'         => $analysis->response_json,
            'error_message'    => $analysis->error_message,
            'created_at'       => $analysis->created_at,
            'disclaimer'       => 'التوصيات استرشادية وليست بديلاً عن التقييم المهني المتخصص.',
        ];
    }
}
