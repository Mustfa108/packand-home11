<?php

namespace Database\Seeders;

use App\Models\Pillar;
use Illuminate\Database\Seeder;

class PillarSeeder extends Seeder
{
    public function run(): void
    {
        $pillars = [
            [
                'key' => 'team',
                'name_ar' => 'الفريق',
                'name_en' => 'Team',
                'description_ar' => 'يقيس متانة الفريق وجاهزيته البشرية والمؤسسية للنمو',
                'description_en' => 'Measures team strength and organizational readiness for growth',
                'display_order' => 1,
            ],
            [
                'key' => 'funding',
                'name_ar' => 'التمويل',
                'name_en' => 'Funding',
                'description_ar' => 'يقيس استدامة مصادر التمويل وتنوعها وقدرة المنظمة على الاستمرار ماليًا',
                'description_en' => 'Measures funding sustainability, diversity, and financial continuity',
                'display_order' => 2,
            ],
            [
                'key' => 'impact',
                'name_ar' => 'الأثر',
                'name_en' => 'Impact',
                'description_ar' => 'يقيس وضوح الأثر المُحقَّق وآليات قياسه وتوثيقه',
                'description_en' => 'Measures clarity of impact and how it is measured and documented',
                'display_order' => 3,
            ],
            [
                'key' => 'partnerships',
                'name_ar' => 'الشراكات',
                'name_en' => 'Partnerships',
                'description_ar' => 'يقيس عمق الشبكة التعاونية للمنظمة ومستوى الشراكات الاستراتيجية',
                'description_en' => 'Measures the depth of collaborative networks and strategic partnerships',
                'display_order' => 4,
            ],
            [
                'key' => 'technology',
                'name_ar' => 'التقنية',
                'name_en' => 'Technology',
                'description_ar' => 'يقيس مستوى توظيف الأدوات الرقمية في إدارة العمل المؤسسي',
                'description_en' => 'Measures use of digital tools in organizational operations',
                'display_order' => 5,
            ],
            [
                'key' => 'sustainability',
                'name_ar' => 'الاستدامة',
                'name_en' => 'Sustainability',
                'description_ar' => 'يقيس قدرة المنظمة على الاستمرارية المؤسسية على المدى البعيد',
                'description_en' => 'Measures long-term institutional continuity capacity',
                'display_order' => 6,
            ],
        ];

        foreach ($pillars as $pillar) {
            Pillar::updateOrCreate(['key' => $pillar['key']], $pillar);
        }
    }
}
