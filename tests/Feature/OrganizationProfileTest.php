<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_organization_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->patchJson('/api/profile/organization', [
                'org_type' => 'volunteer_team',
                'org_size' => 'small',
                'team_member_count' => 8,
            ])
            ->assertOk()
            ->assertJsonPath('data.org_type', 'volunteer_team')
            ->assertJsonPath('data.org_size', 'small');

        $user->refresh();
        $this->assertSame('volunteer_team', $user->org_type);
        $this->assertSame('small', $user->org_size);
        $this->assertSame(8, $user->team_member_count);
    }

    public function test_invalid_org_type_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->patchJson('/api/profile/organization', [
                'org_type' => 'not_a_type',
                'org_size' => 'small',
            ])
            ->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_update_organization(): void
    {
        $this->patchJson('/api/profile/organization', [
            'org_type' => 'startup',
            'org_size' => 'small',
        ])->assertStatus(401);
    }
}
