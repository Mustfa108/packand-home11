<?php

namespace App\Services;

use App\Models\Assessment;

/**
 * Rule-based recommendation engine. Guarantees the user always sees practical
 * recommendations even when Gemini is unavailable. Recommendations are derived
 * only from stored assessment results — never from AI output.
 */
class RuleBasedRecommendationService
{
    private const AXIS_ACTIONS = [
        'team' => [
            'title'        => 'تقوية هيكل الفريق',
            'description'  => 'وثّق الأدوار والمسؤوليات لكل عضو، وحدد فجوات المهارات الأساسية، وابدأ بجلسة تدريب واحدة هذا الشهر.',
            'timeframe_ar' => 'قصيرة المدى',
        ],
        'funding' => [
            'title'        => 'تنويع مصادر التمويل',
            'description'  => 'أعدّ قائمة بثلاثة مصادر تمويل بديلة (منح، تبرعات فردية، شراكات)، وجهّز ملف تعريفي موجز للمشروع.',
            'timeframe_ar' => 'قصيرة المدى',
        ],
        'impact' => [
            'title'        => 'قياس الأثر بشكل منظم',
            'description'  => 'اختر مؤشري قياس بسيطين لكل نشاط، واجمع البيانات شهرياً، وأعد تقرير أثر مصور كل ثلاثة أشهر.',
            'timeframe_ar' => 'متوسطة المدى',
        ],
        'partnerships' => [
            'title'        => 'بناء شراكات مجتمعية',
            'description'  => 'حدد خمس مؤسسات محلية ذات اهتمام مشترك، وتواصل معها بعرض شراكة محدد النطاق والمنفعة المتبادلة.',
            'timeframe_ar' => 'متوسطة المدى',
        ],
        'technology' => [
            'title'        => 'تحسين الأدوات الرقمية',
            'description'  => 'ابدأ بأدوات مجانية لإدارة المهام والمستندات، ودرّب الفريق عليها، ثم توسّع تدريجياً بحسب الحاجة.',
            'timeframe_ar' => 'قصيرة المدى',
        ],
        'sustainability' => [
            'title'        => 'خطة استدامة أولية',
            'description'  => 'اكتب خطة استدامة من صفحة واحدة تغطي مصادر الدخل، والاحتياجات التشغيلية، وسيناريو الطوارئ لستة أشهر.',
            'timeframe_ar' => 'طويلة المدى',
        ],
    ];

    private const ORG_SIZE_HINTS = [
        'small'  => 'بما أن فريقكم صغير، ركّزوا على إجراء واحد بسيط وقابل للتنفيذ في كل مرة بدلاً من خطط واسعة.',
        'medium' => 'استفيدوا من حجم فريقكم بتوزيع مسؤولية واضحة لكل توصية على عضو محدد.',
        'large'  => 'مع حجم فريقكم، اعتمدوا على التفويض واللجان الصغيرة لتتبع تنفيذ التوصيات.',
    ];

    public function build(Assessment $assessment): array
    {
        $results = $assessment->pillarResults()->with('pillar')->get()->sortBy('percentage');
        $recommendations = [];

        foreach ($results->take(3) as $result) {
            $key = $result->pillar?->key;
            $template = self::AXIS_ACTIONS[$key] ?? null;

            if (! $template) {
                continue;
            }

            $hint = self::ORG_SIZE_HINTS[$assessment->org_size] ?? '';

            $recommendations[] = [
                'title'        => $template['title'],
                'description'  => trim($template['description'].' '.$hint),
                'timeframe_ar' => $template['timeframe_ar'],
                'related_axis' => $result->pillar->name_ar,
                'priority'     => $result->percentage < 40 ? 'مرتفعة' : 'متوسطة',
                'source'       => 'rule_based',
            ];
        }

        if (count($recommendations) < 3) {
            $recommendations[] = [
                'title'        => 'إعادة التقييم بعد التنفيذ',
                'description'  => 'طبّق أول توصية لمدة شهر كامل، ثم أعد التقييم لقياس التحسن الفعلي في نتائجك.',
                'timeframe_ar' => 'قصيرة المدى',
                'related_axis' => 'عام',
                'priority'     => 'مرتفعة',
                'source'       => 'rule_based',
            ];
        }

        return array_slice($recommendations, 0, 5);
    }

    /**
     * Default summary built from the strongest and weakest axes.
     */
    public function summary(Assessment $assessment): string
    {
        $results = $assessment->pillarResults()->with('pillar')->get();
        $strongest = $results->sortByDesc('percentage')->first();
        $weakest = $results->sortBy('percentage')->first();

        return sprintf(
            'حصلت منظمتكم على نتيجة عامة %.1f%% بمستوى جاهزية %s. أقوى محاوركم هو "%s" بنسبة %.0f%%، بينما يحتاج "%s" إلى أولوية التطوير بنسبة %.0f%%. التوصيات أدناه استرشادية ومبنية على نتائجكم الفعلية.',
            $assessment->overall_score ?? 0,
            $assessment->readiness_level_ar,
            $strongest?->pillar_name_ar ?? $strongest?->pillar?->name_ar ?? 'غير محدد',
            $strongest?->percentage ?? 0,
            $weakest?->pillar_name_ar ?? $weakest?->pillar?->name_ar ?? 'غير محدد',
            $weakest?->percentage ?? 0,
        );
    }

    /**
     * Priority list derived from the weakest axes.
     */
    public function priorities(Assessment $assessment): array
    {
        return $assessment->pillarResults()->with('pillar')->get()
            ->sortBy('percentage')
            ->take(3)
            ->map(fn ($result) => [
                'axis'     => $result->pillar_name_ar ?? $result->pillar?->name_ar,
                'priority' => $result->percentage < 40 ? 'مرتفعة' : ($result->percentage < 60 ? 'متوسطة' : 'منخفضة'),
                'reason'   => sprintf('نسبة هذا المحور %.0f%% وهي من الأدنى في نتيجتكم.', $result->percentage),
            ])
            ->values()
            ->all();
    }
}
