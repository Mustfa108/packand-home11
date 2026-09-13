<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAssessmentCatalog;
use Tests\TestCase;

/**
 * Performance: user-facing listing/progress endpoints stay fast and correct
 * when the user (and the platform) have many completed assessments.
 */
class AssessmentPerformanceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAssessmentCatalog;

    public function test_progress_and_list_handle_many_assessments(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $service = app(\App\Services\AssessmentScoringService::class);

        for ($i = 0; $i < 25; $i++) {
            $assessment = Assessment::create(['user_id' => $user->id, 'status' => 'in_progress']);
            foreach ($this->answersWithScore(min(5, 1 + ($i % 5))) as $answer) {
                $assessment->answers()->create($answer);
            }
            $service->calculate($assessment->fresh());
        }

        $startedAt = microtime(true);

        $this->actingAsUser($user)
            ->getJson('/api/assessments/progress')
            ->assertOk()
            ->assertJsonPath('data.total_assessments', 25);

        $this->actingAsUser($user)
            ->getJson('/api/assessments')
            ->assertOk();

        $elapsed = microtime(true) - $startedAt;

        $this->assertLessThan(5.0, $elapsed, 'Endpoints should handle 25 assessments in under 5 seconds.');
    }
}
