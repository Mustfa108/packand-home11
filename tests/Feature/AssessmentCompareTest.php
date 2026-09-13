<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAssessmentCatalog;
use Tests\TestCase;

class AssessmentCompareTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAssessmentCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    public function test_user_can_compare_own_assessments(): void
    {
        $user = User::factory()->create();
        $first = $this->completeAssessment($user, 2);
        $second = $this->completeAssessment($user, 4);

        $response = $this->actingAsUser($user)
            ->getJson("/api/assessments/compare?first_id={$first->id}&second_id={$second->id}")
            ->assertOk();

        $this->assertSame($first->id, $response->json('data.first.id'));
        $this->assertSame($second->id, $response->json('data.second.id'));

        // 2/5 (40%) -> 4/5 (80%) => +40 points overall.
        $this->assertEquals(40.0, $response->json('data.overall.difference'));
        $this->assertSame('improved', $response->json('data.overall.direction'));
        $this->assertEqualsWithDelta(100.0, $response->json('data.overall.difference_percent'), 0.01);

        // All six axes improved equally.
        $this->assertCount(6, $response->json('data.improved_axes'));
        $this->assertCount(0, $response->json('data.declined_axes'));
        $this->assertCount(0, $response->json('data.unchanged_axes'));
        $this->assertCount(6, $response->json('data.axes'));
    }

    public function test_user_cannot_compare_other_users_assessments(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $first = $this->completeAssessment($owner, 2);
        $second = $this->completeAssessment($owner, 4);

        $this->actingAsUser($other)
            ->getJson("/api/assessments/compare?first_id={$first->id}&second_id={$second->id}")
            ->assertStatus(403);
    }

    public function test_compare_requires_two_different_assessments(): void
    {
        $user = User::factory()->create();
        $assessment = $this->completeAssessment($user, 3);

        $this->actingAsUser($user)
            ->getJson("/api/assessments/compare?first_id={$assessment->id}&second_id={$assessment->id}")
            ->assertStatus(422);
    }

    public function test_user_cannot_view_other_users_assessment_details(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assessment = $this->completeAssessment($owner, 3);

        $this->actingAsUser($other)
            ->getJson("/api/assessments/{$assessment->id}")
            ->assertStatus(403);
    }

    public function test_progress_returns_all_completed_assessments(): void
    {
        $user = User::factory()->create();
        $first = $this->completeAssessment($user, 2);
        $second = $this->completeAssessment($user, 4);

        $response = $this->actingAsUser($user)
            ->getJson('/api/assessments/progress')
            ->assertOk();

        $this->assertSame(2, $response->json('data.total_assessments'));
        $this->assertCount(2, $response->json('data.points'));
        $this->assertEquals(40.0, $response->json('data.overall_trend'));

        // Second point carries the difference from the first.
        $this->assertEquals(40.0, $response->json('data.points.1.difference'));
    }

    private function completeAssessment(User $user, int $score)
    {
        $assessment = Assessment::create(['user_id' => $user->id, 'status' => 'in_progress']);

        foreach ($this->answersWithScore($score) as $answer) {
            $assessment->answers()->create($answer);
        }

        app(\App\Services\AssessmentScoringService::class)->calculate($assessment->fresh());

        return $assessment->fresh();
    }
}
