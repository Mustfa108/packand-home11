<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_view_another_users_results(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $assessment = Assessment::create([
            'user_id' => $owner->id,
            'status' => 'completed',
        ]);

        $this->actingAsUser($other)
            ->getJson("/api/assessment/{$assessment->id}/results")
            ->assertStatus(403);
    }

    public function test_user_cannot_download_another_users_report(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $assessment = Assessment::create([
            'user_id' => $owner->id,
            'status' => 'completed',
            'pdf_path' => 'reports/fake.pdf',
        ]);

        $this->actingAsUser($other)
            ->getJson("/api/report/{$assessment->id}/download")
            ->assertStatus(403);
    }
}
