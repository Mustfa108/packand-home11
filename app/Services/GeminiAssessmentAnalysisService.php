<?php

namespace App\Services;

use App\Models\AiAnalysis;
use App\Models\Assessment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends pre-computed assessment results (no PII) to Gemini and expects a
 * structured JSON analysis. Gemini never computes scores — it only explains
 * the rule-based results. JSON structure is validated before saving.
 */
class GeminiAssessmentAnalysisService
{
    private const PROMPT_VERSION = 'v1';

    private string $apiKey;

    private string $model;

    private string $apiUrl;

    private int $timeout;

    public function __construct()
    {
        $this->apiKey = (string) config('gemini.api_key');
        $this->model = (string) config('gemini.model', 'gemini-3.5-flash-lite');
        $this->timeout = (int) config('gemini.timeout', 45);
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";
    }

    /**
     * Clean, PII-free context sent to Gemini.
     */
    public function buildContext(Assessment $assessment): array
    {
        $results = $assessment->pillarResults()->get();

        $axes = $results->map(fn ($r) => [
            'name'       => $r->pillar_name_ar ?? $r->pillar?->name_ar,
            'percentage' => (float) $r->percentage,
            'is_weak'    => (bool) $r->is_weak,
        ])->values();

        $previous = Assessment::where('user_id', $assessment->user_id)
            ->where('status', 'completed')
            ->where('id', '!=', $assessment->id)
            ->where('completed_at', '<', $assessment->completed_at ?? $assessment->created_at)
            ->orderByDesc('completed_at')
            ->first();

        $previousDelta = null;
        if ($previous && $previous->overall_score !== null && $assessment->overall_score !== null) {
            $previousDelta = round($assessment->overall_score - $previous->overall_score, 2);
        }

        $rules = app(RuleBasedRecommendationService::class);

        return [
            'org_type'         => $assessment->org_type_ar,
            'org_size'         => $assessment->org_size_ar,
            'overall_score'    => $assessment->overall_score,
            'readiness_level'  => $assessment->readiness_level_ar,
            'axes'             => $axes->all(),
            'strongest_axes'   => $axes->sortByDesc('percentage')->take(3)->pluck('name')->all(),
            'weakest_axes'     => $axes->sortBy('percentage')->take(3)->pluck('name')->all(),
            'priorities'       => $rules->priorities($assessment),
            'previous_delta'   => $previousDelta,
            'has_previous'     => $previous !== null,
            'previous_score'   => $previous?->overall_score,
        ];
    }

    /**
     * Generate and persist an analysis. Returns the stored AiAnalysis.
     * On Gemini failure, a fallback record (rule-based content) is stored
     * so the basic results page keeps working.
     */
    public function generate(Assessment $assessment): AiAnalysis
    {
        $startedAt = microtime(true);
        $context = $this->buildContext($assessment);
        $rules = app(RuleBasedRecommendationService::class);

        $responseText = $this->callGemini($context);

        $data = $responseText ? $this->parseAndValidate($responseText) : null;

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($data === null) {
            return AiAnalysis::create([
                'assessment_id' => $assessment->id,
                'model'         => $this->model,
                'prompt_version' => self::PROMPT_VERSION,
                'response_json' => $this->fallbackPayload($assessment, $context),
                'status'        => 'completed',
                'is_fallback'   => true,
                'error_message' => $responseText === null
                    ? 'تعذر الاتصال بخدمة الذكاء الاصطناعي.'
                    : 'تعذر التحقق من صحة استجابة الذكاء الاصطناعي.',
                'duration_ms'   => $durationMs,
            ]);
        }

        return AiAnalysis::create([
            'assessment_id'  => $assessment->id,
            'model'          => $this->model,
            'prompt_version' => self::PROMPT_VERSION,
            'response_json'  => $data,
            'status'         => 'completed',
            'is_fallback'    => false,
            'duration_ms'    => $durationMs,
        ]);
    }

