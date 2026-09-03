<?php

namespace Tests\Feature;

use App\Models\ExpansionArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpansionAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_crud_own_expansion_areas(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $create = $this->postJson('/api/expansion-areas', [
            'name_ar' => 'الرياض',
            'name_en' => 'Riyadh',
            'lat' => 24.7136,
            'lng' => 46.6753,
            'notes' => 'منطقة مستهدفة',
        ])->assertCreated();

        $id = $create->json('data.id');

        $this->getJson('/api/expansion-areas')
            ->assertOk()
            ->assertJsonPath('data.items.0.name_ar', 'الرياض');

        $this->patchJson("/api/expansion-areas/{$id}", [
            'name_en' => 'Riyadh City',
        ])->assertOk()->assertJsonPath('data.name_en', 'Riyadh City');

        $this->deleteJson("/api/expansion-areas/{$id}")->assertOk();
        $this->assertDatabaseMissing('expansion_areas', ['id' => $id]);
    }

    public function test_user_cannot_update_another_users_area(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $area = ExpansionArea::create([
            'user_id' => $owner->id,
            'name_ar' => 'جدة',
            'lat' => 21.4858,
            'lng' => 39.1925,
        ]);

        $this->actingAsUser($other)
            ->patchJson("/api/expansion-areas/{$area->id}", [
                'name_ar' => 'اختراق',
            ])
            ->assertStatus(403);
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson('/api/expansion-areas', [
                'name_ar' => 'خطأ',
                'lat' => 200,
                'lng' => 10,
            ])
            ->assertStatus(422);
    }
}
