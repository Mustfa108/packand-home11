<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Assessment;
use App\Models\AssessmentPillarResult;
use App\Models\Pillar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::factory()->create();
    }

    protected function actingAsAdmin(): static
    {
        Sanctum::actingAs($this->admin);

        return $this;
    }

    public function test_admin_can_read_platform_statistics(): void
    {
        $user = User::factory()->create([
            'organization_name' => 'منظمة تجريبية',
            'org_type' => 'startup',
            'org_size' => 'small',
        ]);

        $assessment = Assessment::create([
            'user_id' => $user->id,
            'status' => 'completed',
            'overall_score' => 72.0,
            'readiness_level' => 'good',
            'org_type' => 'startup',
            'org_size' => 'small',
            'completed_at' => now(),
        ]);

        AssessmentPillarResult::create([
            'assessment_id' => $assessment->id,
            'pillar_id' => Pillar::factory()->create(['key' => 'stat_axis'])->id,
            'pillar_name_ar' => 'محور إحصائي',
            'raw_score' => 11,
            'max_score' => 15,
            'percentage' => 73.33,
            'is_weak' => false,
        ]);

        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/statistics')
            ->assertOk();

        $this->assertSame(1, $response->json('data.total_users'));
        $this->assertSame(1, $response->json('data.total_organizations'));
        $this->assertSame(1, $response->json('data.completed_assessments'));
        $this->assertEquals(72.0, $response->json('data.average_readiness'));
        $this->assertSame(1, $response->json('data.readiness_distribution.good.count'));
        $this->assertSame(1, $response->json('data.assessments_over_time.0.count'));
    }

    public function test_statistics_filters_by_org_type_and_size(): void
    {
        foreach ([['startup', 'small'], ['volunteer_team', 'large']] as [$type, $size]) {
            $user = User::factory()->create(['org_type' => $type, 'org_size' => $size]);

            Assessment::create([
                'user_id' => $user->id,
                'status' => 'completed',
                'overall_score' => 60,
                'readiness_level' => 'medium',
                'org_type' => $type,
                'org_size' => $size,
                'completed_at' => now(),
            ]);
        }

        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/statistics?type=startup&size=small')
            ->assertOk();

        $this->assertSame(1, $response->json('data.completed_assessments'));
    }

    public function test_statistics_filters_by_date_range(): void
    {
        $user = User::factory()->create();

        Assessment::create([
            'user_id' => $user->id,
            'status' => 'completed',
            'overall_score' => 60,
            'readiness_level' => 'medium',
            'completed_at' => now()->subMonths(6),
        ]);

        Assessment::create([
            'user_id' => $user->id,
            'status' => 'completed',
            'overall_score' => 80,
            'readiness_level' => 'good',
            'completed_at' => now(),
        ]);

        $from = now()->subDays(7)->toDateString();
        $to = now()->addDay()->toDateString();

        $response = $this->actingAsAdmin()
            ->getJson("/api/admin/statistics?from={$from}&to={$to}")
            ->assertOk();

        $this->assertSame(1, $response->json('data.completed_assessments'));
    }

    public function test_regular_user_cannot_access_statistics(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/statistics')->assertStatus(403);
    }

    public function test_guest_cannot_access_statistics(): void
    {
        $this->getJson('/api/admin/statistics')->assertStatus(401);
    }
}
