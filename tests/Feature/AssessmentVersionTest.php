<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AssessmentVersion;
use App\Models\Question;
use App\Services\AssessmentVersionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAssessmentCatalog;
use Tests\TestCase;

class AssessmentVersionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAssessmentCatalog;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->admin = Admin::factory()->create();
    }

    protected function actingAsAdmin(): static
    {
        Sanctum::actingAs($this->admin);

        return $this;
    }

    public function test_admin_can_create_draft_and_publish_version(): void
    {
        $this->actingAsAdmin();

        $created = $this->postJson('/api/admin/assessment-versions', [
            'notes_ar' => 'نسخة أولى',
        ])->assertStatus(201)->json('data');

        $this->assertSame('draft', $created['status']);

        // Draft copies the legacy questionnaire (6 axes, 18 questions) with
        // normalized weights.
        $detail = $this->getJson("/api/admin/assessment-versions/{$created['id']}")
            ->assertOk()
            ->json('data');

        $this->assertCount(6, $detail['axes']);
        $this->assertCount(18, collect($detail['axes'])->flatMap(fn ($a) => $a['questions'])->all());
        $this->assertTrue($detail['weight_report']['axis_weights_valid']);
        $this->assertTrue($detail['is_editable']);

        $published = $this->postJson("/api/admin/assessment-versions/{$created['id']}/publish")
            ->assertOk()
            ->json('data');

        $this->assertSame('published', $published['status']);
        $this->assertNotNull($published['published_at']);
    }

    public function test_only_one_published_version_exists_at_a_time(): void
    {
        $this->actingAsAdmin();
        $manager = app(AssessmentVersionManager::class);

        $first = $manager->createDraft($this->admin->id);
        $manager->publish($first, $this->admin->id);

        $second = $manager->createDraft($this->admin->id);
        $manager->publish($second, $this->admin->id);

        $first->refresh();
        $second->refresh();

        $this->assertSame('archived', $first->status);
        $this->assertSame('published', $second->status);
        $this->assertSame(1, AssessmentVersion::where('status', 'published')->count());
    }

    public function test_publish_is_blocked_when_axis_weights_do_not_sum_to_100(): void
    {
        $this->actingAsAdmin();
        $manager = app(AssessmentVersionManager::class);

        $draft = $manager->createDraft($this->admin->id);

        // Break one axis weight.
        $draft->pillars()->first()->update(['weight' => 5]);

        $this->postJson("/api/admin/assessment-versions/{$draft->id}/publish")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_publish_is_blocked_when_question_weights_do_not_sum_to_100(): void
    {
        $this->actingAsAdmin();
        $manager = app(AssessmentVersionManager::class);

        $draft = $manager->createDraft($this->admin->id);
        $draft->questions()->first()->update(['weight' => 3]);

        $this->postJson("/api/admin/assessment-versions/{$draft->id}/publish")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_published_version_cannot_be_edited(): void
    {
        $this->actingAsAdmin();
        $manager = app(AssessmentVersionManager::class);

        $draft = $manager->createDraft($this->admin->id);
        $manager->publish($draft, $this->admin->id);

        $axis = $draft->pillars()->first();

        $this->patchJson("/api/admin/axes/{$axis->id}", ['weight' => 10])
            ->assertStatus(403);

        $question = Question::where('pillar_id', $axis->id)->first();

        $this->patchJson("/api/admin/questions/{$question->id}", ['weight' => 10])
            ->assertStatus(403);
    }

    public function test_questions_cannot_be_deleted_only_deactivated(): void
    {
        $this->actingAsAdmin();
        $manager = app(AssessmentVersionManager::class);

        $draft = $manager->createDraft($this->admin->id);
        $question = $draft->questions()->first();

        $this->patchJson("/api/admin/questions/{$question->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('questions', ['id' => $question->id]);
    }

    public function test_users_receive_published_version_questions(): void
    {
        $user = \App\Models\User::factory()->create();

        // No versions yet: legacy questionnaire is served.
        $legacy = $this->actingAsUser($user)->getJson('/api/assessment/questions')->assertOk()->json('data');
        $this->assertNull($legacy['version_number']);
        $this->assertSame(18, $legacy['total_questions']);

        $this->actingAsAdmin();
        $manager = app(AssessmentVersionManager::class);
        $draft = $manager->createDraft($this->admin->id);
        $manager->publish($draft, $this->admin->id);

        $versioned = $this->actingAsUser($user)->getJson('/api/assessment/questions')->assertOk()->json('data');
        $this->assertSame(1, $versioned['version_number']);
        $this->assertSame(18, $versioned['total_questions']);

        // Starting an assessment attaches the published version.
        Queue::fake();
        $start = $this->actingAsUser($user)->postJson('/api/assessment/start')->assertStatus(201);
        $assessment = \App\Models\Assessment::find($start->json('data.assessment_id'));
        $this->assertSame($draft->id, $assessment->assessment_version_id);
    }

    public function test_weighted_scoring_uses_question_and_axis_weights(): void
    {
        $this->actingAsAdmin();
        $manager = app(AssessmentVersionManager::class);
        $draft = $manager->createDraft($this->admin->id);

        // Give "team" axis 50% weight, all others 10% each (total 100).
        $teamAxis = $draft->pillars()->where('key', 'team')->first();
        $draft->pillars()->update(['weight' => 10]);
        $teamAxis->update(['weight' => 50]);
        $manager->publish($draft, $this->admin->id);

        $user = \App\Models\User::factory()->create();
        $assessment = \App\Models\Assessment::create([
            'user_id' => $user->id,
            'status' => 'in_progress',
            'assessment_version_id' => $draft->id,
        ]);

        // Team = 1 (low), everything else = 5 (high).
        foreach (Question::where('assessment_version_id', $draft->id)->with('pillar')->get() as $question) {
            $score = $question->pillar->key === 'team' ? 1 : 5;
            $assessment->answers()->create(['question_id' => $question->id, 'score' => $score]);
        }

        app(\App\Services\AssessmentScoringService::class)->calculate($assessment->fresh());
        $assessment->refresh();

        // Unweighted average would be (20 + 100*5)/6 ≈ 86.7; weighted result
        // must be lower because the weak axis carries half the total weight.
        $this->assertLessThan(86.7, $assessment->overall_score);
        $expected = 20 * 0.5 + 100 * 0.5;
        $this->assertEqualsWithDelta($expected, $assessment->overall_score, 0.5);

        // Org snapshot saved from the user's profile.
        $user->update(['org_type' => 'startup', 'org_size' => 'small', 'team_member_count' => 5]);
    }
}
