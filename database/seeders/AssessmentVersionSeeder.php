<?php

namespace Database\Seeders;

use App\Models\AssessmentVersion;
use App\Services\AssessmentVersionManager;
use Illuminate\Database\Seeder;

/**
 * Creates the first published questionnaire version from the legacy global
 * pillars/questions so versioning is active out of the box.
 */
class AssessmentVersionSeeder extends Seeder
{
    public function run(): void
    {
        if (AssessmentVersion::where('status', 'published')->exists()) {
            return;
        }

        $manager = app(AssessmentVersionManager::class);

        $draft = $manager->createDraft(null, 'الإصدار الأول للاستبيان');
        $manager->publish($draft, null);
    }
}
