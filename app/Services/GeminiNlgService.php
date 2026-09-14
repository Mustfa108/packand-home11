<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\ProjectReview;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiNlgService
{
    private string $apiKey;

    private string $model;

    private string $apiUrl;

    public function __construct(private ?SiteSettingService $settings = null)
    {
        $this->refreshCredentials();
    }

    private function refreshCredentials(): void
    {
        $settings = $this->settings ?? app(SiteSettingService::class);
        $this->apiKey = $settings->geminiApiKey();
        $this->model = $settings->geminiModel();
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";
    }

    /**
     * Generate Arabic analytical summary. Must not invent new scores or priorities.
     */
    public function generateAssessmentSummary(Assessment $assessment): ?string
    {
        $pillarResults = $assessment->pillarResults()->with('pillar')->get();
        $weakPillars = $pillarResults->where('is_weak', true)->pluck('pillar.name_ar')->join(' و');

        $readinessAr = match ($assessment->readiness_level) {
            'low' => 'منخفض',
            'medium' => 'متوسط',
            'good' => 'جيد',
            default => 'غير محدد',
        };

        $pillarLines = $pillarResults->map(fn ($r) => "  - {$r->pillar->name_ar}: {$r->percentage}%"
        )->join("\n");

        $prompt = <<<PROMPT
أنت مستشار تنظيمي محترف متخصص في دعم منظمات المجتمع المدني والمبادرات المجتمعية.

مهم: النتائج والمستوى وأضعف المحاور محددة مسبقاً بنظام قواعد ثابت. اشرحها فقط. لا تغيّر الأرقام ولا تقترح محاور ضعف بديلة ولا تغيّر مستوى الجاهزية.

بناءً على نتائج تقييم الاستعداد للنمو التالية:

المستوى العام: {$readinessAr} ({$assessment->overall_score}%)

نتائج المحاور الستة:
{$pillarLines}

المحاور الأضعف التي تحتاج أولوية تطوير: {$weakPillars}

المطلوب: اكتب ملخصًا تحليليًا احترافيًا من 3 إلى 4 جمل باللغة العربية الفصيحة يوضح:
1. الوضع الراهن للمنظمة بشكل موضوعي
2. نقاط القوة البارزة إن وُجدت
3. أسباب أولوية تطوير المحاور الضعيفة المعطاة أعلاه فقط

الأسلوب: مهني، داعم، موجَّه نحو الحلول، ومحفِّز على العمل.
أجب بالملخص مباشرةً دون أي مقدمة أو تنسيق إضافي.
PROMPT;

        return $this->callGemini($prompt);
    }

    /**
     * Rephrase action texts only. Do not invent KPIs, phases, or priorities.
     *
     * @param  array<int, array{id:int, phase_label_ar:string, pillar_name_ar:string, action_ar:string}>  $actions
     * @return array<int, array{ai_rephrased_ar?: string}>|null
     */
    public function rephraseActionPlanItems(array $actions): ?array
    {
        $actionsList = collect($actions)->map(fn ($a, $i) => ($i + 1).". [{$a['phase_label_ar']} - {$a['pillar_name_ar']}]: {$a['action_ar']}"
        )->join("\n");

        $prompt = <<<PROMPT
أنت مستشار تنظيمي محترف. أعد صياغة الإجراءات التنموية التالية بأسلوب تحفيزي وإنساني وعملي باللغة العربية.

قيود صارمة:
- أعد صياغة نص الإجراء فقط.
- لا تقترح مؤشرات أداء (KPI).
- لا تغيّر المرحلة الزمنية ولا المحور.
- لا تضف إجراءات جديدة.

الإجراءات:
{$actionsList}

أجب بصيغة JSON صالحة فقط، بدون أي نص إضافي أو ```json أو أي تنسيق، على الشكل الآتي:
[
  {"ai_rephrased_ar": "..."},
  {"ai_rephrased_ar": "..."}
]

يجب أن يكون عدد العناصر في المصفوفة مساويًا لعدد الإجراءات المدخلة.
PROMPT;

        $response = $this->callGemini($prompt);

        if (! $response) {
            return null;
        }

        try {
            $clean = trim(preg_replace('/^```json|```$/m', '', $response) ?? $response);

            return json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::channel('ai')->error('Gemini JSON parse failed', [
                'response' => $response,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Evaluate a real project without changing the platform's core readiness
     * assessment. AI only explains the submitted project context and returns
     * structured guidance that can be reviewed by the team.
     */
    public function evaluateProject(array $data): array
    {
        $prompt = <<<PROMPT
أنت مستشار مشاريع اجتماعية. قيّم المشروع التالي بشكل واقعي ومحايد، اعتماداً على المعلومات المدخلة فقط.
لا تخترع أرقاماً أو مصادر أو نتائج ميدانية. أعد JSON صالحاً فقط باللغة العربية على الشكل:
{
  "score": 0,
  "level": "مبكر",
  "summary": "ملخص قصير من 2 إلى 3 جمل",
  "full_summary": "ملخص كامل للمشروع من 5 إلى 8 جمل يوضح الفكرة والمشكلة والمستفيدين والوضع الحالي",
  "features": ["ميزة متوقعة للمشروع", "ميزة متوقعة"],
  "ideal_steps": ["خطوة مثالية 1", "خطوة مثالية 2", "خطوة مثالية 3", "خطوة مثالية 4"],
  "strengths": ["نقطة قوة", "نقطة قوة"],
  "risks": ["مخاطرة قابلة للتحقق", "مخاطرة قابلة للتحقق"],
  "recommendations": ["إجراء عملي", "إجراء عملي", "إجراء عملي"],
  "kpis": ["مؤشر قياس مع طريقة متابعة", "مؤشر قياس"]
}
الدرجة من 0 إلى 100. استخدم مستوى واحداً فقط من: مبكر، قابل للتجربة، جاهز للنمو، متقدم.
اجعل التوصيات وideal_steps قابلة للتنفيذ خلال 30 إلى 90 يوماً، واجعل مؤشرات القياس بسيطة.
features تصف الميزات المتوقعة للمشروع عند نضجه.
ideal_steps هي خارطة طريق مرتبة زمنياً.

بيانات المشروع:
الاسم: {$data['project_name']}
المرحلة: {$data['stage']}
الموقع: {$data['location']}
حجم الفريق: {$data['team_size']}
الميزانية السنوية: {$data['annual_budget']}
الرسالة: {$data['mission']}
المشكلة: {$data['problem']}
الفئة المستفيدة: {$data['beneficiaries']}
الأنشطة: {$data['activities']}
الأثر المتوقع: {$data['impact']}
خطة الاستدامة: {$data['sustainability']}
PROMPT;

        $response = $this->callGemini($prompt, 1536);
        $parsed = $this->parseJsonResponse($response);

        if (is_array($parsed) && isset($parsed['score'], $parsed['summary'])) {
            return $this->normalizeProjectEvaluation($parsed);
        }

        return $this->fallbackProjectEvaluation($data);
    }

    /**
     * Answer a question using the saved project review and recent chat history.
     */
    public function chatAboutProject(ProjectReview $review, string $message, array $history = []): string
    {
        $historyText = collect($history)->take(-8)->map(
            fn ($item) => strtoupper((string) ($item['role'] ?? 'user')).": ".($item['content'] ?? '')
        )->join("\n");
        $strengths = $this->jsonLine($review->ai_strengths);
        $risks = $this->jsonLine($review->ai_risks);
        $recommendations = $this->jsonLine($review->ai_recommendations);

        $prompt = <<<PROMPT
أنت مساعد HumaScale لمشاريع المجتمع المدني. أجب بالعربية الفصيحة بأسلوب عملي ومختصر.
أنت تعمل داخل سياق تقييم محفوظ؛ لا تدّعي تنفيذ إجراءات ولا تخترع بيانات. اربط إجابتك بنقاط القوة والمخاطر والتوصيات الموجودة، واقترح خطوة تالية واحدة واضحة عندما يكون ذلك مناسباً.

المشروع: {$review->project_name}
الدرجة: {$review->ai_score}/100
الملخص: {$review->ai_summary_ar}
نقاط القوة: {$strengths}
المخاطر: {$risks}
التوصيات: {$recommendations}

السياق السابق:
{$historyText}

سؤال المستخدم:
{$message}
PROMPT;

        return $this->callGemini($prompt, 900)
            ?: 'أستطيع مساعدتك في تحويل نتيجة التقييم إلى خطوة عملية. ابدأ بتحديد التوصية الأهم هذا الأسبوع، ثم اكتب مسؤولاً وموعداً ومؤشراً بسيطاً لقياس إنجازها.';
    }

    private function normalizeProjectEvaluation(array $result): array
    {
        $score = max(0, min(100, (int) round((float) ($result['score'] ?? 0))));
        $levels = ['مبكر', 'قابل للتجربة', 'جاهز للنمو', 'متقدم'];
        $level = in_array($result['level'] ?? null, $levels, true)
            ? $result['level']
            : ($score < 40 ? 'مبكر' : ($score < 60 ? 'قابل للتجربة' : ($score < 80 ? 'جاهز للنمو' : 'متقدم')));

        return [
            'score' => $score,
            'level' => $level,
            'summary' => (string) ($result['summary'] ?? ''),
            'full_summary' => (string) ($result['full_summary'] ?? $result['summary'] ?? ''),
            'features' => $this->stringList($result['features'] ?? [], 6),
            'ideal_steps' => $this->stringList($result['ideal_steps'] ?? [], 8),
            'strengths' => $this->stringList($result['strengths'] ?? [], 4),
            'risks' => $this->stringList($result['risks'] ?? [], 4),
            'recommendations' => $this->stringList($result['recommendations'] ?? [], 5),
            'kpis' => $this->stringList($result['kpis'] ?? [], 4),
        ];
    }

    private function fallbackProjectEvaluation(array $data): array
    {
        $fields = ['mission', 'problem', 'beneficiaries', 'activities', 'impact', 'sustainability'];
        $completed = collect($fields)->filter(fn ($field) => trim((string) ($data[$field] ?? '')) !== '')->count();
        $score = min(88, 28 + ($completed * 9) + (! empty($data['team_size']) ? 5 : 0) + (! empty($data['annual_budget']) ? 5 : 0));
        $level = $score < 40 ? 'مبكر' : ($score < 60 ? 'قابل للتجربة' : ($score < 80 ? 'جاهز للنمو' : 'متقدم'));

        return [
            'score' => $score,
            'level' => $level,
            'summary' => 'يعكس التقييم الحالي وضوحاً أولياً في فكرة المشروع، مع حاجة إلى تحويلها إلى نتائج قابلة للقياس وخطة تشغيل محددة.',
            'full_summary' => 'المشروع يعالج احتياجاً مجتمعياً مذكوراً في وصف المشكلة، ويستهدف فئة مستفيدة محددة عبر أنشطة أولية. الجاهزية الحالية تعتمد على اكتمال الفريق والتمويل وخطة الاستدامة. لرفع الجاهزية يلزم التحقق الميداني السريع، تجربة مصغرة، ومؤشرات أثر بسيطة مرتبطة بالمرحلة الحالية للمشروع.',
            'features' => [
                'خدمة أو تدخل واضح للمستفيدين',
                'قابلية القياس عبر مؤشرات بسيطة',
                'إمكانية التوسع الجغرافي عند نضج التشغيل',
            ],
            'ideal_steps' => [
                'توثيق المشكلة والفئة المستفيدة بمقابلات قصيرة',
                'تصميم تجربة أولية لمدة 30 يوماً',
                'تحديد مسؤوليات الفريق والميزانية التشغيلية',
                'قياس نتيجة واحدة رئيسية ومراجعة أسبوعية',
                'بناء شراكة محلية واحدة داعمة للتوسع',
            ],
            'strengths' => ['وجود مشكلة مجتمعية واضحة', 'إمكانية تحويل الأنشطة إلى تدخل قابل للقياس'],
            'risks' => ['عدم كفاية الأدلة على حجم الاحتياج', 'غياب مسؤوليات ومؤشرات زمنية محددة'],
            'recommendations' => ['نفّذ مقابلات قصيرة مع 5 مستفيدين للتحقق من المشكلة', 'حوّل النشاط الرئيسي إلى تجربة صغيرة لمدة 30 يوماً', 'عيّن مسؤولاً لكل نتيجة واكتب موعد المراجعة الأسبوعية'],
            'kpis' => ['عدد المستفيدين الذين أكملوا التجربة', 'نسبة تحقيق النتيجة المستهدفة', 'تكلفة الوصول إلى مستفيد واحد'],
        ];
    }

    private function parseJsonResponse(?string $response): ?array
    {
        if (! $response) {
            return null;
        }

        try {
            $clean = trim(preg_replace('/^```(?:json)?|```$/m', '', $response) ?? $response);
            $decoded = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::channel('ai')->warning('Project evaluation JSON parse failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function stringList(mixed $items, int $limit): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }

    private function jsonLine(mixed $value): string
    {
        return json_encode($value ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    private function callGemini(string $prompt, int $maxOutputTokens = 1024): ?string
    {
        $this->refreshCredentials();

        if ($this->apiKey === '' || $this->apiKey === 'your_gemini_api_key_here') {
            Log::channel('ai')->warning('Gemini API key missing; skipping AI call.');

            return null;
        }

        $startTime = microtime(true);

        try {
            $response = Http::timeout(60)
                ->post("{$this->apiUrl}?key={$this->apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => $maxOutputTokens,
                    ],
                ]);

            $duration = round((microtime(true) - $startTime) * 1000);

            if ($response->failed()) {
                Log::channel('ai')->error('Gemini API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'duration' => "{$duration}ms",
                ]);

                return null;
            }

            $text = $response->json('candidates.0.content.parts.0.text');

            Log::channel('ai')->info('Gemini API call success', [
                'duration' => "{$duration}ms",
                'response_chars' => strlen($text ?? ''),
            ]);

            return $text;
        } catch (\Throwable $e) {
            Log::channel('ai')->error('Gemini API exception', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
