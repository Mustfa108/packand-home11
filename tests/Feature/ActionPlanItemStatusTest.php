<?php

namespace Tests\Feature;

use App\Models\ActionPlan;
use App\Models\ActionPlanItem;
use App\Models\Assessment;
use App\Models\Pillar;
use App\Models\User;
use Database\Seeders\PillarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionPlanItemStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_item_status(): void
    {
        $this->seed(PillarSeeder::class);
        $user = User::factory()->create();
        $item = $this->makeItem($user);

        $this->actingAsUser($user)
            ->patchJson("/api/action-plan/items/{$item->id}", [
                'status' => 'in_progress',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertSame('in_progress', $item->fresh()->status->value);
    }

    public function test_other_user_cannot_update_item_status(): void
    {
        $this->seed(PillarSeeder::class);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $item = $this->makeItem($owner);

        $this->actingAsUser($other)
            ->patchJson("/api/action-plan/items/{$item->id}", [
                'status' => 'completed',
            ])
            ->assertStatus(403);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->seed(PillarSeeder::class);
        $user = User::factory()->create();
        $item = $this->makeItem($user);

        $this->actingAsUser($user)
            ->patchJson("/api/action-plan/items/{$item->id}", [
                'status' => 'done',
            ])
            ->assertStatus(422);
    }

    private function makeItem(User $user): ActionPlanItem
    {
        $assessment = Assessment::create([
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        $plan = ActionPlan::create(['assessment_id' => $assessment->id]);
        $pillar = Pillar::query()->first();

        return ActionPlanItem::create([
            'action_plan_id' => $plan->id,
            'pillar_id' => $pillar->id,
            'phase' => 'immediate',
            'phase_label_ar' => '0-30 يوم',
            'phase_label_en' => '0-30 days',
            'action_ar' => 'إجراء تجريبي',
            'action_en' => 'Sample action',
            'kpi_ar' => 'مؤشر',
            'kpi_en' => 'KPI',
            'status' => 'not_started',
        ]);
    }
}
