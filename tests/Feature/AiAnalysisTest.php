<?php

namespace Tests\Feature;

use App\Models\AiAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAssessmentCatalog;
use Tests\TestCase;

class AiAnalysisTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAssessmentCatalog;

    private User $user;

    private int $assessmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();

        // No GEMINI_API_KEY in tests => fallback rule-based analysis path.
        config(['gemini.api_key' => '']);

        $this->user = User::factory()->create([
            'org_type' => 'startup',
            'org_size' => 'small',
        ]);

        $assessment = \App\Models\Assessment::create(['user_id' => $this->user->id, 'status' => 'in_progress']);
        foreach ($this->answersWithScore(3) as $answer) {
            $assessment->answers()->create($answer);
        }
        app(\App\Services\AssessmentScoringService::class)->calculate($assessment->fresh());

        $this->assessmentId = $assessment->id;
    }

    public function test_analysis_is_generated_and_saved(): void
    {
        $response = $this->actingAsUser($this->user)
            ->postJson("/api/assessments/{$this->assessmentId}/ai-analysis")
            ->assertOk();

        $this->assertNotNull($response->json('data.analysis'));
        $this->assertNotNull($response->json('data.analysis.overall_summary'));
        $this->assertNotEmpty($response->json('data.analysis.recommendations'));
        $this->assertLessThanOrEqual(5, count($response->json('data.analysis.recommendations')));
        $this->assertNotNull($response->json('data.disclaimer'));

        $this->assertSame(1, AiAnalysis::where('assessment_id', $this->assessmentId)->count());
    }

    public function test_analysis_is_not_regenerated_on_repeat_requests(): void
    {
        $this->actingAsUser($this->user)
            ->postJson("/api/assessments/{$this->assessmentId}/ai-analysis")
            ->assertOk();

        $this->actingAsUser($this->user)
            ->postJson("/api/assessments/{$this->assessmentId}/ai-analysis")
            ->assertOk();

        // Second POST (page refresh) must reuse the saved analysis.
        $this->assertSame(1, AiAnalysis::where('assessment_id', $this->assessmentId)->count());
    }

    public function test_get_returns_existing_analysis_without_generation(): void
    {
        $this->actingAsUser($this->user)
            ->getJson("/api/assessments/{$this->assessmentId}/ai-analysis")
            ->assertOk()
            ->assertJsonPath('data.status', 'none');

        $this->actingAsUser($this->user)
            ->postJson("/api/assessments/{$this->assessmentId}/ai-analysis")
            ->assertOk();

        $this->actingAsUser($this->user)
            ->getJson("/api/assessments/{$this->assessmentId}/ai-analysis")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame(1, AiAnalysis::where('assessment_id', $this->assessmentId)->count());
    }

    public function test_analysis_requires_completed_organization_profile(): void
    {
        $this->user->update(['org_type' => null, 'org_size' => null]);

        $this->actingAsUser($this->user)
            ->postJson("/api/assessments/{$this->assessmentId}/ai-analysis")
            ->assertStatus(422);
    }

    public function test_other_user_cannot_access_analysis(): void
    {
        $this->actingAsUser($this->user)
            ->postJson("/api/assessments/{$this->assessmentId}/ai-analysis")
            ->assertOk();

        $other = User::factory()->create();

        $this->actingAsUser($other)
            ->getJson("/api/assessments/{$this->assessmentId}/ai-analysis")
            ->assertStatus(403);

        $this->actingAsUser($other)
            ->postJson("/api/assessments/{$this->assessmentId}/ai-chat", ['message' => 'لماذا هذه النتيجة؟'])
            ->assertStatus(403);
    }

    public function test_chat_answers_within_assessment_context(): void
    {
        $response = $this->actingAsUser($this->user)
            ->postJson("/api/assessments/{$this->assessmentId}/ai-chat", [
                'message' => 'لماذا حصلت على هذه النتيجة؟',
            ])
            ->assertOk();

        $this->assertNotNull($response->json('data.answer'));
        // Fallback expected because no API key is configured.
        $this->assertTrue($response->json('data.is_fallback'));
        $this->assertSame(2, \App\Models\AiChatMessage::where('assessment_id', $this->assessmentId)->count());
    }

    public function test_chat_rejects_overly_long_questions(): void
    {
        $this->actingAsUser($this->user)
            ->postJson("/api/assessments/{$this->assessmentId}/ai-chat", [
                'message' => str_repeat('س', 600),
            ])
            ->assertStatus(422);
    }

    public function test_gemini_json_validation_rejects_malformed_structure(): void
    {
        $service = app(\App\Services\GeminiAssessmentAnalysisService::class);

        $this->assertNull($service->parseAndValidate('not json at all'));
        $this->assertNull($service->parseAndValidate('{"foo": "bar"}'));
        $this->assertNull($service->parseAndValidate(
            json_encode(['overall_summary' => 'x', 'strengths' => [], 'weaknesses' => [], 'recommendations' => []], JSON_UNESCAPED_UNICODE)
        ));

        $valid = $service->parseAndValidate(json_encode([
            'overall_summary' => 'ملخص',
            'strengths' => ['قوة'],
            'weaknesses' => ['ضعف'],
            'priorities' => [['axis' => 'التمويل', 'priority' => 'مرتفعة', 'reason' => 'الأدنى']],
            'recommendations' => [['title' => 'عنوان', 'description' => 'وصف']],
            'progress_summary' => 'مقارنة',
        ], JSON_UNESCAPED_UNICODE));

        $this->assertNotNull($valid);
        $this->assertSame('ملخص', $valid['overall_summary']);
        $this->assertCount(1, $valid['recommendations']);
    }

    public function test_gemini_failure_produces_fallback_analysis_with_basic_results_intact(): void
    {
        $service = app(\App\Services\GeminiAssessmentAnalysisService::class);
        $assessment = \App\Models\Assessment::find($this->assessmentId);

        $analysis = $service->generate($assessment);

        $this->assertTrue($analysis->is_fallback);
        $this->assertSame('completed', $analysis->status);
        $this->assertNotNull($analysis->response_json['overall_summary']);
        $this->assertNotEmpty($analysis->response_json['recommendations']);
    }
}
