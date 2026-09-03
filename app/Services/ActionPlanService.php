<?php

namespace App\Services;

use App\Enums\ActionItemStatus;
use App\Models\ActionPlan;
use App\Models\ActionPlanItem;
use App\Models\Assessment;
use App\Models\AssessmentPillarResult;

class ActionPlanService
{
    /**
     * Fixed bilingual rules. AI may only rephrase action text later — never KPIs or phases.
     *
     * @var array<string, array<string, array{immediate: array{ar: string, en: string, kpi_ar: string, kpi_en: string}, medium: array{ar: string, en: string, kpi_ar: string, kpi_en: string}, long: array{ar: string, en: string, kpi_ar: string, kpi_en: string}}>>
     */
    private array $rules = [
        'team' => [
            'immediate' => [
                'ar' => 'حدِّد الأدوار والمسؤوليات لكل عضو في الفريق ووثِّقها في وثيقة مشتركة خلال أسبوع.',
                'en' => 'Define roles and responsibilities for every team member and document them in a shared file within one week.',
                'kpi_ar' => 'وثيقة أدوار مكتملة وموقّعة من جميع الأعضاء خلال 14 يوماً.',
                'kpi_en' => 'A completed roles document signed by all members within 14 days.',
            ],
            'medium' => [
                'ar' => 'نفِّذ جلسة تدريبية لتعزيز مهارات التواصل والتنسيق بين أعضاء الفريق.',
                'en' => 'Run a training session to strengthen communication and coordination across the team.',
                'kpi_ar' => 'جلسة تدريبية واحدة على الأقل بحضور 80% من الأعضاء خلال 60 يوماً.',
                'kpi_en' => 'At least one training session with 80% attendance within 60 days.',
            ],
            'long' => [
                'ar' => 'ضع خطة تطوير بشري سنوية شاملة تتضمن مسارات نمو واضحة لكل عضو.',
                'en' => 'Create an annual people-development plan with clear growth paths for each member.',
                'kpi_ar' => 'خطة تطوير بشري معتمدة تغطي جميع الأعضاء خلال 6 أشهر.',
                'kpi_en' => 'An approved people-development plan covering all members within 6 months.',
            ],
        ],
        'funding' => [
            'immediate' => [
                'ar' => 'أعدَّ قائمة شاملة بمصادر التمويل المتاحة المحلية والدولية وقيِّم مدى أهلية منظمتك لكل منها.',
                'en' => 'Prepare a full list of local and international funding sources and assess your eligibility for each.',
                'kpi_ar' => 'قائمة مقيّمة لـ10 مصادر تمويل على الأقل خلال 30 يوماً.',
                'kpi_en' => 'An assessed list of at least 10 funding sources within 30 days.',
            ],
            'medium' => [
                'ar' => 'طوِّر مقترح مشروع متكاملاً وقدِّمه على الأقل لجهتَين مانحتَين مناسبتَين.',
                'en' => 'Develop a complete project proposal and submit it to at least two suitable funders.',
                'kpi_ar' => 'تقديم مقترحين تمويليين خلال 90 يوماً.',
                'kpi_en' => 'Two funding proposals submitted within 90 days.',
            ],
            'long' => [
                'ar' => 'طوِّر استراتيجية تمويل متنوعة تشمل التبرعات، الرسوم، المنح، والشراكات المدرِّة.',
                'en' => 'Build a diversified funding strategy covering donations, fees, grants, and revenue partnerships.',
                'kpi_ar' => 'استراتيجية تمويل معتمدة بمصدرين نشطين على الأقل خلال 6 أشهر.',
                'kpi_en' => 'An approved funding strategy with at least two active sources within 6 months.',
            ],
        ],
        'impact' => [
            'immediate' => [
                'ar' => 'حدِّد مؤشرات أثر واضحة وقابلة للقياس لكل نشاط رئيسي تقوم به منظمتك.',
                'en' => 'Define clear, measurable impact indicators for every major activity.',
                'kpi_ar' => 'مؤشر أثر واحد على الأقل لكل نشاط رئيسي خلال 30 يوماً.',
                'kpi_en' => 'At least one impact indicator per major activity within 30 days.',
            ],
            'medium' => [
                'ar' => 'أنشئ نظامًا منتظمًا لجمع البيانات وقياس الأثر الفعلي على المستفيدين.',
                'en' => 'Set up a regular system to collect data and measure real impact on beneficiaries.',
                'kpi_ar' => 'دورة جمع بيانات شهرية مفعّلة خلال 90 يوماً.',
                'kpi_en' => 'A monthly data-collection cycle active within 90 days.',
            ],
            'long' => [
                'ar' => 'طوِّر تقريرًا سنويًا للأثر يُشارَك مع الداعمين والشركاء والمستفيدين.',
                'en' => 'Produce an annual impact report shared with supporters, partners, and beneficiaries.',
                'kpi_ar' => 'تقرير أثر سنوي منشور ومشارك خلال 6 أشهر.',
                'kpi_en' => 'An annual impact report published and shared within 6 months.',
            ],
        ],
        'partnerships' => [
            'immediate' => [
                'ar' => 'أعدَّ خريطة بالشركاء المحتملين ذوي الصلة بعملك وحدِّد أولويات التواصل معهم.',
                'en' => 'Map potential partners relevant to your work and prioritize outreach.',
                'kpi_ar' => 'خريطة شركاء بـ15 جهة وأولويات تواصل خلال 30 يوماً.',
                'kpi_en' => 'A partner map of 15 organizations with outreach priorities within 30 days.',
            ],
            'medium' => [
                'ar' => 'أبرم اتفاقيتَي تعاون رسميتَين على الأقل مع شركاء استراتيجيين.',
                'en' => 'Sign at least two formal cooperation agreements with strategic partners.',
                'kpi_ar' => 'اتفاقيتا تعاون موقّعتان خلال 90 يوماً.',
                'kpi_en' => 'Two signed cooperation agreements within 90 days.',
            ],
            'long' => [
                'ar' => 'طوِّر شبكة شراكات استراتيجية متنوعة تشمل المستويَين المحلي والإقليمي.',
                'en' => 'Grow a diversified strategic partner network at local and regional levels.',
                'kpi_ar' => 'شبكة نشطة من 5 شركاء على الأقل خلال 6 أشهر.',
                'kpi_en' => 'An active network of at least 5 partners within 6 months.',
            ],
        ],
        'technology' => [
            'immediate' => [
                'ar' => 'قيِّم الأدوات الرقمية التي يستخدمها فريقك وحدِّد الفجوات التقنية الأكثر إلحاحاً.',
                'en' => 'Assess the digital tools your team uses and identify the most urgent tech gaps.',
                'kpi_ar' => 'تقرير فجوات تقنية بأولويات واضحة خلال 30 يوماً.',
                'kpi_en' => 'A tech-gap report with clear priorities within 30 days.',
            ],
            'medium' => [
                'ar' => 'تبنَّ منصة إدارة مشاريع رقمية موحدة (مثل Notion أو Trello) لتنظيم عمل الفريق.',
                'en' => 'Adopt one shared digital project-management platform (e.g. Notion or Trello).',
                'kpi_ar' => 'منصة موحّدة مستخدمة من 100% من الفريق خلال 90 يوماً.',
                'kpi_en' => 'One shared platform used by 100% of the team within 90 days.',
            ],
            'long' => [
                'ar' => 'ضع خطة تحول رقمي شاملة تتضمن أتمتة العمليات المتكررة وتحليل البيانات.',
                'en' => 'Create a digital transformation plan covering automation and data analysis.',
                'kpi_ar' => 'خطة تحول رقمي معتمدة مع عمليتين مؤتمتتين خلال 6 أشهر.',
                'kpi_en' => 'An approved digital plan with two automated workflows within 6 months.',
            ],
        ],
        'sustainability' => [
            'immediate' => [
                'ar' => 'وثِّق جميع العمليات والإجراءات الأساسية للمنظمة في دليل مكتوب ومحدَّث.',
                'en' => 'Document all core organizational processes in an up-to-date written handbook.',
                'kpi_ar' => 'دليل عمليات أساسي مكتمل خلال 30 يوماً.',
                'kpi_en' => 'A completed core operations handbook within 30 days.',
            ],
            'medium' => [
                'ar' => 'طوِّر نموذج عمل يضمن استمرارية الأنشطة ذاتيًا حتى في حال انقطاع التمويل الخارجي.',
                'en' => 'Design a business model that sustains activities even if external funding pauses.',
                'kpi_ar' => 'نموذج عمل مكتوب بمصدر إيراد داخلي واحد على الأقل خلال 90 يوماً.',
                'kpi_en' => 'A written model with at least one internal revenue source within 90 days.',
            ],
            'long' => [
                'ar' => 'أنشئ خطة استدامة مؤسسية خمسية تشمل الحوكمة والموارد البشرية والمالية.',
                'en' => 'Create a five-year institutional sustainability plan covering governance, people, and finance.',
                'kpi_ar' => 'خطة استدامة خمسية معتمدة خلال 6 أشهر.',
                'kpi_en' => 'An approved five-year sustainability plan within 6 months.',
            ],
        ],
    ];

