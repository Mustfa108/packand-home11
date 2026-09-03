<?php

namespace Database\Seeders;

use App\Models\Pillar;
use App\Models\Question;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            ['pillar_key' => 'team', 'order' => 1,
                'text_ar' => 'هل يمتلك فريقك أدواراً ومسؤوليات محددة ومكتوبة بوضوح لكل عضو؟',
                'text_en' => 'Does your team have clear, written roles and responsibilities for every member?'],
            ['pillar_key' => 'team', 'order' => 2,
                'text_ar' => 'هل توجد آليات واضحة ومعتمدة لاتخاذ القرار داخل الفريق؟',
                'text_en' => 'Are there clear, approved decision-making mechanisms inside the team?'],
            ['pillar_key' => 'team', 'order' => 3,
                'text_ar' => 'هل يمتلك الفريق خطة لتطوير الكفاءات البشرية وتنمية المهارات باستمرار؟',
                'text_en' => 'Does the team have an ongoing skills and capacity development plan?'],

            ['pillar_key' => 'funding', 'order' => 1,
                'text_ar' => 'هل تمتلك منظمتك مصادر تمويل متنوعة وغير معتمدة على مصدر واحد فقط؟',
                'text_en' => 'Does your organization have diversified funding sources (not one source only)?'],
            ['pillar_key' => 'funding', 'order' => 2,
                'text_ar' => 'هل لديكم ميزانية سنوية واضحة ومعتمدة للعام القادم؟',
                'text_en' => 'Do you have a clear approved annual budget for the coming year?'],
            ['pillar_key' => 'funding', 'order' => 3,
                'text_ar' => 'هل نجحتم في تقديم واستكمال مقترح تمويلي واحد على الأقل خلال العام الماضي؟',
                'text_en' => 'Did you submit and complete at least one funding proposal in the past year?'],

            ['pillar_key' => 'impact', 'order' => 1,
                'text_ar' => 'هل حددتم مؤشرات أثر واضحة وقابلة للقياس لجميع أنشطتكم الرئيسية؟',
                'text_en' => 'Have you defined clear measurable impact indicators for all major activities?'],
            ['pillar_key' => 'impact', 'order' => 2,
                'text_ar' => 'هل تجمعون بيانات منتظمة ومنهجية لتقييم الأثر الفعلي على المستفيدين؟',
                'text_en' => 'Do you regularly collect systematic data to evaluate real impact on beneficiaries?'],
            ['pillar_key' => 'impact', 'order' => 3,
                'text_ar' => 'هل تُعِدُّون تقارير أثر دورية وتشاركونها مع الجهات المعنية والداعمين؟',
                'text_en' => 'Do you prepare periodic impact reports and share them with stakeholders and supporters?'],

            ['pillar_key' => 'partnerships', 'order' => 1,
                'text_ar' => 'هل لديكم شراكات رسمية موثقة مع منظمات أو جهات أخرى؟',
                'text_en' => 'Do you have documented formal partnerships with other organizations?'],
            ['pillar_key' => 'partnerships', 'order' => 2,
                'text_ar' => 'هل تتعاونون بفاعلية مع جهات حكومية أو قطاع خاص لتحقيق أهدافكم؟',
                'text_en' => 'Do you collaborate effectively with government or private-sector actors?'],
            ['pillar_key' => 'partnerships', 'order' => 3,
                'text_ar' => 'هل لديكم استراتيجية واضحة لبناء شبكة شراكات جديدة وتطويرها مستقبلاً؟',
                'text_en' => 'Do you have a clear strategy to build and grow a future partner network?'],

            ['pillar_key' => 'technology', 'order' => 1,
                'text_ar' => 'هل توظف منظمتك أدوات رقمية لإدارة المشاريع وتنسيق عمل الفريق يومياً؟',
                'text_en' => 'Does your organization use digital tools for daily project and team coordination?'],
            ['pillar_key' => 'technology', 'order' => 2,
                'text_ar' => 'هل لديكم حضور رقمي فاعل (موقع إلكتروني أو منصات تواصل) لخدمة مستفيديكم؟',
                'text_en' => 'Do you have an active digital presence (website or social channels) serving beneficiaries?'],
            ['pillar_key' => 'technology', 'order' => 3,
                'text_ar' => 'هل تستخدمون البيانات والتحليلات الرقمية بشكل منتظم في اتخاذ قراراتكم؟',
                'text_en' => 'Do you regularly use digital data and analytics in decision-making?'],

            ['pillar_key' => 'sustainability', 'order' => 1,
                'text_ar' => 'هل وثَّقتم السياسات والإجراءات والعمليات الأساسية للمنظمة بشكل مكتوب؟',
                'text_en' => 'Have you documented core policies, procedures, and processes in writing?'],
            ['pillar_key' => 'sustainability', 'order' => 2,
                'text_ar' => 'هل لديكم نموذج عمل واضح يضمن استمرارية الأنشطة ذاتيًا دون اعتماد كامل على التمويل الخارجي؟',
                'text_en' => 'Do you have a clear model that sustains activities without full reliance on external funding?'],
            ['pillar_key' => 'sustainability', 'order' => 3,
                'text_ar' => 'هل يوجد لديكم خطة لضمان استمرار العمل المؤسسي في حالة تغيير الأعضاء أو القيادة الرئيسية؟',
                'text_en' => 'Do you have a continuity plan if key members or leadership change?'],
        ];

        foreach ($questions as $q) {
            $pillar = Pillar::where('key', $q['pillar_key'])->first();

            if ($pillar) {
                Question::updateOrCreate(
                    ['pillar_id' => $pillar->id, 'display_order' => $q['order']],
                    [
                        'text_ar' => $q['text_ar'],
                        'text_en' => $q['text_en'],
                        'display_order' => $q['order'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