    /**
     * Validate the JSON structure returned by Gemini.
     */
    public function parseAndValidate(string $responseText): ?array
    {
        try {
            $clean = trim(preg_replace('/^```(?:json)?|```$/m', '', $responseText) ?? $responseText);
            $decoded = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::channel('ai')->warning('Gemini assessment analysis JSON parse failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! is_array($decoded) || ! isset($decoded['overall_summary'])) {
            return null;
        }

        foreach (['strengths', 'weaknesses', 'recommendations'] as $listKey) {
            if (! isset($decoded[$listKey]) || ! is_array($decoded[$listKey])) {
                return null;
            }
        }

        $strengths = $this->stringList($decoded['strengths']);
        $weaknesses = $this->stringList($decoded['weaknesses']);
        $recommendations = $this->normalizeRecommendations($decoded['recommendations']);

        if ($strengths === [] || $recommendations === []) {
            return null;
        }

        $priorities = [];
        if (isset($decoded['priorities']) && is_array($decoded['priorities'])) {
            foreach ($decoded['priorities'] as $priority) {
                if (is_array($priority) && isset($priority['axis'], $priority['reason'])) {
                    $priorities[] = [
                        'axis'     => (string) $priority['axis'],
                        'priority' => in_array($priority['priority'] ?? null, ['مرتفعة', 'متوسطة', 'منخفضة'], true)
                            ? $priority['priority']
                            : 'متوسطة',
                        'reason'   => (string) $priority['reason'],
                    ];
                }
            }
        }

        return [
            'overall_summary'  => (string) $decoded['overall_summary'],
            'strengths'        => $strengths,
            'weaknesses'       => $weaknesses,
            'priorities'       => array_slice($priorities, 0, 5),
            'recommendations'  => array_slice($recommendations, 0, 5),
            'progress_summary' => isset($decoded['progress_summary'])
                ? (string) $decoded['progress_summary']
                : null,
        ];
    }

    public function fallbackPayload(Assessment $assessment, array $context): array
    {
        $rules = app(RuleBasedRecommendationService::class);

        $weakest = $context['weakest_axes'] ?? [];
        $strengthAxis = $context['strongest_axes'][0] ?? null;

        $progress = null;
        if ($context['has_previous'] && $context['previous_delta'] !== null) {
            $delta = $context['previous_delta'];
            $progress = $delta > 0
                ? sprintf('تحسنت نتيجتك العامة بمقدار %.1f نقطة مقارنة بالتقييم السابق.', $delta)
                : ($delta < 0
                    ? sprintf('تراجعت نتيجتك العامة بمقدار %.1f نقطة مقارنة بالتقييم السابق.', abs($delta))
                    : 'نتيجتك العامة مستقرة مقارنة بالتقييم السابق.');
        }

        return [
            'overall_summary'  => $rules->summary($assessment),
            'strengths'        => $strengthAxis ? ["التميز في محور \"{$strengthAxis}\" مقارنة ببقية المحاور."] : [],
            'weaknesses'       => array_map(
                fn ($axis) => "محور \"{$axis}\" من الأدنى في نتيجتكم ويحتاج إلى خطة تطوير.",
                $weakest
            ),
            'priorities'       => $rules->priorities($assessment),
            'recommendations'  => $rules->build($assessment),
            'progress_summary' => $progress,
        ];
    }

    private function normalizeRecommendations(mixed $items): array
    {
        $normalized = [];

        foreach (is_array($items) ? $items : [] as $item) {
            if (is_string($item) && trim($item) !== '') {
                $normalized[] = [
                    'title'        => mb_substr(trim($item), 0, 120),
                    'description'  => trim($item),
                    'timeframe_ar' => 'قصيرة المدى',
                    'related_axis' => null,
                    'source'       => 'gemini',
                ];

                continue;
            }

            if (is_array($item) && isset($item['title'], $item['description'])) {
                $normalized[] = [
                    'title'        => mb_substr(trim((string) $item['title']), 0, 120),
                    'description'  => trim((string) $item['description']),
                    'timeframe_ar' => in_array($item['timeframe_ar'] ?? null, ['قصيرة المدى', 'متوسطة المدى', 'طويلة المدى'], true)
                        ? $item['timeframe_ar']
                        : 'قصيرة المدى',
                    'related_axis' => isset($item['related_axis']) ? (string) $item['related_axis'] : null,
                    'source'       => 'gemini',
                ];
            }
        }

        return $normalized;
    }

    private function stringList(mixed $items, int $limit = 5): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }

    private function callGemini(array $context): ?string
    {
        if ($this->apiKey === '' || $this->apiKey === 'your_gemini_api_key_here') {
            Log::channel('ai')->warning('Gemini API key missing; assessment analysis falls back to rule-based.');

            return null;
        }

        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $prompt = <<<PROMPT
أنت مستشار تنظيمي متخصص في تطوير منظمات المجتمع المدني والفرق التطوعية.

هذه نتائج تقييم جاهزية محسوبة مسبقاً بنظام قواعد ثابت. مهمتك شرح النتائج وتبسيطها فقط:
- لا تحسب درجات جديدة ولا تغيّر النسب.
- لا تخترع محاور أو بيانات غير موجودة في السياق.
- كل المخرجات باللغة العربية الفصيحة الواضحة والمهنية.

سياق النتائج (بدون أي بيانات شخصية):
{$contextJson}

المطلوب: أجب بصيغة JSON صالحة فقط (بدون ```json أو أي نص إضافي) على الشكل:
{
  "overall_summary": "ملخص مبسط للنتيجة العامة من 3-4 جمل",
  "strengths": ["نقطة قوة 1", "نقطة قوة 2"],
  "weaknesses": ["جانب يحتاج إلى تحسين 1", "جانب يحتاج إلى تحسين 2"],
  "priorities": [{"axis": "اسم المحور", "priority": "مرتفعة|متوسطة|منخفضة", "reason": "سبب الأولوية"}],
  "recommendations": [{"title": "عنوان التوصية", "description": "شرح عملي قابل للتنفيذ", "timeframe_ar": "قصيرة المدى|متوسطة المدى|طويلة المدى", "related_axis": "اسم المحور"}],
  "progress_summary": "مقارنة مختصرة مع التقييم السابق أو وصف الوضع الحالي"
}
القيود: من 3 إلى 5 توصيات عملية، خصّصها حسب نوع وحجم المنظمة في السياق، ورتّب الأولويات بدءاً من المحاور الأضعف.
PROMPT;

        $startTime = microtime(true);

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->apiUrl}?key={$this->apiKey}", [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.6,
                        'maxOutputTokens' => 2048,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            $duration = round((microtime(true) - $startTime) * 1000);

            if ($response->failed()) {
                Log::channel('ai')->error('Gemini assessment analysis request failed', [
                    'status'   => $response->status(),
                    'duration' => "{$duration}ms",
                ]);

                return null;
            }

            $text = $response->json('candidates.0.content.parts.0.text');

            Log::channel('ai')->info('Gemini assessment analysis success', [
                'duration'       => "{$duration}ms",
                'response_chars' => strlen((string) $text),
            ]);

            return $text;
        } catch (\Throwable $e) {
            Log::channel('ai')->error('Gemini assessment analysis exception', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
