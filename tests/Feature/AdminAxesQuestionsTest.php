<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Pillar;
use App\Services\AssessmentVersionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAssessmentCatalog;
use Tests\TestCase;

class AdminAxesQuestionsTest extends TestCase
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

    private function createDraft(): object
    {
        return app(AssessmentVersionManager::class)->createDraft($this->admin->id);
    }

    public function test_admin_can_add_update_and_toggle_axis_in_draft(): void
    {
        $this->actingAsAdmin();
        $draft = $this->createDraft();

        $created = $this->postJson('/api/admin/axes', [
            'version_id' => $draft->id,
            'key' => 'governance',
            'name_ar' => 'الحوكمة',
            'description_ar' => 'محور الحوكمة والشفافية',
            'display_order' => 7,
            'weight' => 10,
        ])->assertStatus(201)->json('data');

        $this->patchJson("/api/admin/axes/{$created['id']}", ['weight' => 12, 'name_ar' => 'الحوكمة والشفافية'])
            ->assertOk();

        $this->assertDatabaseHas('pillars', ['id' => $created['id'], 'weight' => 12, 'name_ar' => 'الحوكمة والشفافية']);

        $this->patchJson("/api/admin/axes/{$created['id']}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        // Deactivated, not deleted.
        $this->assertDatabaseHas('pillars', ['id' => $created['id'], 'is_active' => false]);
    }

    public function test_admin_can_add_update_and_toggle_question_in_draft(): void
    {
        $this->actingAsAdmin();
        $draft = $this->createDraft();

        $axis = $draft->pillars()->first();

        $created = $this->postJson('/api/admin/questions', [
            'version_id' => $draft->id,
            'pillar_id' => $axis->id,
            'text_ar' => 'هل لديكم سياسة مكتوبة للاستقطاب؟',
            'display_order' => 99,
            'weight' => 10,
        ])->assertStatus(201)->json('data');

        $this->patchJson("/api/admin/questions/{$created['id']}", ['weight' => 15])
            ->assertOk();

        $this->assertDatabaseHas('questions', ['id' => $created['id'], 'weight' => 15]);

        $this->patchJson("/api/admin/questions/{$created['id']}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_question_must_belong_to_axis_in_same_version(): void
    {
        $this->actingAsAdmin();
        $draft = $this->createDraft();

        $this->postJson('/api/admin/questions', [
            'version_id' => $draft->id,
            'pillar_id' => 999999,
            'text_ar' => 'سؤال غير مرتبط',
            'display_order' => 1,
            'weight' => 10,
        ])->assertStatus(422);
    }

    public function test_user_cannot_access_admin_axes_management(): void
    {
        $user = \App\Models\User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/axes')->assertStatus(403);
        $this->postJson('/api/admin/axes', ['key' => 'x'])->assertStatus(403);
    }

    public function test_weight_report_reflects_current_weights(): void
    {
        $this->actingAsAdmin();
        $draft = $this->createDraft();

        $response = $this->getJson("/api/admin/assessment-versions/{$draft->id}")
            ->assertOk()
            ->json('data');

        $this->assertTrue($response['weight_report']['axis_weights_valid']);
        $this->assertEqualsWithDelta(100.0, $response['weight_report']['axis_weight_sum'], 0.05);
    }
}
