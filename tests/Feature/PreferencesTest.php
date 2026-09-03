<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_read_and_update_preferences(): void
    {
        $user = User::factory()->create([
            'locale' => 'ar',
            'theme' => 'system',
        ]);

        $this->actingAsUser($user)
            ->getJson('/api/profile/preferences')
            ->assertOk()
            ->assertJsonPath('data.locale', 'ar')
            ->assertJsonPath('data.theme', 'system');

        $this->actingAsUser($user)
            ->patchJson('/api/profile/preferences', [
                'locale' => 'en',
                'theme' => 'dark',
            ])
            ->assertOk()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.theme', 'dark');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'locale' => 'en',
            'theme' => 'dark',
        ]);
    }

    public function test_invalid_theme_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->patchJson('/api/profile/preferences', [
                'theme' => 'neon',
            ])
            ->assertStatus(422);
    }
}
