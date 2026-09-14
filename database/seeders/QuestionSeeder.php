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
            // Team — mix of yes/no and likert-style maturity
            ['pillar_key' => 'team', 'order' => 1, 'answer_type' => 'yes_no',
                'text_ar' => 'هل يمتلك فريقك أدواراً ومسؤوليات محددة ومكتوبة بوضوح لكل عضو؟',
                'text_en' => 'Does your team have clear, written roles and responsibilities for every member?'],
            ['pillar_key' => 'team', 'order' => 2, 'answer_type' => 'yes_no',
                'text_ar' => 'هل توجد آليات واضحة ومعتمدة لاتخاذ القرار داخل الفريق؟',
                'text_en' => 'Are there clear, approved decision-making mechanisms inside the team?'],
            ['pillar_key' => 'team', 'order' => 3, 'answer_type' => 'likert',
                'text_ar' => 'ما مدى نضج خطة تطوير الكفاءات البشرية وتنمية المهارات في فريقك؟',
                'text_en' => 'How mature is your team skills and capacity development plan?'],
            ['pillar_key' => 'team', 'order' => 4, 'answer_type' => 'yes_no',
                'text_ar' => 'هل لديكم توزيع واضح للأدوار بين مرحلة الفكرة والتجربة والتشغيل؟',
                'text_en' => 'Do you clearly assign roles across idea, pilot, and operating stages?'],

            // Funding
            ['pillar_key' => 'funding', 'order' => 1, 'answer_type' => 'yes_no',
                'text_ar' => 'هل تمتلك منظمتك مصادر تمويل متنوعة وغير معتمدة على مصدر واحد فقط؟',
                'text_en' => 'Does your organization have diversified funding sources (not one source only)?'],
            ['pillar_key' => 'funding', 'order' => 2, 'answer_type' => 'yes_no',
                'text_ar' => 'هل لديكم ميزانية سنوية واضحة ومعتمدة للعام القادم؟',
                'text_en' => 'Do you have a clear approved annual budget for the coming year?'],
            ['pillar_key' => 'funding', 'order' => 3, 'answer_type' => 'likert',
                'text_ar' => 'ما مدى جاهزيتكم لتقديم مقترحات تمويلية مكتملة خلال العام الجاري؟',
                'text_en' => 'How ready are you to submit complete funding proposals this year?'],
            ['pillar_key' => 'funding', 'order' => 4, 'answer_type' => 'yes_no',
                'text_ar' => 'هل لديكم تتبع واضح لتكلفة الوصول إلى مستفيد واحد؟',
                'text_en' => 'Do you track cost-per-beneficiary clearly?'],

            // Impact
            ['pillar_key' => 'impact', 'order' => 1, 'answer_type' => 'yes_no',
                'text_ar' => 'هل حددتم مؤشرات أثر واضحة وقابلة للقياس لجميع أنشطتكم الرئيسية؟',
                'text_en' => 'Have you defined clear measurable impact indicators for all major activities?'],
            ['pillar_key' => 'impact', 'order' => 2, 'answer_type' => 'likert',
                'text_ar' => 'ما مدى انتظام جمع بيانات الأثر على المستفيدين بشكل منهجي؟',
                'text_en' => 'How systematically do you collect impact data on beneficiaries?'],
            ['pillar_key' => 'impact', 'order' => 3, 'answer_type' => 'yes_no',
                'text_ar' => 'هل تُعِدُّون تقارير أثر دورية وتشاركونها مع الجهات المعنية والداعمين؟',
                'text_en' => 'Do you prepare periodic impact reports and share them with stakeholders and supporters?'],
            ['pillar_key' => 'impact', 'order' => 4, 'answer_type' => 'yes_no',
                'text_ar' => 'هل تختلف مؤشرات الأثر بحسب نوع المشروع (فكرة، تجريبي، تشغيلي، نمو)؟',
                'text_en' => 'Do impact indicators differ by project stage (idea, pilot, operating, growth)?'],

            // Partnerships
            ['pillar_key' => 'partnerships', 'order' => 1, 'answer_type' => 'yes_no',
                'text_ar' => 'هل لديكم شراكات رسمية موثقة مع منظمات أو جهات أخرى؟',
                'text_en' => 'Do you have documented formal partnerships with other organizations?'],
            ['pillar_key' => 'partnerships', 'order' => 2, 'answer_type' => 'likert',
                'text_ar' => 'ما مدى فاعلية تعاونكم مع جهات حكومية أو قطاع خاص؟',
                'text_en' => 'How effective is your collaboration with government or private-sector actors?'],
            ['pillar_key' => 'partnerships', 'order' => 3, 'answer_type' => 'yes_no',
                'text_ar' => 'هل لديكم استراتيجية واضحة لبناء شبكة شراكات جديدة وتطويرها مستقبلاً؟',
                'text_en' => 'Do you have a clear strategy to build and grow a future partner network?'],

            // Technology
            ['pillar_key' => 'technology', 'order' => 1, 'answer_type' => 'yes_no',
                'text_ar' => 'هل توظف منظمتك أدوات رقمية لإدارة المشاريع وتنسيق عمل الفريق يومياً؟',
                'text_en' => 'Does your organization use digital tools for daily project and team coordination?'],
            ['pillar_key' => 'technology', 'order' => 2, 'answer_type' => 'yes_no',
                'text_ar' => 'هل لديكم حضور رقمي فاعل (موقع إلكتروني أو منصات تواصل) لخدمة مستفيديكم؟',
                'text_en' => 'Do you have an active digital presence (website or social channels) serving beneficiaries?'],
            ['pillar_key' => 'technology', 'order' => 3, 'answer_type' => 'likert',
                'text_ar' => 'ما مدى استخدامكم للبيانات والتحليلات الرقمية في اتخاذ القرارات؟',
                'text_en' => 'How regularly do you use digital data and analytics in decision-making?'],
            ['pillar_key' => 'technology', 'order' => 4, 'answer_type' => 'yes_no',
                'text_ar' => 'هل لديكم آلية رقمية لتتبع مواقع المشاريع على الخريطة وتجنب التداخل الجغرافي؟',
                'text_en' => 'Do you digitally track project locations on a map to avoid geographic overlap?'],

            // Sustainability
            ['pillar_key' => 'sustainability', 'order' => 1, 'answer_type' => 'yes_no',
                'text_ar' => 'هل وثَّقتم السياسات والإجراءات والعمليات الأساسية للمنظمة بشكل مكتوب؟',
                'text_en' => 'Have you documented core policies, procedures, and processes in writing?'],
            ['pillar_key' => 'sustainability', 'order' => 2, 'answer_type' => 'likert',
                'text_ar' => 'ما مدى وضوح نموذج العمل الذي يضمن استمرارية الأنشطة دون اعتماد كامل على التمويل الخارجي؟',
                'text_en' => 'How clear is your model for sustaining activities without full reliance on external funding?'],
            ['pillar_key' => 'sustainability', 'order' => 3, 'answer_type' => 'yes_no',
                'text_ar' => 'هل يوجد لديكم خطة لضمان استمرار العمل المؤسسي في حالة تغيير الأعضاء أو القيادة الرئيسية؟',
                'text_en' => 'Do you have a continuity plan if key members or leadership change?'],
            ['pillar_key' => 'sustainability', 'order' => 4, 'answer_type' => 'yes_no',
                'text_ar' => 'هل لديكم خطة توسع جغرافية واضحة للمشاريع الخاصة مع أولويات مناطق؟',
                'text_en' => 'Do you have a clear geographic expansion plan for private projects with area priorities?'],
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
                        'answer_type' => $q['answer_type'] ?? 'likert',
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
