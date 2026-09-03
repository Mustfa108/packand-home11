<?php

namespace Tests\Feature;

use App\Models\ActionPlanItem;
use App\Models\Assessment;
use App\Models\User;
use App\Services\AssessmentScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\SeedsAssessmentCatalog;
use Tests\TestCase;

class AssessmentScoringTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAssessmentCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    public function test_all_ones_score_is_low(): void
    {
        $assessment = $this->submitAndCalculate(1);

        $this->assertSame(20.0, (float) $assessment->overall_score);
        $this->assertSame('low', $assessment->readiness_level);
        $this->assertSame(3, $assessment->pillarResults()->where('is_weak', true)->count());
        $this->assertSame(9, $assessment->actionPlan->items()->count());
        $this->assertTrue($assessment->actionPlan->items()->whereNotNull('kpi_ar')->exists());
    }

    public function test_all_threes_score_is_medium(): void
    {
        $assessment = $this->submitAndCalculate(3);

        $this->assertSame(60.0, (float) $assessment->overall_score);
        $this->assertSame('medium', $assessment->readiness_level);
    }

    public function test_all_fours_score_is_good(): void
    {
        $assessment = $this->submitAndCalculate(4);

        $this->assertSame(80.0, (float) $assessment->overall_score);
        $this->assertSame('good', $assessment->readiness_level);
    }

    public function test_weakest_three_pillars_are_marked(): void
    {
        $user = User::factory()->create();
        $assessment = Assessment::create(['user_id' => $user->id, 'status' => 'in_progress']);

        $this->storeAnswers($assessment, $this->answersByPillar([
            'team' => 1,
            'funding' => 1,
            'impact' => 2,
            'partnerships' => 5,
            'technology' => 5,
            'sustainability' => 5,
        ]));

        app(AssessmentScoringService::class)->calculate($assessment->fresh());
        $assessment->refresh();

        $weakKeys = $assessment->pillarResults()
            ->where('is_weak', true)
            ->with('pillar')
            ->get()
            ->pluck('pillar.key')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['funding', 'impact', 'team'], $weakKeys);
    }

    public function test_submit_requires_eighteen_answers(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $start = $this->postJson('/api/assessment/start')->assertStatus(201);
        $id = $start->json('data.assessment_id');

        $this->postJson("/api/assessment/{$id}/submit", [
            'answers' => array_slice($this->answersWithScore(3), 0, 5),
        ])->assertStatus(422);
    }

    public function test_submit_rejects_score_outside_one_to_five(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $start = $this->postJson('/api/assessment/start');
        $id = $start->json('data.assessment_id');

        $answers = $this->answersWithScore(3);
        $answers[0]['score'] = 9;

        $this->postJson("/api/assessment/{$id}/submit", [
            'answers' => $answers,
        ])->assertStatus(422);
    }

    public function test_completed_assessment_cannot_be_resubmitted(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $start = $this->postJson('/api/assessment/start');
        $id = $start->json('data.assessment_id');

        $this->postJson("/api/assessment/{$id}/submit", [
            'answers' => $this->answersWithScore(3),
        ])->assertOk();

        $this->postJson("/api/assessment/{$id}/submit", [
            'answers' => $this->answersWithScore(4),
        ])->assertStatus(422);
    }

    public function test_action_plan_kpis_come_from_rules_not_ai(): void
    {
        $user = User::factory()->create();
        $assessment = Assessment::create(['user_id' => $user->id, 'status' => 'in_progress']);
        $this->storeAnswers($assessment, $this->answersWithScore(2));
        app(AssessmentScoringService::class)->calculate($assessment->fresh());

        $item = ActionPlanItem::query()->first();
        $this->assertNotNull($item->kpi_ar);
        $this->assertNotNull($item->kpi_en);
        $this->assertNull($item->ai_rephrased_ar);
        $this->assertSame('not_started', $item->status->value);
    }

    private function submitAndCalculate(int $score): Assessment
    {
        $user = User::factory()->create();
        $assessment = Assessment::create(['user_id' => $user->id, 'status' => 'in_progress']);
        $this->storeAnswers($assessment, $this->answersWithScore($score));
        app(AssessmentScoringService::class)->calculate($assessment->fresh());

        return $assessment->fresh(['pillarResults', 'actionPlan.items']);
    }

    /**
     * @param  array<int, array{question_id: int, score: int}>  $answers
     */
    private function storeAnswers(Assessment $assessment, array $answers): void
    {
        foreach ($answers as $answer) {
            $assessment->answers()->create($answer);
        }
    }
}