    private array $phaseLabels = [
        'immediate' => ['ar' => '0-30 يوم', 'en' => '0-30 days'],
        'medium' => ['ar' => '1-3 أشهر', 'en' => '1-3 months'],
        'long' => ['ar' => '3-6 أشهر', 'en' => '3-6 months'],
    ];

    /**
     * Generate a rule-based action plan for the three weakest pillars.
     */
    public function generate(Assessment $assessment): ActionPlan
    {
        $weakPillars = AssessmentPillarResult::with('pillar')
            ->where('assessment_id', $assessment->id)
            ->where('is_weak', true)
            ->orderBy('percentage')
            ->get();

        $actionPlan = ActionPlan::create(['assessment_id' => $assessment->id]);

        foreach ($weakPillars as $pillarResult) {
            $pillarKey = $pillarResult->pillar->key;
            $pillarRules = $this->rules[$pillarKey] ?? null;

            if (! $pillarRules) {
                continue;
            }

            foreach (['immediate', 'medium', 'long'] as $phase) {
                $rule = $pillarRules[$phase];

                ActionPlanItem::create([
                    'action_plan_id' => $actionPlan->id,
                    'pillar_id' => $pillarResult->pillar_id,
                    'phase' => $phase,
                    'phase_label_ar' => $this->phaseLabels[$phase]['ar'],
                    'phase_label_en' => $this->phaseLabels[$phase]['en'],
                    'action_ar' => $rule['ar'],
                    'action_en' => $rule['en'],
                    'kpi_ar' => $rule['kpi_ar'],
                    'kpi_en' => $rule['kpi_en'],
                    'status' => ActionItemStatus::NOT_STARTED->value,
                ]);
            }
        }

        return $actionPlan;
    }
}
